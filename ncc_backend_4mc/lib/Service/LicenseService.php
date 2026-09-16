<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\Db\SettingMapper;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class LicenseService {
	private const KEY_LICENSE_MODE = 'license.mode';
	private const KEY_LICENSE_EMAIL = 'license.email';
	private const KEY_LICENSE_KEY = 'license.key';
	private const KEY_LICENSE_PURCHASED_SEATS = 'license.purchased_seats';
	private const KEY_LICENSE_STATUS_RAW = 'license.status_raw';
	private const KEY_LICENSE_EXPIRES_AT = 'license.expires_at';
	private const KEY_LICENSE_LAST_SYNC_AT = 'license.last_sync_at';
	private const KEY_LICENSE_LAST_ERROR = 'license.last_error';
	private const KEY_LICENSE_COMMERCIAL_STATUS = 'license.commercial_status';
	private const KEY_ACTIVATION = 'license.activation';
	private const KEY_INSTALLATION_SECRET = 'license.installation_secret';
	private const KEY_LAST_VERIFIED_AT = 'license.last_verified_at';

	private const LICENSE_ENDPOINT = 'https://nc-connector.de/wp-json/ncc/v1/license/status';

	private const GRACE_PERIOD_DAYS = 14;
	private const MODE_COMMUNITY = 'community';
	private const MODE_PRO = 'pro';

	public function __construct(
		private SettingMapper $settings,
		private ICrypto $crypto,
		private IClientService $clientService,
		private LoggerInterface $logger,
		private IConfig $config,
	) {
	}

	public function hasCredentials(): bool {
		$email = trim((string)$this->settings->getValue(self::KEY_LICENSE_EMAIL, ''));
		$encryptedKey = trim((string)$this->settings->getValue(self::KEY_LICENSE_KEY, ''));
		return $email !== '' && $encryptedKey !== '';
	}

	public function getMode(): string {
		$raw = trim(strtolower((string)$this->settings->getValue(self::KEY_LICENSE_MODE, self::MODE_COMMUNITY)));
		if ($raw !== self::MODE_COMMUNITY && $raw !== self::MODE_PRO) {
			return self::MODE_COMMUNITY;
		}
		return $raw;
	}

	public function canContactLicenseServer(): bool {
		return $this->getMode() === self::MODE_PRO && $this->hasCredentials();
	}

	public function setMode(string $mode): void {
		$mode = trim(strtolower($mode));
		if ($mode !== self::MODE_COMMUNITY && $mode !== self::MODE_PRO) {
			throw new \InvalidArgumentException('Invalid mode');
		}

		$this->settings->setValue(self::KEY_LICENSE_MODE, $mode, time());
	}

	public function setCredentials(string $email, string $licenseKey): void {
		$email = trim(strtolower($email));
		$licenseKey = trim($licenseKey);
		if ($email === '' || $licenseKey === '') {
			throw new \InvalidArgumentException('Email and license key are required');
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			throw new \InvalidArgumentException('Invalid email address');
		}

		$previousKey = $this->settings->getValue(self::KEY_LICENSE_KEY);
		$encryptedLicenseKey = $this->crypto->encrypt($licenseKey);
		$credentialsChanged = !$this->storedCredentialsMatch($email, $licenseKey);
		$now = time();
		$values = [];
		if ($credentialsChanged) {
			$values = [
				self::KEY_LICENSE_PURCHASED_SEATS => '0',
				self::KEY_LICENSE_STATUS_RAW => '',
				self::KEY_LICENSE_COMMERCIAL_STATUS => '',
				self::KEY_LICENSE_EXPIRES_AT => '',
				self::KEY_LICENSE_LAST_SYNC_AT => '',
				self::KEY_LICENSE_LAST_ERROR => '',
				self::KEY_ACTIVATION => '',
				self::KEY_LAST_VERIFIED_AT => '',
			];
		}
		$values[self::KEY_LICENSE_EMAIL] = $email;
		$values[self::KEY_LICENSE_KEY] = $encryptedLicenseKey;
		if (!$this->settings->setValuesIfUnchanged($values, $now, self::KEY_LICENSE_KEY, $previousKey)) {
			throw new \RuntimeException('License credentials changed. Please reload and try again.');
		}
	}

	public function getPurchasedSeats(): int {
		$value = (string)$this->settings->getValue(self::KEY_LICENSE_PURCHASED_SEATS, '0');
		$purchased = (int)$value;
		return max(0, $purchased);
	}

	public function getTotalSeats(): int {
		if ($this->getMode() === self::MODE_COMMUNITY) {
			return 1;
		}

		$purchased = $this->getPurchasedSeats();
		if ($purchased > 0) {
			return $purchased;
		}
		return 0;
	}

	public function isLicenseValid(): bool {
		if ($this->getMode() === self::MODE_COMMUNITY) {
			return true;
		}

		$status = $this->getEffectiveStatus();
		return $status === 'ACTIVE' || $status === 'GRACE';
	}

	public function getSnapshot(): array {
		$hasCredentials = $this->hasCredentials();
		$email = trim((string)$this->settings->getValue(self::KEY_LICENSE_EMAIL, ''));
		$expiresAt = $this->getExpiresAt();
		$graceUntil = null;
		if ($expiresAt !== null) {
			$graceUntil = $expiresAt + (self::GRACE_PERIOD_DAYS * 86400);
		}
		$lastSyncAt = $this->getLastSyncAt();
		$activation = $this->getActivation();
		$lastVerifiedAt = (int)$this->settings->getValue(self::KEY_LAST_VERIFIED_AT, '0') ?: null;
		$offlineUntil = $this->getOfflineUntil($activation, $lastVerifiedAt);

		return [
			'mode' => $this->getMode(),
			'has_credentials' => $hasCredentials,
			'email' => $email,
			'purchased_seats' => $this->getPurchasedSeats(),
			'total_seats' => $this->getTotalSeats(),
			'status_raw' => $this->getRawStatus(),
			'status_effective' => $this->getEffectiveStatus(),
			'license_status_effective' => $this->getCommercialStatus(),
			'is_valid' => $this->isLicenseValid(),
			'activation' => $activation,
			'last_verified_at' => $lastVerifiedAt,
			'last_verified_at_iso' => $this->formatIso($lastVerifiedAt),
			'offline_until' => $offlineUntil,
			'offline_until_iso' => $this->formatIso($offlineUntil),
			'expires_at' => $expiresAt,
			'expires_at_iso' => $this->formatIso($expiresAt),
			'grace_until' => $graceUntil,
			'grace_until_iso' => $this->formatIso($graceUntil),
			'last_sync_at' => $lastSyncAt,
			'last_sync_at_iso' => $this->formatIso($lastSyncAt),
			'last_error' => $hasCredentials ? (string)$this->settings->getValue(self::KEY_LICENSE_LAST_ERROR, '') : '',
		];
	}

	public function syncNow(): array {
		if ($this->getMode() !== self::MODE_PRO) {
			throw new \RuntimeException('No license server communication in Community mode');
		}

		if (!$this->hasCredentials()) {
			throw new \RuntimeException('License credentials are not configured');
		}

		$email = trim((string)$this->settings->getValue(self::KEY_LICENSE_EMAIL, ''));
		$encryptedKey = (string)$this->settings->getValue(self::KEY_LICENSE_KEY, '');
		$now = time();
		$errorCode = 'license_sync_unavailable';
		try {
			$licenseKey = $this->crypto->decrypt($encryptedKey);
			$instanceId = $this->config->getSystemValueString('instanceid');
			if (preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $instanceId) !== 1) {
				$errorCode = 'installation_proof_unavailable';
				throw new \RuntimeException('Nextcloud installation ID is unavailable');
			}
			$errorCode = 'installation_proof_unavailable';
			$secret = $this->getInstallationSecret();
			$errorCode = 'license_sync_unavailable';
			$client = $this->clientService->newClient();
			$requestBody = json_encode([
				'email' => $email,
				'license_key' => $licenseKey,
				'instance_id' => $instanceId,
				'installation_secret' => $secret,
			], JSON_THROW_ON_ERROR);

			$response = $client->post(self::LICENSE_ENDPOINT, [
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body' => $requestBody,
				'allow_redirects' => false,
			]);

			if ($response->getStatusCode() !== 200) {
				throw new \RuntimeException('License server did not return a status response');
			}
			$errorCode = 'license_response_invalid';
			$body = $response->getBody();
			if (is_resource($body)) {
				$body = stream_get_contents($body);
			}
			if (!is_string($body)) {
				throw new \RuntimeException('Invalid license response (empty body)');
			}

			$payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
			if (!is_array($payload)) {
				throw new \RuntimeException('Invalid license response (no JSON object)');
			}

			$status = $payload['status'] ?? null;
			if (!is_string($status) || !in_array($status, ['active', 'expired', 'inactive', 'invalid'], true)
				|| !isset($payload['seats']) || !is_int($payload['seats']) || $payload['seats'] < 0
				|| !array_key_exists('expires_at', $payload)) {
				throw new \RuntimeException('Invalid license response fields');
			}
			$expiresAt = $this->parseExpiresAt($payload['expires_at']);
			$activation = $this->parseActivation($payload['activation'] ?? null, $status);
			$commercialStatus = $payload['license_status'] ?? $status;
			if (!is_string($commercialStatus) || !in_array($commercialStatus, ['active', 'expired', 'inactive', 'invalid'], true)) {
				throw new \RuntimeException('Invalid commercial license status');
			}
			if ($commercialStatus !== $status && ($status !== 'invalid' || !$activation
				|| !($activation['enforced'] || $activation['state'] === 'credentials_changed'))) {
				throw new \RuntimeException('Inconsistent license refusal');
			}
			$usable = in_array($this->evaluateCommercialStatus(strtoupper($commercialStatus), $expiresAt), ['ACTIVE', 'GRACE'], true);
			$verifiedAt = $activation && $activation['verified'] && $usable && $status !== 'invalid' ? $now : null;
			$values = [
				self::KEY_LICENSE_PURCHASED_SEATS => (string)$payload['seats'],
				self::KEY_LICENSE_STATUS_RAW => $status,
				self::KEY_LICENSE_COMMERCIAL_STATUS => $commercialStatus,
				self::KEY_LICENSE_EXPIRES_AT => $expiresAt !== null ? (string)$expiresAt : '',
				self::KEY_LICENSE_LAST_SYNC_AT => (string)$now,
				self::KEY_LICENSE_LAST_ERROR => '',
				self::KEY_ACTIVATION => $activation === null ? '' : json_encode($activation, JSON_THROW_ON_ERROR),
				self::KEY_LAST_VERIFIED_AT => $verifiedAt === null ? '' : (string)$verifiedAt,
			];
			if (!$this->settings->setValuesIfUnchanged($values, $now, self::KEY_LICENSE_KEY, $encryptedKey)) {
				throw new \RuntimeException('License credentials changed during synchronization');
			}
		} catch (\Throwable $exception) {
			// HTTP exceptions may retain the complete request body; never log them.
			$this->logger->error('License sync failed', [
				'reason' => $errorCode,
				'exception_class' => get_class($exception),
			]);
			$this->settings->setValuesIfUnchanged(
				[self::KEY_LICENSE_LAST_ERROR => $errorCode], $now, self::KEY_LICENSE_KEY, $encryptedKey
			);
			throw new \RuntimeException('License synchronization failed. Check the connection and try again.');
		}

		return $this->getSnapshot();
	}

	private function getRawStatus(): string {
		if ($this->getMode() === self::MODE_COMMUNITY) {
			return 'COMMUNITY';
		}

		$raw = trim((string)$this->settings->getValue(self::KEY_LICENSE_STATUS_RAW, ''));
		if ($raw === '') {
			return 'UNKNOWN';
		}
		return strtoupper($raw);
	}

	private function getExpiresAt(): ?int {
		$value = trim((string)$this->settings->getValue(self::KEY_LICENSE_EXPIRES_AT, ''));
		if ($value === '') {
			return null;
		}
		$ts = (int)$value;
		return $ts > 0 ? $ts : null;
	}

	private function getLastSyncAt(): ?int {
		$value = trim((string)$this->settings->getValue(self::KEY_LICENSE_LAST_SYNC_AT, ''));
		if ($value === '') {
			return null;
		}
		$ts = (int)$value;
		return $ts > 0 ? $ts : null;
	}

	private function getEffectiveStatus(): string {
		$status = $this->getCommercialStatus();
		if (!in_array($status, ['ACTIVE', 'GRACE'], true)) {
			return $status;
		}
		$activation = $this->getActivation();
		if ($activation && $activation['required'] && $activation['enforced']) {
			if (!$activation['verified']) {
				return 'ACTIVATION_REQUIRED';
			}
			$lastVerified = (int)$this->settings->getValue(self::KEY_LAST_VERIFIED_AT, '0') ?: null;
			$until = $this->getOfflineUntil($activation, $lastVerified);
			if ($until === null || time() > $until) {
				return 'OFFLINE_EXPIRED';
			}
		}
		return $this->getRawStatus() === 'INVALID' ? 'INVALID' : $status;
	}

	private function getCommercialStatus(): string {
		if ($this->getMode() === self::MODE_COMMUNITY) {
			return 'COMMUNITY';
		}
		$raw = (string)$this->settings->getValue(self::KEY_LICENSE_COMMERCIAL_STATUS, '');
		return $this->evaluateCommercialStatus($raw === '' ? $this->getRawStatus() : strtoupper($raw), $this->getExpiresAt());
	}

	private function evaluateCommercialStatus(string $raw, ?int $expiresAt): string {
		$now = time();
		$base = match ($raw) {
			'COMMUNITY' => 'COMMUNITY',
			'ACTIVE', 'VALID', 'PAID' => 'ACTIVE',
			'EXPIRED' => 'EXPIRED',
			'INACTIVE', 'DISABLED', 'CANCELLED', 'CANCELED' => 'INACTIVE',
			'INVALID' => 'INVALID',
			default => 'UNKNOWN',
		};
		if ($base === 'COMMUNITY') {
			return 'COMMUNITY';
		}

		if ($expiresAt === null) {
			return $base;
		}
		if ($base === 'INACTIVE' || $base === 'INVALID' || $base === 'UNKNOWN') {
			return $base;
		}

		if ($expiresAt > $now) {
			return $base === 'EXPIRED' ? 'EXPIRED' : 'ACTIVE';
		}

		$graceUntil = $expiresAt + (self::GRACE_PERIOD_DAYS * 86400);
		if ($now <= $graceUntil) {
			return 'GRACE';
		}

		return 'EXPIRED';
	}

	private function storedCredentialsMatch(string $email, string $licenseKey): bool {
		$storedEmail = trim(strtolower((string)$this->settings->getValue(self::KEY_LICENSE_EMAIL, '')));
		$encryptedLicenseKey = trim((string)$this->settings->getValue(self::KEY_LICENSE_KEY, ''));
		if ($storedEmail === '' || $encryptedLicenseKey === '') {
			return false;
		}

		try {
			$storedLicenseKey = $this->crypto->decrypt($encryptedLicenseKey);
		} catch (\Throwable $exception) {
			$this->logger->warning('Stored license credentials could not be compared', [
				'exception_class' => get_class($exception),
			]);
			return false;
		}

		return $storedEmail === $email
			&& strtoupper(trim($storedLicenseKey)) === strtoupper($licenseKey);
	}

	private function getInstallationSecret(): string {
		$encrypted = $this->settings->getValue(self::KEY_INSTALLATION_SECRET);
		if ($encrypted === null) {
			$encrypted = $this->settings->getOrCreateValue(
				self::KEY_INSTALLATION_SECRET, $this->crypto->encrypt(bin2hex(random_bytes(32))), time()
			);
		}
		$secret = $this->crypto->decrypt($encrypted);
		if (preg_match('/^[a-f0-9]{64}$/D', $secret) !== 1) {
			throw new \RuntimeException('Stored installation proof is unavailable');
		}
		return $secret;
	}

	private function getActivation(): ?array {
		if ($this->getMode() === self::MODE_COMMUNITY) {
			return null;
		}
		$raw = (string)$this->settings->getValue(self::KEY_ACTIVATION, '');
		return $raw === '' ? null : json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
	}

	private function parseActivation(mixed $value, string $status): ?array {
		$previous = $this->getActivation();
		if ($value === null) {
			if ($status === 'invalid') {
				return $previous === null ? null : array_merge($previous, [
					'state' => 'credentials_changed', 'verified' => false, 'activated_at' => null,
				]);
			}
			if ($previous !== null) {
				throw new \RuntimeException('License activation information is missing');
			}
			return null;
		}
		$states = ['not_required', 'proof_required', 'invalid_proof', 'activated', 'conflict', 'license_unavailable', 'credentials_changed'];
		if (!is_array($value) || !is_bool($value['required'] ?? null) || !is_bool($value['enforced'] ?? null)
			|| !is_bool($value['verified'] ?? null) || !in_array($value['state'] ?? null, $states, true)) {
			throw new \RuntimeException('Invalid activation response');
		}
		$credentialsRevoked = $status === 'invalid' && $value['state'] === 'credentials_changed' && !$value['verified'];
		if (!$credentialsRevoked && ((!$value['required'] && ($value['enforced'] || $value['verified'] || $value['state'] !== 'not_required'))
			|| ($value['required'] && $value['state'] === 'not_required')
			|| ($value['verified'] !== ($value['state'] === 'activated'))
			|| ($value['required'] && $value['enforced'] && !$value['verified'] && $status !== 'invalid'))) {
			throw new \RuntimeException('Inconsistent activation response');
		}
		$date = $value['activated_at'] ?? null;
		if ($value['verified'] && (!is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $date) !== 1)) {
			throw new \RuntimeException('Invalid activation timestamp');
		}
		return [
			'required' => $value['required'],
			'enforced' => $value['enforced'],
			'state' => $value['state'],
			'verified' => $value['verified'],
			'activated_at' => $value['verified'] ? $date : null,
		];
	}

	private function getOfflineUntil(?array $activation, ?int $lastVerifiedAt): ?int {
		if (!$activation || !$activation['required'] || !$activation['enforced'] || !$activation['verified'] || $lastVerifiedAt === null) {
			return null;
		}
		$until = $lastVerifiedAt + self::GRACE_PERIOD_DAYS * 86400;
		$expiresAt = $this->getExpiresAt();
		return $expiresAt === null ? $until : min($until, $expiresAt + self::GRACE_PERIOD_DAYS * 86400);
	}

	private function parseExpiresAt(mixed $expiresAt): ?int {
		if ($expiresAt === null) {
			return null;
		}
		if (is_int($expiresAt)) {
			return $expiresAt > 0 ? $expiresAt : null;
		}
		if (is_string($expiresAt) && trim($expiresAt) !== '') {
			try {
				$dt = new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC'));
				return $dt->getTimestamp();
			} catch (\Throwable $exception) {
				throw new \RuntimeException('Invalid license expiration timestamp');
			}
		}
		throw new \RuntimeException('Invalid license expiration timestamp');
	}

	private function formatIso(?int $ts): ?string {
		if ($ts === null || $ts <= 0) {
			return null;
		}
		return gmdate('c', $ts);
	}

}
