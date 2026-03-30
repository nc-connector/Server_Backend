<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\Db\SeatMapper;

class SeatService {
	public const SEAT_STATE_NONE = 'none';
	public const SEAT_STATE_ACTIVE = 'active';
	public const SEAT_STATE_SUSPENDED_OVERLIMIT = 'suspended_overlimit';

	/** @var array<string, string>|null */
	private ?array $seatStateMapCache = null;
	/** @var array<string, mixed>|null */
	private ?array $seatUsageCache = null;

	public function __construct(
		private SeatMapper $seatMapper,
		private LicenseService $licenseService,
	) {
	}

	public function getTotalSeats(): int {
		return $this->licenseService->getTotalSeats();
	}

	public function getAssignedSeats(): int {
		if ($this->seatStateMapCache !== null) {
			return count($this->seatStateMapCache);
		}
		return $this->seatMapper->countAssigned();
	}

	public function getFreeSeats(): int {
		return max(0, $this->getTotalSeats() - $this->getAssignedSeats());
	}

	public function userHasSeat(string $userId): bool {
		return $this->seatMapper->getSeatForUser($userId) !== null;
	}

	public function userHasActiveSeat(string $userId): bool {
		return $this->getSeatStateForUser($userId) === self::SEAT_STATE_ACTIVE;
	}

	/**
	 * @return array<string, string> Map: userId => seat state
	 */
	public function getSeatStateMap(): array {
		if ($this->seatStateMapCache !== null) {
			return $this->seatStateMapCache;
		}

		$allSeats = $this->seatMapper->listAllAssigned();
		$overflow = max(0, count($allSeats) - $this->getTotalSeats());

		$map = [];
		foreach ($allSeats as $index => $seat) {
			$map[$seat->getUserId()] = $index < $overflow
				? self::SEAT_STATE_SUSPENDED_OVERLIMIT
				: self::SEAT_STATE_ACTIVE;
		}

		$this->seatStateMapCache = $map;
		return $this->seatStateMapCache;
	}

	public function getSeatStateForUser(string $userId): string {
		$seat = $this->seatMapper->getSeatForUser($userId);
		if ($seat === null) {
			return self::SEAT_STATE_NONE;
		}

		$stateMap = $this->getSeatStateMap();
		return $stateMap[$userId] ?? self::SEAT_STATE_ACTIVE;
	}

	public function getSeatUsage(): array {
		if ($this->seatUsageCache !== null) {
			return $this->seatUsageCache;
		}

		$assigned = count($this->getSeatStateMap());
		$total = $this->getTotalSeats();
		$overlicensedBy = max(0, $assigned - $total);
		$activeAssigned = $assigned - $overlicensedBy;
		$suspendedAssigned = $overlicensedBy;

		$this->seatUsageCache = [
			'assigned' => $assigned,
			'active_assigned' => $activeAssigned,
			'suspended_assigned' => $suspendedAssigned,
			'total' => $total,
			'free' => max(0, $total - $activeAssigned),
			'overlicensed' => $overlicensedBy > 0,
			'overlicensed_by' => $overlicensedBy,
		];
		return $this->seatUsageCache;
	}

	/**
	 * @throws SeatLimitExceededException
	 */
	public function assignSeat(string $userId, ?string $assignedBy = null): void {
		$existing = $this->seatMapper->getSeatForUser($userId);
		if ($existing !== null) {
			return;
		}

		if ($this->getFreeSeats() <= 0) {
			throw new SeatLimitExceededException('Not enough free seats');
		}

		$this->seatMapper->assign($userId, time(), $assignedBy);
		$this->invalidateCache();
	}

	public function unassignSeat(string $userId): void {
		$this->seatMapper->unassign($userId);
		$this->invalidateCache();
	}

	private function invalidateCache(): void {
		$this->seatStateMapCache = null;
		$this->seatUsageCache = null;
	}
}
