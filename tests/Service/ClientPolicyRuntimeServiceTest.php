<?php

declare(strict_types=1);

namespace OCP\App {
	if (!interface_exists(IAppManager::class)) {
		interface IAppManager {
			public function isEnabledForUser(string $appId): bool;
		}
	}
}

namespace OCA\NcConnector\Tests\Service {

use OCA\NcConnector\Service\ClientPolicyRuntimeService;
use OCA\NcConnector\Service\ClientSettingsDefinitionService;
use OCA\NcConnector\Service\EmailSignatureRuntimeService;
use OCA\NcConnector\Service\TalkTemplateRuntimeService;
use OCA\NcConnector\Service\TemplateSanitizerService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ClientPolicyRuntimeServiceTest extends TestCase {
	public function testEditableDisabledComposeKeepsDependentValuesAndRendersTemplate(): void {
		$emailSignatures = $this->emailSignatureRenderer();
		$emailSignatures
			->expects(self::once())
			->method('renderTemplateForPolicy')
			->with('<p>{NAME}</p>', 'alice')
			->willReturn('<p>Alice</p>');
		$service = $this->service($emailSignatures);
		$settings = $this->signatureSettings();
		$sources = array_fill_keys(array_keys($settings), 'default');
		$policies = array_fill_keys(array_keys($settings), 'managed');
		$addonEditable = array_fill_keys(array_keys($settings), false);
		$addonEditable['email_signature_on_compose'] = true;

		$service->applyForUser($settings, $sources, $policies, $addonEditable, 'alice');

		self::assertFalse($settings['email_signature_on_compose']);
		self::assertTrue($settings['email_signature_on_reply']);
		self::assertFalse($settings['email_signature_on_forward']);
		self::assertSame('<p>Alice</p>', $settings['email_signature_template']);
	}

	public function testManagedDisabledComposeClearsDependentValuesBeforeRendering(): void {
		$emailSignatures = $this->emailSignatureRenderer();
		$emailSignatures
			->expects(self::never())
			->method('renderTemplateForPolicy');
		$service = $this->service($emailSignatures);
		$settings = $this->signatureSettings();
		$sources = array_fill_keys(array_keys($settings), 'default');
		$policies = array_fill_keys(array_keys($settings), 'managed');
		$addonEditable = array_fill_keys(array_keys($settings), false);

		$service->applyForUser($settings, $sources, $policies, $addonEditable, 'alice');

		self::assertFalse($settings['email_signature_on_compose']);
		self::assertNull($settings['email_signature_on_reply']);
		self::assertNull($settings['email_signature_on_forward']);
		self::assertNull($settings['email_signature_template']);
	}

	/**
	 * @return EmailSignatureRuntimeService&MockObject
	 */
	private function emailSignatureRenderer(): EmailSignatureRuntimeService {
		return $this->getMockBuilder(EmailSignatureRuntimeService::class)
			->disableOriginalConstructor()
			->onlyMethods(['renderTemplateForPolicy'])
			->getMock();
	}

	private function service(EmailSignatureRuntimeService $emailSignatures): ClientPolicyRuntimeService {
		$appManager = $this->createMock(IAppManager::class);
		$appManager
			->method('isEnabledForUser')
			->willReturn(true);

		return new ClientPolicyRuntimeService(
			new ClientSettingsDefinitionService(new TemplateSanitizerService()),
			new TalkTemplateRuntimeService(),
			$emailSignatures,
			$appManager,
			$this->createMock(LoggerInterface::class)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function signatureSettings(): array {
		return [
			'email_signature_on_compose' => false,
			'email_signature_on_reply' => true,
			'email_signature_on_forward' => false,
			'email_signature_template' => '<p>{NAME}</p>',
		];
	}
}
}
