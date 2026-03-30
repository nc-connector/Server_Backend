<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Cron;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Service\LicenseService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\ILogger;
use OCP\Server;

class LicenseSyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $timeFactory,
		private LicenseService $license,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		if (!$this->license->canContactLicenseServer()) {
			return;
		}

		$this->logDebug('Running license sync (24h job)');

		$this->license->syncNow();
	}

	private function logDebug(string $message): void {
		try {
			Server::get(ILogger::class)->debug($message, [
				'app' => Application::APP_ID,
			]);
		} catch (\Throwable) {
			// Never fail because logging is unavailable.
		}
	}
}
