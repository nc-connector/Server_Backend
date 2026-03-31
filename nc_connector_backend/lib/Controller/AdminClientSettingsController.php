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
use Psr\Log\LoggerInterface;

class AdminClientSettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
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
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/schema',
			]);
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
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/defaults',
			]);
		}

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

		try {
			$stored = $this->clientSettings->setDefaults($defaultsPayload);
		} catch (\InvalidArgumentException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('Saving default client settings failed', $e);
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
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/users/get',
				'target_user_id' => $targetUserId,
			]);
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
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/groups/get',
				'group_id' => $group_id,
			]);
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
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/users/set',
				'target_user_id' => $targetUserId,
			]);
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

		try {
			$items = $this->clientSettings->setUserSettings($targetUserId, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('Saving user client settings failed', $e);
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
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'client-settings/groups/set',
				'group_id' => $group_id,
			]);
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

		try {
			$priority = (int)$this->request->getParam('priority', 100);
			$groupSettings = $this->clientSettings->setGroupSettings($targetGroupId, $priority, $overridePayload, $this->userId);
		} catch (\InvalidArgumentException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'group_id' => $targetGroupId,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('Saving group client settings failed', $e);
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
		$this->logger->error($message, [
			'exception' => $exception,
		]);
	}

	private function warningResponse(string $message, int $status, array $context = []): DataResponse {
		$this->logger->warning($message, $context);
		return new DataResponse(['error' => $message], $status);
	}
}
