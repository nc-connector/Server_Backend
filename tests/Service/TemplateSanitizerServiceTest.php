<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Service\TemplateSanitizerService;
use PHPUnit\Framework\TestCase;

final class TemplateSanitizerServiceTest extends TestCase {
	private TemplateSanitizerService $sanitizer;

	protected function setUp(): void {
		$this->sanitizer = new TemplateSanitizerService();
	}

	public function testCssImageUrlsAreRemovedButMailStylesStay(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<span style="color: #123; background-image: url(https://example.invalid/a.png); padding: 4px;">Text</span>'
		);

		self::assertStringContainsString('color: #123', $html);
		self::assertStringContainsString('padding: 4px', $html);
		self::assertStringNotContainsString('background-image', $html);
		self::assertStringNotContainsString('url(', strtolower($html));
	}

	public function testAllRejectedStyleDeclarationsRemoveStyleAttribute(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<span style="background: url(https://example.invalid/a.png); behavior: url(#x);">Text</span>'
		);

		self::assertStringContainsString('<span>Text</span>', $html);
		self::assertStringNotContainsString('style=', $html);
	}

	public function testScriptTagsAndInlineHandlersAreRemoved(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<p onclick="alert(1)">Safe</p><script>alert(1)</script><iframe src="https://example.invalid"></iframe>'
		);

		self::assertStringContainsString('<p>Safe</p>', $html);
		self::assertStringNotContainsString('onclick', $html);
		self::assertStringNotContainsString('<script', $html);
		self::assertStringNotContainsString('<iframe', $html);
	}

	public function testHttpImageSourcesStayForTemplateAssetCache(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<img src="https://example.invalid/logo.png" alt="Logo" width="120" height="40">'
		);

		self::assertStringContainsString('<img', $html);
		self::assertStringContainsString('src="https://example.invalid/logo.png"', $html);
		self::assertStringContainsString('alt="Logo"', $html);
	}

	public function testBlankTargetsReceiveNoopenerAndNoreferrer(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<a href="https://example.invalid" target="_blank">Open</a>'
		);

		self::assertStringContainsString('target="_blank"', $html);
		self::assertStringContainsString('rel="noopener noreferrer"', $html);
	}

	public function testTemplateVariablesStayResolvableInsideLinkAttributes(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<a href="tel:{PHONE}">{PHONE}</a>'
			. '<a href="mailto:{EMAIL}">{EMAIL}</a>'
			. '<a href="{CUSTOM1}">{CUSTOM1}</a>'
		);

		self::assertStringContainsString('href="tel:{PHONE}"', $html);
		self::assertStringContainsString('href="mailto:{EMAIL}"', $html);
		self::assertStringContainsString('href="{CUSTOM1}"', $html);
		self::assertStringNotContainsString('%7B', $html);
	}

	public function testRenderedTemplateValuesCannotIntroduceUnsafeLinkSchemes(): void {
		$template = $this->sanitizer->sanitizeHtml('<a href="{CUSTOM1}">{CUSTOM1}</a>');
		$rendered = strtr($template, ['{CUSTOM1}' => 'javascript:alert(1)']);
		$html = $this->sanitizer->sanitizeHtml($rendered);

		self::assertSame('<a>javascript:alert(1)</a>', $html);
		self::assertStringNotContainsString('href=', $html);
	}

	public function testUnsafeUrlSchemesAreRemoved(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<a href="java&#x0A;script:alert(1)">Bad</a><img src="vbscript:alert(1)" alt="Bad">'
		);

		self::assertStringContainsString('<a>Bad</a>', $html);
		self::assertStringContainsString('<img alt="Bad">', $html);
		self::assertStringNotContainsString('javascript', strtolower($html));
		self::assertStringNotContainsString('vbscript', strtolower($html));
	}

	public function testUnknownElementsAreUnwrappedButTextStays(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<custom-card><span>Visible</span></custom-card>'
		);

		self::assertSame('<span>Visible</span>', $html);
	}

	public function testDocumentWrapperCommentsAndProcessingInstructionsAreRemoved(): void {
		$html = $this->sanitizer->sanitizeHtml(
			'<!doctype html><html><head><title>X</title></head><body><!--x--><?pi test?><p>Visible</p></body></html>'
		);

		self::assertSame('<p>Visible</p>', $html);
	}
}
