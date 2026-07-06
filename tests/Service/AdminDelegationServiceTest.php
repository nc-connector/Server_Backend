<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Db\AdminDelegation;
use OCA\NcConnector\Db\AdminDelegationMapper;
use OCA\NcConnector\Service\AdminDelegationService;
use PHPUnit\Framework\TestCase;

final class AdminDelegationServiceTest extends TestCase {
	public function testSaveNormalizesDeduplicatesAndPersistsOnlyValidPermissions(): void {
		$mapper = new CapturingAdminDelegationMapper();
		$service = new AdminDelegationService($mapper);

		$service->save('alice', true, [
			' SHARE.POLICY ',
			'share.policy',
			'unknown.scope',
			'SIGNATURE.TEMPLATES',
		], 'admin');

		self::assertSame([], $mapper->deletedUsers);
		self::assertCount(1, $mapper->upserts);
		self::assertSame('alice', $mapper->upserts[0]['userId']);
		self::assertTrue($mapper->upserts[0]['enabled']);
		self::assertSame('admin', $mapper->upserts[0]['updatedBy']);
		self::assertSame(
			['share.policy', 'signature.templates'],
			json_decode((string)$mapper->upserts[0]['permissions'], true, 512, JSON_THROW_ON_ERROR)
		);
	}

	public function testSaveDeletesDelegationWhenNoValidPermissionsRemain(): void {
		$mapper = new CapturingAdminDelegationMapper();
		$service = new AdminDelegationService($mapper);

		$service->save('alice', true, ['unknown.scope', ''], 'admin');

		self::assertSame(['alice'], $mapper->deletedUsers);
		self::assertSame([], $mapper->upserts);
	}

	public function testInactiveAndInvalidDelegationsExposeNoPermissions(): void {
		$mapper = new CapturingAdminDelegationMapper();
		$mapper->delegations['inactive'] = self::delegation('inactive', false, '["share.policy"]');
		$mapper->delegations['invalid'] = self::delegation('invalid', true, '{not json');
		$service = new AdminDelegationService($mapper);

		self::assertFalse($service->hasAnyActivePermission('inactive'));
		self::assertFalse($service->hasPermission('inactive', 'share.policy'));
		self::assertSame([], $service->getActivePermissions('inactive'));
		self::assertFalse($service->hasAnyActivePermission('invalid'));
		self::assertSame([], $service->getActivePermissions('invalid'));
	}

	public function testActivePermissionsAreFilteredAgainstKnownPermissionList(): void {
		$mapper = new CapturingAdminDelegationMapper();
		$mapper->delegations['alice'] = self::delegation(
			'alice',
			true,
			'["share.policy","share.policy","invalid","talk.templates"]'
		);
		$service = new AdminDelegationService($mapper);

		self::assertTrue($service->hasAnyActivePermission('alice'));
		self::assertTrue($service->hasPermission('alice', 'talk.templates'));
		self::assertFalse($service->hasPermission('alice', 'invalid'));
		self::assertSame(['share.policy', 'talk.templates'], $service->getActivePermissions('alice'));
	}

	private static function delegation(string $userId, bool $enabled, string $permissions): AdminDelegation {
		$delegation = new AdminDelegation();
		$delegation->setUserId($userId);
		$delegation->setEnabled($enabled ? 1 : 0);
		$delegation->setPermissions($permissions);
		return $delegation;
	}
}

final class CapturingAdminDelegationMapper extends AdminDelegationMapper {
	/**
	 * @var array<string, AdminDelegation>
	 */
	public array $delegations = [];

	/**
	 * @var list<array<string, mixed>>
	 */
	public array $upserts = [];

	/**
	 * @var list<string>
	 */
	public array $deletedUsers = [];

	public function __construct() {
	}

	public function getForUser(string $userId): ?AdminDelegation {
		return $this->delegations[$userId] ?? null;
	}

	/**
	 * @return AdminDelegation[]
	 */
	public function listAll(): array {
		return array_values($this->delegations);
	}

	public function upsert(
		string $userId,
		bool $enabled,
		string $permissions,
		int $updatedAt,
		?string $updatedBy,
	): void {
		$this->upserts[] = [
			'userId' => $userId,
			'enabled' => $enabled,
			'permissions' => $permissions,
			'updatedAt' => $updatedAt,
			'updatedBy' => $updatedBy,
		];
	}

	public function deleteForUser(string $userId): void {
		$this->deletedUsers[] = $userId;
	}
}
