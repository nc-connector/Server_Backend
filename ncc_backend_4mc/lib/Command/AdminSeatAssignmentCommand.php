<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Command;

use OCA\NcConnector\Service\SeatService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdminSeatAssignmentCommand extends Command {
	public function __construct(
		private SeatService $seats,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('ncc:admin-seat-assignment')
			->setDescription('Control whether Nextcloud administrator accounts can receive NC Connector seats.')
			->addArgument('state', InputArgument::OPTIONAL, 'status, enable, or disable', 'status');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$state = strtolower(trim((string)$input->getArgument('state')));

		if ($state === 'status') {
			$this->writeStatus($output);
			return 0;
		}

		if ($state === 'enable') {
			$this->seats->setAdminSeatAssignmentAllowed(true);
			$output->writeln('Admin seat assignment enabled.');
			$output->writeln('Administrator accounts are now visible in seat search and can receive seats.');
			return 0;
		}

		if ($state === 'disable') {
			$this->seats->setAdminSeatAssignmentAllowed(false);
			$output->writeln('Admin seat assignment disabled.');
			$output->writeln('Administrator accounts are hidden from seat assignment and cannot receive new seats.');
			$output->writeln('Existing admin seats are not removed automatically.');
			return 0;
		}

		$output->writeln(sprintf('<error>Invalid state "%s". Use status, enable, or disable.</error>', $state));
		return 1;
	}

	private function writeStatus(OutputInterface $output): void {
		if ($this->seats->adminSeatAssignmentAllowed()) {
			$output->writeln('Admin seat assignment is enabled.');
			$output->writeln('Administrator accounts are visible in seat search and can receive seats.');
			return;
		}

		$output->writeln('Admin seat assignment is disabled.');
		$output->writeln('Default safety is active: administrator accounts are hidden from seat assignment and cannot receive new seats.');
		$output->writeln('Existing admin seats, if any, are still visible in the assigned-seat overview.');
	}
}
