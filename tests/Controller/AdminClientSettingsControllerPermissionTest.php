<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\AdminClientSettingsController;
use OCA\NcConnector\Service\AdminPermissionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class AdminClientSettingsControllerPermissionTest extends TestCase {
	public function testDelegatedSignatureTemplateAdminCanSaveSignatureTemplateUserFields(): void {
		[$controller, $settings] = $this->controller(
			['signature.user_overrides', 'signature.templates'],
			[
				'overrides' => [
					'email_signature_phone_mobile' => ['mode' => 'forced', 'value' => '0160 / 123'],
					'email_signature_custom1' => ['mode' => 'forced', 'value' => 'Team Nord'],
				],
			]
		);

		$response = $controller->setUserSettings('target');

		self::assertSame(200, $response->getStatus());
		self::assertCount(1, $settings->setUserCalls);
		self::assertSame('target', $settings->setUserCalls[0]['user_id']);
		self::assertSame('delegate', $settings->setUserCalls[0]['updated_by']);
		self::assertArrayHasKey('email_signature_phone_mobile', $settings->setUserCalls[0]['overrides']);
		self::assertArrayHasKey('email_signature_custom1', $settings->setUserCalls[0]['overrides']);
	}

	public function testDelegatedSignatureTemplateAdminCannotSaveSignaturePolicySwitches(): void {
		[$controller, $settings] = $this->controller(
			['signature.user_overrides', 'signature.templates'],
			[
				'overrides' => [
					'email_signature_on_reply' => ['mode' => 'forced', 'value' => true],
				],
			]
		);

		$response = $controller->setUserSettings('target');

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin permission required'], $response->getData());
		self::assertSame([], $settings->setUserCalls);
	}

	public function testDelegatedSignatureTemplateAdminSeesOnlyTemplateScopedUserSettings(): void {
		[$controller] = $this->controller(['signature.user_overrides', 'signature.templates']);

		$response = $controller->getUserSettings('target');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame(
			['email_signature_phone_mobile', 'email_signature_custom1', 'email_signature_template'],
			array_keys($data['items'])
		);
		self::assertContains('email_signature_phone_mobile', array_keys($data['schema']));
		self::assertContains('email_signature_custom1', array_keys($data['schema']));
		self::assertContains('email_signature_template', array_keys($data['schema']));
		self::assertArrayNotHasKey('email_signature_on_reply', $data['items']);
		self::assertArrayNotHasKey('share_send_password_mode', $data['items']);
	}

	public function testDelegatedSignaturePolicyAdminSeesOnlyPolicyScopedUserSettings(): void {
		[$controller] = $this->controller(['signature.user_overrides', 'signature.policy']);

		$response = $controller->getUserSettings('target');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame(['email_signature_on_reply'], array_keys($data['items']));
		self::assertContains('email_signature_on_reply', array_keys($data['schema']));
		self::assertArrayNotHasKey('email_signature_template', $data['items']);
		self::assertArrayNotHasKey('email_signature_phone_mobile', $data['items']);
		self::assertArrayNotHasKey('email_signature_custom1', $data['items']);
	}

	public function testDelegatedSignaturePolicyAdminCannotSaveTemplateUserFields(): void {
		[$controller, $settings] = $this->controller(
			['signature.user_overrides', 'signature.policy'],
			[
				'overrides' => [
					'email_signature_template' => ['mode' => 'forced', 'value' => '<p>Signature</p>'],
				],
			]
		);

		$response = $controller->setUserSettings('target');

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin permission required'], $response->getData());
		self::assertSame([], $settings->setUserCalls);
	}

	public function testUserSettingsRequireAssignedSeatAfterPermissionCheck(): void {
		[$controller, $settings] = $this->controller(
			['signature.user_overrides', 'signature.templates'],
			[
				'overrides' => [
					'email_signature_custom1' => ['mode' => 'forced', 'value' => 'Team Nord'],
				],
			],
			seatUsers: []
		);

		$response = $controller->setUserSettings('target');

		self::assertSame(422, $response->getStatus());
		self::assertSame(['error' => 'User has no seat'], $response->getData());
		self::assertSame([], $settings->setUserCalls);
	}

	public function testDelegatedSignatureGroupOverrideAdminWithoutPolicySeesOnlyTemplateFields(): void {
		[$controller] = $this->controller(['signature.group_overrides', 'signature.templates']);

		$response = $controller->getGroupSettings('group-a');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame(['email_signature_template'], array_keys($data['items']));
		self::assertContains('email_signature_template', array_keys($data['schema']));
		self::assertArrayNotHasKey('email_signature_on_reply', $data['items']);
	}

	public function testDelegatedSignatureGroupPolicyAdminCannotEditTemplateFields(): void {
		[$controller] = $this->controller(['signature.group_overrides', 'signature.policy']);

		$response = $controller->getGroupSettings('group-a');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame(['email_signature_on_reply'], array_keys($data['items']));
		self::assertArrayNotHasKey('email_signature_template', $data['items']);
	}

	public function testDelegatedSignatureGroupPolicyAdminCannotSaveTemplateFields(): void {
		[$controller] = $this->controller(
			['signature.group_overrides', 'signature.policy'],
			[
				'group_id' => 'group-a',
				'overrides' => [
					'email_signature_template' => ['mode' => 'forced', 'value' => '<p>Signature</p>'],
				],
			]
		);

		$response = $controller->setGroupSettings();

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin permission required'], $response->getData());
	}

	public function testDelegatedSignatureGroupOverrideAdminNeedsPolicyForPolicyFields(): void {
		[$controller] = $this->controller(
			['signature.group_overrides', 'signature.templates'],
			[
				'group_id' => 'group-a',
				'overrides' => [
					'email_signature_on_reply' => ['mode' => 'forced', 'value' => true],
				],
			]
		);

		$response = $controller->setGroupSettings();

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin permission required'], $response->getData());
	}

	public function testDelegatedShareAndTalkOverridesNeedPolicyForPolicyFields(): void {
		[$shareController] = $this->controller(
			['share.user_overrides'],
			[
				'overrides' => [
					'share_send_password_mode' => ['mode' => 'forced', 'value' => 'secrets'],
				],
			]
		);
		[$talkController] = $this->controller(
			['talk.group_overrides'],
			[
				'group_id' => 'group-a',
				'overrides' => [
					'talk_lobby_enabled' => ['mode' => 'forced', 'value' => true],
				],
			]
		);

		self::assertSame(403, $shareController->setUserSettings('target')->getStatus());
		self::assertSame(403, $talkController->setGroupSettings()->getStatus());
	}

	public function testDelegatedSharePolicyAdminSeesAttachmentLinkTarget(): void {
		[$controller] = $this->controller(['share.user_overrides', 'share.policy']);

		$data = $controller->getUserSettings('target')->getData();

		self::assertArrayHasKey('attachment_link_target', $data['schema']);
		self::assertArrayHasKey('attachment_link_target', $data['items']);
	}

	/**
	 * @param string[] $permissions
	 * @param array<string, mixed> $requestParams
	 * @param string[] $seatUsers
	 * @return array{0:AdminClientSettingsController, 1:TestClientSettingsService}
	 */
	private function controller(
		array $permissions,
		array $requestParams = [],
		array $seatUsers = ['target'],
	): array {
		$delegations = new TestAdminDelegationService(['delegate' => $permissions]);
		$access = new TestAccessService();
		$settings = new TestClientSettingsService(
			[
				'email_signature_phone_mobile' => ['type' => 'string'],
				'email_signature_custom1' => ['type' => 'string'],
				'email_signature_template' => ['type' => 'html'],
				'email_signature_on_reply' => ['type' => 'boolean'],
				'share_send_password_mode' => ['type' => 'string'],
				'attachment_link_target' => ['type' => 'enum'],
				'talk_lobby_enabled' => ['type' => 'boolean'],
			],
			[
				'email_signature_phone_mobile' => ['mode' => 'forced', 'effective_value' => '0160 / 123'],
				'email_signature_custom1' => ['mode' => 'forced', 'effective_value' => 'Team Nord'],
				'email_signature_template' => ['mode' => 'forced', 'effective_value' => '<p>Signature</p>'],
				'email_signature_on_reply' => ['mode' => 'forced', 'effective_value' => true],
				'share_send_password_mode' => ['mode' => 'forced', 'effective_value' => 'secrets'],
				'attachment_link_target' => ['mode' => 'forced', 'effective_value' => 'zip_download'],
				'talk_lobby_enabled' => ['mode' => 'forced', 'effective_value' => true],
			]
		);

		return [
			new AdminClientSettingsController(
				'ncc_backend_4mc',
				new TestRequest($requestParams),
				$access,
				new AdminPermissionService($access, $delegations),
				$settings,
				new TestSeatService($seatUsers),
				new TestGroupManager(groups: ['group-a' => new TestGroup('Group A')]),
				new TestUserManager(['target' => new TestUser('Target User')]),
				new TestLogger(),
				'delegate'
			),
			$settings,
		];
	}
}
