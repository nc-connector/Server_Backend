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

class ClientOverrideMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nccb_client_overrides', ClientOverride::class);
	}

	/**
	 * @return array<string, ClientOverride>
	 */
	public function getForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$entities = $this->findEntities($qb);
		$result = [];
		foreach ($entities as $entity) {
			$result[$entity->getSettingKey()] = $entity;
		}

		return $result;
	}

	public function upsert(
		string $userId,
		string $settingKey,
		string $mode,
		?string $settingValue,
		int $updatedAt,
		?string $updatedBy,
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('mode', $qb->createNamedParameter($mode, IQueryBuilder::PARAM_STR))
			->set('setting_value', $settingValue !== null
				? $qb->createNamedParameter($settingValue, IQueryBuilder::PARAM_STR)
				: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			)
			->set('updated_at', $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT))
			->set('updated_by', $updatedBy !== null
				? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
				: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('setting_key', $qb->createNamedParameter($settingKey, IQueryBuilder::PARAM_STR)));

		$affected = $qb->executeStatement();
		if ($affected > 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
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

	public function deleteForUserAndKey(string $userId, string $settingKey): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('setting_key', $qb->createNamedParameter($settingKey, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	/**
	 * @param string[] $userIds
	 * @return array<string, bool> Map: userId => hasOverrides
	 */
	public function getUsersWithOverrides(array $userIds): array {
		$userIds = array_values(array_filter($userIds, static fn ($id) => is_string($id) && $id !== ''));
		if ($userIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('user_id')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY))
			)
			->andWhere($qb->expr()->eq('mode', $qb->createNamedParameter('forced', IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		$map = [];
		try {
			while (($userId = $result->fetchOne()) !== false) {
				if (is_string($userId) && $userId !== '') {
					$map[$userId] = true;
				}
			}
		} finally {
			if (method_exists($result, 'closeCursor')) {
				$result->closeCursor();
			}
		}

		return $map;
	}
}
