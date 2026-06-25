<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class TemplateAssetService {
	private const MAX_IMAGE_BYTES = 4194304;
	private const MAX_REDIRECTS = 3;
	private const BLOCKED_IMAGE_DATA_URI = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

	public function __construct(
		private IClientService $clientService,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public function buildAssetMap(string $contextKey, string $template): array {
		return $this->buildAssetResult($contextKey, $template)['assets'];
	}

	/**
	 * @return array{assets:array<string, string>, warnings:list<array<string, mixed>>}
	 */
	public function buildAssetResult(string $contextKey, string $template): array {
		// Editor previews use local image URLs so external logos work under the app CSP.
		$sources = $this->extractExternalImageSources($template);
		$assets = [];
		$warnings = [];
		foreach ($sources as $source) {
			$cached = $this->cacheImage($contextKey, $source);
			if (is_string($cached['asset'] ?? null)) {
				$assets[$source] = $cached['asset'];
				continue;
			}
			if (is_array($cached['warning'] ?? null)) {
				$warnings[] = $cached['warning'];
				$assets[$source] = self::BLOCKED_IMAGE_DATA_URI;
			}
		}
		return [
			'assets' => $assets,
			'warnings' => $warnings,
		];
	}

	/**
	 * @return list<string>
	 */
	private function extractExternalImageSources(string $template): array {
		if (trim($template) === '') {
			return [];
		}

		$previousUseInternalErrors = libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previousUseInternalErrors);

		if ($loaded === false) {
			return [];
		}

		$sources = [];
		foreach ($document->getElementsByTagName('img') as $img) {
			$src = trim((string)$img->getAttribute('src'));
			if ($src === '' || !preg_match('#^https?://#i', $src)) {
				continue;
			}
			$sources[] = $src;
		}

		return array_values(array_unique($sources));
	}

	/**
	 * @return array{asset:?string, warning:?array<string, mixed>}
	 */
	private function cacheImage(string $contextKey, string $source): array {
		try {
			$urlWarning = $this->validateDownloadUrl($source);
			if ($urlWarning !== null) {
				return $this->cacheWarning($source, $urlWarning['reason'], $urlWarning);
			}

			$response = null;
			$currentUrl = $source;
			$redirects = 0;
			while (true) {
				$response = $this->clientService->newClient()->get($currentUrl, [
					'timeout' => 15,
					'allow_redirects' => false,
					'headers' => [
						'Accept' => 'image/png,image/jpeg,image/gif,image/webp',
					],
				]);

				$statusCode = $response->getStatusCode();
				if ($statusCode < 300 || $statusCode >= 400) {
					break;
				}
				if ($redirects >= self::MAX_REDIRECTS) {
					return $this->cacheWarning($source, 'too_many_redirects', [
						'redirects' => $redirects,
					]);
				}
				$location = $this->firstHeaderValue($response->getHeaders(), 'Location');
				$nextUrl = $this->resolveRedirectUrl($currentUrl, $location);
				if ($nextUrl === null) {
					return $this->cacheWarning($source, 'download_failed', [
						'detail' => 'invalid_redirect',
					]);
				}
				$urlWarning = $this->validateDownloadUrl($nextUrl);
				if ($urlWarning !== null) {
					return $this->cacheWarning($source, $urlWarning['reason'], $urlWarning);
				}
				$currentUrl = $nextUrl;
				$redirects++;
			}

			$statusCode = $response->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logger->warning('Template image cache received non-success response', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'status_code' => $statusCode,
				]);
				return $this->cacheWarning($source, 'http_status', [
					'status_code' => $statusCode,
				]);
			}

			$headers = $response->getHeaders();
			$contentLength = $this->firstHeaderValue($headers, 'Content-Length');
			if ($contentLength !== '' && (int)$contentLength > self::MAX_IMAGE_BYTES) {
				$this->logger->warning('Template image cache rejected oversized image by content length', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'content_length' => (int)$contentLength,
					'max_bytes' => self::MAX_IMAGE_BYTES,
				]);
				return $this->cacheWarning($source, 'too_large', [
					'max_mb' => (int)(self::MAX_IMAGE_BYTES / 1048576),
				]);
			}

			$contentTypeHeader = $this->firstHeaderValue($headers, 'Content-Type');
			$contentType = strtolower(trim(explode(';', (string)$contentTypeHeader)[0]));
			$extension = $this->mapImageContentTypeToExtension($contentType);
			if ($extension === null) {
				$this->logger->warning('Template image cache received unsupported content type', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'content_type' => $contentTypeHeader,
				]);
				return $this->cacheWarning($source, 'unsupported_content_type', [
					'content_type' => $contentTypeHeader,
				]);
			}

			$bodyResult = $this->readImageBody($response->getBody());
			if (is_array($bodyResult['warning'] ?? null)) {
				$this->logger->warning('Template image cache rejected image body', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'reason' => $bodyResult['warning']['reason'] ?? 'unknown',
				]);
				return $this->cacheWarning($source, (string)($bodyResult['warning']['reason'] ?? 'download_failed'), $bodyResult['warning']);
			}
			$body = $bodyResult['body'] ?? '';
			if (!is_string($body) || $body === '') {
				return $this->cacheWarning($source, 'empty_body');
			}

			$actualExtension = $this->detectImageExtension($body);
			if ($actualExtension !== $extension) {
				$this->logger->warning('Template image cache rejected image body with mismatching magic bytes', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'content_type' => $contentTypeHeader,
					'expected_extension' => $extension,
					'actual_extension' => $actualExtension,
				]);
				return $this->cacheWarning($source, 'invalid_image_body', [
					'content_type' => $contentTypeHeader,
				]);
			}

			$runtimeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'runtime';
			if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
				$this->logger->error('Template image cache failed to create runtime directory', [
					'app' => Application::APP_ID,
					'runtime_dir' => $runtimeDir,
				]);
				return $this->cacheWarning($source, 'runtime_dir_failed');
			}

			$contextPart = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($contextKey)) ?: 'template';
			$fileBaseName = $contextPart . '_' . substr(sha1($source), 0, 12);
			foreach (glob($runtimeDir . DIRECTORY_SEPARATOR . $fileBaseName . '.*') ?: [] as $existingFile) {
				if (is_file($existingFile)) {
					if (!unlink($existingFile) && is_file($existingFile)) {
						$this->logger->warning('Template image cache could not delete stale runtime file', [
							'file_path' => $existingFile,
							'context_key' => $contextKey,
							'src' => $source,
						]);
					}
				}
			}

			$fileName = $fileBaseName . '.' . $extension;
			$filePath = $runtimeDir . DIRECTORY_SEPARATOR . $fileName;
			if (file_put_contents($filePath, $body) === false) {
				$this->logger->error('Template image cache failed to write runtime file', [
					'app' => Application::APP_ID,
					'file_path' => $filePath,
					'src' => $source,
				]);
				return $this->cacheWarning($source, 'write_failed');
			}

			return [
				'asset' => $this->urlGenerator->imagePath(Application::APP_ID, 'runtime/' . $fileName)
					. '?v=' . substr(sha1($body), 0, 12),
				'warning' => null,
			];
		} catch (\Throwable $exception) {
			$this->logger->error('Template image cache failed', [
				'app' => Application::APP_ID,
				'context_key' => $contextKey,
				'src' => $source,
				'exception' => $exception,
			]);
			return $this->cacheWarning($source, 'download_failed');
		}
	}

	private function mapImageContentTypeToExtension(string $contentType): ?string {
		return match ($contentType) {
			'image/png' => 'png',
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			default => null,
		};
	}

	/**
	 * @return array{body:?string, warning:?array<string, mixed>}
	 */
	private function readImageBody(mixed $body): array {
		if (is_resource($body)) {
			$content = '';
			while (!feof($body)) {
				$chunk = fread($body, 8192);
				if ($chunk === false) {
					return [
						'body' => null,
						'warning' => ['reason' => 'download_failed', 'detail' => 'read_failed'],
					];
				}
				$content .= $chunk;
				if (strlen($content) > self::MAX_IMAGE_BYTES) {
					return [
						'body' => null,
						'warning' => [
							'reason' => 'too_large',
							'max_mb' => (int)(self::MAX_IMAGE_BYTES / 1048576),
						],
					];
				}
			}
			return [
				'body' => $content,
				'warning' => $content === '' ? ['reason' => 'empty_body'] : null,
			];
		}

		if (!is_string($body) || $body === '') {
			return [
				'body' => null,
				'warning' => ['reason' => 'empty_body'],
			];
		}
		if (strlen($body) > self::MAX_IMAGE_BYTES) {
			return [
				'body' => null,
				'warning' => [
					'reason' => 'too_large',
					'max_mb' => (int)(self::MAX_IMAGE_BYTES / 1048576),
				],
			];
		}

		return [
			'body' => $body,
			'warning' => null,
		];
	}

	private function detectImageExtension(string $body): ?string {
		if (str_starts_with($body, "\x89PNG\r\n\x1a\n")) {
			return 'png';
		}
		if (str_starts_with($body, "\xff\xd8\xff")) {
			return 'jpg';
		}
		if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
			return 'gif';
		}
		if (strlen($body) >= 12 && substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP') {
			return 'webp';
		}
		return null;
	}

	/**
	 * @return array{reason:string, detail?:string}|null
	 */
	private function validateDownloadUrl(string $url): ?array {
		$parts = parse_url($url);
		if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
			return ['reason' => 'unsupported_scheme'];
		}
		$host = trim((string)($parts['host'] ?? ''));
		if ($host === '') {
			return ['reason' => 'download_failed', 'detail' => 'missing_host'];
		}
		$addresses = $this->resolveHostAddresses($host);
		if ($addresses === []) {
			return ['reason' => 'download_failed', 'detail' => 'dns'];
		}
		// Template image URLs are admin-entered; block private targets before the server fetches them.
		foreach ($addresses as $address) {
			if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return ['reason' => 'blocked_address'];
			}
		}
		return null;
	}

	/**
	 * @return list<string>
	 */
	private function resolveHostAddresses(string $host): array {
		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return [$host];
		}

		$addresses = [];
		if (function_exists('dns_get_record')) {
			$records = dns_get_record($host, DNS_A + DNS_AAAA);
			if (is_array($records)) {
				foreach ($records as $record) {
					if (is_string($record['ip'] ?? null)) {
						$addresses[] = $record['ip'];
					}
					if (is_string($record['ipv6'] ?? null)) {
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}
		if ($addresses === []) {
			$ipv4Addresses = gethostbynamel($host);
			if (is_array($ipv4Addresses)) {
				$addresses = array_merge($addresses, $ipv4Addresses);
			}
		}

		return array_values(array_unique($addresses));
	}

	private function resolveRedirectUrl(string $currentUrl, string $location): ?string {
		$location = trim($location);
		if ($location === '') {
			return null;
		}
		if (preg_match('#^https?://#i', $location)) {
			return $location;
		}

		$current = parse_url($currentUrl);
		if (!is_array($current) || !is_string($current['scheme'] ?? null) || !is_string($current['host'] ?? null)) {
			return null;
		}
		if (str_starts_with($location, '//')) {
			return $current['scheme'] . ':' . $location;
		}
		$port = isset($current['port']) ? ':' . (int)$current['port'] : '';
		$base = $current['scheme'] . '://' . $current['host'] . $port;
		if (str_starts_with($location, '/')) {
			return $base . $location;
		}
		$path = (string)($current['path'] ?? '/');
		$directory = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
		return $base . $directory . $location;
	}

	private function firstHeaderValue(array $headers, string $name): string {
		foreach ($headers as $key => $value) {
			if (strtolower((string)$key) !== strtolower($name)) {
				continue;
			}
			if (is_array($value)) {
				return trim((string)($value[0] ?? ''));
			}
			return trim((string)$value);
		}
		return '';
	}

	/**
	 * @return array{asset:null, warning:array<string, mixed>}
	 */
	private function cacheWarning(string $source, string $reason, array $context = []): array {
		return [
			'asset' => null,
			'warning' => array_filter([
				'source' => $source,
				'reason' => $reason,
				'detail' => $context['detail'] ?? null,
				'status_code' => $context['status_code'] ?? null,
				'content_type' => $context['content_type'] ?? null,
				'max_mb' => $context['max_mb'] ?? null,
				'redirects' => $context['redirects'] ?? null,
			], static fn ($value) => $value !== null && $value !== ''),
		];
	}

}
