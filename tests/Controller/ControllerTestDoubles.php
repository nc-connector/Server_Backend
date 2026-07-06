<?php

declare(strict_types=1);

namespace OCA\NcConnector\Tests\Controller;

use OCA\NcConnector\Db\AdminDelegation;
use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\AdminDelegationService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\LicenseService;
use OCA\NcConnector\Service\SeatService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

final class TestRequest implements IRequest {
	/**
	 * @param array<string, mixed> $params
	 */
	public function __construct(
		private array $params = [],
	) {
	}

	public function getParam(string $key, mixed $default = null): mixed {
		return array_key_exists($key, $this->params) ? $this->params[$key] : $default;
	}
}

final class TestUser implements IUser {
	public function __construct(
		private string $displayName,
	) {
	}

	public function getDisplayName(): string {
		return $this->displayName;
	}
}

final class TestGroup implements IGroup {
	public function __construct(
		private string $displayName,
	) {
	}

	public function getDisplayName(): string {
		return $this->displayName;
	}
}

final class TestUserManager implements IUserManager {
	/**
	 * @param array<string, TestUser> $users
	 */
	public function __construct(
		private array $users = [],
	) {
	}

	public function get(string $userId): ?IUser {
		return $this->users[$userId] ?? null;
	}
}

final class TestGroupManager implements IGroupManager {
	/**
	 * @param string[] $adminUsers
	 * @param array<string, TestGroup> $groups
	 */
	public function __construct(
		private array $adminUsers = [],
		private array $groups = [],
	) {
	}

	public function isAdmin(string $userId): bool {
		return in_array($userId, $this->adminUsers, true);
	}

	public function get(string $groupId): ?IGroup {
		return $this->groups[$groupId] ?? null;
	}
}

final class TestAccessService extends AccessService {
	/**
	 * @param string[] $adminUsers
	 * @param string[] $validSeatUsers
	 */
	public function __construct(
		private array $adminUsers = [],
		private array $validSeatUsers = [],
	) {
	}

	public function isAdmin(?string $userId): bool {
		return $userId !== null && in_array($userId, $this->adminUsers, true);
	}

	public function isSeatUserWithValidLicense(?string $userId): bool {
		return $userId !== null && in_array($userId, $this->validSeatUsers, true);
	}
}

final class TestAdminDelegationService extends AdminDelegationService {
	/**
	 * @param array<string, string[]> $activePermissions
	 * @param string[] $validPermissions
	 * @param array<string, AdminDelegation> $delegations
	 */
	public function __construct(
		private array $activePermissions = [],
		private array $validPermissions = [
			'share.policy',
			'share.templates',
			'share.group_overrides',
			'share.user_overrides',
			'talk.policy',
			'talk.templates',
			'talk.group_overrides',
			'talk.user_overrides',
			'signature.policy',
			'signature.templates',
			'signature.group_overrides',
			'signature.user_overrides',
		],
		private array $delegations = [],
	) {
	}

	/**
	 * @return string[]
	 */
	public function getValidPermissions(): array {
		return $this->validPermissions;
	}

	public function getForUser(string $userId): ?AdminDelegation {
		return $this->delegations[$userId] ?? null;
	}

	public function hasAnyActivePermission(string $userId): bool {
		return ($this->activePermissions[$userId] ?? []) !== [];
	}

	public function hasPermission(string $userId, string $permission): bool {
		return in_array($permission, $this->activePermissions[$userId] ?? [], true);
	}

	/**
	 * @return string[]
	 */
	public function getActivePermissions(string $userId): array {
		return $this->activePermissions[$userId] ?? [];
	}

	/**
	 * @return AdminDelegation[]
	 */
	public function listAll(): array {
		return array_values($this->delegations);
	}

	/**
	 * @param mixed[] $permissions
	 */
	public function save(string $userId, bool $enabled, array $permissions, ?string $updatedBy): void {
		$delegation = new AdminDelegation();
		$delegation->setUserId($userId);
		$delegation->setEnabled($enabled ? 1 : 0);
		$delegation->setPermissions(json_encode(array_values($permissions), JSON_THROW_ON_ERROR));
		$delegation->setCreatedAt(1);
		$delegation->setCreatedBy($updatedBy);
		$delegation->setUpdatedAt(2);
		$delegation->setUpdatedBy($updatedBy);
		$this->delegations[$userId] = $delegation;
		$this->activePermissions[$userId] = array_values($permissions);
	}

	public function delete(string $userId): void {
		unset($this->delegations[$userId], $this->activePermissions[$userId]);
	}

	/**
	 * @return string[]
	 */
	public function decodePermissions(AdminDelegation $delegation): array {
		try {
			$decoded = json_decode($delegation->getPermissions(), true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return [];
		}
		return array_values(array_filter($decoded, static fn (mixed $item): bool => is_string($item)));
	}
}

final class TestClientSettingsService extends ClientSettingsService {
	/**
	 * @var list<array{user_id:string, overrides:array<string, mixed>, updated_by:?string}>
	 */
	public array $setUserCalls = [];

