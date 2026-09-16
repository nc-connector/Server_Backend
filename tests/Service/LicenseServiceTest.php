<?php

declare(strict_types=1);

namespace OCP\Http\Client {
	if (!interface_exists(IClientService::class)) {
		interface IClientService {
		}
	}
}

namespace OCP\Security {
	if (!interface_exists(ICrypto::class)) {
		interface ICrypto {
			public function encrypt(string $plaintext, string $password = ''): string;

			public function decrypt(string $authenticatedCiphertext, string $password = ''): string;
		}
	}
}

namespace OCA\NcConnector\Tests\Service {

use OCA\NcConnector\Db\SettingMapper;
use OCA\NcConnector\Service\LicenseService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LicenseServiceTest extends TestCase {
	public function testUnknownStatusDoesNotBecomeActiveFromFutureExpiry(): void {
		$settings = $this->licenseSettings('revoked', time() + 86400);

		$snapshot = $this->service($settings)->getSnapshot();

		self::assertSame('UNKNOWN', $snapshot['status_effective']);
		self::assertFalse($this->service($settings)->isLicenseValid());
	}

	public function testExpiredStatusDoesNotBecomeActiveFromFutureExpiry(): void {
		$settings = $this->licenseSettings('expired', time() + 86400);

		$snapshot = $this->service($settings)->getSnapshot();

		self::assertSame('EXPIRED', $snapshot['status_effective']);
		self::assertFalse($this->service($settings)->isLicenseValid());
	}

	public function testExpiredStatusUsesGracePeriodAfterElapsedExpiry(): void {
		$settings = $this->licenseSettings('expired', time() - 60);

		$snapshot = $this->service($settings)->getSnapshot();

		self::assertSame('GRACE', $snapshot['status_effective']);
		self::assertTrue($this->service($settings)->isLicenseValid());
	}

	public function testExpiredStatusLeavesGraceAfterFourteenDays(): void {
		$settings = $this->licenseSettings('expired', time() - (15 * 86400));

		$snapshot = $this->service($settings)->getSnapshot();

		self::assertSame('EXPIRED', $snapshot['status_effective']);
		self::assertFalse($this->service($settings)->isLicenseValid());
	}

	public function testActiveStatusWithFutureExpiryRemainsValid(): void {
		$settings = $this->licenseSettings('active', time() + 86400);

		$snapshot = $this->service($settings)->getSnapshot();

		self::assertSame('ACTIVE', $snapshot['status_effective']);
		self::assertTrue($this->service($settings)->isLicenseValid());
	}

	public function testChangedEmailClearsCachedEntitlement(): void {
		$settings = $this->cachedLicenseSettings();

		$this->service($settings)->setCredentials('new@example.com', 'NCC-OLD');

		self::assertSame('new@example.com', $settings->value('license.email'));
		self::assertSame('encrypted:NCC-OLD', $settings->value('license.key'));
		$this->assertCachedEntitlementCleared($settings);
	}

	public function testChangedLicenseKeyClearsCachedEntitlement(): void {
		$settings = $this->cachedLicenseSettings();

		$this->service($settings)->setCredentials('owner@example.com', 'NCC-NEW');

		self::assertSame('owner@example.com', $settings->value('license.email'));
		self::assertSame('encrypted:NCC-NEW', $settings->value('license.key'));
		$this->assertCachedEntitlementCleared($settings);
	}

	public function testEquivalentCredentialsRetainCachedEntitlement(): void {
		$settings = $this->cachedLicenseSettings();

		$this->service($settings)->setCredentials('OWNER@EXAMPLE.COM', 'ncc-old');

		self::assertSame('20', $settings->value('license.purchased_seats'));
		self::assertSame('active', $settings->value('license.status_raw'));
		self::assertSame('4070908800', $settings->value('license.expires_at'));
		self::assertSame('1234567890', $settings->value('license.last_sync_at'));
		self::assertSame('temporary outage', $settings->value('license.last_error'));
	}

	public function testModeRoundTripRetainsCachedEntitlement(): void {
		$settings = $this->cachedLicenseSettings();
		$service = $this->service($settings);

		$service->setMode('community');
		$service->setMode('pro');

		self::assertSame('20', $settings->value('license.purchased_seats'));
		self::assertSame('active', $settings->value('license.status_raw'));
		self::assertSame('4070908800', $settings->value('license.expires_at'));
		self::assertSame('1234567890', $settings->value('license.last_sync_at'));
	}

	private function assertCachedEntitlementCleared(InMemorySettingMapper $settings): void {
		self::assertSame('0', $settings->value('license.purchased_seats'));
		self::assertSame('', $settings->value('license.status_raw'));
		self::assertSame('', $settings->value('license.expires_at'));
		self::assertSame('', $settings->value('license.last_sync_at'));
		self::assertSame('', $settings->value('license.last_error'));
	}

	public function testFirstSyncPersistsEncryptedProofBeforeSendingAndReusesIt(): void {
		$settings = $this->cachedLicenseSettings();
		$proofs = [];
		$service = $this->service($settings, function (array $request) use ($settings, &$proofs): array {
			$proofs[] = $request['installation_secret'];
			self::assertSame('oc-test-instance', $request['instance_id']);
			self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $request['installation_secret']);
			self::assertSame('encrypted:' . $request['installation_secret'], $settings->value('license.installation_secret'));
			return $this->response();
		});
		$first = $service->syncNow();
		$service->syncNow();
		self::assertSame($proofs[0], $proofs[1]);
		self::assertTrue($first['activation']['verified']);
		self::assertTrue($first['is_valid']);
		self::assertStringNotContainsString($proofs[0], json_encode($first));
	}

