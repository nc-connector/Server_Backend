<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Settings;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\AdminSettingsResponseFactory;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class DelegatedAdmin implements ISettings {
	public function __construct(
		private AdminSettingsResponseFactory $responseFactory,
		private AccessService $access,
		private IUserSession $userSession,
	) {
	}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		if ($user === null || !$this->access->isDelegatedAdmin($user->getUID())) {
			$response = new TemplateResponse(Application::APP_ID, 'error', [
				'message' => 'Access denied',
			]);
			$response->setStatus(403);
			return $response;
		}

		return $this->responseFactory->create(false);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
