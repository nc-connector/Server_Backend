<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Setup;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallSchema implements IRepairStep {
	private const TABLE_SETTINGS = 'nccb_settings';
	private const TABLE_SEATS = 'nccb_seats';
	private const TABLE_CLIENT_OVERRIDES = 'nccb_client_overrides';
	private const TABLE_GROUP_OVERRIDES = 'nccb_group_overrides';

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function getName() {
		return 'Create NC Connector database tables';
	}

	public function run(IOutput $output) {
		$schema = $this->db->createSchema();
		$changed = false;

		$changed = $this->ensureSettingsTable($schema) || $changed;
		$changed = $this->ensureSeatsTable($schema) || $changed;
		$changed = $this->ensureClientOverridesTable($schema) || $changed;
		$changed = $this->ensureGroupOverridesTable($schema) || $changed;

		if (!$changed) {
			return;
		}

		$this->db->migrateToSchema($schema);
		$output->info('NC Connector tables created');
	}

	private function tableName(string $name): string {
		$prefix = (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
		return $prefix . $name;
	}

	private function ensureSettingsTable(Schema $schema): bool {
		$tableName = $this->tableName(self::TABLE_SETTINGS);
		if ($schema->hasTable($tableName)) {
			return false;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('config_key', Types::STRING, [
			'length' => 128,
			'notnull' => true,
		]);
		$table->addColumn('config_value', Types::TEXT, [
			'notnull' => true,
		]);
		$table->addColumn('updated_at', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['config_key'], 'nccb_settings_key_uq');

		return true;
	}

	private function ensureSeatsTable(Schema $schema): bool {
		$tableName = $this->tableName(self::TABLE_SEATS);
		if ($schema->hasTable($tableName)) {
			return false;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('assigned_at', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('assigned_by', Types::STRING, [
			'length' => 64,
			'notnull' => false,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'nccb_seats_user_uq');

		return true;
	}

	private function ensureClientOverridesTable(Schema $schema): bool {
		$tableName = $this->tableName(self::TABLE_CLIENT_OVERRIDES);
		if ($schema->hasTable($tableName)) {
			return false;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('setting_key', Types::STRING, [
			'length' => 128,
			'notnull' => true,
		]);
		$table->addColumn('mode', Types::STRING, [
			'length' => 16,
			'notnull' => true,
		]);
		$table->addColumn('setting_value', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('updated_at', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_by', Types::STRING, [
			'length' => 64,
			'notnull' => false,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id', 'setting_key'], 'nccb_client_override_uq');
		$table->addIndex(['user_id'], 'nccb_client_override_user_idx');

		return true;
	}

	private function ensureGroupOverridesTable(Schema $schema): bool {
		$tableName = $this->tableName(self::TABLE_GROUP_OVERRIDES);
		if ($schema->hasTable($tableName)) {
			return false;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('group_id', Types::STRING, [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('priority', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('setting_key', Types::STRING, [
			'length' => 128,
			'notnull' => true,
		]);
		$table->addColumn('mode', Types::STRING, [
			'length' => 16,
			'notnull' => true,
		]);
		$table->addColumn('setting_value', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('updated_at', Types::INTEGER, [
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('updated_by', Types::STRING, [
			'length' => 64,
			'notnull' => false,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['group_id', 'setting_key'], 'nccb_group_override_uq');
		$table->addIndex(['group_id'], 'nccb_group_override_group_idx');

		return true;
	}
}
