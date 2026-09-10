<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Service;

use OCA\NcConnector\Db\ClientOverride;
use OCA\NcConnector\Db\ClientOverrideMapper;
use OCA\NcConnector\Db\GroupOverride;
use OCA\NcConnector\Db\GroupOverrideMapper;
use OCA\NcConnector\Db\SettingMapper;
use OCA\NcConnector\Service\ClientPolicyRuntimeService;
use OCA\NcConnector\Service\ClientSettingsDefinitionService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\TemplateAssetService;
use OCA\NcConnector\Service\TemplateSanitizerService;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

final class ClientSettingsServicePersistenceTest extends TestCase {
	public function testDefaultsAreFullyValidatedBeforeAnyWrite(): void {
		[$service, $db, $settings] = $this->service();

		try {
			$service->setDefaults([
				'share_permission_upload' => ['mode' => 'default', 'value' => false],
				'share_permission_edit' => ['mode' => 'default'],
			]);
			self::fail('Expected the missing value to be rejected');
		} catch (\InvalidArgumentException $exception) {
			self::assertStringContainsString('Missing default value', $exception->getMessage());
		}

		self::assertSame([], $settings->values());
		self::assertSame(0, $db->beginCount);
	}

	public function testUserOverridesAreFullyValidatedBeforeAnyWrite(): void {
		$existing = [
			'alice' => [
				'share_permission_upload' => $this->clientOverride('alice', 'share_permission_upload', '1'),
			],
		];
		[$service, $db, , $overrides] = $this->service(clientOverrides: $existing);

		try {
			$service->setUserSettings('alice', [
				'share_permission_upload' => ['mode' => 'inherit'],
				'share_permission_edit' => ['mode' => 'invalid'],
			], 'delegate');
			self::fail('Expected the invalid mode to be rejected');
		} catch (\InvalidArgumentException $exception) {
			self::assertStringContainsString('Invalid mode', $exception->getMessage());
		}

		self::assertArrayHasKey('share_permission_upload', $overrides->rows()['alice']);
		self::assertSame(0, $db->beginCount);
	}

	public function testGroupOverridesAreFullyValidatedBeforePriorityOrContentChanges(): void {
		$existing = [
			'group-a' => [
				'share_permission_upload' => ['priority' => 100, 'value' => '1'],
				'talk_lobby_active' => ['priority' => 100, 'value' => '0'],
			],
		];
		[$service, $db, , , $groupOverrides] = $this->service(groupOverrides: $existing);

		try {
			$service->setGroupSettings('group-a', 50, [
				'share_permission_upload' => ['mode' => 'forced', 'value' => false],
				'unknown_setting' => ['mode' => 'forced', 'value' => true],
			], 'delegate');
			self::fail('Expected the unknown setting to be rejected');
		} catch (\InvalidArgumentException $exception) {
			self::assertStringContainsString('Unknown setting key', $exception->getMessage());
		}

		self::assertSame($existing, $groupOverrides->rows());
		self::assertSame(0, $db->beginCount);
	}

	public function testPersistenceFailureRollsBackTheWholeRequest(): void {
		[$service, $db, $settings] = $this->service(settingFailureAtWrite: 2);

		try {
			$service->setDefaults([
				'share_permission_upload' => ['mode' => 'default', 'value' => false],
			]);
			self::fail('Expected the simulated database failure');
		} catch (\RuntimeException $exception) {
			self::assertSame('Simulated persistence failure', $exception->getMessage());
		}

		self::assertSame([], $settings->values());
		self::assertSame(1, $db->beginCount);
		self::assertSame(0, $db->commitCount);
		self::assertSame(1, $db->rollBackCount);
	}

