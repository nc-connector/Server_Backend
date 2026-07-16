<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

class ClientSettingsDefinitionService {
	public const SHARE_SEND_PASSWORD_MODE_KEY = 'share_send_password_mode';
	public const SHARE_SEND_PASSWORD_MODE_PLAIN = 'plain';
	public const SHARE_SEND_PASSWORD_MODE_SECRETS = 'secrets';
	public const SHARE_SECRETS_EXPIRE_DAYS_KEY = 'share_secrets_expire_days';

	private const MAIL_TEMPLATE_LOGO_URL = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png';
	private const MAIL_TEMPLATE_LOGO_LINK = 'https://nc-connector.de';
	private const USER_OVERRIDE_ONLY_SETTINGS = [
		EmailSignatureRuntimeService::EMAIL_ADDRESS_KEY => true,
		EmailSignatureRuntimeService::PHONE_MOBILE_KEY => true,
		EmailSignatureRuntimeService::CUSTOM1_KEY => true,
		EmailSignatureRuntimeService::CUSTOM2_KEY => true,
	];
	private const BACKEND_ONLY_SETTINGS = [
		self::SHARE_SECRETS_EXPIRE_DAYS_KEY => true,
	];
	private const DEFAULT_SHARE_HTML_BLOCK_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
</head>
<body>
	<div style="font-family:Calibri,'Segoe UI',Arial,sans-serif;font-size:11pt;margin:16px 0;">
		<table role="presentation" width="640" style="border-collapse:separate;border-spacing:0;width:640px;margin:0;background-color:transparent;border:1px solid #d7d7db;border-radius:8px;overflow:hidden;">
			<tbody>
				<tr>
					<td style="padding:0;">
						<table role="presentation" width="640" height="32" style="border-collapse:collapse;width:640px;height:32px;margin:0;background-color:transparent;">
							<tbody>
								<tr>
									<td style="padding:0; background-color:#0082c9; text-align:center; height:32px; min-height:32px; max-height:32px; line-height:0; font-size:0; vertical-align:middle;" height="32">
										<a href="https://nc-connector.de" target="_blank" rel="noopener" style="display:inline-block; text-decoration:none; line-height:0; font-size:0; vertical-align:middle;">
											<img src="https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png" height="32" style="display:block; height:32px; width:auto; border:0; margin:0 auto;">
										</a>
									</td>
								</tr>
							</tbody>
						</table>
						<div style="padding:18px 18px 12px 18px;">
							<p style="margin:0 0 14px 0;line-height:1.4;">{NOTE}</p>
							<p style="margin:0 0 14px 0;line-height:1.4;">{LINK_INTRO}</p>
							<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
								<tbody>
									<tr>
										<th style="text-align:left;width:13ch;vertical-align:top;padding:6px 10px 6px 0;">{LINK_LABEL}</th>
										<td style="padding:6px 0;max-width:50ch;word-break:break-word;"><a href="{URL}" style="color:#0082C9;text-decoration:none;">{URL}</a></td>
									</tr>
									<tr>
										<th style="text-align:left;width:13ch;vertical-align:top;padding:6px 10px 6px 0;">Password</th>
										<td style="padding:6px 0;max-width:50ch;word-break:break-word;"><span style="display:inline-block;font-family:'Consolas','Courier New',monospace;padding:2px 6px;border:1px solid #c7c7c7;border-radius:3px;">{PASSWORD}</span></td>
									</tr>
									<tr>
										<th style="text-align:left;width:13ch;vertical-align:top;padding:6px 10px 6px 0;">Expiration date</th>
										<td style="padding:6px 0;max-width:50ch;word-break:break-word;">{EXPIRATIONDATE}</td>
									</tr>
									<tr>
										<th style="text-align:left;width:13ch;vertical-align:top;padding:6px 10px 6px 0;">Rights</th>
										<td style="padding:6px 0;max-width:50ch;word-break:break-word;">{RIGHTS}</td>
									</tr>
								</tbody>
							</table>
						</div>
						<div style="padding:10px 18px 16px 18px;font-size:9pt;font-style:italic;">
							<a href="https://nextcloud.com/" style="color:#0082C9;text-decoration:none;">Nextcloud</a> is a solution for secure email and data exchange.
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</body>
</html>
HTML;
	private const DEFAULT_SHARE_PASSWORD_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
</head>
<body>
	<div style="font-family:Calibri,'Segoe UI',Arial,sans-serif;font-size:11pt;margin:16px 0;">
		<table role="presentation" width="640" style="border-collapse:separate;border-spacing:0;width:640px;margin:0;background-color:transparent;border:1px solid #d7d7db;border-radius:8px;overflow:hidden;">
			<tbody>
				<tr>
					<td style="padding:0;">
						<table role="presentation" width="640" height="32" style="border-collapse:collapse;width:640px;height:32px;margin:0;background-color:transparent;">
							<tbody>
								<tr>
									<td style="padding:0; background-color:#0082c9; text-align:center; height:32px; min-height:32px; max-height:32px; line-height:0; font-size:0; vertical-align:middle;" height="32">
										<a href="https://nc-connector.de" target="_blank" rel="noopener" style="display:inline-block; text-decoration:none; line-height:0; font-size:0; vertical-align:middle;">
											<img src="https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png" height="32" style="display:block; height:32px; width:auto; border:0; margin:0 auto;">
										</a>
									</td>
								</tr>
							</tbody>
						</table>
						<div style="padding:18px 18px 12px 18px;">
							<p style="margin:0 0 14px 0;line-height:1.4;">Here is your password for the sent share.</p>
							<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
								<tbody>
									<tr>
										<th style="text-align:left;width:12ch;vertical-align:top;padding:6px 10px 6px 0;">Password</th>
										<td style="padding:6px 0;max-width:50ch;word-break:break-word;">
											<span style="display:inline-block;font-family:'Consolas','Courier New',monospace;padding:2px 6px;border:1px solid #c7c7c7;border-radius:3px;">{PASSWORD}</span>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</body>
</html>
HTML;
	private const DEFAULT_TALK_INVITATION_TEMPLATE = <<<'HTML'
<p>This meeting takes place in Nextcloud Talk</p>
<p>Meeting link:<br><a href="{MEETING_URL}">{MEETING_URL}</a></p>
<p>Password: {PASSWORD}</p>
<p>Need help?</p>
<p><a href="https://docs.nextcloud.com/server/latest/user_manual/en/talk/guest.html">https://docs.nextcloud.com/server/latest/user_manual/en/talk/guest.html</a></p>
HTML;
	private const DEFAULT_EMAIL_SIGNATURE_TEMPLATE = <<<'HTML'
<div style="font-family: Arial,sans-serif; font-size: 12px; line-height: 16px;">
<div style="margin: 0 0 12px 0;">Kind regards,<br><strong style="font-size: 12px;">{NAME}</strong><br><em>{FUNCTION}</em></div>
<div style="margin: 0 0 12px 0;">{ABOUT}</div>
<div style="margin: 0 0 2px 0;">{ORGANISATION}</div>
<div style="margin: 0 0 2px 0;">Musterstra&szlig;e 1</div>
<div style="margin: 0 0 2px 0;">9999 Musterort</div>
<div style="margin: 0 0 2px 0;">&nbsp;</div>
<div style="margin: 0 0 2px 0;">Phone:&nbsp;<a style="color: windowtext; text-decoration: none;" href="tel:{PHONE}">{PHONE}</a></div>
<div style="margin: 0 0 10px 0;">Mobile: <a style="color: windowtext; text-decoration: none;" href="tel:{PHONE_MOBILE}">{PHONE_MOBILE}</a></div>
<div style="margin: 0 0 2px 0;">Email: <a style="color: windowtext; text-decoration: none;" href="mailto:{EMAIL}">{EMAIL}</a></div>
<div style="margin: 0 0 2px 0;">Custom1: <a style="color: windowtext; text-decoration: none;" href="{CUSTOM1}">{CUSTOM1}</a></div>
<div style="margin: 0 0 2px 0;">Custom2: <a style="color: windowtext; text-decoration: none;" href="{CUSTOM2}">{CUSTOM2}</a></div>
<div style="margin: 0 0 10px 0;">&nbsp;</div>
<div style="margin: 0 0 14px 0;"><a style="display: inline-block; text-decoration: none; line-height: 0;" href="https://nc-connector.de" target="_blank" rel="noopener noreferrer"><img style="display: block; height: 48px; width: auto; border: 0;" src="https://raw.githubusercontent.com/nc-connector/Server_Backend/refs/heads/main/ncc_backend_4mc/img/header.png" alt="NC Connector" height="48"></a></div>
<div style="margin: 10px 0 0 0; font-size: 11px; line-height: 15px;">This email and any attachments may contain confidential and/or legally protected information. If you are not the intended recipient or have received this email in error,<br>please inform the sender immediately and delete this email. Any use, reproduction, or distribution is not permitted.</div>
<div style="margin: 6px 0 0 0; font-size: 11px; line-height: 15px;"><em><span style="color: #2e7d32;">Please consider the environment before printing this email.</span></em></div>
</div>
HTML;