	public function testCommunityNeverCreatesProofOrContactsServer(): void {
		$settings = $this->cachedLicenseSettings();
		$service = $this->service($settings, static function (): array {
			self::fail('Community contacted server');
		});
		$service->setMode('community');
		self::assertNull($service->getSnapshot()['activation']);
		$this->expectException(\RuntimeException::class);
		try {
			$service->syncNow();
		} finally {
			self::assertNull($settings->value('license.installation_secret'));
		}
	}

	public function testManualLicenseRemainsExemptWithVerificationEnabledForPaidLicenses(): void {
		$settings = $this->cachedLicenseSettings();
		$snapshot = $this->service($settings, fn (): array => $this->response([
			'activation' => ['required' => false, 'enforced' => false, 'state' => 'not_required', 'verified' => false, 'activated_at' => null],
		]))->syncNow();
		self::assertTrue($snapshot['is_valid']);
		self::assertNull($snapshot['offline_until']);
		self::assertFalse($snapshot['activation']['required']);
	}

	public function testConflictIsInformationalWhileVerificationIsOff(): void {
		$snapshot = $this->service($this->cachedLicenseSettings(), fn (): array => $this->response([
			'activation' => ['required' => true, 'enforced' => false, 'state' => 'conflict', 'verified' => false, 'activated_at' => null],
		]))->syncNow();
		self::assertTrue($snapshot['is_valid']);
		self::assertSame('ACTIVE', $snapshot['license_status_effective']);
		self::assertFalse($snapshot['activation']['verified']);
		self::assertNull($snapshot['offline_until']);
	}

	public function testEnforcedConflictRevokesCachedAccessWithoutLosingCapacity(): void {
		$settings = $this->cachedLicenseSettings();
		$snapshot = $this->service($settings, fn (): array => $this->response([
			'status' => 'invalid',
			'license_status' => 'active',
			'activation' => ['required' => true, 'enforced' => true, 'state' => 'conflict', 'verified' => false, 'activated_at' => null],
		]))->syncNow();
		self::assertFalse($snapshot['is_valid']);
		self::assertSame('ACTIVE', $snapshot['license_status_effective']);
		self::assertSame('ACTIVATION_REQUIRED', $snapshot['status_effective']);
		self::assertSame(20, $snapshot['purchased_seats']);
		self::assertNull($snapshot['last_verified_at']);
		self::assertSame('', $snapshot['last_error']);
	}