	public function testGroupPriorityAppliesAcrossPolicyDomainsAndCompetingGroups(): void {
		$existing = [
			'group-a' => [
				'share_permission_upload' => ['priority' => 100, 'value' => '1'],
				'talk_lobby_active' => ['priority' => 100, 'value' => '0'],
			],
			'group-b' => [
				'share_permission_upload' => ['priority' => 75, 'value' => '1'],
				'talk_lobby_active' => ['priority' => 75, 'value' => '1'],
			],
		];
		[$service, $db, , , $groupOverrides] = $this->service(
			groupOverrides: $existing,
			userGroups: ['group-a', 'group-b']
		);

		$service->setGroupSettings('group-a', 50, [
			'share_permission_upload' => ['mode' => 'forced', 'value' => false],
		], 'delegate');

		$rows = $groupOverrides->rows();
		self::assertSame(50, $rows['group-a']['share_permission_upload']['priority']);
		self::assertSame(50, $rows['group-a']['talk_lobby_active']['priority']);
		self::assertSame(75, $rows['group-b']['share_permission_upload']['priority']);
		self::assertSame(75, $rows['group-b']['talk_lobby_active']['priority']);

		$items = $service->getUserSettings('alice');
		self::assertFalse($items['share_permission_upload']['effective_value']);
		self::assertSame('group-a', $items['share_permission_upload']['group_id']);
		self::assertFalse($items['talk_lobby_active']['effective_value']);
		self::assertSame('group-a', $items['talk_lobby_active']['group_id']);
		self::assertSame(50, $items['talk_lobby_active']['group_priority']);
		self::assertSame(1, $db->beginCount);
		self::assertSame(1, $db->commitCount);
		self::assertSame(0, $db->rollBackCount);
	}

	/**
	 * @param array<string, array<string, ClientOverride>> $clientOverrides
	 * @param array<string, array<string, array{priority:int, value:string}>> $groupOverrides
	 * @param string[] $userGroups
	 * @return array{ClientSettingsService, PolicyTestDbConnection, PolicyTestSettingMapper, PolicyTestClientOverrideMapper, PolicyTestGroupOverrideMapper}
	 */
	private function service(
		array $clientOverrides = [],
		array $groupOverrides = [],
		array $userGroups = [],
		?int $settingFailureAtWrite = null,
	): array {
		$db = new PolicyTestDbConnection();
		$settings = new PolicyTestSettingMapper($db, [], $settingFailureAtWrite);
		$overrides = new PolicyTestClientOverrideMapper($db, $clientOverrides);
		$groupMapper = new PolicyTestGroupOverrideMapper($db, $groupOverrides);
		$groups = [];
		foreach ($userGroups as $groupId) {
			$groups[$groupId] = new PolicyTestGroup($groupId);
		}

		$service = new ClientSettingsService(
			new ClientSettingsDefinitionService(new TemplateSanitizerService()),
			$settings,
			$overrides,
			$groupMapper,
			$db,
			new PolicyTestGroupManager($groups),
			new PolicyTestUserManager(new PolicyTestUser()),
			$this->createMock(TemplateAssetService::class),
			$this->createMock(ClientPolicyRuntimeService::class),
		);

		return [$service, $db, $settings, $overrides, $groupMapper];
	}

	private function clientOverride(string $userId, string $key, string $value): ClientOverride {
		$override = new ClientOverride();
		$override->setUserId($userId);
		$override->setSettingKey($key);
		$override->setMode('forced');
		$override->setSettingValue($value);
		return $override;
	}
}

interface PolicyTestTransactionalStore {
	public function snapshot(): mixed;

	public function restore(mixed $snapshot): void;
}

final class PolicyTestDbConnection implements IDBConnection {
	public int $beginCount = 0;
	public int $commitCount = 0;
	public int $rollBackCount = 0;
	private bool $active = false;

	/** @var PolicyTestTransactionalStore[] */
	private array $stores = [];

	/** @var array<int, mixed> */
	private array $snapshots = [];

	public function register(PolicyTestTransactionalStore $store): void {
		$this->stores[] = $store;
	}

	public function inTransaction(): bool {
		return $this->active;
	}

	public function beginTransaction(): void {
		$this->beginCount++;
		$this->active = true;
		$this->snapshots = [];
		foreach ($this->stores as $index => $store) {
			$this->snapshots[$index] = $store->snapshot();
		}
	}

	public function commit(): void {
		$this->commitCount++;
		$this->active = false;
		$this->snapshots = [];
	}

	public function rollBack(): void {
		$this->rollBackCount++;
		foreach ($this->stores as $index => $store) {
			$store->restore($this->snapshots[$index]);
		}
		$this->active = false;
		$this->snapshots = [];
	}
}

final class PolicyTestSettingMapper extends SettingMapper implements PolicyTestTransactionalStore {
	private int $writeCount = 0;

	/** @param array<string, string> $values */
	public function __construct(
		PolicyTestDbConnection $db,
		private array $values = [],
		private ?int $failureAtWrite = null,
	) {
		$db->register($this);
	}

	public function getValue(string $key, ?string $default = null): ?string {
		return $this->values[$key] ?? $default;
	}

	public function setValue(string $key, string $value, int $updatedAt): void {
		$this->writeCount++;
		if ($this->failureAtWrite === $this->writeCount) {
			throw new \RuntimeException('Simulated persistence failure');
		}
		$this->values[$key] = $value;
	}

