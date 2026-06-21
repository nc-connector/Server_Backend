<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCP\AppFramework\Http\DataResponse;

trait AdminWarningResponseTrait {
	private function warningResponse(string $message, int $status, array $context = []): DataResponse {
		$this->logger->warning($message, $context);
		return new DataResponse(['error' => $message], $status);
	}
}