	public function testOfflineAccessStopsFourteenDaysAfterVerifiedUsableCheck(): void {
		$settings = $this->cachedLicenseSettings();
		$service = $this->service($settings, fn (): array => $this->response());
		$snapshot = $service->syncNow();
		self::assertSame($snapshot['last_verified_at'] + 14 * 86400, $snapshot['offline_until']);
		$settings->setValue('license.last_verified_at', (string)(time() - 15 * 86400), time());
		$snapshot = $service->getSnapshot();
		self::assertSame('OFFLINE_EXPIRED', $snapshot['status_effective']);
		self::assertFalse($snapshot['is_valid']);
		self::assertSame('ACTIVE', $snapshot['license_status_effective']);
	}

	public function testOfflineAndLicenseGraceDeadlinesDoNotStack(): void {
		$settings = $this->cachedLicenseSettings();
		$expiry = time() - 13 * 86400;
		$snapshot = $this->service($settings, fn (): array => $this->response([
			'status' => 'expired', 'expires_at' => gmdate('c', $expiry),
		]))->syncNow();
		self::assertSame('GRACE', $snapshot['status_effective']);
		self::assertSame($expiry + 14 * 86400, $snapshot['offline_until']);
	}

	public function testNonEnforcingRolloutHasNoNewOfflineDeadline(): void {
		$settings = $this->cachedLicenseSettings();
		$response = $this->response();
		$response['activation']['enforced'] = false;
		$service = $this->service($settings, static fn (): array => $response);
		$service->syncNow();
		$settings->setValue('license.last_verified_at', '1', time());
		self::assertTrue($service->isLicenseValid());
		self::assertNull($service->getSnapshot()['offline_until']);
	}

	public function testNetworkFailureKeepsBoundedGrantAndDoesNotLogRequestSecrets(): void {
		$settings = $this->cachedLicenseSettings();
		$this->service($settings, fn (): array => $this->response())->syncNow();
		$confirmedAt = $settings->value('license.last_verified_at');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('error')->with('License sync failed', self::callback(
			static fn (array $context): bool => !isset($context['exception']) && !str_contains(json_encode($context), 'SECRET')
		));
		$service = $this->service($settings, static function (): array {
			throw new \RuntimeException('request containing SECRET');
		}, $logger);
		try {
			$service->syncNow();
			self::fail('Expected synchronization failure');
		} catch (\RuntimeException $exception) {
			self::assertStringNotContainsString('SECRET', $exception->getMessage());
		}
		self::assertSame($confirmedAt, $settings->value('license.last_verified_at'));
		self::assertSame('license_sync_unavailable', $settings->value('license.last_error'));
		self::assertTrue($service->isLicenseValid());
	}

	public function testKnownActivationCannotDowngradeToLegacySuccess(): void {
		$settings = $this->cachedLicenseSettings();
		$this->service($settings, fn (): array => $this->response())->syncNow();
		$confirmedAt = $settings->value('license.last_verified_at');
		$service = $this->service($settings, fn (): array => $this->response(['activation' => null]));
		try {
			$service->syncNow();
			self::fail('Expected missing proof failure');
		} catch (\RuntimeException) {
			self::assertSame('license_response_invalid', $settings->value('license.last_error'));
		}
		self::assertSame($confirmedAt, $settings->value('license.last_verified_at'));
		self::assertTrue($service->getSnapshot()['activation']['enforced']);
	}

	public function testOldServerWorksBeforeActivationProtocolIsKnown(): void {
		$snapshot = $this->service($this->cachedLicenseSettings(), fn (): array => $this->response(['activation' => null]))->syncNow();
		self::assertTrue($snapshot['is_valid']);
		self::assertNull($snapshot['activation']);
	}

