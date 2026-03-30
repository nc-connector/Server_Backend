<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Service\AccessService;
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
use OCP\ILogger;
use OCP\Server;

class AdminClientSettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private ClientSettingsService $clientSettings,
		private SeatService $seats,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/client-settings/schema')]
	public function getSchema(): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse([
			'schema' => $this->clientSettings->getSchema(),
			'defaults' => $this->clientSettings->getDefaults(),
			'default_modes' => $this->clientSettings->getDefaultModes(),
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForDefaults(),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/client-settings/defaults')]
	public function setDefaults(array $defaults = []): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$defaultsPayload = $this->request->getParam('defaults', $defaults);
		if (!is_array($defaultsPayload)) {
			return new DataResponse(['error' => 'Invalid defaults payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return new DataResponse(['error' => 'Invalid template asset preview payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$stored = $this->clientSettings->setDefaults($defaultsPayload);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			$this->logError('Saving default client settings failed: ' . $e->getMessage(), $e);
			return new DataResponse(['error' => 'Failed to save defaults'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([
			'schema' => $this->clientSettings->getSchema(),
			'defaults' => $stored['defaults'] ?? [],
			'default_modes' => $stored['default_modes'] ?? [],
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForDefaults($stored['defaults'] ?? [], $templateAssetPreview),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/client-settings/users/{targetUserId}')]
	public function getUserSettings(string $targetUserId): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$user = $this->userManager->get($targetUserId);
		if ($user === null) {
			return new DataResponse(['error' => 'User not found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->seats->userHasSeat($targetUserId)) {
			return new DataResponse(['error' => 'User has no seat'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$items = $this->clientSettings->getUserSettings($targetUserId);
		return new DataResponse([
			'user_id' => $targetUserId,
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForUser($targetUserId, $items),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getGroupSettings(string $group_id = ''): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$targetGroupId = trim((string)$this->request->getParam('group_id', $group_id));
		if ($targetGroupId === '') {
			return new DataResponse(['error' => 'Group required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$group = $this->groupManager->get($targetGroupId);
		if ($group === null) {
			return new DataResponse(['error' => 'Group not found'], Http::STATUS_NOT_FOUND);
		}

		$groupSettings = $this->clientSettings->getGroupSettings($targetGroupId);
		$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		return new DataResponse([
			'group_id' => $targetGroupId,
			'priority' => (int)($groupSettings['priority'] ?? 100),
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForGroup($targetGroupId, $items),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/client-settings/users/{targetUserId}')]
	public function setUserSettings(string $targetUserId, array $overrides = []): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$user = $this->userManager->get($targetUserId);
		if ($user === null) {
			return new DataResponse(['error' => 'User not found'], Http::STATUS_NOT_FOUND);
		}
		if (!$this->seats->userHasSeat($targetUserId)) {
			return new DataResponse(['error' => 'User has no seat'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$overridePayload = $this->request->getParam('overrides', $overrides);
		if (!is_array($overridePayload)) {
			return new DataResponse(['error' => 'Invalid overrides payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return new DataResponse(['error' => 'Invalid template asset preview payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$items = $this->clientSettings->setUserSettings($targetUserId, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			$this->logError('Saving user client settings failed: ' . $e->getMessage(), $e);
			return new DataResponse(['error' => 'Failed to save user settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([
			'user_id' => $targetUserId,
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForUser($targetUserId, $items, $templateAssetPreview),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	#[NoAdminRequired]
	public function setGroupSettings(string $group_id = '', array $overrides = []): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$targetGroupId = trim((string)$this->request->getParam('group_id', $group_id));
		if ($targetGroupId === '') {
			return new DataResponse(['error' => 'Group required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$group = $this->groupManager->get($targetGroupId);
		if ($group === null) {
			return new DataResponse(['error' => 'Group not found'], Http::STATUS_NOT_FOUND);
		}

		$overridePayload = $this->request->getParam('overrides', $overrides);
		if (!is_array($overridePayload)) {
			return new DataResponse(['error' => 'Invalid overrides payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$templateAssetPreview = $this->request->getParam('template_asset_preview', []);
		if (!is_array($templateAssetPreview)) {
			return new DataResponse(['error' => 'Invalid template asset preview payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$priority = (int)$this->request->getParam('priority', 100);
			$groupSettings = $this->clientSettings->setGroupSettings($targetGroupId, $priority, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			$this->logError('Saving group client settings failed: ' . $e->getMessage(), $e);
			return new DataResponse(['error' => 'Failed to save group settings'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		return new DataResponse([
			'group_id' => $targetGroupId,
			'priority' => (int)($groupSettings['priority'] ?? 100),
			'schema' => $this->clientSettings->getSchema(),
			'items' => $items,
			'template_assets' => $this->clientSettings->getEditorTemplateAssetsForGroup($targetGroupId, $items, $templateAssetPreview),
			'schema_template_assets' => $this->clientSettings->getEditorTemplateAssetsForSchemaDefaults(),
		]);
	}

	private function logError(string $message, \Throwable $exception): void {
		try {
			Server::get(ILogger::class)->error($message, [
				'app' => 'nc_connector',
				'exception' => $exception,
			]);
		} catch (\Throwable) {
			// Never fail because logging is unavailable.
		}
	}
}
