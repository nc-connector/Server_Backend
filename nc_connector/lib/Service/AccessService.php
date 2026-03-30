<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCP\IGroupManager;

class AccessService {
	public function __construct(
		private IGroupManager $groupManager,
		private SeatService $seatService,
		private LicenseService $licenseService,
	) {
	}

	public function isAdmin(?string $userId): bool {
		if ($userId === null || $userId === '') {
			return false;
		}
		return $this->groupManager->isAdmin($userId);
	}

	public function canAccessUserPage(?string $userId): bool {
		if ($this->isAdmin($userId)) {
			return true;
		}
		return $this->isSeatUserWithValidLicense($userId);
	}

	public function isSeatUserWithValidLicense(?string $userId): bool {
		if ($userId === null || $userId === '') {
			return false;
		}
		if (!$this->licenseService->isLicenseValid()) {
			return false;
		}
		return $this->seatService->userHasActiveSeat($userId);
	}
}