	public function testRevokedKeyWithoutActivationMetadataEndsAccess(): void {
		$settings = $this->cachedLicenseSettings();
		$this->service($settings, fn (): array => $this->response())->syncNow();
		$snapshot = $this->service($settings, static fn (): array => [
			'status' => 'invalid', 'seats' => 0, 'expires_at' => null,
		])->syncNow();
		self::assertFalse($snapshot['is_valid']);
		self::assertSame('INVALID', $snapshot['license_status_effective']);
		self::assertFalse($snapshot['activation']['verified']);
		self::assertNull($snapshot['last_verified_at']);
	}

	public function testReplacementClearsConfirmationButKeepsInstallationSecret(): void {
		$settings = $this->cachedLicenseSettings();
		$service = $this->service($settings, fn (): array => $this->response());
		$service->syncNow();
		$proof = $settings->value('license.installation_secret');
		$service->setCredentials('owner@example.com', 'NCC-REPLACEMENT');
		self::assertSame($proof, $settings->value('license.installation_secret'));
		self::assertNull($service->getSnapshot()['activation']);
		self::assertNull($service->getSnapshot()['last_verified_at']);
		self::assertFalse($service->isLicenseValid());
	}

	public function testOldInFlightResponseDoesNotRestoreReplacedCredentials(): void {
		$settings = $this->cachedLicenseSettings();
		$service = $this->service($settings, function () use ($settings): array {
			$this->service($settings)->setCredentials('other@example.com', 'NCC-OTHER');
			return $this->response();
		});
		try {
			$service->syncNow();
			self::fail('Expected stale request to be discarded');
		} catch (\RuntimeException) {
			self::assertSame('other@example.com', $settings->value('license.email'));
		}
		self::assertFalse($service->isLicenseValid());
		self::assertNull($service->getSnapshot()['activation']);
		self::assertSame('', $settings->value('license.last_error'));
	}

	public function testCorruptStoredSecretIsNotSilentlyRegenerated(): void {
		$settings = $this->cachedLicenseSettings();
		$settings->setValue('license.installation_secret', 'encrypted:broken', time());
		try {
			$this->service($settings)->syncNow();
			self::fail('Expected invalid proof failure');
		} catch (\RuntimeException) {
			self::assertSame('encrypted:broken', $settings->value('license.installation_secret'));
			self::assertSame('installation_proof_unavailable', $settings->value('license.last_error'));
		}
	}

	public function testManualKeyReplacedDuringServerCheckEndsAccessEvenWithEnforcementEnabled(): void {
		$service = $this->service($this->cachedLicenseSettings(), fn (): array => $this->response([
			'status' => 'invalid', 'license_status' => 'active',
			'activation' => ['required' => false, 'enforced' => true, 'verified' => false, 'state' => 'credentials_changed'],
		]));
		$snapshot = $service->syncNow();
		self::assertSame('INVALID', $snapshot['status_effective']);
		self::assertSame('ACTIVE', $snapshot['license_status_effective']);
		self::assertFalse($snapshot['is_valid']);
		self::assertSame('', $snapshot['last_error']);
	}

	public function testMalformedResponsesDoNotReplaceVerifiedEntitlementOrRenewTheOfflineWindow(): void {
		$valid = $this->response();
		foreach ([
			['activation' => array_replace($valid['activation'], ['enforced' => 'false'])],
			['activation' => array_replace($valid['activation'], ['state' => 'conflict', 'verified' => false])],
			['activation' => array_replace($valid['activation'], ['required' => false])],
			['license_status' => 'inactive'],
			['seats' => -10],
			['expires_at' => 'not-a-date'],
		] as $override) {
			$settings = $this->cachedLicenseSettings();
			$this->service($settings, fn (): array => $valid)->syncNow();
			$before = $this->service($settings)->getSnapshot();
			try {
				$this->service($settings, fn (): array => $this->response($override))->syncNow();
				self::fail('Expected invalid response');
			} catch (\RuntimeException) {
				$after = $this->service($settings)->getSnapshot();
				self::assertSame('license_response_invalid', $after['last_error']);
				self::assertSame($before['last_verified_at'], $after['last_verified_at']);
				self::assertSame($before['offline_until'], $after['offline_until']);
				self::assertTrue($after['is_valid']);
			}
		}
	}

