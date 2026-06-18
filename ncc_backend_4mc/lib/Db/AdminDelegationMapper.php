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

class AdminDelegationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nccb_admin_delegations', AdminDelegation::class);
	}

	public function getForUser(string $userId): ?AdminDelegation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	/**
	 * @return AdminDelegation[]
	 */
	public function listAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('updated_at', 'DESC')
			->addOrderBy('user_id', 'ASC');

		return $this->findEntities($qb);
	}

	public function upsert(
		string $userId,
		bool $enabled,
		string $permissions,
		int $updatedAt,
		?string $updatedBy,
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('enabled', $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT))
			->set('permissions', $qb->createNamedParameter($permissions, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT))
			->set(
				'updated_by',
				$updatedBy !== null
					? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$affected = $qb->executeStatement();
		if ($affected > 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'enabled' => $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT),
				'permissions' => $qb->createNamedParameter($permissions, IQueryBuilder::PARAM_STR),
				'created_at' => $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT),
				'created_by' => $updatedBy !== null
					? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
				'updated_at' => $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT),
				'updated_by' => $updatedBy !== null
					? $qb->createNamedParameter($updatedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			]);
		$qb->executeStatement();
	}

	public function deleteForUser(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
