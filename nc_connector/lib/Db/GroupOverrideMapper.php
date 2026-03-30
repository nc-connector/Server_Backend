<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class GroupOverrideMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nccv_group_overrides', GroupOverride::class);
	}

	/**
	 * @return array<string, GroupOverride>
	 */
	public function getForGroup(string $groupId): array {
		$groups = $this->getForGroups([$groupId]);
		return $groups[$groupId] ?? [];
	}

	/**
	 * @param string[] $groupIds
	 * @return array<string, array<string, GroupOverride>>
	 */
	public function getForGroups(array $groupIds): array {
		$groupIds = array_values(array_unique(array_filter(
			$groupIds,
			static fn (mixed $groupId): bool => is_string($groupId) && $groupId !== ''
		)));
		if ($groupIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('group_id', $qb->createNamedParameter($groupIds, IQueryBuilder::PARAM_STR_ARRAY))
			);

		$entities = $this->findEntities($qb);
		$result = [];
		foreach ($entities as $entity) {
			$groupId = $entity->getGroupId();
			$result[$groupId][$entity->getSettingKey()] = $entity;
		}

		return $result;
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
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('priority', $qb->createNamedParameter($priority, IQueryBuilder::PARAM_INT))
			->set('mode', $qb->createNamedParameter($mode, IQueryBuilder::PARAM_STR))
			->set(
				'setting_value',
				$settingValue !== null
					? $qb->createNamedParameter($settingValue, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			)
			->set('updated_at', $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT))
			->set(
				'updated_by',
				$updatedBy !== null
					? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			)
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('setting_key', $qb->createNamedParameter($settingKey, IQueryBuilder::PARAM_STR)));

		$affected = $qb->executeStatement();
		if ($affected > 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'group_id' => $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR),
				'priority' => $qb->createNamedParameter($priority, IQueryBuilder::PARAM_INT),
				'setting_key' => $qb->createNamedParameter($settingKey, IQueryBuilder::PARAM_STR),
				'mode' => $qb->createNamedParameter($mode, IQueryBuilder::PARAM_STR),
				'setting_value' => $settingValue !== null
					? $qb->createNamedParameter($settingValue, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
				'updated_at' => $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT),
				'updated_by' => $updatedBy !== null
					? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			]);
		$qb->executeStatement();
	}

	public function deleteForGroupAndKey(string $groupId, string $settingKey): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('setting_key', $qb->createNamedParameter($settingKey, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
