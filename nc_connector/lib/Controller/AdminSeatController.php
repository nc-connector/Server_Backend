<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Db\ClientOverrideMapper;
use OCA\NcConnector\Db\SeatMapper;
use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\SeatLimitExceededException;
use OCA\NcConnector\Service\SeatService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;

class AdminSeatController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private IUserManager $userManager,
		private SeatService $seats,
		private SeatMapper $seatMapper,
		private ClientOverrideMapper $overrideMapper,
		private ClientSettingsService $clientSettings,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/seats')]
	public function listAssignedSeats(int $limit = 50, int $offset = 0): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		$limit = max(1, min(200, $limit));
		$offset = max(0, $offset);

		$seatStates = $this->seats->getSeatStateMap();
		$seatUsage = $this->seats->getSeatUsage();
		$rows = [];
		foreach ($this->seatMapper->listAssigned($limit, $offset) as $seat) {
			$targetUserId = $seat->getUserId();
			if ($this->access->isAdmin($targetUserId)) {
				continue;
			}

			$user = $this->userManager->get($targetUserId);
			$seatState = $seatStates[$targetUserId] ?? SeatService::SEAT_STATE_ACTIVE;
			$rows[] = [
				'user_id' => $targetUserId,
				'display_name' => $user !== null ? $user->getDisplayName() : '',
				'assigned_at' => (int)$seat->getAssignedAt(),
				'assigned_by' => $seat->getAssignedBy(),
				'seat_state' => $seatState,
			];
		}
		$overrideMap = $this->overrideMapper->getUsersWithOverrides(array_column($rows, 'user_id'));
		$groupOverrideDetails = $this->clientSettings->getUsersWithGroupOverrideDetails(array_column($rows, 'user_id'));
		$items = [];
		foreach ($rows as $row) {
			$userId = (string)($row['user_id'] ?? '');
			$row['has_overrides'] = (bool)($overrideMap[$userId] ?? false);
			$row['group_override_groups'] = $groupOverrideDetails[$userId] ?? [];
			$row['has_group_overrides'] = ($row['group_override_groups'] ?? []) !== [];
			$items[] = $row;
		}

		return new DataResponse([
			'items' => $items,
			'seat_status' => $seatUsage,
			'pagination' => [
				'limit' => $limit,
				'offset' => $offset,
			],
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/seats/{targetUserId}')]
	public function setSeat(string $targetUserId, bool $assigned): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		if ($this->access->isAdmin($targetUserId)) {
			if ($assigned) {
				return new DataResponse(
					['error' => 'Administrator cannot be assigned a seat'],
					Http::STATUS_UNPROCESSABLE_ENTITY
				);
			}
			$this->seats->unassignSeat($targetUserId);
			$seatUsage = $this->seats->getSeatUsage();
			return new DataResponse([
				'target_user_id' => $targetUserId,
				'assigned' => false,
				'seats' => $seatUsage,
			]);
		}

		if ($this->userManager->get($targetUserId) === null) {
			return new DataResponse(['error' => 'User not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			if ($assigned) {
				$this->seats->assignSeat($targetUserId, $this->userId);
			} else {
				$this->seats->unassignSeat($targetUserId);
			}
		} catch (SeatLimitExceededException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
		}

		$seatUsage = $this->seats->getSeatUsage();
		return new DataResponse([
			'target_user_id' => $targetUserId,
			'assigned' => $assigned,
			'seats' => $seatUsage,
		]);
	}
}
