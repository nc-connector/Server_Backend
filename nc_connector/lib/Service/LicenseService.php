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
use OCP\ILogger;
use OCP\Server;
use OCP\Security\ICrypto;

class LicenseService {
	private const KEY_LICENSE_MODE = 'license.mode';
	private const KEY_LICENSE_EMAIL = 'license.email';
	private const KEY_LICENSE_KEY = 'license.key';
	private const KEY_LICENSE_PURCHASED_SEATS = 'license.purchased_seats';
	private const KEY_LICENSE_STATUS_RAW = 'license.status_raw';
	private const KEY_LICENSE_EXPIRES_AT = 'license.expires_at';
	private const KEY_LICENSE_LAST_SYNC_AT = 'license.last_sync_at';
	private const KEY_LICENSE_LAST_ERROR = 'license.last_error';

	/**
	 * Lizenzserver erwartet JSON payload mit:
	 * - email (string)
	 * - license_key (string)
	 *
	 * und liefert:
	 * - status (active|expired|inactive|invalid)
	 * - seats (int)
	 * - expires_at (ISO-8601 string|YYYY-MM-DD|null)
	 */
	private const LICENSE_ENDPOINT = 'https://nc-connector.de/wp-json/ncc/v1/license/status';

	private const GRACE_PERIOD_DAYS = 14;
	private const MODE_COMMUNITY = 'community';
	private const MODE_PRO = 'pro';

	public function __construct(
		private SettingMapper $settings,
		private ICrypto $crypto,
		private IClientService $clientService,
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

		$now = time();
		$this->settings->setValue(self::KEY_LICENSE_EMAIL, $email, $now);
		$this->settings->setValue(self::KEY_LICENSE_KEY, $this->crypto->encrypt($licenseKey), $now);
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

		return [
			'mode' => $this->getMode(),
			'has_credentials' => $hasCredentials,
			'email' => $email,
			'purchased_seats' => $this->getPurchasedSeats(),
			'total_seats' => $this->getTotalSeats(),
			'status_raw' => $this->getRawStatus(),
			'status_effective' => $this->getEffectiveStatus(),
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
		$licenseKey = $this->crypto->decrypt($encryptedKey);

		$client = $this->clientService->newClient();
		$now = time();

		try {
			$requestBody = json_encode([
				'email' => $email,
				'license_key' => $licenseKey,
			], JSON_THROW_ON_ERROR);

			$response = $client->post(self::LICENSE_ENDPOINT, [
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
				],
				'body' => $requestBody,
			]);

			$body = $response->getBody();
			if (is_resource($body)) {
				$body = stream_get_contents($body);
			}
			if (!is_string($body)) {
				throw new \RuntimeException('Invalid license response (empty body)');
			}

			$payload = json_decode($body, true);
			if (!is_array($payload)) {
				throw new \RuntimeException('Invalid license response (no JSON object)');
			}

			$statusCode = $response->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$errorMessage = trim((string)($payload['message'] ?? $payload['error'] ?? ''));
				if ($errorMessage === '') {
					$errorMessage = 'License server returned HTTP ' . $statusCode;
				}
				throw new \RuntimeException($errorMessage);
			}

			$purchasedSeats = (int)($payload['seats'] ?? $payload['purchased_seats'] ?? 0);
			$status = (string)($payload['status'] ?? '');
			$expiresAt = $this->parseExpiresAt($payload['expires_at'] ?? null);

			$this->settings->setValue(self::KEY_LICENSE_PURCHASED_SEATS, (string)max(0, $purchasedSeats), $now);
			$this->settings->setValue(self::KEY_LICENSE_STATUS_RAW, $status, $now);
			$this->settings->setValue(self::KEY_LICENSE_EXPIRES_AT, $expiresAt !== null ? (string)$expiresAt : '', $now);
			$this->settings->setValue(self::KEY_LICENSE_LAST_SYNC_AT, (string)$now, $now);
			$this->settings->setValue(self::KEY_LICENSE_LAST_ERROR, '', $now);
		} catch (\Throwable $e) {
			$this->logError('License sync failed: ' . $e->getMessage(), $e);
			$this->settings->setValue(self::KEY_LICENSE_LAST_ERROR, $e->getMessage(), $now);
			throw $e;
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
		$raw = $this->getRawStatus();
		$expiresAt = $this->getExpiresAt();
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
		if ($base === 'INACTIVE' || $base === 'INVALID') {
			return $base;
		}

		if ($expiresAt > $now) {
			return 'ACTIVE';
		}

		$graceUntil = $expiresAt + (self::GRACE_PERIOD_DAYS * 86400);
		if ($now <= $graceUntil) {
			return 'GRACE';
		}

		return 'EXPIRED';
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
			} catch (\Throwable) {
				return null;
			}
		}
		return null;
	}

	private function formatIso(?int $ts): ?string {
		if ($ts === null || $ts <= 0) {
			return null;
		}
		return gmdate('c', $ts);
	}

	private function logError(string $message, \Throwable $exception): void {
		try {
			Server::get(ILogger::class)->error($message, [
				'app' => 'nc_connector',
				'exception' => $exception,
			]);
		} catch (\Throwable) {
			// Never fail because logging is unavailable.
		}
	}
}
