<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		if (!$this->access->canAccessUserPage($this->userId)) {
			$this->logger->warning('Denying NC Connector page access because the user has no seat', [
				'actor_user_id' => $this->userId,
			]);
			$response = new TemplateResponse(Application::APP_ID, 'error', [
				'message' => 'Access denied (seat required)',
			]);
			$response->setStatus(403);
			return $response;
		}

		return new TemplateResponse(Application::APP_ID, 'index');
	}
}
