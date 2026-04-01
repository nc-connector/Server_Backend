<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\LicenseService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AdminLicenseController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private LicenseService $license,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/admin/license')]
	public function getLicense(): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'license/get',
			]);
		}

		return new DataResponse($this->license->getSnapshot());
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/license/credentials')]
	public function setCredentials(?string $email = null, ?string $license_key = null): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'license/credentials',
			]);
		}
		$email = (string)$this->request->getParam('email', $email ?? '');
		$licenseKey = (string)$this->request->getParam('license_key', $license_key ?? '');

		try {
			$this->license->setCredentials($email, $licenseKey);
		} catch (\InvalidArgumentException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('Saving license credentials failed', $e);
			return new DataResponse(['error' => 'Failed to save credentials'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($this->license->getSnapshot());
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/v1/admin/license/mode')]
	public function setMode(?string $mode = null): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'license/mode',
			]);
		}
		$mode = (string)$this->request->getParam('mode', $mode ?? '');

		try {
			$this->license->setMode($mode);
		} catch (\InvalidArgumentException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY, [
				'actor_user_id' => $this->userId,
				'requested_mode' => $mode,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('Saving license mode failed', $e);
			return new DataResponse(['error' => 'Failed to save mode'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse($this->license->getSnapshot());
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/admin/license/sync')]
	public function syncNow(): DataResponse {
		if (!$this->access->isAdmin($this->userId)) {
			return $this->warningResponse('Admin required', Http::STATUS_FORBIDDEN, [
				'actor_user_id' => $this->userId,
				'endpoint' => 'license/sync',
			]);
		}

		try {
			return new DataResponse($this->license->syncNow());
		} catch (\RuntimeException $e) {
			return $this->warningResponse($e->getMessage(), Http::STATUS_CONFLICT, [
				'actor_user_id' => $this->userId,
				'exception' => $e,
			]);
		} catch (\Throwable $e) {
			$this->logError('License sync failed', $e);
			return new DataResponse(['error' => 'License sync failed'], Http::STATUS_BAD_GATEWAY);
		}
	}

	private function logError(string $message, \Throwable $exception): void {
		$this->logger->error($message, [
			'exception' => $exception,
		]);
	}

	private function warningResponse(string $message, int $status, array $context = []): DataResponse {
		$this->logger->warning($message, $context);
		return new DataResponse(['error' => $message], $status);
	}
}
