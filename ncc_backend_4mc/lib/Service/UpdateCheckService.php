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
use Psr\Log\LoggerInterface;

class UpdateCheckService {
	private const PRODUCT = 'backend';
	private const CHANNEL = 'stable';
	private const UPDATE_ENDPOINT = 'https://nc-connector.de/wp-json/ncc/v1/update-check';
	private const KEY_INSTALL_ID = 'update.install_id';
	private const KEY_LAST_CHECK_AT = 'update.last_check_at';
	private const KEY_LAST_PAYLOAD = 'update.last_payload';
	private const KEY_LAST_ERROR = 'update.last_error';

	public function __construct(
		private SettingMapper $settings,
		private IClientService $clientService,
		private LoggerInterface $logger,
	) {
	}

	public function getSnapshot(): array {
		$currentVersion = $this->getInstalledVersion();
		$cachedPayload = $this->getCachedPayload();
		$lastCheckAt = $this->getLastCheckAt();

		return $this->buildSnapshot($currentVersion, $cachedPayload, $lastCheckAt, (string)$this->settings->getValue(self::KEY_LAST_ERROR, ''));
	}

	public function refreshIfDue(): array {
		$currentVersion = $this->getInstalledVersion();
		$cachedPayload = $this->getCachedPayload();
		$lastCheckAt = $this->getLastCheckAt();

		if ($lastCheckAt !== null && gmdate('Y-m-d', $lastCheckAt) === gmdate('Y-m-d') && $cachedPayload !== null) {
			return $this->buildSnapshot($currentVersion, $cachedPayload, $lastCheckAt, '');
		}

		try {
			$payload = $this->fetchUpdateMetadata($currentVersion);
			$now = time();
			$this->settings->setValue(self::KEY_LAST_PAYLOAD, json_encode($payload, JSON_THROW_ON_ERROR), $now);
			$this->settings->setValue(self::KEY_LAST_CHECK_AT, (string)$now, $now);
			$this->settings->setValue(self::KEY_LAST_ERROR, '', $now);
			return $this->buildSnapshot($currentVersion, $payload, $now, '');
		} catch (\Throwable $exception) {
			$message = $exception->getMessage();
			$this->settings->setValue(self::KEY_LAST_ERROR, $message, time());
			$this->logger->warning('Backend update check failed', [
				'exception' => $exception,
			]);
			return $this->buildSnapshot($currentVersion, $cachedPayload, $lastCheckAt, $message);
		}
	}

	public function refreshIfMissing(): array {
		if ($this->getLastCheckAt() !== null && $this->getCachedPayload() !== null) {
			return $this->getSnapshot();
		}

		return $this->refreshIfDue();
	}

	private function fetchUpdateMetadata(string $currentVersion): array {
		$client = $this->clientService->newClient();
		$url = self::UPDATE_ENDPOINT . '?' . http_build_query([
			'product' => self::PRODUCT,
			'version' => $currentVersion,
			'channel' => self::CHANNEL,
			'client_day_id' => $this->getClientDayId(),
		]);

		$response = $client->get($url, [
			'timeout' => 5,
			'headers' => [
				'Accept' => 'application/json',
			],
		]);

		$body = $response->getBody();
		if (is_resource($body)) {
			$body = stream_get_contents($body);
		}
		if (!is_string($body) || trim($body) === '') {
			throw new \RuntimeException('Update server returned an empty response');
		}

		$payload = json_decode($body, true);
		if (!is_array($payload)) {
			throw new \RuntimeException('Update server returned invalid JSON');
		}

		$statusCode = $response->getStatusCode();
		if ($statusCode < 200 || $statusCode >= 300) {
			$message = trim((string)($payload['message'] ?? $payload['error'] ?? ''));
			throw new \RuntimeException($message !== '' ? $message : 'Update server returned HTTP ' . $statusCode);
		}

		return $payload;
	}

	private function buildSnapshot(string $currentVersion, ?array $payload, ?int $lastCheckAt, string $lastError): array {
		$latestVersion = trim((string)($payload['latest_version'] ?? ''));
		$updateAvailable = $latestVersion !== ''
			&& $currentVersion !== ''
			&& version_compare($this->normalizeVersion($latestVersion), $this->normalizeVersion($currentVersion), '>');

		return [
			'product' => self::PRODUCT,
			'current_version' => $currentVersion,
			'latest_version' => $latestVersion,
			'update_available' => $updateAvailable,
			'is_current' => $currentVersion !== '' && $latestVersion !== '' && !$updateAvailable,
			'last_checked_at' => $lastCheckAt,
			'last_checked_at_iso' => $this->formatIso($lastCheckAt),
			'last_error' => $lastError,
		];
	}

	private function getClientDayId(): string {
		return hash('sha256', $this->getInstallId() . '|' . gmdate('Y-m-d') . '|' . self::PRODUCT);
	}

	private function getInstallId(): string {
		$value = trim((string)$this->settings->getValue(self::KEY_INSTALL_ID, ''));
		if ($value !== '') {
			return $value;
		}

		$value = bin2hex(random_bytes(16));
		$this->settings->setValue(self::KEY_INSTALL_ID, $value, time());
		return $value;
	}

	private function getCachedPayload(): ?array {
		$value = trim((string)$this->settings->getValue(self::KEY_LAST_PAYLOAD, ''));
		if ($value === '') {
			return null;
		}

		try {
			$payload = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
		} catch (\Throwable $exception) {
			$this->logger->warning('Ignoring invalid cached update metadata', [
				'exception' => $exception,
			]);
			return null;
		}

		return is_array($payload) ? $payload : null;
	}

	private function getLastCheckAt(): ?int {
		$value = trim((string)$this->settings->getValue(self::KEY_LAST_CHECK_AT, ''));
		if ($value === '') {
			return null;
		}

		$timestamp = (int)$value;
		return $timestamp > 0 ? $timestamp : null;
	}

	private function getInstalledVersion(): string {
		$path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'appinfo' . DIRECTORY_SEPARATOR . 'info.xml';
		if (!is_file($path) || !is_readable($path)) {
			return '';
		}

		$xml = file_get_contents($path);
		if (!is_string($xml) || !preg_match('/<version>([^<]+)<\\/version>/', $xml, $matches)) {
			return '';
		}

		return trim((string)$matches[1]);
	}

	private function normalizeVersion(string $version): string {
		return ltrim(trim($version), "vV \t\n\r\0\x0B");
	}

	private function formatIso(?int $timestamp): ?string {
		if ($timestamp === null || $timestamp <= 0) {
			return null;
		}
		return gmdate('c', $timestamp);
	}
}
