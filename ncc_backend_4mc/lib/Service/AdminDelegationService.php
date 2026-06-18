<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\Db\AdminDelegation;
use OCA\NcConnector\Db\AdminDelegationMapper;

class AdminDelegationService {
	private const VALID_PERMISSIONS = [
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
	];

	public function __construct(
		private AdminDelegationMapper $mapper,
	) {
	}

	/**
	 * @return string[]
	 */
	public function getValidPermissions(): array {
		return self::VALID_PERMISSIONS;
	}

	public function getForUser(string $userId): ?AdminDelegation {
		return $this->mapper->getForUser($userId);
	}

	public function hasAnyActivePermission(string $userId): bool {
		$delegation = $this->mapper->getForUser($userId);
		if (!$delegation instanceof AdminDelegation || $delegation->getEnabled() !== 1) {
			return false;
		}

		return $this->decodePermissions($delegation) !== [];
	}

	public function hasPermission(string $userId, string $permission): bool {
		$delegation = $this->mapper->getForUser($userId);
		if (!$delegation instanceof AdminDelegation || $delegation->getEnabled() !== 1) {
			return false;
		}

		return in_array($permission, $this->decodePermissions($delegation), true);
	}

	/**
	 * @return string[]
	 */
	public function getActivePermissions(string $userId): array {
		$delegation = $this->mapper->getForUser($userId);
		if (!$delegation instanceof AdminDelegation || $delegation->getEnabled() !== 1) {
			return [];
		}

		return $this->decodePermissions($delegation);
	}

	/**
	 * @return AdminDelegation[]
	 */
	public function listAll(): array {
		return $this->mapper->listAll();
	}

	/**
	 * @param mixed[] $permissions
	 */
	public function save(string $userId, bool $enabled, array $permissions, ?string $updatedBy): void {
		$normalizedPermissions = $this->normalizePermissions($permissions);
		if ($normalizedPermissions === []) {
			$this->mapper->deleteForUser($userId);
			return;
		}

		$this->mapper->upsert(
			$userId,
			$enabled,
			json_encode($normalizedPermissions, JSON_THROW_ON_ERROR),
			time(),
			$updatedBy
		);
	}

	public function delete(string $userId): void {
		$this->mapper->deleteForUser($userId);
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

		return $this->normalizePermissions(is_array($decoded) ? $decoded : []);
	}

	/**
	 * @param mixed[] $permissions
	 * @return string[]
	 */
	private function normalizePermissions(array $permissions): array {
		$valid = array_flip(self::VALID_PERMISSIONS);
		$normalized = [];
		foreach ($permissions as $permission) {
			$permission = strtolower(trim((string)$permission));
			if ($permission === '' || !isset($valid[$permission])) {
				continue;
			}
			$normalized[$permission] = true;
		}

		return array_values(array_keys($normalized));
	}
}
