<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\AdminDelegationController;
use OCA\NcConnector\Service\AdminPermissionService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class AdminDelegationControllerPermissionTest extends TestCase {
	public function testDelegatedAdminCanReadOwnAdminPayload(): void {
		$controller = $this->controller(
			'delegate',
			activePermissions: ['delegate' => ['signature.templates']]
		);

		$response = $controller->getCurrentAdmin();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertFalse($data['is_nextcloud_admin']);
		self::assertTrue($data['is_delegated_admin']);
		self::assertSame(['signature.templates'], $data['permissions']);
	}

	public function testDelegatedAdminCannotManageDelegations(): void {
		$controller = $this->controller(
			'delegate',
			activePermissions: ['delegate' => ['signature.templates']],
			requestParams: [
				'permissions' => ['share.policy'],
				'enabled' => true,
			]
		);

		$response = $controller->saveDelegation('target');

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin required'], $response->getData());
	}

	public function testFullAdminCannotDelegateAnotherFullAdmin(): void {
		$controller = $this->controller(
			'admin',
			adminUsers: ['admin', 'target-admin'],
			users: ['target-admin' => new TestUser('Target Admin')]
		);

		$response = $controller->saveDelegation('target-admin');

		self::assertSame(422, $response->getStatus());
		self::assertSame(['error' => 'Nextcloud admins already have full access'], $response->getData());
	}

	public function testFullAdminCanSaveDelegation(): void {
		$controller = $this->controller(
			'admin',
			adminUsers: ['admin'],
			requestParams: [
				'permissions' => ['signature.templates', 'signature.user_overrides'],
				'enabled' => true,
			]
		);

		$response = $controller->saveDelegation('target');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('target', $data['item']['user_id']);
		self::assertSame('Target User', $data['item']['display_name']);
		self::assertTrue($data['item']['enabled']);
		self::assertSame(['signature.templates', 'signature.user_overrides'], $data['item']['permissions']);
		self::assertFalse($data['item']['is_nextcloud_admin']);
	}

	/**
	 * @param string[] $adminUsers
	 * @param array<string, string[]> $activePermissions
	 * @param array<string, mixed> $requestParams
	 * @param array<string, TestUser> $users
	 */
	private function controller(
		string $actorUserId,
		array $adminUsers = [],
		array $activePermissions = [],
		array $requestParams = [],
		?array $users = null,
	): AdminDelegationController {
		$access = new TestAccessService($adminUsers);
		$delegations = new TestAdminDelegationService($activePermissions);
		$users ??= ['target' => new TestUser('Target User')];

		return new AdminDelegationController(
			'ncc_backend_4mc',
			new TestRequest($requestParams),
			$access,
			$delegations,
			new AdminPermissionService($access, $delegations),
			new TestUserManager($users),
			new TestLogger(),
			$actorUserId
		);
	}
}
