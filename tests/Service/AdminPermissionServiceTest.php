<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\AdminDelegationService;
use OCA\NcConnector\Service\AdminPermissionService;
use OCA\NcConnector\Service\EmailSignatureRuntimeService;
use PHPUnit\Framework\TestCase;

final class AdminPermissionServiceTest extends TestCase {
	public function testSignatureUserOverrideFieldsStayInTemplateScope(): void {
		$service = self::service();

		self::assertSame('signature.templates', $service->scopeForUserOverrideSetting(EmailSignatureRuntimeService::PHONE_MOBILE_KEY));
		self::assertSame('signature.templates', $service->scopeForUserOverrideSetting(EmailSignatureRuntimeService::CUSTOM1_KEY));
		self::assertSame('signature.templates', $service->scopeForUserOverrideSetting(EmailSignatureRuntimeService::CUSTOM2_KEY));
	}

	public function testSignatureInsertSwitchesStayInPolicyScope(): void {
		$service = self::service();

		self::assertSame('signature.policy', $service->scopeForUserOverrideSetting('email_signature_on_compose'));
		self::assertSame('signature.policy', $service->scopeForUserOverrideSetting('email_signature_on_reply'));
		self::assertSame('signature.policy', $service->scopeForUserOverrideSetting('email_signature_on_forward'));
	}

	public function testTemplateDefaultsDoNotRequirePolicyScope(): void {
		$service = self::service();

		self::assertSame('share.templates', $service->scopeForDefaultSetting('share_html_block_template'));
		self::assertSame('share.templates', $service->scopeForDefaultSetting('share_password_template'));
		self::assertSame('talk.templates', $service->scopeForDefaultSetting('talk_invitation_template'));
		self::assertSame('talk.templates', $service->scopeForDefaultSetting('talk_invitation_template_format'));
		self::assertSame('signature.templates', $service->scopeForDefaultSetting('email_signature_template'));
		self::assertSame('share.policy', $service->scopeForDefaultSetting('share_send_password_mode'));
	}

	public function testOverridePayloadRequiresOverrideAndContentScope(): void {
		$service = self::service();

		self::assertSame(
			['signature.user_overrides', 'signature.templates'],
			$service->scopesForUserOverridePayload([
				EmailSignatureRuntimeService::PHONE_MOBILE_KEY => ['mode' => 'forced', 'value' => '0160 / 123'],
			])
		);
		self::assertSame(
			['signature.user_overrides', 'signature.policy'],
			$service->scopesForUserOverridePayload([
				'email_signature_on_reply' => ['mode' => 'forced', 'value' => true],
			])
		);
		self::assertSame(
			['talk.group_overrides', 'talk.policy'],
			$service->scopesForGroupOverridePayload([
				'talk_lobby_enabled' => ['mode' => 'forced', 'value' => true],
			])
		);
		self::assertSame(
			['share.group_overrides', 'share.templates'],
			$service->scopesForGroupOverridePayload([
				'share_html_block_template' => ['mode' => 'forced', 'value' => '<p>x</p>'],
			])
		);
	}

	public function testPayloadScopesAreUniqueAcrossSettingsAndTemplateAssetPreview(): void {
		$service = self::service();

		$scopes = $service->scopesForDefaultPayload(
			[
				'share_html_block_template' => '<p>x</p>',
				'share_send_password_mode' => 'secrets',
			],
			[
				'share_html_block_template' => '<p>preview</p>',
				'email_signature_template' => '<p>sig</p>',
			]
		);

		self::assertSame(['share.templates', 'share.policy', 'signature.templates'], $scopes);
	}

	public function testCurrentUserPayloadUsesFullAdminAndDelegatedPermissions(): void {
		$validPermissions = ['share.policy', 'signature.templates'];

		$adminPayload = self::service(true, [], $validPermissions)->buildCurrentUserPayload('admin');
		self::assertTrue($adminPayload['is_nextcloud_admin']);
		self::assertFalse($adminPayload['is_delegated_admin']);
		self::assertSame($validPermissions, $adminPayload['permissions']);

		$delegatedPayload = self::service(false, ['bob' => ['signature.templates']], $validPermissions)
			->buildCurrentUserPayload('bob');
		self::assertFalse($delegatedPayload['is_nextcloud_admin']);
		self::assertTrue($delegatedPayload['is_delegated_admin']);
		self::assertSame(['signature.templates'], $delegatedPayload['permissions']);
	}

	/**
	 * @param array<string, string[]> $activePermissions
	 * @param string[] $validPermissions
	 */
	private static function service(
		bool $admin = false,
		array $activePermissions = [],
		array $validPermissions = ['share.policy', 'signature.templates'],
	): AdminPermissionService {
		return new AdminPermissionService(
			new FakeAccessService($admin),
			new FakeAdminDelegationService($activePermissions, $validPermissions)
		);
	}
}

final class FakeAccessService extends AccessService {
	public function __construct(
		private bool $admin,
	) {
	}

	public function isAdmin(?string $userId): bool {
		return $this->admin && $userId !== null && $userId !== '';
	}
}

final class FakeAdminDelegationService extends AdminDelegationService {
	/**
	 * @param array<string, string[]> $activePermissions
	 * @param string[] $validPermissions
	 */
	public function __construct(
		private array $activePermissions,
		private array $validPermissions,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getValidPermissions(): array {
		return $this->validPermissions;
	}

	public function hasAnyActivePermission(string $userId): bool {
		return ($this->activePermissions[$userId] ?? []) !== [];
	}

	public function hasPermission(string $userId, string $permission): bool {
		return in_array($permission, $this->activePermissions[$userId] ?? [], true);
	}

	/**
	 * @return string[]
	 */
	public function getActivePermissions(string $userId): array {
		return $this->activePermissions[$userId] ?? [];
	}
}
