<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Setup;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Cron\LicenseSyncJob;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Server;

class UninstallCleanup implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function getName() {
		return 'Remove NC Connector data on app removal';
	}

	public function run(IOutput $output) {
		if (!$this->isOccRemoveCommand() || $this->isOccKeepDataRemoveCommand()) {
			return;
		}

		try {
			foreach ([
				$this->tableName('nccv_settings'),
				$this->tableName('nccv_seats'),
				$this->tableName('nccv_client_overrides'),
				$this->tableName('nccv_group_overrides'),
			] as $tableName) {
				$this->dropTableIfExists($tableName);
			}

			$this->deleteBackgroundJobs();
			$this->purgeRuntimeImageCache();
			$output->info('NC Connector data removed');
		} catch (\Throwable $exception) {
			$this->logError('NC Connector uninstall cleanup failed: ' . $exception->getMessage(), [
				'app' => Application::APP_ID,
				'exception' => $exception,
			]);
			throw $exception;
		}
	}

	private function isOccRemoveCommand(): bool {
		if (PHP_SAPI !== 'cli') {
			return false;
		}

		$argv = $_SERVER['argv'] ?? $GLOBALS['argv'] ?? [];
		if (!is_array($argv) || $argv === []) {
			return false;
		}

		$normalized = array_map(static fn (mixed $value): string => strtolower((string)$value), $argv);
		$scriptName = basename($normalized[0] ?? '');
		if ($scriptName !== 'occ') {
			return false;
		}

		return in_array('app:remove', $normalized, true) && in_array(Application::APP_ID, $normalized, true);
	}

	private function isOccKeepDataRemoveCommand(): bool {
		$argv = $_SERVER['argv'] ?? $GLOBALS['argv'] ?? [];
		$normalized = is_array($argv)
			? array_map(static fn (mixed $value): string => strtolower((string)$value), $argv)
			: [];

		return in_array('--keep-data', $normalized, true);
	}

	private function purgeRuntimeImageCache(): void {
		$runtimeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'runtime';
		if (!is_dir($runtimeDir)) {
			return;
		}

		foreach (glob($runtimeDir . DIRECTORY_SEPARATOR . '*') ?: [] as $filePath) {
			if (!is_file($filePath)) {
				continue;
			}
			if (!unlink($filePath)) {
				throw new \RuntimeException(sprintf('Failed to delete runtime image cache file "%s"', $filePath));
			}
		}

		if ((glob($runtimeDir . DIRECTORY_SEPARATOR . '*') ?: []) === [] && !rmdir($runtimeDir) && is_dir($runtimeDir)) {
			throw new \RuntimeException(sprintf('Failed to remove runtime image cache directory "%s"', $runtimeDir));
		}
	}

	private function tableName(string $name): string {
		$prefix = (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
		return $prefix . $name;
	}

	private function dropTableIfExists(string $tableName): void {
		$quotedTableName = $this->db->getDatabasePlatform()->quoteIdentifier($tableName);
		$this->db->executeStatement('DROP TABLE IF EXISTS ' . $quotedTableName);
	}

	private function deleteBackgroundJobs(): void {
		$queryBuilder = $this->db->getQueryBuilder();
		$queryBuilder
			->delete('jobs')
			->where($queryBuilder->expr()->eq('class', $queryBuilder->createNamedParameter(LicenseSyncJob::class)));
		$queryBuilder->executeStatement();
	}

	private function logError(string $message, array $context = []): void {
		try {
			Server::get(\OCP\ILogger::class)->error($message, $context);
		} catch (\Throwable) {
		}
	}
}
