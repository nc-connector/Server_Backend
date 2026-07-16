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
	private const SHARE_HTML_BLOCK_TEMPLATE_KEY = 'share_html_block_template';
	private const SHARE_HTML_BLOCK_TEMPLATE_V2_KEY = 'share_html_block_template_v2';
	private const LEGACY_SHARE_LINK_INTRO_ATTRIBUTE = 'data-nccb-legacy-link-intro';
	private const LEGACY_SHARE_LINK_LABEL_ATTRIBUTE = 'data-nccb-legacy-link-label';
	private const LEGACY_SHARE_LINK_INTRO = 'The files have been provided securely and in a privacy-compliant manner via Nextcloud. You can download them using the link below.';
	private const LEGACY_SHARE_LINK_LABEL = 'Download link';

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
			$policySettings = $this->projectShareTemplateVersions($effective['settings'] ?? []);
			$policy = $this->groupPolicyByAddonArea($policySettings);
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
	 * @return array<string, mixed>
	 */
	private function projectShareTemplateVersions(array $settings): array {
		if (!array_key_exists(self::SHARE_HTML_BLOCK_TEMPLATE_KEY, $settings)) {
			return $settings;
		}

		$template = $settings[self::SHARE_HTML_BLOCK_TEMPLATE_KEY];
		if (!is_string($template)) {
			$settings[self::SHARE_HTML_BLOCK_TEMPLATE_V2_KEY] = $template;
			return $settings;
		}

		$legacyIntro = $this->readLegacyShareCopy(
			$template,
			self::LEGACY_SHARE_LINK_INTRO_ATTRIBUTE,
			self::LEGACY_SHARE_LINK_INTRO
		);
		$legacyLabel = $this->readLegacyShareCopy(
			$template,
			self::LEGACY_SHARE_LINK_LABEL_ATTRIBUTE,
			self::LEGACY_SHARE_LINK_LABEL
		);
		$template = $this->stripLegacyShareCopyMetadata($template);
		$settings[self::SHARE_HTML_BLOCK_TEMPLATE_V2_KEY] = $template;

		// Older clients render unknown placeholders literally, so the existing API key must remain placeholder-free.
		$settings[self::SHARE_HTML_BLOCK_TEMPLATE_KEY] = str_replace(
			['{LINK_INTRO}', '{LINK_LABEL}'],
			[$legacyIntro, $legacyLabel],
			$template
		);

		return $settings;
	}

	private function readLegacyShareCopy(string $template, string $attribute, string $fallback): string {
		$pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*(["\'])(.*?)\1/is';
		if (preg_match($pattern, $template, $matches) !== 1) {
			return $fallback;
		}

		$value = trim(html_entity_decode((string)($matches[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		return $value !== ''
			? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
			: $fallback;
	}

	private function stripLegacyShareCopyMetadata(string $template): string {
		$attributes = implode('|', array_map(
			static fn(string $attribute): string => preg_quote($attribute, '/'),
			[self::LEGACY_SHARE_LINK_INTRO_ATTRIBUTE, self::LEGACY_SHARE_LINK_LABEL_ATTRIBUTE]
		));
		$clean = preg_replace('/\s+(?:' . $attributes . ')\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $template);
		return is_string($clean) ? $clean : $template;
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