	/** @return array<string, string> */
	public function values(): array {
		return $this->values;
	}

	public function snapshot(): mixed {
		return $this->values;
	}

	public function restore(mixed $snapshot): void {
		$this->values = is_array($snapshot) ? $snapshot : [];
	}
}

final class PolicyTestClientOverrideMapper extends ClientOverrideMapper implements PolicyTestTransactionalStore {
	/** @param array<string, array<string, ClientOverride>> $rows */
	public function __construct(PolicyTestDbConnection $db, private array $rows = []) {
		$db->register($this);
	}

	public function getForUser(string $userId): array {
		return $this->rows[$userId] ?? [];
	}

	public function upsert(
		string $userId,
		string $settingKey,
		string $mode,
		?string $settingValue,
		int $updatedAt,
		?string $updatedBy,
	): void {
		$override = new ClientOverride();
		$override->setUserId($userId);
		$override->setSettingKey($settingKey);
		$override->setMode($mode);
		$override->setSettingValue($settingValue);
		$override->setUpdatedAt($updatedAt);
		$override->setUpdatedBy($updatedBy);
		$this->rows[$userId][$settingKey] = $override;
	}

	public function deleteForUserAndKey(string $userId, string $settingKey): void {
		unset($this->rows[$userId][$settingKey]);
	}

	/** @return array<string, array<string, ClientOverride>> */
	public function rows(): array {
		return $this->rows;
	}

	public function snapshot(): mixed {
		return $this->rows;
	}

	public function restore(mixed $snapshot): void {
		$this->rows = is_array($snapshot) ? $snapshot : [];
	}
}

final class PolicyTestGroupOverrideMapper extends GroupOverrideMapper implements PolicyTestTransactionalStore {
	/** @param array<string, array<string, array{priority:int, value:string}>> $rows */
	public function __construct(PolicyTestDbConnection $db, private array $rows = []) {
		$db->register($this);
	}

	public function getForGroup(string $groupId): array {
		return $this->getForGroups([$groupId])[$groupId] ?? [];
	}

	public function getForGroups(array $groupIds): array {
		$result = [];
		foreach ($groupIds as $groupId) {
			foreach ($this->rows[$groupId] ?? [] as $key => $row) {
				$override = new GroupOverride();
				$override->setGroupId($groupId);
				$override->setPriority($row['priority']);
				$override->setSettingKey($key);
				$override->setMode('forced');
				$override->setSettingValue($row['value']);
				$result[$groupId][$key] = $override;
			}
		}
		return $result;
	}

	public function updatePriorityForGroup(
		string $groupId,
		int $priority,
		int $updatedAt,
		?string $updatedBy,
	): void {
		foreach ($this->rows[$groupId] ?? [] as $key => $row) {
			$this->rows[$groupId][$key]['priority'] = $priority;
		}
	}

	public function upsert(
		string $groupId,
		int $priority,
		string $settingKey,
		string $mode,
		?string $settingValue,
		int $updatedAt,
		?string $updatedBy,
	): void {
		$this->rows[$groupId][$settingKey] = [
			'priority' => $priority,
			'value' => (string)$settingValue,
		];
	}

	public function deleteForGroupAndKey(string $groupId, string $settingKey): void {
		unset($this->rows[$groupId][$settingKey]);
	}

	/** @return array<string, array<string, array{priority:int, value:string}>> */
	public function rows(): array {
		return $this->rows;
	}

	public function snapshot(): mixed {
		return $this->rows;
	}

	public function restore(mixed $snapshot): void {
		$this->rows = is_array($snapshot) ? $snapshot : [];
	}
}

final class PolicyTestUser implements IUser {
}

final class PolicyTestUserManager implements IUserManager {
	public function __construct(private IUser $user) {
	}

	public function get(string $userId): ?IUser {
		return $userId === 'alice' ? $this->user : null;
	}
}

final class PolicyTestGroup implements IGroup {
	public function __construct(private string $groupId) {
	}

	public function getGID(): string {
		return $this->groupId;
	}

	public function getDisplayName(): string {
		return $this->groupId;
	}
}

final class PolicyTestGroupManager implements IGroupManager {
	/** @param array<string, PolicyTestGroup> $groups */
	public function __construct(private array $groups) {
	}

	public function get(string $groupId): ?IGroup {
		return $this->groups[$groupId] ?? null;
	}

	/** @return IGroup[] */
	public function getUserGroups(IUser $user): array {
		return array_values($this->groups);
	}
}
