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

class SettingMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'nccb_settings', Setting::class);
	}

	public function getValue(string $key, ?string $default = null): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('config_value')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('config_key', $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR))
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		try {
			$value = $result->fetchOne();
		} finally {
			if (method_exists($result, 'closeCursor')) {
				$result->closeCursor();
			}
		}

		if ($value === false || $value === null) {
			return $default;
		}

		return (string)$value;
	}

	public function setValue(string $key, string $value, int $updatedAt): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('config_value', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT))
			->where(
				$qb->expr()->eq('config_key', $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR))
			);

		$affected = $qb->executeStatement();
		if ($affected > 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert($this->getTableName())
			->values([
				'config_key' => $qb->createNamedParameter($key, IQueryBuilder::PARAM_STR),
				'config_value' => $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR),
				'updated_at' => $qb->createNamedParameter($updatedAt, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}
}
