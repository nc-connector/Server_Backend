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
		$sources = $this->extractExternalImageSources($template);
		$assets = [];
		foreach ($sources as $index => $source) {
			$localUrl = $this->cacheImage($contextKey, $index, $source);
			if ($localUrl !== null) {
				$assets[$source] = $localUrl;
			}
		}
		return $assets;
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

	private function cacheImage(string $contextKey, int $index, string $source): ?string {
		try {
			$response = $this->clientService->newClient()->get($source, [
				'timeout' => 15,
				'headers' => [
					'Accept' => 'image/*,*/*;q=0.8',
				],
			]);
			$statusCode = $response->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logError('Template image cache received non-success response', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'status_code' => $statusCode,
				]);
				return null;
			}

			$headers = $response->getHeaders();
			$contentTypeHeader = $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? '';
			$contentType = strtolower(trim(explode(';', (string)$contentTypeHeader)[0]));
			$extension = $this->mapImageContentTypeToExtension($contentType);
			if ($extension === null) {
				$this->logError('Template image cache received unsupported content type', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'content_type' => $contentTypeHeader,
				]);
				return null;
			}

			$body = $response->getBody();
			if (is_resource($body)) {
				$body = stream_get_contents($body);
			}
			if (!is_string($body) || $body === '') {
				$this->logError('Template image cache received empty image body', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
				]);
				return null;
			}

			$runtimeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'runtime';
			if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
				$this->logError('Template image cache failed to create runtime directory', [
					'app' => Application::APP_ID,
					'runtime_dir' => $runtimeDir,
				]);
				return null;
			}

			$fileBaseName = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($contextKey)) . '_' . $index;
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
				$this->logError('Template image cache failed to write runtime file', [
					'app' => Application::APP_ID,
					'file_path' => $filePath,
					'src' => $source,
				]);
				return null;
			}

			return $this->urlGenerator->imagePath(Application::APP_ID, 'runtime/' . $fileName)
				. '?v=' . substr(sha1($body), 0, 12);
		} catch (\Throwable $exception) {
			$this->logError('Template image cache failed', [
				'app' => Application::APP_ID,
				'context_key' => $contextKey,
				'src' => $source,
				'exception' => $exception,
			]);
			return null;
		}
	}

	private function mapImageContentTypeToExtension(string $contentType): ?string {
		return match ($contentType) {
			'image/png' => 'png',
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			default => null,
		};
	}

	private function logError(string $message, array $context = []): void {
		$this->logger->error($message, $context);
	}
}
