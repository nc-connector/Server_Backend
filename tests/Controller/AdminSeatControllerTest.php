<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Controller\AdminSeatController;
use OCA\NcConnector\Db\ClientOverrideMapper;
use OCA\NcConnector\Db\SeatMapper;
use OCA\NcConnector\Service\AdminPermissionService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\SeatService;
use OCA\NcConnector\Service\SeatLimitExceededException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ControllerTestDoubles.php';

final class AdminSeatControllerTest extends TestCase {
	public function testFullAdminCanRemoveSeatAfterUserWasDeleted(): void {
		$seats = $this->createMock(SeatService::class);
		$seats->expects(self::once())->method('unassignSeat')->with('deleted-user');
		$seats->expects(self::never())->method('assignSeat');
		$seats->method('getSeatUsage')->willReturn(['assigned' => 0]);

		$response = $this->controller(
			actorUserId: 'admin',
			adminUsers: ['admin'],
			users: [],
			seats: $seats,
		)->setSeat('deleted-user', false);

		self::assertSame(200, $response->getStatus());
		self::assertSame('deleted-user', $response->getData()['target_user_id']);
		self::assertFalse($response->getData()['assigned']);
	}

	public function testSeatCannotBeAssignedToMissingUser(): void {
		$seats = $this->createMock(SeatService::class);
		$seats->expects(self::never())->method('assignSeat');
		$seats->expects(self::never())->method('unassignSeat');

		$response = $this->controller(
			actorUserId: 'admin',
			adminUsers: ['admin'],
			users: [],
			seats: $seats,
		)->setSeat('missing-user', true);

		self::assertSame(404, $response->getStatus());
		self::assertSame(['error' => 'User not found'], $response->getData());
	}

	public function testNonAdminCannotRemoveSeatForMissingUser(): void {
		$seats = $this->createMock(SeatService::class);
		$seats->expects(self::never())->method('assignSeat');
		$seats->expects(self::never())->method('unassignSeat');

		$response = $this->controller(
			actorUserId: 'delegate',
			users: [],
			seats: $seats,
		)->setSeat('deleted-user', false);

		self::assertSame(403, $response->getStatus());
		self::assertSame(['error' => 'Admin required'], $response->getData());
	}

	public function testFullAdminCanAssignAndRemoveOwnOrAnotherAdminSeat(): void {
		foreach (['admin', 'other-admin'] as $target) {
			foreach ([false, true] as $assigned) {
				$seats = $this->createMock(SeatService::class);
				$seats->expects($assigned ? self::once() : self::never())->method('assignSeat')->with($target, 'admin');
				$seats->expects($assigned ? self::never() : self::once())->method('unassignSeat')->with($target);
				$seats->method('getSeatUsage')->willReturn(['assigned' => $assigned ? 1 : 0]);
				$response = $this->controller('admin', ['admin', 'other-admin'], [$target => new TestUser($target)], $seats)->setSeat($target, $assigned);
				self::assertSame(200, $response->getStatus());
				self::assertSame($assigned, $response->getData()['assigned']);
			}
		}
	}

	public function testAdminAssignmentStillReportsSeatCapacityLimit(): void {
		$seats = $this->createMock(SeatService::class);
		$seats->expects(self::once())->method('assignSeat')->with('admin', 'admin')
			->willThrowException(new SeatLimitExceededException('Not enough free seats'));
		$response = $this->controller('admin', ['admin'], ['admin' => new TestUser('Admin')], $seats)->setSeat('admin', true);
		self::assertSame(409, $response->getStatus());
		self::assertSame(['error' => 'Not enough free seats'], $response->getData());
	}

	public function testNonAdminCannotAssignAnAdminSeat(): void {
		$seats = $this->createMock(SeatService::class);
		$seats->expects(self::never())->method('assignSeat');
		$response = $this->controller('delegate', ['admin'], ['admin' => new TestUser('Admin')], $seats)->setSeat('admin', true);
		self::assertSame(403, $response->getStatus());
	}

	/**
	 * @param string[] $adminUsers
	 * @param array<string, TestUser> $users
	 */
	private function controller(
		string $actorUserId,
		array $adminUsers = [],
		array $users = [],
		?SeatService $seats = null,
		?SeatMapper $seatMapper = null,
		?ClientOverrideMapper $overrideMapper = null,
		?ClientSettingsService $clientSettings = null,
	): AdminSeatController {
		$access = new TestAccessService($adminUsers);
		$delegations = new TestAdminDelegationService();

		return new AdminSeatController(
			'ncc_backend_4mc',
			new TestRequest(),
			$access,
			new AdminPermissionService($access, $delegations),
			new TestUserManager($users),
			$seats ?? $this->createMock(SeatService::class),
			$seatMapper ?? $this->createMock(SeatMapper::class),
			$overrideMapper ?? $this->createMock(ClientOverrideMapper::class),
			$clientSettings ?? $this->createMock(ClientSettingsService::class),
			new TestLogger(),
			$actorUserId,
		);
	}
}
