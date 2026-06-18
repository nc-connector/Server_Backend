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
 * @method int getEnabled()
 * @method void setEnabled(int $enabled)
 * @method string getPermissions()
 * @method void setPermissions(string $permissions)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method string|null getCreatedBy()
 * @method void setCreatedBy(?string $createdBy)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method string|null getUpdatedBy()
 * @method void setUpdatedBy(?string $updatedBy)
 */
class AdminDelegation extends Entity {
	protected string $userId = '';
	protected int $enabled = 1;
	protected string $permissions = '[]';
	protected int $createdAt = 0;
	protected ?string $createdBy = null;
	protected int $updatedAt = 0;
	protected ?string $updatedBy = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('enabled', 'integer');
		$this->addType('permissions', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('createdBy', 'string');
		$this->addType('updatedAt', 'integer');
		$this->addType('updatedBy', 'string');
	}
}
