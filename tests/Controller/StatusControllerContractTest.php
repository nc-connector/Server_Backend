<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\StatusController;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class StatusControllerContractTest extends TestCase {
	public function testStatusApiGroupsEffectivePolicyForSeatUser(): void {
		$versionedShareTemplate = '<p>{LINK_INTRO}</p><p>{LINK_LABEL}: <a href="{URL}">{URL}</a></p>';
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(['user_id' => 'target']),
			new TestAccessService(['admin'], ['target']),
			new TestSeatService(['target']),
			new TestLicenseService(true, [
				'mode' => 'pro',
				'expires_at_iso' => null,
				'grace_until_iso' => null,
			]),
			new TestClientSettingsService(
				effectiveSettings: [
					'share_default_expire_days' => 14,
					'share_html_block_template' => $versionedShareTemplate,
					'share_send_password_mode' => 'secrets',
					'talk_lobby_enabled' => true,
					'talk_invitation_template_format' => 'html',
					'email_signature_on_compose' => true,
				],
				effectiveEditable: [
					'share_default_expire_days' => false,
					'share_html_block_template' => false,
					'share_send_password_mode' => true,
					'talk_lobby_enabled' => false,
					'talk_invitation_template_format' => false,
					'email_signature_on_compose' => false,
				],
				signatureEmail: 'target@example.test'
			),
			'admin'
		);

		$response = $controller->status();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('target', $data['status']['user_id']);
		self::assertTrue($data['status']['seat_assigned']);
		self::assertSame('active', $data['status']['seat_state']);
		self::assertFalse($data['status']['overlicensed']);
		self::assertSame('pro', $data['status']['mode']);
		self::assertTrue($data['status']['is_valid']);

		self::assertSame('secrets', $data['policy']['share']['share_send_password_mode']);
		self::assertSame(14, $data['policy']['share']['share_default_expire_days']);
		self::assertSame($versionedShareTemplate, $data['policy']['share']['share_html_block_template_v2']);
		self::assertStringNotContainsString('{LINK_INTRO}', $data['policy']['share']['share_html_block_template']);
		self::assertStringNotContainsString('{LINK_LABEL}', $data['policy']['share']['share_html_block_template']);
		self::assertStringContainsString('The files have been provided securely', $data['policy']['share']['share_html_block_template']);
		self::assertStringContainsString('Download link', $data['policy']['share']['share_html_block_template']);
		self::assertTrue($data['policy']['talk']['talk_lobby_enabled']);
		self::assertSame('html', $data['policy']['talk']['event_description_type']);
		self::assertTrue($data['policy']['email_signature']['email_signature_on_compose']);
		self::assertSame('target@example.test', $data['policy']['email_signature']['user_email']);

		self::assertTrue($data['policy_editable']['share']['share_send_password_mode']);
		self::assertArrayNotHasKey('share_html_block_template_v2', $data['policy_editable']['share']);
		self::assertFalse($data['policy_editable']['talk']['talk_lobby_enabled']);
		self::assertFalse($data['policy_editable']['email_signature']['email_signature_on_compose']);
	}

	public function testStatusApiKeepsExistingTemplatesWithoutVersionedVariablesUnchanged(): void {
		$legacyTemplate = '<p>Existing customer link: <a href="{URL}">{URL}</a></p>';
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['customer']),
			new TestSeatService(['customer']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: ['share_html_block_template' => $legacyTemplate],
				effectiveEditable: ['share_html_block_template' => false]
			),
			'customer'
		);

		$policy = $controller->status()->getData()['policy']['share'];

		self::assertSame($legacyTemplate, $policy['share_html_block_template']);
		self::assertSame($legacyTemplate, $policy['share_html_block_template_v2']);
	}

	public function testStatusApiProjectsNullTemplateForNonCustomLanguage(): void {
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: ['customer']),
			new TestSeatService(['customer']),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: ['share_html_block_template' => null],
				effectiveEditable: ['share_html_block_template' => false]
			),
			'customer'
		);

		$policy = $controller->status()->getData()['policy']['share'];

		self::assertNull($policy['share_html_block_template']);
		self::assertNull($policy['share_html_block_template_v2']);
	}

	public function testStatusApiDoesNotExposePolicyWithoutSeat(): void {
		$controller = new StatusController(
			'ncc_backend_4mc',
			new TestRequest(),
			new TestAccessService(validSeatUsers: []),
			new TestSeatService(),
			new TestLicenseService(),
			new TestClientSettingsService(
				effectiveSettings: ['share_send_password_mode' => 'secrets'],
				effectiveEditable: ['share_send_password_mode' => true]
			),
			'seatless'
		);

		$response = $controller->status();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('seatless', $data['status']['user_id']);
		self::assertFalse($data['status']['seat_assigned']);
		self::assertSame('none', $data['status']['seat_state']);
		self::assertNull($data['policy']['share']);
		self::assertNull($data['policy']['talk']);
		self::assertNull($data['policy']['email_signature']);
		self::assertNull($data['policy_editable']['share']);
	}
}
