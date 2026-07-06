<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Service\TemplateAssetService;
use PHPUnit\Framework\TestCase;

final class TemplateAssetServiceTest extends TestCase {
	private TemplateAssetService $service;

	protected function setUp(): void {
		$this->service = (new \ReflectionClass(TemplateAssetService::class))->newInstanceWithoutConstructor();
	}

	public function testRedirectResolutionHandlesCommonLocationForms(): void {
		self::assertSame(
			'https://cdn.example.org/logo.png',
			$this->invoke('resolveRedirectUrl', 'https://example.org/images/start.png', 'https://cdn.example.org/logo.png')
		);
		self::assertSame(
			'https://cdn.example.org/logo.png',
			$this->invoke('resolveRedirectUrl', 'https://example.org/images/start.png', '//cdn.example.org/logo.png')
		);
		self::assertSame(
			'https://example.org/assets/logo.png',
			$this->invoke('resolveRedirectUrl', 'https://example.org/images/start.png', '/assets/logo.png')
		);
		self::assertSame(
			'https://example.org/images/logo.png',
			$this->invoke('resolveRedirectUrl', 'https://example.org/images/start.png', 'logo.png')
		);
	}

	public function testDownloadUrlRejectsNonHttpsAndPrivateAddresses(): void {
		self::assertSame(
			['reason' => 'unsupported_scheme'],
			$this->invoke('validateDownloadUrl', 'http://example.org/logo.png')
		);
		self::assertSame(
			['reason' => 'blocked_address'],
			$this->invoke('validateDownloadUrl', 'https://127.0.0.1/logo.png')
		);
		self::assertSame(
			['reason' => 'blocked_address'],
			$this->invoke('validateDownloadUrl', 'https://10.0.0.5/logo.png')
		);
	}

	public function testReadImageBodyRejectsEmptyAndOversizedBodies(): void {
		self::assertSame(
			['body' => null, 'warning' => ['reason' => 'empty_body']],
			$this->invoke('readImageBody', '')
		);

		$result = $this->invoke('readImageBody', str_repeat('x', 4194305));
		self::assertNull($result['body']);
		self::assertSame('too_large', $result['warning']['reason']);
		self::assertSame(4, $result['warning']['max_mb']);
	}

	public function testDetectsSupportedImageMagicBytes(): void {
		self::assertSame('png', $this->invoke('detectImageExtension', "\x89PNG\r\n\x1a\n" . 'body'));
		self::assertSame('jpg', $this->invoke('detectImageExtension', "\xff\xd8\xff" . 'body'));
		self::assertSame('gif', $this->invoke('detectImageExtension', 'GIF89a' . 'body'));
		self::assertSame('webp', $this->invoke('detectImageExtension', 'RIFFxxxxWEBP' . 'body'));
		self::assertNull($this->invoke('detectImageExtension', '<svg></svg>'));
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		$reflection = new \ReflectionMethod(TemplateAssetService::class, $method);
		return $reflection->invoke($this->service, ...$arguments);
	}
}