	/**
	 * @param array<string, array<string, mixed>> $schema
	 * @param array<string, array<string, mixed>> $userSettings
	 * @param array<string, mixed> $effectiveSettings
	 * @param array<string, bool> $effectiveEditable
	 */
	public function __construct(
		private array $schema = [],
		private array $userSettings = [],
		private array $effectiveSettings = [],
		private array $effectiveEditable = [],
		private string $signatureEmail = '',
	) {
	}

	public function getSchema(): array {
		return $this->schema;
	}

	public function getUserSettings(string $userId): array {
		return $this->userSettings;
	}

	public function getGroupSettings(string $groupId): array {
		$userOnlyKeys = [
			'email_signature_email_address' => true,
			'email_signature_phone_mobile' => true,
			'email_signature_custom1' => true,
			'email_signature_custom2' => true,
		];

		return [
			'priority' => 100,
			'items' => array_diff_key($this->userSettings, $userOnlyKeys),
		];
	}

	public function setUserSettings(string $userId, array $overrides, ?string $updatedBy): array {
		$this->setUserCalls[] = [
			'user_id' => $userId,
			'overrides' => $overrides,
			'updated_by' => $updatedBy,
		];
		$this->userSettings = $overrides;
		return $this->userSettings;
	}

	public function getEditorTemplateAssetDataForUser(string $userId, ?array $items = null, ?array $templateAssetPreview = null): array {
		return ['assets' => [], 'warnings' => []];
	}

	public function getEditorTemplateAssetDataForSchemaDefaults(): array {
		return ['assets' => [], 'warnings' => []];
	}

	public function getEditorTemplateAssetDataForGroup(string $groupId, ?array $items = null, ?array $templateAssetPreview = null): array {
		return ['assets' => [], 'warnings' => []];
	}

	public function setGroupSettings(string $groupId, int $priority, array $overrides, ?string $updatedBy): array {
		return [
			'priority' => $priority,
			'items' => $overrides,
		];
	}

	public function getEffectiveForUser(string $userId): array {
		return [
			'settings' => $this->effectiveSettings,
			'sources' => array_fill_keys(array_keys($this->effectiveSettings), 'default'),
			'policies' => array_fill_keys(array_keys($this->effectiveSettings), 'managed'),
			'addon_editable' => $this->effectiveEditable,
		];
	}

	public function getEmailSignatureUserEmail(string $userId): string {
		return $this->signatureEmail;
	}
}

final class TestSeatService extends SeatService {
	/**
	 * @param string[] $seatUsers
	 * @param array<string, mixed> $seatUsage
	 */
	public function __construct(
		private array $seatUsers = [],
		private array $seatUsage = ['overlicensed' => false],
	) {
	}

	public function userHasSeat(string $userId): bool {
		return in_array($userId, $this->seatUsers, true);
	}

	public function userHasActiveSeat(string $userId): bool {
		return $this->userHasSeat($userId);
	}

	public function getSeatStateForUser(string $userId): string {
		return $this->userHasSeat($userId) ? SeatService::SEAT_STATE_ACTIVE : SeatService::SEAT_STATE_NONE;
	}

	public function getSeatUsage(): array {
		return $this->seatUsage;
	}
}

final class TestLicenseService extends LicenseService {
	/**
	 * @param array<string, mixed> $snapshot
	 */
	public function __construct(
		private bool $valid = true,
		private array $snapshot = ['mode' => 'community'],
	) {
	}

	public function isLicenseValid(): bool {
		return $this->valid;
	}

	public function getSnapshot(): array {
		return $this->snapshot;
	}
}

final class TestLogger implements LoggerInterface {
	/**
	 * @var list<array{level:string, message:string, context:array<string, mixed>}>
	 */
	public array $messages = [];

	public function emergency(string|\Stringable $message, array $context = []): void {
		$this->log('emergency', $message, $context);
	}

	public function alert(string|\Stringable $message, array $context = []): void {
		$this->log('alert', $message, $context);
	}

	public function critical(string|\Stringable $message, array $context = []): void {
		$this->log('critical', $message, $context);
	}

	public function error(string|\Stringable $message, array $context = []): void {
		$this->log('error', $message, $context);
	}

	public function warning(string|\Stringable $message, array $context = []): void {
		$this->log('warning', $message, $context);
	}

	public function notice(string|\Stringable $message, array $context = []): void {
		$this->log('notice', $message, $context);
	}

	public function info(string|\Stringable $message, array $context = []): void {
		$this->log('info', $message, $context);
	}

	public function debug(string|\Stringable $message, array $context = []): void {
		$this->log('debug', $message, $context);
	}

	/**
	 * @param mixed[] $context
	 */
	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->messages[] = [
			'level' => (string)$level,
			'message' => (string)$message,
			'context' => $context,
		];
	}
}