	public function __construct(
		private TemplateSanitizerService $templateSanitizer,
	) {
	}

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private const DEFINITIONS = [
		'share_base_directory' => ['type' => 'string', 'default' => 'NC Connector', 'max_length' => 255],
		'share_name_template' => ['type' => 'string', 'default' => 'Share name', 'max_length' => 120],
		'share_permission_upload' => ['type' => 'bool', 'default' => true],
		'share_permission_edit' => ['type' => 'bool', 'default' => true],
		'share_permission_delete' => ['type' => 'bool', 'default' => true],
		'share_set_password' => ['type' => 'bool', 'default' => true],
		'share_send_password_separately' => ['type' => 'bool', 'default' => true],
		self::SHARE_SEND_PASSWORD_MODE_KEY => ['type' => 'enum', 'default' => self::SHARE_SEND_PASSWORD_MODE_PLAIN, 'options' => [
			self::SHARE_SEND_PASSWORD_MODE_PLAIN, self::SHARE_SEND_PASSWORD_MODE_SECRETS,
		]],
		self::SHARE_SECRETS_EXPIRE_DAYS_KEY => ['type' => 'int', 'default' => 7, 'min' => 1, 'max' => 365],
		'share_expire_days' => ['type' => 'int', 'default' => 8, 'min' => 0, 'max' => 3650],

		'attachments_always_via_ncconnector' => ['type' => 'bool', 'default' => false],
		'attachments_min_size_mb' => ['type' => 'int', 'default' => 5, 'min' => 0, 'max' => 10240],
		'share_html_block_template' => ['type' => 'string', 'default' => self::DEFAULT_SHARE_HTML_BLOCK_TEMPLATE, 'max_length' => 32768],
		'share_password_template' => ['type' => 'string', 'default' => self::DEFAULT_SHARE_PASSWORD_TEMPLATE, 'max_length' => 32768],

		'language_share_html_block' => ['type' => 'enum', 'default' => 'en', 'options' => [
			'ui_default', 'custom', 'en', 'de', 'fr', 'zh_cn', 'zh_tw', 'it', 'ja', 'nl', 'pl', 'pt_br', 'pt_pt', 'ru', 'es', 'cs', 'hu',
		]],
		'language_talk_description' => ['type' => 'enum', 'default' => 'en', 'options' => [
			'ui_default', 'custom', 'en', 'de', 'fr', 'zh_cn', 'zh_tw', 'it', 'ja', 'nl', 'pl', 'pt_br', 'pt_pt', 'ru', 'es', 'cs', 'hu',
		]],
		'talk_invitation_template_format' => ['type' => 'enum', 'default' => TalkTemplateRuntimeService::FORMAT_PLAIN_TEXT, 'options' => [
			TalkTemplateRuntimeService::FORMAT_PLAIN_TEXT, TalkTemplateRuntimeService::FORMAT_HTML,
		]],
		'talk_invitation_template' => ['type' => 'string', 'default' => self::DEFAULT_TALK_INVITATION_TEMPLATE, 'max_length' => 32768],

		'talk_generate_password' => ['type' => 'bool', 'default' => true],
		'talk_title' => ['type' => 'string', 'default' => 'Meeting', 'max_length' => 120],
		'talk_lobby_active' => ['type' => 'bool', 'default' => true],
		'talk_show_in_search' => ['type' => 'bool', 'default' => true],
		'talk_add_users' => ['type' => 'bool', 'default' => true],
		'talk_add_guests' => ['type' => 'bool', 'default' => false],
		'talk_set_password' => ['type' => 'bool', 'default' => true],
		'talk_delete_room_on_event_delete' => ['type' => 'bool', 'default' => false],
		'talk_room_type' => ['type' => 'enum', 'default' => 'event', 'options' => ['event', 'group']],

		'email_signature_on_compose' => ['type' => 'bool', 'default' => true],
		'email_signature_on_reply' => ['type' => 'bool', 'default' => false],
		'email_signature_on_forward' => ['type' => 'bool', 'default' => false],
		'email_signature_template' => ['type' => 'string', 'default' => self::DEFAULT_EMAIL_SIGNATURE_TEMPLATE, 'max_length' => 32768],
		EmailSignatureRuntimeService::EMAIL_ADDRESS_KEY => ['type' => 'string', 'default' => '', 'max_length' => 255],
		EmailSignatureRuntimeService::PHONE_MOBILE_KEY => ['type' => 'string', 'default' => '', 'max_length' => 255],
		EmailSignatureRuntimeService::CUSTOM1_KEY => ['type' => 'string', 'default' => '', 'max_length' => 255],
		EmailSignatureRuntimeService::CUSTOM2_KEY => ['type' => 'string', 'default' => '', 'max_length' => 255],
	];

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return self::DEFINITIONS;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(string $key): array {
		if (!array_key_exists($key, self::DEFINITIONS)) {
			throw new \InvalidArgumentException(sprintf('Unknown setting key "%s"', $key));
		}

		return self::DEFINITIONS[$key];
	}

