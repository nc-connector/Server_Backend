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
use Psr\Log\LoggerInterface;

class LicenseSyncJob extends TimedJob {
	private ?LicenseService $license = null;
	private ?LoggerInterface $logger = null;

	public function __construct(
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		try {
			$license = $this->getLicenseService();
			if (!$license->canContactLicenseServer()) {
				return;
			}

			$this->getLogger()->debug('Running license sync (24h job)');
			$license->syncNow();
		} catch (\Throwable $exception) {
			$logger = $this->logger;
			if ($logger === null) {
				try {
					$logger = $this->getLogger();
				} catch (\Throwable) {
					throw $exception;
				}
			}

			$logger->error('License sync background job failed', [
				'exception' => $exception,
			]);
			throw $exception;
		}
	}

	private function getLicenseService(): LicenseService {
		if ($this->license === null) {
			$this->license = (new Application())->getContainer()->get(LicenseService::class);
		}

		return $this->license;
	}

	private function getLogger(): LoggerInterface {
		if ($this->logger === null) {
			$this->logger = (new Application())->getContainer()->get(LoggerInterface::class);
		}

		return $this->logger;
	}
}
