<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Db\AdminDelegation;
use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\AdminDelegationService;
use OCA\NcConnector\Service\AdminPermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminDelegationController extends Controller {
	use AdminWarningResponseTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private AdminDelegationService $delegations,
		private AdminPermissionService $permissions,
		private IUserManager $userManager,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCurrentAdmin(): DataResponse {
		if (!$this->permissions->canAccessAnyAdminScope($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'admin/me',
			]);
		}

		return new DataResponse($this->permissions->buildCurrentUserPayload($this->userId));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function listDelegations(): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'delegations/list',
			]);
		}

		$items = [];
		foreach ($this->delegations->listAll() as $delegation) {
			$items[] = $this->formatDelegation($delegation);
		}

		return new DataResponse([
			'items' => $items,
			'valid_permissions' => $this->delegations->getValidPermissions(),
		]);
	}

	#[NoAdminRequired]
	public function saveDelegation(string $targetUserId, bool $enabled = true, array $permissions = []): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'delegations/save',
				'target_user_id' => $targetUserId,
			]);
		}

		$user = $this->userManager->get($targetUserId);
		if (!$user instanceof IUser) {
			return $this->warningResponse('User not found', Http::STATUS_NOT_FOUND, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}
		if ($this->access->isAdmin($targetUserId)) {
			return $this->warningResponse('Nextcloud admins already have full access', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}

		$permissionsPayload = $this->request->getParam('permissions', $permissions);
		if (!is_array($permissionsPayload)) {
			return $this->warningResponse('Invalid permissions payload', Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'target_user_id' => $targetUserId,
			]);
		}

		try {
			$this->delegations->save(
				$targetUserId,
				(bool)$this->request->getParam('enabled', $enabled),
				$permissionsPayload,
				$this->userId
			);
		} catch (\Throwable $exception) {
			$this->logError('Saving NC Connector admin delegation failed', $exception);
			return new DataResponse(['error' => 'Failed to save delegation'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$delegation = $this->delegations->getForUser($targetUserId);
		return new DataResponse([
			'item' => $delegation instanceof AdminDelegation
				? $this->formatDelegation($delegation)
				: null,
		]);
	}

	#[NoAdminRequired]
	public function deleteDelegation(string $targetUserId): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'delegations/delete',
				'target_user_id' => $targetUserId,
			]);
		}

		$this->delegations->delete($targetUserId);
		return new DataResponse(['ok' => true]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function formatDelegation(AdminDelegation $delegation): array {
		$userId = $delegation->getUserId();
		$user = $this->userManager->get($userId);
		return [
			'user_id' => $userId,
			'display_name' => $user instanceof IUser ? $user->getDisplayName() : '',
			'enabled' => $delegation->getEnabled() === 1,
			'permissions' => $this->delegations->decodePermissions($delegation),
			'created_at' => $delegation->getCreatedAt(),
			'created_by' => $delegation->getCreatedBy(),
			'updated_at' => $delegation->getUpdatedAt(),
			'updated_by' => $delegation->getUpdatedBy(),
			'is_nextcloud_admin' => $this->access->isAdmin($userId),
		];
	}

	private function logError(string $message, \Throwable $exception): void {
		$this->logger->error($message, [
			'exception' => $exception,
		]);
	}

}