	public function has(string $key): bool {
		return array_key_exists($key, self::DEFINITIONS);
	}

	public function isAddonControllableSetting(string $key): bool {
		return !$this->isTemplateManagedSetting($key)
			&& !$this->isUserOverrideOnlySetting($key)
			&& !isset(self::BACKEND_ONLY_SETTINGS[$key]);
	}

	public function isUserOverrideOnlySetting(string $key): bool {
		return isset(self::USER_OVERRIDE_ONLY_SETTINGS[$key]);
	}

	/**
	 * @return string[]
	 */
	public function userOverrideOnlyKeys(): array {
		return array_keys(self::USER_OVERRIDE_ONLY_SETTINGS);
	}

	public function isTemplateManagedSetting(string $key): bool {
		return $this->isTemplateEditorSetting($key) || $key === 'talk_invitation_template_format';
	}

	public function isTemplateEditorSetting(string $key): bool {
		return $key === 'share_html_block_template'
			|| $key === 'share_password_template'
			|| $key === 'talk_invitation_template'
			|| $key === 'email_signature_template';
	}

	public function normalizeValue(string $key, mixed $value): mixed {
		$definition = $this->get($key);
		$type = $definition['type'];

		if ($type === 'bool') {
			if (is_bool($value)) {
				return $value;
			}
			if (is_int($value) && ($value === 0 || $value === 1)) {
				return $value === 1;
			}
			if (is_string($value)) {
				$raw = strtolower(trim($value));
				if (in_array($raw, ['1', 'true', 'yes', 'on'], true)) {
					return true;
				}
				if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
					return false;
				}
			}
			throw new \InvalidArgumentException(sprintf('Setting "%s" expects boolean', $key));
		}

