<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\UpdateCheckService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AdminUpdateController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private UpdateCheckService $updates,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/update-check')]
	public function getUpdateStatus(): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			$this->logger->warning('Admin required', [
				'actor_user_id' => $this->userId,
				'endpoint' => 'update-check',
			]);
			return new DataResponse(['error' => 'Admin required'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse($this->updates->refreshIfMissing());
	}
}
