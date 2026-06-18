<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

class AdminPermissionService {
	private const SIGNATURE_TEMPLATE_USER_SETTINGS = [
		'email_signature_template' => true,
		'email_signature_phone_mobile' => true,
		'email_signature_custom1' => true,
		'email_signature_custom2' => true,
	];

	private const SIGNATURE_POLICY_USER_SETTINGS = [
		'email_signature_on_compose' => true,
		'email_signature_on_reply' => true,
		'email_signature_on_forward' => true,
	];

	private const TEMPLATE_DEFAULT_SETTINGS = [
		'share_html_block_template' => 'share.templates',
		'share_password_template' => 'share.templates',
		'language_share_html_block' => 'share.templates',
		'talk_invitation_template' => 'talk.templates',
		'talk_invitation_template_format' => 'talk.templates',
		'language_talk_description' => 'talk.templates',
		'email_signature_template' => 'signature.templates',
	];

	public function __construct(
		private AccessService $access,
		private AdminDelegationService $delegations,
	) {
	}

	public function isFullAdmin(?string $userId): bool {
		return $this->access->isAdmin($userId);
	}

	public function canAccessAnyAdminScope(?string $userId): bool {
		if ($this->isFullAdmin($userId)) {
			return true;
		}
		if ($userId === null || $userId === '') {
			return false;
		}
		return $this->delegations->hasAnyActivePermission($userId);
	}

	/**
	 * @param string[] $scopes
	 */
	public function hasAnyScope(?string $userId, array $scopes): bool {
		if ($this->isFullAdmin($userId)) {
			return true;
		}
		if ($userId === null || $userId === '') {
			return false;
		}
		foreach ($scopes as $scope) {
			if ($this->delegations->hasPermission($userId, $scope)) {
				return true;
			}
		}
		return false;
	}

	public function hasScope(?string $userId, string $scope): bool {
		return $this->hasAnyScope($userId, [$scope]);
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @param array<string, mixed> $templateAssetPreview
	 * @return string[]
	 */
	public function scopesForDefaultPayload(array $defaults, array $templateAssetPreview = []): array {
		$scopes = [];
		foreach (array_keys($defaults) as $key) {
			$scopes[] = $this->scopeForDefaultSetting((string)$key);
		}
		foreach (array_keys($templateAssetPreview) as $key) {
			$scopes[] = $this->scopeForDefaultSetting((string)$key);
		}
		return $this->uniqueScopes($scopes);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @param array<string, mixed> $templateAssetPreview
	 * @return string[]
	 */
	public function scopesForUserOverridePayload(array $overrides, array $templateAssetPreview = []): array {
		$scopes = [];
		foreach (array_keys($overrides) as $key) {
			$scopes[] = $this->scopeForUserOverrideSetting((string)$key);
		}
		foreach (array_keys($templateAssetPreview) as $key) {
			$scopes[] = $this->scopeForUserOverrideSetting((string)$key);
		}
		return $this->uniqueScopes($scopes);
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @param array<string, mixed> $templateAssetPreview
	 * @return string[]
	 */
	public function scopesForGroupOverridePayload(array $overrides, array $templateAssetPreview = []): array {
		$scopes = [];
		foreach (array_keys($overrides) as $key) {
			$scopes[] = $this->scopeForGroupOverrideSetting((string)$key);
		}
		foreach (array_keys($templateAssetPreview) as $key) {
			$scopes[] = $this->scopeForGroupOverrideSetting((string)$key);
		}
		return $this->uniqueScopes($scopes);
	}

	public function scopeForDefaultSetting(string $key): string {
		if (isset(self::TEMPLATE_DEFAULT_SETTINGS[$key])) {
			return self::TEMPLATE_DEFAULT_SETTINGS[$key];
		}
		return $this->settingDomain($key) . '.policy';
	}

	public function scopeForUserOverrideSetting(string $key): string {
		if (isset(self::SIGNATURE_TEMPLATE_USER_SETTINGS[$key])) {
			return 'signature.templates';
		}
		if (isset(self::SIGNATURE_POLICY_USER_SETTINGS[$key])) {
			return 'signature.policy';
		}
		return $this->settingDomain($key) . '.user_overrides';
	}

	public function scopeForGroupOverrideSetting(string $key): string {
		return $this->settingDomain($key) . '.group_overrides';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function buildCurrentUserPayload(?string $userId): array {
		$isFullAdmin = $this->isFullAdmin($userId);
		$permissions = $isFullAdmin || $userId === null || $userId === ''
			? $this->delegations->getValidPermissions()
			: $this->delegations->getActivePermissions($userId);

		return [
			'is_nextcloud_admin' => $isFullAdmin,
			'is_delegated_admin' => !$isFullAdmin && $permissions !== [],
			'permissions' => $permissions,
			'valid_permissions' => $this->delegations->getValidPermissions(),
		];
	}

	private function settingDomain(string $key): string {
		if (str_starts_with($key, 'talk_') || str_starts_with($key, 'language_talk_')) {
			return 'talk';
		}
		if (str_starts_with($key, 'email_signature_')) {
			return 'signature';
		}
		return 'share';
	}

	/**
	 * @param string[] $scopes
	 * @return string[]
	 */
	private function uniqueScopes(array $scopes): array {
		return array_values(array_keys(array_fill_keys($scopes, true)));
	}
}