		if ($type === 'int') {
			if ($key === 'attachments_min_size_mb' && $value === null) {
				return null;
			}
			if (is_string($value) && trim($value) !== '' && is_numeric($value)) {
				$value = (int)$value;
			}
			if (!is_int($value)) {
				throw new \InvalidArgumentException(sprintf('Setting "%s" expects integer', $key));
			}

			$min = isset($definition['min']) ? (int)$definition['min'] : null;
			$max = isset($definition['max']) ? (int)$definition['max'] : null;
			if ($min !== null && $value < $min) {
				throw new \InvalidArgumentException(sprintf('Setting "%s" must be >= %d', $key, $min));
			}
			if ($max !== null && $value > $max) {
				throw new \InvalidArgumentException(sprintf('Setting "%s" must be <= %d', $key, $max));
			}

			return $value;
		}

		if ($type === 'enum') {
			$normalized = strtolower(trim((string)$value));
			$options = $definition['options'] ?? [];
			if (!in_array($normalized, $options, true)) {
				throw new \InvalidArgumentException(sprintf('Setting "%s" has invalid option', $key));
			}
			return $normalized;
		}

		$normalized = (string)$value;
		$maxLength = isset($definition['max_length']) ? (int)$definition['max_length'] : null;
		if ($maxLength !== null && strlen($normalized) > $maxLength) {
			throw new \InvalidArgumentException(sprintf('Setting "%s" is too long', $key));
		}
		$normalized = $this->normalizeTemplateEditorValue($key, $normalized);
		if ($maxLength !== null && strlen($normalized) > $maxLength) {
			throw new \InvalidArgumentException(sprintf('Setting "%s" is too long', $key));
		}

