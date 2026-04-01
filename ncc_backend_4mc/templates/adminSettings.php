<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

use OCA\NcConnector\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID;
$l = \OC::$server->getL10N($appId);
Util::addScript($appId, 'vendor/tinymce/tinymce.min');
Util::addScript($appId, 'vendor/tinymce/models/dom/model.min');
Util::addScript($appId, 'vendor/tinymce/icons/default/icons.min');
Util::addScript($appId, 'vendor/tinymce/themes/silver/theme.min');
Util::addScript($appId, 'vendor/tinymce/plugins/code/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/link/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/lists/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/autolink/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/preview/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/table/plugin.min');
Util::addScript($appId, 'vendor/tinymce/plugins/image/plugin.min');
Util::addScript($appId, $appId . '-adminSettings');
Util::addStyle($appId, 'tinymceSkin');
Util::addStyle($appId, 'adminSettings');
?>

<div class="nccb-top-banner" role="region" aria-label="NC Connector Backend">
	<div class="nccb-top-banner__title">NC Connector Backend</div>
	<div class="nccb-top-banner__text"><?php p($l->t('Mail client policies, seats and license status managed centrally.')); ?></div>
</div>
<div id="nccb-admin-settings"></div>
