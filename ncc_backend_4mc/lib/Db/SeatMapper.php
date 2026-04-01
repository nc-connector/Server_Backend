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

class SeatMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nccb_seats', Seat::class);
	}

	public function countAssigned(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*) AS cnt'))
			->from($this->getTableName());

		$result = $qb->executeQuery();
		try {
			$value = $result->fetchOne();
		} finally {
			if (method_exists($result, 'closeCursor')) {
				$result->closeCursor();
			}
		}

		if ($value === false || $value === null) {
			return 0;
		}
		return (int)$value;
	}

	public function getSeatForUser(string $userId): ?Seat {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->setMaxResults(1);

		$entities = $this->findEntities($qb);
		return $entities[0] ?? null;
	}

	public function listAssigned(int $limit, int $offset): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('assigned_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);

		return $this->findEntities($qb);
	}

	public function listAllAssigned(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('assigned_at', 'DESC')
			->addOrderBy('id', 'DESC');

		return $this->findEntities($qb);
	}

	public function assign(string $userId, int $assignedAt, ?string $assignedBy): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'assigned_at' => $qb->createNamedParameter($assignedAt, IQueryBuilder::PARAM_INT),
				'assigned_by' => $assignedBy !== null
					? $qb->createNamedParameter($assignedBy, IQueryBuilder::PARAM_STR)
					: $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			]);
		$qb->executeStatement();
	}

	public function unassign(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	/**
	 * @param string[] $userIds
	 * @return array<string, Seat> Map: userId => Seat
	 */
	public function getSeatsForUsers(array $userIds): array {
		$userIds = array_values(array_filter($userIds, static fn ($id) => is_string($id) && $id !== ''));
		if ($userIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY))
			);

		$seats = $this->findEntities($qb);
		$map = [];
		foreach ($seats as $seat) {
			$map[$seat->getUserId()] = $seat;
		}
		return $map;
	}
}
