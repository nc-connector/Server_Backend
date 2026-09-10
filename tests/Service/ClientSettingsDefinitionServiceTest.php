<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Service\ClientSettingsDefinitionService;
use OCA\NcConnector\Service\EmailSignatureRuntimeService;
use OCA\NcConnector\Service\TemplateSanitizerService;
use PHPUnit\Framework\TestCase;

final class ClientSettingsDefinitionServiceTest extends TestCase {
	private ClientSettingsDefinitionService $definitions;

	protected function setUp(): void {
		$this->definitions = new ClientSettingsDefinitionService(new TemplateSanitizerService());
	}

	public function testPasswordDeliveryModeAcceptsOnlyKnownValues(): void {
		self::assertSame(
			ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_SECRETS,
			$this->definitions->normalizeValue(ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY, ' Secrets ')
		);
		self::assertSame(
			ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_PLAIN,
			$this->definitions->normalizeValue(ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY, 'plain')
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->definitions->normalizeValue(ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY, 'sms');
	}

	public function testSecretsExpireDaysIsBackendOnlyAndRangeChecked(): void {
		self::assertFalse($this->definitions->isAddonControllableSetting(ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY));
		self::assertSame(7, $this->definitions->normalizeValue(ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY, '7'));
		self::assertSame('7', $this->definitions->serializeValue(ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY, 7));

		$this->expectException(\InvalidArgumentException::class);
		$this->definitions->normalizeValue(ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY, 0);
	}

	public function testAttachmentMinimumSizeCanStayDisabled(): void {
		self::assertNull($this->definitions->normalizeValue('attachments_min_size_mb', null));
		self::assertSame('', $this->definitions->serializeValue('attachments_min_size_mb', null));
		self::assertNull($this->definitions->parseStoredValue('attachments_min_size_mb', ''));
	}

	public function testAttachmentLinkTargetIsAddonControllableEnumWithZipDefault(): void {
		$definition = $this->definitions->get(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_KEY);

		self::assertSame('enum', $definition['type']);
		self::assertSame(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_ZIP_DOWNLOAD, $definition['default']);
		self::assertSame([
			ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_ZIP_DOWNLOAD,
			ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_SHARE_PAGE,
		], $definition['options']);
		self::assertTrue($this->definitions->isAddonControllableSetting(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_KEY));
		self::assertSame(
			ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_SHARE_PAGE,
			$this->definitions->normalizeValue(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_KEY, ' SHARE_PAGE ')
		);
		self::assertSame(
			ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_ZIP_DOWNLOAD,
			$this->definitions->parseStoredValue(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_KEY, 'zip_download')
		);

		$this->expectException(\InvalidArgumentException::class);
		$this->definitions->normalizeValue(ClientSettingsDefinitionService::ATTACHMENT_LINK_TARGET_KEY, 'direct_download');
	}

	public function testVfsSwitchesAreAddonControllableBooleansWithTheirBuiltInDefaults(): void {
		foreach ([
			ClientSettingsDefinitionService::VFS_PROVIDER_ENABLED_KEY => true,
			ClientSettingsDefinitionService::VFS_EXTERNAL_PROVIDERS_ENABLED_KEY => false,
		] as $key => $expectedDefault) {
			$definition = $this->definitions->get($key);
			self::assertSame('bool', $definition['type']);
			self::assertSame($expectedDefault, $definition['default']);
			self::assertTrue($this->definitions->isAddonControllableSetting($key));
			self::assertTrue($this->definitions->normalizeValue($key, true));
			self::assertFalse($this->definitions->parseStoredValue($key, '0'));
		}
	}

	public function testTemplateValuesAreSanitizedBeforeStorageAndAfterRead(): void {
		$dirty = '<p onclick="alert(1)">Hello</p><script>alert(1)</script>';

		$normalized = $this->definitions->normalizeValue('email_signature_template', $dirty);
		$parsed = $this->definitions->parseStoredValue('email_signature_template', $dirty);

		foreach ([$normalized, $parsed] as $value) {
			self::assertStringContainsString('<p>Hello</p>', $value);
			self::assertStringNotContainsString('onclick', $value);
			self::assertStringNotContainsString('<script', $value);
		}
	}

	public function testDefaultShareTemplateUsesClientResolvedLinkVariables(): void {
		$default = (string)$this->definitions->get('share_html_block_template')['default'];

		self::assertStringContainsString('{LINK_INTRO}', $default);
		self::assertStringContainsString('{LINK_LABEL}', $default);
		self::assertStringContainsString('{URL}', $default);
		self::assertStringContainsString('data-nccb-legacy-link-intro=', $default);
		self::assertStringContainsString('data-nccb-legacy-link-label=', $default);
		self::assertStringNotContainsString('>Download link<', $default);
	}

	public function testDefaultShareTemplatesUseTransparentOutlookSafeBranding(): void {
		$transparentLogoUrl = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-transparent-164x48.png';
		$legacyLogoUrl = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png';

		foreach (['share_html_block_template', 'share_password_template'] as $key) {
			$default = (string)$this->definitions->get($key)['default'];
			$roundTripped = (string)$this->definitions->normalizeValue($key, $default);

			self::assertStringContainsString($transparentLogoUrl, $default);
			self::assertStringContainsString($transparentLogoUrl, $roundTripped);
			self::assertStringContainsString('<nobr style="white-space: nowrap">Password</nobr>', $roundTripped);
			self::assertStringContainsString('cellspacing="0"', $roundTripped);
			self::assertStringContainsString('cellpadding="0"', $roundTripped);
			self::assertStringContainsString('padding: 18px 18px 22px', $roundTripped);
			self::assertStringContainsString('width="124"', $roundTripped);
			self::assertStringContainsString('width: 124px', $roundTripped);
			self::assertStringContainsString('font-family: Calibri', $roundTripped);
			self::assertStringContainsString('font-size: 11pt', $roundTripped);
			self::assertStringContainsString('margin: 0', $roundTripped);
			self::assertStringNotContainsString($legacyLogoUrl, $default);
			self::assertStringNotContainsString($legacyLogoUrl, $roundTripped);
		}

		$shareDefault = (string)$this->definitions->get('share_html_block_template')['default'];
		$shareRoundTripped = (string)$this->definitions->normalizeValue('share_html_block_template', $shareDefault);
		self::assertStringContainsString('<nobr style="white-space: nowrap">{LINK_LABEL}</nobr>', $shareRoundTripped);
		self::assertStringContainsString('<nobr style="white-space: nowrap">{EXPIRATIONDATE}</nobr>', $shareRoundTripped);
		self::assertStringNotContainsString('width: 12ch', $shareRoundTripped);
		self::assertStringContainsString('-ms-user-select: all', $shareRoundTripped);
		self::assertStringNotContainsString('width: 13ch', $shareRoundTripped);
	}

	public function testStoredLegacyShareBrandingIsNotMigratedToTheNewDefault(): void {
		$legacyLogoUrl = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png';
		$transparentLogoUrl = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-transparent-164x48.png';
		$storedTemplate = '<div><img src="' . $legacyLogoUrl . '" height="32" style="display:block; height:32px; width:auto; border:0; margin:0 auto;"></div>';

		foreach ([
			$this->definitions->normalizeValue('share_html_block_template', $storedTemplate),
			$this->definitions->parseStoredValue('share_html_block_template', $storedTemplate),
		] as $value) {
			self::assertStringContainsString($legacyLogoUrl, $value);
			self::assertStringNotContainsString($transparentLogoUrl, $value);
		}
	}

	public function testLegacyStoredShareTemplateIsNotRewrittenWithNewVariables(): void {
		$legacy = '<p>Legacy link: {URL}</p>';

		foreach ([
			$this->definitions->normalizeValue('share_html_block_template', $legacy),
			$this->definitions->parseStoredValue('share_html_block_template', $legacy),
		] as $value) {
			self::assertStringContainsString('Legacy link: {URL}', $value);
			self::assertStringNotContainsString('{LINK_INTRO}', $value);
			self::assertStringNotContainsString('{LINK_LABEL}', $value);
		}
	}

	public function testDefaultEmailSignatureIncludesAllSupportedContactVariables(): void {
		$default = (string)$this->definitions->get('email_signature_template')['default'];
		$sanitized = (string)$this->definitions->normalizeValue('email_signature_template', $default);

		foreach ([
			'{NAME}',
			'{FUNCTION}',
			'{ABOUT}',
			'{ORGANISATION}',
			'{PHONE}',
			'{PHONE_MOBILE}',
			'{EMAIL}',
			'{CUSTOM1}',
			'{CUSTOM2}',
		] as $variable) {
			self::assertStringContainsString($variable, $default);
		}

		self::assertStringContainsString('Musterstra&szlig;e 1', $default);
		self::assertStringContainsString('9999 Musterort', $default);
		self::assertStringContainsString(
			'https://raw.githubusercontent.com/nc-connector/Server_Backend/refs/heads/main/ncc_backend_4mc/img/header.png',
			$default
		);
		self::assertStringNotContainsString('/img/runtime/', $default);
		self::assertStringContainsString('href="tel:{PHONE}"', $sanitized);
		self::assertStringContainsString('href="tel:{PHONE_MOBILE}"', $sanitized);
		self::assertStringContainsString('href="mailto:{EMAIL}"', $sanitized);
		self::assertStringContainsString('href="{CUSTOM1}"', $sanitized);
		self::assertStringContainsString('href="{CUSTOM2}"', $sanitized);
		self::assertStringContainsString('src="https://raw.githubusercontent.com/nc-connector/Server_Backend/refs/heads/main/ncc_backend_4mc/img/header.png"', $sanitized);
	}

	public function testStoredEmailSignatureTemplateIsNotReplacedByNewDefault(): void {
		$customerTemplate = '<div>Customer signature for {NAME}</div>';

		foreach ([
			$this->definitions->normalizeValue('email_signature_template', $customerTemplate),
			$this->definitions->parseStoredValue('email_signature_template', $customerTemplate),
		] as $value) {
			self::assertSame($customerTemplate, $value);
			self::assertStringNotContainsString('Musterort', $value);
			self::assertStringNotContainsString('{PHONE_MOBILE}', $value);
		}
	}

	public function testUserOverrideOnlySignatureFieldsAreNotAddonControllable(): void {
		self::assertSame([
			EmailSignatureRuntimeService::EMAIL_ADDRESS_KEY,
			EmailSignatureRuntimeService::PHONE_MOBILE_KEY,
			EmailSignatureRuntimeService::CUSTOM1_KEY,
			EmailSignatureRuntimeService::CUSTOM2_KEY,
		], $this->definitions->userOverrideOnlyKeys());

		foreach ($this->definitions->userOverrideOnlyKeys() as $key) {
			self::assertTrue($this->definitions->isUserOverrideOnlySetting($key));
			self::assertFalse($this->definitions->isAddonControllableSetting($key));
		}
	}

	public function testTemplateAssetPreviewIgnoresNonTemplateKeys(): void {
		$preview = $this->definitions->normalizeTemplateAssetPreview([
			'email_signature_template' => '<p onclick="alert(1)">Signature</p>',
			'share_send_password_mode' => 'secrets',
		]);

		self::assertSame(['email_signature_template'], array_keys($preview));
		self::assertSame('<p>Signature</p>', $preview['email_signature_template']);
	}
}
