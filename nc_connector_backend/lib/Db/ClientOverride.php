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
 * @method string getSettingKey()
 * @method void setSettingKey(string $settingKey)
 * @method string getMode()
 * @method void setMode(string $mode)
 * @method string|null getSettingValue()
 * @method void setSettingValue(?string $settingValue)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 * @method string|null getUpdatedBy()
 * @method void setUpdatedBy(?string $updatedBy)
 */
class ClientOverride extends Entity {
	protected string $userId = '';
	protected string $settingKey = '';
	protected string $mode = 'inherit';
	protected ?string $settingValue = null;
	protected int $updatedAt = 0;
	protected ?string $updatedBy = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('settingKey', 'string');
		$this->addType('mode', 'string');
		$this->addType('settingValue', 'string');
		$this->addType('updatedAt', 'integer');
		$this->addType('updatedBy', 'string');
	}
}
