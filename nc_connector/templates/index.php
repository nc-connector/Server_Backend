<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

/** @var array $_ */

use OCA\NcConnector\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID;
$l = \OC::$server->getL10N($appId);
Util::addScript($appId, $appId . '-main');
Util::addStyle($appId, 'main');
?>

<div id="app-content">
	<div class="nccv-top-banner" role="region" aria-label="<?php p($l->t('NC Connector notice')); ?>">
		<div class="nccv-top-banner__title">NC Connector</div>
		<div class="nccv-top-banner__text"><?php p($l->t('Mail client policies, seats and license status managed centrally.')); ?></div>
	</div>
	<div id="nccv-app"></div>
</div>
