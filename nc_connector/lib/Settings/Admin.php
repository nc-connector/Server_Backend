<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Settings;

use OCA\NcConnector\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function getForm(): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, 'adminSettings');
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*');
		$csp->addAllowedImageDomain('data:');
		$csp->addAllowedImageDomain('blob:');
		$response->setContentSecurityPolicy($csp);

		return $response;
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
