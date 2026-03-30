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
 * @method string getConfigKey()
 * @method void setConfigKey(string $configKey)
 * @method string getConfigValue()
 * @method void setConfigValue(string $configValue)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class Setting extends Entity {
	protected string $configKey = '';
	protected string $configValue = '';
	protected int $updatedAt = 0;

	public function __construct() {
		$this->addType('configKey', 'string');
		$this->addType('configValue', 'string');
		$this->addType('updatedAt', 'integer');
	}
}
