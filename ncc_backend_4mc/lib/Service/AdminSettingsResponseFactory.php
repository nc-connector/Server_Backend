<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;

class AdminSettingsResponseFactory {
	public function create(bool $fullAdminFallback): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, 'adminSettings', [
			'fullAdminFallback' => $fullAdminFallback,
		]);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('*');
		$policy->addAllowedImageDomain('data:');
		$policy->addAllowedImageDomain('blob:');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}
}
