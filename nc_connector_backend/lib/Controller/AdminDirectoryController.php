<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Db\SeatMapper;
use OCA\NcConnector\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class AdminDirectoryController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private SeatMapper $seatMapper,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/groups')]
	public function listGroups(string $search = '', int $limit = 100, int $offset = 0): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'groups/list',
			]);
		}

		$limit = max(1, min(200, $limit));
		$offset = max(0, $offset);

		$groups = $this->toGroups($this->groupManager->search(trim($search), $limit, $offset));
		$items = [];
		foreach ($groups as $group) {
			$items[] = [
				'group_id' => $group->getGID(),
				'display_name' => $group->getDisplayName(),
			];
		}

		return new DataResponse([
			'items' => $items,
			'pagination' => [
				'limit' => $limit,
				'offset' => $offset,
			],
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/users')]
	public function listUsers(
		string $search = '',
		?string $group_id = null,
		int $limit = 50,
		int $offset = 0,
	): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'users/list',
			]);
		}

		$limit = max(1, min(200, $limit));
		$offset = max(0, $offset);

		$users = [];
		if ($group_id !== null && trim($group_id) !== '') {
			$gid = trim($group_id);
			$group = $this->groupManager->get($gid);
			if ($group === null) {
				return $this->warningResponse('Group not found', Http::STATUS_NOT_FOUND, [
					'actor_user_id' => $this->userId,
					'group_id' => $gid,
				]);
			}
			$users = $group->getUsers();
		} else {
			$users = $this->getAllUsers();
		}

		$filteredUsers = $this->filterUsers($this->toUsers($users), trim($search));
		$filteredUsers = array_values(array_filter($filteredUsers, fn (IUser $user): bool => !$this->access->isAdmin($user->getUID())));
		usort($filteredUsers, static function (IUser $left, IUser $right): int {
			$leftDisplay = trim($left->getDisplayName());
			$rightDisplay = trim($right->getDisplayName());
			$leftValue = $leftDisplay !== '' ? strtolower($leftDisplay) : strtolower($left->getUID());
			$rightValue = $rightDisplay !== '' ? strtolower($rightDisplay) : strtolower($right->getUID());
			return $leftValue <=> $rightValue;
		});
		$pagedUsers = array_slice($filteredUsers, $offset, $limit);

		$userIds = array_map(static fn (IUser $u): string => $u->getUID(), $pagedUsers);
		$seats = $this->seatMapper->getSeatsForUsers($userIds);

		$items = [];
		foreach ($pagedUsers as $user) {
			$uid = $user->getUID();
			$hasSeat = isset($seats[$uid]);
			$items[] = [
				'user_id' => $uid,
				'display_name' => $user->getDisplayName(),
				'has_seat' => $hasSeat,
			];
		}

		return new DataResponse([
			'items' => $items,
			'pagination' => [
				'limit' => $limit,
				'offset' => $offset,
				'total' => count($filteredUsers),
			],
		]);
	}

	/**
	 * @param IUser[] $users
	 * @return array<int, IUser>
	 */
	private function filterUsers(array $users, string $search): array {
		$needle = strtolower($search);
		if ($needle === '') {
			return $users;
		}

		$filtered = [];
		foreach ($users as $user) {
			$uid = strtolower($user->getUID());
			$name = strtolower($user->getDisplayName());
			if ($needle !== '' && !str_contains($uid, $needle) && !str_contains($name, $needle)) {
				continue;
			}
			$filtered[] = $user;
		}

		return $filtered;
	}

	/**
	 * @return array<int, IUser>
	 */
	private function getAllUsers(): array {
		$users = [];
		$this->userManager->callForAllUsers(static function (IUser $user) use (&$users): bool {
			$users[] = $user;
			return true;
		}, '');
		return $users;
	}

	/**
	 * @param iterable<mixed> $users
	 * @return IUser[]
	 */
	private function toUsers(iterable $users): array {
		$result = [];
		foreach ($users as $user) {
			if ($user instanceof IUser) {
				$result[] = $user;
			}
		}
		return $result;
	}

	/**
	 * @param iterable<mixed> $groups
	 * @return IGroup[]
	 */
	private function toGroups(iterable $groups): array {
		$result = [];
		foreach ($groups as $group) {
			if ($group instanceof IGroup) {
				$result[] = $group;
			}
		}
		return $result;
	}

	private function warningResponse(string $message, int $status, array $context = []): DataResponse {
		$this->logger->warning($message, $context);
		return new DataResponse(['error' => $message], $status);
	}
}
