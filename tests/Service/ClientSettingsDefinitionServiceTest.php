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
