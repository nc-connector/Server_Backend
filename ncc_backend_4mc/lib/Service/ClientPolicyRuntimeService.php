<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\AppInfo\Application;
use OCP\App\IAppManager;
use Psr\Log\LoggerInterface;

class ClientPolicyRuntimeService {
	private const SECRETS_APP_ID = 'secrets';

	public function __construct(
		private ClientSettingsDefinitionService $settingDefinitions,
		private TalkTemplateRuntimeService $talkTemplates,
		private EmailSignatureRuntimeService $emailSignatures,
		private IAppManager $appManager,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $sources
	 * @param array<string, string> $policies
	 * @param array<string, bool> $addonEditable
	 */
	public function applyForUser(array &$settings, array &$sources, array &$policies, array &$addonEditable, string $userId): void {
		$this->applyAttachmentPolicyDependency($settings);
		$this->applyShareSecretsPolicyDependency($settings, $addonEditable, $policies);
		$this->applyTemplateLanguageDependency($settings);
		$this->applyEmailSignaturePolicyDependency($settings);
		$this->applyEmailSignatureProfileVariables($settings, $userId);
		$this->removeUserOverrideOnlySettings($settings, $sources, $policies, $addonEditable);
	}

	public function getEmailSignatureUserEmail(string $userId): string {
		return $this->emailSignatures->getUserEmail($userId);
	}

	public function getUserOnlyFallbackValue(string $key, string $userId): string {
		return $this->emailSignatures->getUserOnlyFallbackValue($key, $userId);
	}

	/**
	 * @param array<string, array<string, mixed>> $schema
	 * @return array<string, array<string, mixed>>
	 */
	public function applySchemaAvailability(array $schema): array {
		if ($this->isSecretsAppAvailable()) {
			return $schema;
		}

		$schema[ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY]['disabled_options'] = [
			ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_SECRETS => true,
		];
		$schema[ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY]['disabled_note'] = 'The Nextcloud Secrets app is not installed or disabled.';
		$schema[ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY]['disabled'] = true;
		$schema[ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY]['disabled_note'] = 'The Nextcloud Secrets app is not installed or disabled.';
		return $schema;
	}

	/**
	 * @return list<array{id:string, name:string, enabled:bool, purpose:string}>
	 */
	public function getRecommendedApps(): array {
		return [
			[
				'id' => self::SECRETS_APP_ID,
				'name' => 'Nextcloud Secrets',
				'enabled' => $this->isSecretsAppAvailable(),
				'purpose' => 'Enables separate share passwords as expiring Nextcloud Secrets links instead of plain password emails.',
			],
		];
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function applyAttachmentPolicyDependency(array &$settings): void {
		$alwaysViaConnector = $settings['attachments_always_via_ncconnector'] ?? false;
		if ($alwaysViaConnector === true) {
			$settings['attachments_min_size_mb'] = null;
		}
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, bool> $addonEditable
	 * @param array<string, string> $policies
	 */
	private function applyShareSecretsPolicyDependency(array &$settings, array &$addonEditable, array &$policies): void {
		if ($this->isSecretsAppAvailable()) {
			return;
		}

		// Null tells clients Secrets is unavailable; they can warn and keep sending via plaintext.
		$settings[ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY] = null;
		$settings[ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY] = null;
		$addonEditable[ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY] = false;
		$addonEditable[ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY] = false;
		$policies[ClientSettingsDefinitionService::SHARE_SEND_PASSWORD_MODE_KEY] = 'managed';
		$policies[ClientSettingsDefinitionService::SHARE_SECRETS_EXPIRE_DAYS_KEY] = 'managed';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function applyTemplateLanguageDependency(array &$settings): void {
		$shareLanguage = (string)($settings['language_share_html_block'] ?? '');
		if ($shareLanguage !== 'custom') {
			$settings['share_html_block_template'] = null;
			$settings['share_password_template'] = null;
		}

		$talkLanguage = (string)($settings['language_talk_description'] ?? '');
		if ($talkLanguage !== 'custom') {
			$settings['talk_invitation_template'] = null;
			$settings['talk_invitation_template_format'] = null;
			return;
		}

		$talkTemplateFormat = $this->talkTemplates->normalizeFormat((string)($settings['talk_invitation_template_format'] ?? TalkTemplateRuntimeService::FORMAT_PLAIN_TEXT));
		$settings['talk_invitation_template_format'] = $talkTemplateFormat;
		$settings['talk_invitation_template'] = $this->talkTemplates->renderForPolicy(
			(string)($settings['talk_invitation_template'] ?? ''),
			$talkTemplateFormat
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function applyEmailSignaturePolicyDependency(array &$settings): void {
		if (($settings['email_signature_on_compose'] ?? false) === true) {
			return;
		}

		$settings['email_signature_on_reply'] = null;
		$settings['email_signature_on_forward'] = null;
		$settings['email_signature_template'] = null;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function applyEmailSignatureProfileVariables(array &$settings, string $userId): void {
		if (!array_key_exists('email_signature_template', $settings)) {
			return;
		}

		$template = $settings['email_signature_template'];
		if ($template === null) {
			return;
		}
		if ($template === '') {
			$settings['email_signature_template'] = '';
			return;
		}

		$settings['email_signature_template'] = $this->emailSignatures->renderTemplateForPolicy((string)$template, $userId);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $sources
	 * @param array<string, string> $policies
	 * @param array<string, bool> $addonEditable
	 */
	private function removeUserOverrideOnlySettings(array &$settings, array &$sources, array &$policies, array &$addonEditable): void {
		foreach ($this->settingDefinitions->userOverrideOnlyKeys() as $key) {
			unset($settings[$key], $sources[$key], $policies[$key], $addonEditable[$key]);
		}
	}

	private function isSecretsAppAvailable(): bool {
		try {
			return $this->appManager->isEnabledForUser(self::SECRETS_APP_ID);
		} catch (\Throwable $e) {
			$this->logger->warning('Unable to check Nextcloud Secrets app state.', [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
			return false;
		}
	}
}