		return $normalized;
	}

	public function serializeValue(string $key, mixed $value): string {
		$type = $this->get($key)['type'];
		return match ($type) {
			'bool' => $value ? '1' : '0',
			'int' => $value === null ? '' : (string)$value,
			default => (string)$value,
		};
	}

	public function parseStoredValue(string $key, string $stored): mixed {
		$type = $this->get($key)['type'];
		if ($type === 'int' && $key === 'attachments_min_size_mb' && trim($stored) === '') {
			return null;
		}
		if ($this->isTemplateEditorSetting($key)) {
			$stored = $this->normalizeTemplateEditorValue($key, $stored);
		}
		return match ($type) {
			'bool' => $stored === '1',
			'int' => (int)$stored,
			default => $stored,
		};
	}

	/**
	 * @param array<string, mixed>|null $templateAssetPreview
	 * @return array<string, string>
	 */
	public function normalizeTemplateAssetPreview(?array $templateAssetPreview): array {
		if (!is_array($templateAssetPreview)) {
			return [];
		}

		$normalized = [];
		foreach ($templateAssetPreview as $key => $value) {
			$key = (string)$key;
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}

			$normalized[$key] = $this->normalizeTemplateEditorValue($key, (string)$value);
		}

		return $normalized;
	}

	private function normalizeTemplateEditorValue(string $key, string $value): string {
		if (!$this->isTemplateEditorSetting($key)) {
			return $value;
		}

		if ($key === 'share_html_block_template' || $key === 'share_password_template') {
			$value = $this->normalizeTemplateBranding($value);
		}

		return $this->templateSanitizer->sanitizeHtml($value);
	}

	private function normalizeTemplateBranding(string $template): string {
		$template = str_replace(
			[
				'href="https://github.com/nc-connector/NC_Connector_for_Thunderbird"',
				'href=\'https://github.com/nc-connector/NC_Connector_for_Thunderbird\'',
			],
			[
				'href="' . self::MAIL_TEMPLATE_LOGO_LINK . '"',
				'href=\'' . self::MAIL_TEMPLATE_LOGO_LINK . '\'',
			],
			$template
		);

		$template = str_replace(
			[
				'src="../../apps/ncc_backend_4mc/img/header.png"',
				"src='../../apps/ncc_backend_4mc/img/header.png'",
				'src="../img/header.png"',
				"src='../img/header.png'",
			],
			[
				'src="' . self::MAIL_TEMPLATE_LOGO_URL . '"',
				"src='" . self::MAIL_TEMPLATE_LOGO_URL . "'",
				'src="' . self::MAIL_TEMPLATE_LOGO_URL . '"',
				"src='" . self::MAIL_TEMPLATE_LOGO_URL . "'",
				'src="' . self::MAIL_TEMPLATE_LOGO_URL . '"',
				"src='" . self::MAIL_TEMPLATE_LOGO_URL . "'",
			],
			$template
		);

		$template = preg_replace(
			'/src=(["\'])cid:[^"\']+\\1/i',
			'src="' . self::MAIL_TEMPLATE_LOGO_URL . '"',
			$template
		) ?? $template;

		$template = str_replace(
			'table role="presentation" width="640" style="border-collapse:collapse;width:640px;margin:0;background-color:transparent;"',
			'table role="presentation" width="640" height="32" style="border-collapse:collapse;width:640px;height:32px;margin:0;background-color:transparent;"',
			$template
		);

		$template = preg_replace(
			'/<td\b[^>]*style=(["\'])(?=[^"\']*background-color:#0082[cC]9)(?=[^"\']*text-align:center)(?=[^"\']*height:32px)(?=[^"\']*min-height:32px)(?=[^"\']*max-height:32px)[^"\']*\\1[^>]*>/i',
			'<td style="padding:0; background-color:#0082c9; text-align:center; height:32px; min-height:32px; max-height:32px; line-height:0; font-size:0; vertical-align:middle;" height="32">',
			$template
		) ?? $template;

		$template = preg_replace(
			'/<a\b[^>]*href=(["\'])' . preg_quote(self::MAIL_TEMPLATE_LOGO_LINK, '/') . '\\1[^>]*>/i',
			'<a href="' . self::MAIL_TEMPLATE_LOGO_LINK . '" target="_blank" rel="noopener" style="display:inline-block; text-decoration:none; line-height:0; font-size:0; vertical-align:middle;">',
			$template
		) ?? $template;

		$template = preg_replace(
			'/<img\b[^>]*src=(["\'])' . preg_quote(self::MAIL_TEMPLATE_LOGO_URL, '/') . '\\1[^>]*>/i',
			'<img src="' . self::MAIL_TEMPLATE_LOGO_URL . '" height="32" style="display:block; height:32px; width:auto; border:0; margin:0 auto;">',
			$template
		) ?? $template;

		return $template;
	}
}
