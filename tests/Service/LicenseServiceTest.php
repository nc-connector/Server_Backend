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

	private function service(InMemorySettingMapper $settings): LicenseService {
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(
			static fn (string $plaintext): string => 'encrypted:' . $plaintext
		);
		$crypto->method('decrypt')->willReturnCallback(
			static fn (string $ciphertext): string => str_starts_with($ciphertext, 'encrypted:')
				? substr($ciphertext, strlen('encrypted:'))
				: $ciphertext
		);

		return new LicenseService(
			$settings,
			$crypto,
			$this->createMock(IClientService::class),
			$this->createMock(LoggerInterface::class)
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

	public function value(string $key): ?string {
		return $this->values[$key] ?? null;
	}
}
}
