<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\AppInfo;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Settings\AdminSection;
use OCA\NcConnector\Settings\DelegatedAdmin;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IUserSession;
use OCP\Settings\IManager as ISettingsManager;

class Application extends App implements IBootstrap {
	public const APP_ID = 'ncc_backend_4mc';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Background jobs are registered via appinfo/info.xml for compatibility across NC 31–33.
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();
		$userSession = $server->get(IUserSession::class);
		$user = $userSession->getUser();
		if ($user === null) {
			return;
		}

		$access = $server->get(AccessService::class);
		$userId = $user->getUID();
		if (!$access->isDelegatedAdmin($userId)) {
			return;
		}

		$settingsManager = $server->get(ISettingsManager::class);
		$settingsManager->registerSection('personal', AdminSection::class);
		$settingsManager->registerSetting('personal', DelegatedAdmin::class);
	}
}
