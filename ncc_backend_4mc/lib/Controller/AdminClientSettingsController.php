<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\AdminPermissionService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\SeatService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminClientSettingsController extends Controller {
	use AdminWarningResponseTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private AdminPermissionService $adminPermissions,
		private ClientSettingsService $clientSettings,
		private SeatService $seats,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/client-settings/schema')]
	public function getSchema(): DataResponse {
		$accessDenied = $this->requireAnyAdminScope('client-settings/schema', []);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForDefaults();
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterDefaultPayload([
			'schema' => $this->clientSettings->getSchema(),
			'defaults' => $this->clientSettings->getDefaults(),
			'default_modes' => $this->clientSettings->getDefaultModes(),
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
			'recommended_apps' => $this->clientSettings->getRecommendedApps(),
		]));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/client-settings/defaults')]
	public function setDefaults(array $defaults = []): DataResponse {
		$defaultsPayload = $this->request->getParam('defaults', $defaults);
		if (!is_array($defaultsPayload)) {
			return $this->warningResponse('Invalid defaults payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
			]);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return $this->warningResponse('Invalid template asset preview payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
			]);
		}
		$accessDenied = $this->requirePayloadScopes(
			'client-settings/defaults',
			$this->adminPermissions->scopesForDefaultPayload($defaultsPayload, $templateAssetPreview)
		);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		try {
			$stored = $this->clientSettings->setDefaults($defaultsPayload);
		} catch (\InvalidArgumentException $exception) {
			return $this->warningResponse($exception->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'exception' => $exception,
			]);
		} catch (\Throwable $exception) {
			$this->logger->error('Saving default client settings failed', [
				'exception' => $exception,
			]);
			return new DataResponse(['error' => 'Failed to save defaults'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForDefaults($stored['defaults'] ?? [], $templateAssetPreview);
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterDefaultPayload([
			'schema' => $this->clientSettings->getSchema(),
			'defaults' => $stored['defaults'] ?? [],
			'default_modes' => $stored['default_modes'] ?? [],
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
			'recommended_apps' => $this->clientSettings->getRecommendedApps(),
		]));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/client-settings/users/{targetUserId}')]
	public function getUserSettings(string $targetUserId): DataResponse {
		$accessDenied = $this->requireAnyAdminScope('client-settings/users/get', [
			'share.user_overrides',
			'talk.user_overrides',
			'signature.user_overrides',
			'signature.templates',
		], [
			'target_user_id' => $targetUserId,
		]);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		$user = $this->userManager->get($targetUserId);
		if ($user === null) {
			return $this->warningResponse('User not found', Http::STATUS_NOT_FOUND, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}
		if (!$this->seats->userHasSeat($targetUserId)) {
			return $this->warningResponse('User has no seat', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}

		$items = $this->clientSettings->getUserSettings($targetUserId);
		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForUser($targetUserId, $items);
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterUserPayload([
			'user_id' => $targetUserId,
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
		]));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getGroupSettings(string $group_id = ''): DataResponse {
		$accessDenied = $this->requireAnyAdminScope('client-settings/groups/get', [
			'share.group_overrides',
			'talk.group_overrides',
			'signature.group_overrides',
		], [
			'group_id' => $group_id,
		]);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		$targetGroupId = trim((string)$this->request->getParam('group_id', $group_id));
		if ($targetGroupId === '') {
			return $this->warningResponse('Group required', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
			]);
		}

		$group = $this->groupManager->get($targetGroupId);
		if ($group === null) {
			return $this->warningResponse('Group not found', Http::STATUS_NOT_FOUND, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
			]);
		}

		$groupSettings = $this->clientSettings->getGroupSettings($targetGroupId);
		$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForGroup($targetGroupId, $items);
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterGroupPayload([
			'group_id' => $targetGroupId,
			'priority' => (int)($groupSettings['priority'] ?? 100),
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
		]));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/client-settings/users/{targetUserId}')]
	public function setUserSettings(string $targetUserId, array $overrides = []): DataResponse {
		$overridePayload = $this->request->getParam('overrides', $overrides);
		if (!is_array($overridePayload)) {
			return $this->warningResponse('Invalid overrides payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return $this->warningResponse('Invalid template asset preview payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}
		$accessDenied = $this->requirePayloadScopes(
			'client-settings/users/set',
			$this->adminPermissions->scopesForUserOverridePayload($overridePayload, $templateAssetPreview),
			[
				'target_user_id' => $targetUserId,
			]
		);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		$user = $this->userManager->get($targetUserId);
		if ($user === null) {
			return $this->warningResponse('User not found', Http::STATUS_NOT_FOUND, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}
		if (!$this->seats->userHasSeat($targetUserId)) {
			return $this->warningResponse('User has no seat', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}

		try {
			$items = $this->clientSettings->setUserSettings($targetUserId, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $exception) {
			return $this->warningResponse($exception->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
				'exception' => $exception,
			]);
		} catch (\Throwable $exception) {
			$this->logger->error('Saving user client settings failed', [
				'exception' => $exception,
			]);
			return new DataResponse(['error' => 'Failed to save user settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForUser($targetUserId, $items, $templateAssetPreview);
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterUserPayload([
			'user_id' => $targetUserId,
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
		]));
	}

	#[NoAdminRequired]
	public function setGroupSettings(string $group_id = '', array $overrides = []): DataResponse {
		$targetGroupId = trim((string)$this->request->getParam('group_id', $group_id));
		if ($targetGroupId === '') {
			return $this->warningResponse('Group required', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
			]);
		}
		$overridePayload = $this->request->getParam('overrides', $overrides);
		if (!is_array($overridePayload)) {
			return $this->warningResponse('Invalid overrides payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
			]);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return $this->warningResponse('Invalid template asset preview payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
			]);
		}
		$accessDenied = $this->requirePayloadScopes(
			'client-settings/groups/set',
			$this->adminPermissions->scopesForGroupOverridePayload($overridePayload, $templateAssetPreview),
			[
				'group_id' => $targetGroupId,
			]
		);
		if ($accessDenied !== null) {
			return $accessDenied;
		}

		$group = $this->groupManager->get($targetGroupId);
		if ($group === null) {
			return $this->warningResponse('Group not found', Http::STATUS_NOT_FOUND, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
			]);
		}

		try {
			$priority = (int)$this->request->getParam('priority', 100);
			$groupSettings = $this->clientSettings->setGroupSettings($targetGroupId, $priority, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $exception) {
			return $this->warningResponse($exception->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
				'exception' => $exception,
			]);
		} catch (\Throwable $exception) {
			$this->logger->error('Saving group client settings failed', [
				'exception' => $exception,
			]);
			return new DataResponse(['error' => 'Failed to save group settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		$templateAssetData = $this->clientSettings->getEditorTemplateAssetDataForGroup($targetGroupId, $items, $templateAssetPreview);
		$schemaTemplateAssetData = $this->clientSettings->getEditorTemplateAssetDataForSchemaDefaults();
		return new DataResponse($this->filterGroupPayload([
			'group_id' => $targetGroupId,
			'priority' => (int)($groupSettings['priority'] ?? 100),
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $templateAssetData['assets'],
			'template_asset_warnings' => $templateAssetData['warnings'],
			'schema_template_assets' => $schemaTemplateAssetData['assets'],
			'schema_template_asset_warnings' => $schemaTemplateAssetData['warnings'],
		]));
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filterDefaultPayload(array $payload): array {
		return $this->filterPayloadForLayer($payload, AdminPermissionService::SETTING_LAYER_DEFAULT);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filterUserPayload(array $payload): array {
		return $this->filterPayloadForLayer($payload, AdminPermissionService::SETTING_LAYER_USER_OVERRIDE);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filterGroupPayload(array $payload): array {
		return $this->filterPayloadForLayer($payload, AdminPermissionService::SETTING_LAYER_GROUP_OVERRIDE);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filterPayloadForLayer(array $payload, string $layer): array {
		return $this->filterPayload($payload, fn (string $key): string => $this->adminPermissions->scopeForSettingLayer($layer, $key));
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function filterPayload(array $payload, callable $scopeForKey): array {
		if ($this->adminPermissions->isFullAdmin($this->userId)) {
			return $payload;
		}

		foreach (['schema', 'defaults', 'default_modes', 'items', 'template_assets', 'template_asset_warnings', 'schema_template_assets', 'schema_template_asset_warnings'] as $field) {
			if (is_array($payload[$field] ?? null)) {
				$payload[$field] = $this->filterSettingMap($payload[$field], $scopeForKey);
			}
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $items
	 * @return array<string, mixed>
	 */
	private function filterSettingMap(array $items, callable $scopeForKey): array {
		$filtered = [];
		foreach ($items as $key => $value) {
			$scope = $scopeForKey((string)$key);
			if ($this->adminPermissions->hasScope($this->userId, $scope)) {
				$filtered[$key] = $value;
			}
		}
		return $filtered;
	}

	/**
	 * @param string[] $scopes
	 */
	private function requireAnyAdminScope(string $endpoint, array $scopes, array $context = []): ?DataResponse {
		$allowed = $scopes === []
			? $this->adminPermissions->canAccessAnyAdminScope($this->userId)
			: $this->adminPermissions->hasAnyScope($this->userId, $scopes);
		if ($allowed) {
			return null;
		}

		return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, array_merge([
			'actor_user_id' => $this->userId,
			'endpoint' => $endpoint,
		], $context));
	}

	/**
	 * @param string[] $scopes
	 */
	private function requirePayloadScopes(string $endpoint, array $scopes, array $context = []): ?DataResponse {
		if ($scopes === []) {
			return $this->requireAnyAdminScope($endpoint, [], $context);
		}

		foreach ($scopes as $scope) {
			if (!$this->adminPermissions->hasScope($this->userId, $scope)) {
				return $this->warningResponse('Admin permission required', Http::STATUS_FORBIDDEN, array_merge([
					'actor_user_id' => $this->userId,
					'endpoint' => $endpoint,
					'required_scope' => $scope,
				], $context));
			}
		}

		return null;
	}

}
