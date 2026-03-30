<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getAssignedAt()
 * @method void setAssignedAt(int $assignedAt)
 * @method string|null getAssignedBy()
 * @method void setAssignedBy(?string $assignedBy)
 */
class Seat extends Entity {
	protected string $userId = '';
	protected int $assignedAt = 0;
	protected ?string $assignedBy = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('assignedAt', 'integer');
		$this->addType('assignedBy', 'string');
	}
}
