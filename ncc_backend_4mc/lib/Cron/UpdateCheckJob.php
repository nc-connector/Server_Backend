<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Cron;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Service\UpdateCheckService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class UpdateCheckJob extends TimedJob {
	private ?UpdateCheckService $updates = null;
	private ?LoggerInterface $logger = null;

	public function __construct(
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);
		$this->setInterval(6 * 60 * 60);
	}

	protected function run($argument): void {
		try {
			$this->getLogger()->debug('Running backend update check job');
			$this->getUpdateCheckService()->refreshIfDue();
		} catch (\Throwable $exception) {
			$logger = $this->logger;
			if ($logger === null) {
				try {
					$logger = $this->getLogger();
				} catch (\Throwable) {
					throw $exception;
				}
			}

			$logger->warning('Backend update check background job failed', [
				'exception' => $exception,
			]);
		}
	}

	private function getUpdateCheckService(): UpdateCheckService {
		if ($this->updates === null) {
			$this->updates = (new Application())->getContainer()->get(UpdateCheckService::class);
		}

		return $this->updates;
	}

	private function getLogger(): LoggerInterface {
		if ($this->logger === null) {
			$this->logger = (new Application())->getContainer()->get(LoggerInterface::class);
		}

		return $this->logger;
	}
}