	public function testTrialConversionAndEnforcementChangesReuseTheStoredProof(): void {
		$settings = $this->cachedLicenseSettings();
		$proof = null;
		foreach ([null, false, true, false] as $enforced) {
			$activation = $enforced === null
				? ['required' => false, 'enforced' => false, 'verified' => false, 'state' => 'not_required']
				: array_replace($this->response()['activation'], ['enforced' => $enforced]);
			$service = $this->service($settings, function (array $request) use (&$proof, $activation): array {
				$proof ??= $request['installation_secret'];
				self::assertSame($proof, $request['installation_secret']);
				return $this->response(['activation' => $activation]);
			});
			$snapshot = $service->syncNow();
			self::assertTrue($snapshot['is_valid']);
			self::assertSame($enforced === true, $snapshot['offline_until'] !== null);
		}
	}

	private function response(array $overrides = []): array {
		return array_replace([
			'status' => 'active', 'seats' => 20, 'expires_at' => '2099-01-01',
			'activation' => ['required' => true, 'enforced' => true, 'state' => 'activated', 'verified' => true, 'activated_at' => '2026-09-16 12:00:00'],
		], $overrides);
	}

	private function service(InMemorySettingMapper $settings, ?callable $reply = null, ?LoggerInterface $logger = null): LicenseService {
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(
			static fn (string $plaintext): string => 'encrypted:' . $plaintext
		);
		$crypto->method('decrypt')->willReturnCallback(
			static fn (string $ciphertext): string => str_starts_with($ciphertext, 'encrypted:')
				? substr($ciphertext, strlen('encrypted:'))
				: $ciphertext
		);

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('instanceid')->willReturn('oc-test-instance');
		$clientService = new class($reply) implements IClientService {
			public function __construct(private mixed $reply) {}
			public function newClient(): object {
				return new class($this->reply) {
					public function __construct(private mixed $reply) {}
					public function post(string $url, array $options): object {
						TestCase::assertFalse($options['allow_redirects']);
						$body = ($this->reply)(json_decode($options['body'], true));
						return new class($body) {
							public function __construct(private array $body) {}
							public function getStatusCode(): int { return 200; }
							public function getBody(): string { return json_encode($this->body); }
						};
					}
				};
			}
		};
		return new LicenseService(
			$settings,
			$crypto,
			$clientService,
			$logger ?? $this->createMock(LoggerInterface::class),
			$config,
		);
	}

	private function licenseSettings(string $status, int $expiresAt): InMemorySettingMapper {
		return new InMemorySettingMapper([
			'license.mode' => 'pro',
			'license.status_raw' => $status,
			'license.expires_at' => (string)$expiresAt,
		]);
	}

	private function cachedLicenseSettings(): InMemorySettingMapper {
		return new InMemorySettingMapper([
			'license.mode' => 'pro',
			'license.email' => 'owner@example.com',
			'license.key' => 'encrypted:NCC-OLD',
			'license.purchased_seats' => '20',
			'license.status_raw' => 'active',
			'license.expires_at' => '4070908800',
			'license.last_sync_at' => '1234567890',
			'license.last_error' => 'temporary outage',
		]);
	}
}

final class InMemorySettingMapper extends SettingMapper {
	/**
	 * @param array<string, string> $values
	 */
	public function __construct(private array $values) {
	}

	public function getValue(string $key, ?string $default = null): ?string {
		return $this->values[$key] ?? $default;
	}

	public function setValue(string $key, string $value, int $updatedAt): void {
		$this->values[$key] = $value;
	}

	public function getOrCreateValue(string $key, string $value, int $updatedAt): string {
		return $this->values[$key] ??= $value;
	}

	public function setValuesIfUnchanged(array $values, int $updatedAt, string $guardKey, ?string $expected): bool {
		if ($this->getValue($guardKey) !== $expected) {
			return false;
		}
		$this->values = array_replace($this->values, $values);
		return true;
	}

	public function value(string $key): ?string {
		return $this->values[$key] ?? null;
	}
}
}
