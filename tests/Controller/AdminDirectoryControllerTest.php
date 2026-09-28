<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\AdminDirectoryController;
use OCA\NcConnector\Db\Seat;
use OCA\NcConnector\Db\SeatMapper;
use OCA\NcConnector\Service\AdminPermissionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class AdminDirectoryControllerTest extends TestCase {
	public function testAdminsAppearInSearchAndCountTowardPagination(): void {
		$controller = $this->controller('admin');
		$data = $controller->listUsers()->getData();
		self::assertSame(['admin', 'other-admin', 'user'], array_column($data['items'], 'user_id'));
		self::assertSame([true, true, false], array_column($data['items'], 'is_nextcloud_admin'));
		self::assertSame([true, false, true], array_column($data['items'], 'has_seat'));
		self::assertSame(3, $data['pagination']['total']);
		self::assertArrayNotHasKey('hints', $data);

		$data = $controller->listUsers(search: 'ADMIN', limit: 1, offset: 1)->getData();
		self::assertSame(['other-admin'], array_column($data['items'], 'user_id'));
		self::assertSame(['limit' => 1, 'offset' => 1, 'total' => 2], $data['pagination']);
	}

	public function testGroupFilterIncludesOnlyMatchingAdmins(): void {
		$controller = $this->controller('admin');
		$data = $controller->listUsers(group_id: 'team')->getData();
		self::assertSame(['other-admin', 'user'], array_column($data['items'], 'user_id'));
		$data = $controller->listUsers(search: 'admin', group_id: 'team')->getData();
		self::assertSame(['other-admin'], array_column($data['items'], 'user_id'));
		self::assertSame(404, $controller->listUsers(group_id: 'missing')->getStatus());
	}

	public function testDelegateStillSeesOnlyAssignedUsersIncludingAdmins(): void {
		$controller = $this->controller('delegate', ['share.user_overrides']);
		$data = $controller->listUsers()->getData();
		self::assertSame(['admin', 'user'], array_column($data['items'], 'user_id'));
		self::assertSame(2, $data['pagination']['total']);
		$data = $controller->listUsers(group_id: 'team')->getData();
		self::assertSame(['user'], array_column($data['items'], 'user_id'));
	}

	public function testDirectoryRemainsUnavailableWithoutPermission(): void {
		self::assertSame(403, $this->controller('user')->listUsers()->getStatus());
		self::assertSame(403, $this->controller('delegate', ['share.policy'])->listUsers()->getStatus());
	}

	private function controller(string $actor, array $permissions = []): AdminDirectoryController {
		$users = [
			'admin' => new TestUser('A Admin', 'admin'),
			'other-admin' => new TestUser('B Admin', 'other-admin'),
			'user' => new TestUser('C User', 'user'),
		];
		$access = new TestAccessService(['admin', 'other-admin']);
		$seats = $this->createMock(SeatMapper::class);
		$seats->method('getSeatsForUsers')->willReturnCallback(static function (array $ids): array {
			$result = [];
			foreach (array_intersect($ids, ['admin', 'user']) as $id) {
				$seat = new Seat();
				$seat->setUserId($id);
				$result[$id] = $seat;
			}
			return $result;
		});
		return new AdminDirectoryController(
			'ncc_backend_4mc', new TestRequest(), $access,
			new AdminPermissionService($access, new TestAdminDelegationService([$actor => $permissions])),
			new TestGroupManager(groups: ['team' => new TestGroup('Team', [$users['other-admin'], $users['user']])]),
			new TestUserManager($users), $seats, new TestLogger(), $actor,
		);
	}
}
