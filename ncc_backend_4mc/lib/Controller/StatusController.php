<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Controller;

use OCA\NcConnector\Service\AccessService;
use OCA\NcConnector\Service\ClientSettingsService;
use OCA\NcConnector\Service\LicenseService;
use OCA\NcConnector\Service\SeatService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class StatusController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AccessService $access,
		private SeatService $seats,
		private LicenseService $license,
		private ClientSettingsService $clientSettings,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/status')]
	public function status(): DataResponse {
		$currentUserId = (string)($this->userId ?? '');
		$isAdmin = $this->access->isAdmin($currentUserId);
		$requestedUserId = trim((string)$this->request->getParam('user_id', ''));
		$targetUserId = $currentUserId;
		if ($isAdmin && $requestedUserId !== '') {
			$targetUserId = $requestedUserId;
		}

		$seatUsage = $this->seats->getSeatUsage();
		$currentSeatState = $targetUserId !== ''
			? $this->seats->getSeatStateForUser($targetUserId)
			: SeatService::SEAT_STATE_NONE;
		$seatAssigned = $currentSeatState !== SeatService::SEAT_STATE_NONE;
		$overlicensed = (bool)($seatUsage['overlicensed'] ?? false);
		$licenseSnapshot = $this->license->getSnapshot();
		$canReadPolicies = $targetUserId !== ''
			&& ($isAdmin || $this->access->isSeatUserWithValidLicense($targetUserId));

		$policy = [
			'share' => null,
			'talk' => null,
			'email_signature' => null,
		];
		$policyEditable = [
			'share' => null,
			'talk' => null,
			'email_signature' => null,
		];
		if (!$overlicensed && $seatAssigned && $canReadPolicies) {
			$effective = $this->clientSettings->getEffectiveForUser($targetUserId);
			$policy = $this->groupPolicyByAddonArea($effective['settings'] ?? []);
			$policyEditable = $this->groupPolicyByAddonArea($effective['addon_editable'] ?? []);
			$policy['email_signature']['user_email'] = $this->clientSettings->getEmailSignatureUserEmail($targetUserId);
			ksort($policy['email_signature']);
		}

		return new DataResponse([
			'status' => [
				'user_id' => $targetUserId !== '' ? $targetUserId : null,
				'seat_assigned' => $seatAssigned,
				'seat_state' => $currentSeatState,
				'overlicensed' => $overlicensed,
				'mode' => (string)($licenseSnapshot['mode'] ?? 'community'),
				'is_valid' => $this->license->isLicenseValid(),
				'expires_at_iso' => $licenseSnapshot['expires_at_iso'] ?? null,
				'grace_until_iso' => $licenseSnapshot['grace_until_iso'] ?? null,
			],
			'policy' => $policy,
			'policy_editable' => $policyEditable,
		]);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array{share: array<string, mixed>, talk: array<string, mixed>, email_signature: array<string, mixed>}
	 */
	private function groupPolicyByAddonArea(array $settings): array {
		$grouped = [
			'share' => [],
			'talk' => [],
			'email_signature' => [],
		];

		foreach ($settings as $key => $value) {
			$bucket = $this->resolvePolicyBucket((string)$key);
			$grouped[$bucket][(string)$key] = $value;
		}

		ksort($grouped['share']);
		ksort($grouped['talk']);
		ksort($grouped['email_signature']);

		$grouped['talk']['event_description_type'] = $this->resolveEventDescriptionType($grouped['talk']);
		ksort($grouped['talk']);

		return $grouped;
	}

	private function resolvePolicyBucket(string $key): string {
		if (str_starts_with($key, 'email_signature_')) {
			return 'email_signature';
		}

		if (str_starts_with($key, 'talk_') || $key === 'language_talk_description') {
			return 'talk';
		}

		return 'share';
	}

	/**
	 * @param array<string, mixed> $talkSettings
	 */
	private function resolveEventDescriptionType(array $talkSettings): string {
		$format = strtolower(trim((string)($talkSettings['talk_invitation_template_format'] ?? '')));
		return $format === 'html' ? 'html' : 'plain_text';
	}
}
