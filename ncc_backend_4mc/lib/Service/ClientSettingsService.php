<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\AppInfo\Application;
use OCA\NcConnector\Db\ClientOverride;
use OCA\NcConnector\Db\ClientOverrideMapper;
use OCA\NcConnector\Db\GroupOverride;
use OCA\NcConnector\Db\GroupOverrideMapper;
use OCA\NcConnector\Db\SettingMapper;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\Http\Client\IClientService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ClientSettingsService {
	private const MAIL_TEMPLATE_LOGO_URL = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png';
	private const MAIL_TEMPLATE_LOGO_LINK = 'https://nc-connector.de';
	private const TALK_HELP_URL = 'https://docs.nextcloud.com/server/latest/user_manual/en/talk/guest.html';
	private const DEFAULT_KEY_PREFIX = 'client.default.';
	private const DEFAULT_MODE_KEY_PREFIX = 'client.default_mode.';
	private const MODE_INHERIT = 'inherit';
	private const MODE_FORCED = 'forced';
	private const MODE_USER_CHOICE = 'user_choice';
	private const MODE_DEFAULT = 'default';
	private const GROUP_OVERRIDE_PRIORITY_DEFAULT = 100;
	private const TALK_TEMPLATE_FORMAT_HTML = 'html';
	private const TALK_TEMPLATE_FORMAT_PLAIN_TEXT = 'plain_text';
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
							<p style="margin:0 0 14px 0;line-height:1.4;">The files have been provided securely and in a privacy-compliant manner via Nextcloud. You can download them using the link below.</p>
							<table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
								<tbody>
									<tr>
										<th style="text-align:left;width:13ch;vertical-align:top;padding:6px 10px 6px 0;">Download link</th>
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
<style>
  table, td, th {
    border: 0 !important;
    outline: 0 !important;
    -moz-outline: 0 !important;
    border-color: transparent !important;
    outline-color: transparent !important;
    border-collapse: collapse !important;
  }
  table[border="0"], table[border="0"] td, table[border="0"] th {
    border: 0 !important;
    outline: 0 !important;
    -moz-outline: 0 !important;
  }
</style>

<table cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,sans-serif;font-size:12px;line-height:16px">
  <tr><td style="padding:0 0 12px 0">
    Kind regards,<br>
    <strong style="font-size:12px">{NAME}</strong><br>
    <em>{FUNCTION}</em>
  </td></tr>
  <tr><td style="padding:0 0 8px 0;font-size:11px;line-height:15px">
    {ORGANISATION}<br>
    {ABOUT}
  </td></tr>
  <tr><td style="padding:0 0 2px 0">
    Phone: <a href="tel:{PHONE}" style="color:windowtext;text-decoration:none">{PHONE}</a>
  </td></tr>
  <tr><td style="padding:0 0 10px 0">
    Email: <a href="mailto:{EMAIL}" style="color:windowtext;text-decoration:none">{EMAIL}</a>
  </td></tr>
  <tr><td style="padding:0 0 14px 0">
    <a href="https://nc-connector.de" style="display:inline-block;text-decoration:none;line-height:0" target="_blank" rel="noopener">
      <img src="https://raw.githubusercontent.com/nc-connector/Server_Backend/refs/heads/main/ncc_backend_4mc/img/header.png" height="48" alt="NC Connector" style="display:block;height:48px;width:auto;border:0">
    </a>
  </td></tr>
  <tr><td style="padding:10px 0 0 0;font-size:11px;line-height:15px">
    This email and any attachments may contain confidential and/or legally protected information. If you are not the intended recipient or have received this email in error,<br>
    please inform the sender immediately and delete this email. Any use, reproduction, or distribution is not permitted.
  </td></tr>
  <tr><td style="padding:6px 0 0 0;font-size:11px;line-height:15px">
    <em><font color="#2e7d32">Please consider the environment before printing this email.</font></em>
  </td></tr>
</table>
HTML;

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
		'talk_invitation_template_format' => ['type' => 'enum', 'default' => self::TALK_TEMPLATE_FORMAT_PLAIN_TEXT, 'options' => [
			self::TALK_TEMPLATE_FORMAT_PLAIN_TEXT, self::TALK_TEMPLATE_FORMAT_HTML,
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
		'email_signature_on_reply_forward' => ['type' => 'bool', 'default' => false],
		'email_signature_template' => ['type' => 'string', 'default' => self::DEFAULT_EMAIL_SIGNATURE_TEMPLATE, 'max_length' => 32768],
	];

	public function __construct(
		private SettingMapper $settings,
		private ClientOverrideMapper $overrides,
		private GroupOverrideMapper $groupOverrides,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IAccountManager $accountManager,
		private IClientService $clientService,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function getSchema(): array {
		$result = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			$result[$key] = $definition;
		}
		return $result;
	}

	public function getDefaults(): array {
		$defaults = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			$stored = $this->settings->getValue(self::DEFAULT_KEY_PREFIX . $key, null);
			if ($stored === null) {
				$defaults[$key] = $definition['default'];
				continue;
			}
			$defaults[$key] = $this->parseStoredValue($key, $stored);
		}
		return $defaults;
	}

	/**
	 * @param array<string, mixed>|null $defaults
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForDefaults(?array $defaults = null, ?array $templateAssetPreview = null): array {
		$defaults ??= $this->getDefaults();
		$templateAssetPreview = $this->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}
			$value = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (string)($defaults[$key] ?? $definition['default'] ?? '');
			$assets[$key] = $this->buildTemplateAssetMap('default-' . $key, $value);
		}
		return $assets;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForSchemaDefaults(): array {
		$assets = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}
			$assets[$key] = $this->buildTemplateAssetMap('schema-' . $key, (string)($definition['default'] ?? ''));
		}
		return $assets;
	}

	public function getDefaultModes(): array {
		$modes = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			$storedMode = $this->settings->getValue(self::DEFAULT_MODE_KEY_PREFIX . $key, null);
			$modes[$key] = $storedMode === null
				? $this->getBuiltInDefaultMode($key)
				: $this->normalizeDefaultMode($key, (string)$storedMode);
		}
		return $modes;
	}

	public function setDefaults(array $defaults): array {
		$now = time();
		foreach ($defaults as $key => $rawValue) {
			$this->assertKnownSetting($key);
			$mode = $this->getBuiltInDefaultMode($key);
			$valueToStore = $rawValue;

			if (is_array($rawValue) && array_key_exists('mode', $rawValue)) {
				$mode = $this->normalizeDefaultMode($key, (string)($rawValue['mode'] ?? self::MODE_DEFAULT));
				$this->settings->setValue(self::DEFAULT_MODE_KEY_PREFIX . $key, $mode, $now);

				if ($mode === self::MODE_USER_CHOICE) {
					if (array_key_exists('value', $rawValue)) {
						$normalized = $this->normalizeValue($key, $rawValue['value']);
						$this->settings->setValue(
							self::DEFAULT_KEY_PREFIX . $key,
							$this->serializeValue($key, $normalized),
							$now
						);
					}
					continue;
				}

				if (!array_key_exists('value', $rawValue)) {
					throw new \InvalidArgumentException(sprintf('Missing default value for "%s"', $key));
				}
				$valueToStore = $rawValue['value'];
			} else {
				$this->settings->setValue(self::DEFAULT_MODE_KEY_PREFIX . $key, $this->getBuiltInDefaultMode($key), $now);
			}

			$normalized = $this->normalizeValue($key, $valueToStore);
			$this->settings->setValue(
				self::DEFAULT_KEY_PREFIX . $key,
				$this->serializeValue($key, $normalized),
				$now
			);
		}
		return [
			'defaults' => $this->getDefaults(),
			'default_modes' => $this->getDefaultModes(),
		];
	}

	public function getUserSettings(string $userId): array {
		$defaults = $this->getDefaults();
		$defaultModes = $this->getDefaultModes();
		$overrideMap = $this->overrides->getForUser($userId);
		$groupMatches = $this->resolveGroupOverridesForUser($userId);
		$items = [];

		foreach (self::DEFINITIONS as $key => $definition) {
			$defaultMode = $defaultModes[$key] ?? self::MODE_DEFAULT;
			$override = $overrideMap[$key] ?? null;
			if ($override instanceof ClientOverride && $override->getMode() === self::MODE_FORCED) {
				$forcedValue = $this->parseStoredValue($key, (string)$override->getSettingValue());
				$items[$key] = [
					'mode' => self::MODE_FORCED,
					'value' => $forcedValue,
					'effective_value' => $forcedValue,
					'source' => 'user',
					'default_mode' => $defaultMode,
				];
				continue;
			}

			$groupMatch = $groupMatches[$key] ?? null;
			if (is_array($groupMatch) && ($groupMatch['override'] ?? null) instanceof GroupOverride) {
				/** @var GroupOverride $groupOverride */
				$groupOverride = $groupMatch['override'];
				$forcedValue = $this->parseStoredValue($key, (string)$groupOverride->getSettingValue());
				$items[$key] = [
					'mode' => self::MODE_INHERIT,
					'value' => null,
					'effective_value' => $forcedValue,
					'source' => 'group',
					'default_mode' => $defaultMode,
					'group_id' => (string)($groupMatch['group_id'] ?? ''),
					'group_priority' => (int)($groupMatch['priority'] ?? self::GROUP_OVERRIDE_PRIORITY_DEFAULT),
				];
				continue;
			}

			$items[$key] = [
				'mode' => self::MODE_INHERIT,
				'value' => null,
				'effective_value' => $defaults[$key],
				'source' => $defaultMode === self::MODE_USER_CHOICE ? self::MODE_USER_CHOICE : 'default',
				'default_mode' => $defaultMode,
			];
		}

		return $items;
	}

	/**
	 * @return array{priority:int, items: array<string, array<string, mixed>>}
	 */
	public function getGroupSettings(string $groupId): array {
		$defaults = $this->getDefaults();
		$defaultModes = $this->getDefaultModes();
		$overrideMap = $this->groupOverrides->getForGroup($groupId);
		$priority = $this->getStoredGroupPriority($overrideMap);
		$items = [];

		foreach (self::DEFINITIONS as $key => $definition) {
			$defaultMode = $defaultModes[$key] ?? self::MODE_DEFAULT;
			$override = $overrideMap[$key] ?? null;
			if ($override instanceof GroupOverride && $override->getMode() === self::MODE_FORCED) {
				$forcedValue = $this->parseStoredValue($key, (string)$override->getSettingValue());
				$items[$key] = [
					'mode' => self::MODE_FORCED,
					'value' => $forcedValue,
					'effective_value' => $forcedValue,
					'source' => 'group',
					'default_mode' => $defaultMode,
					'group_id' => $groupId,
					'group_priority' => $priority,
				];
				continue;
			}

			$items[$key] = [
				'mode' => self::MODE_INHERIT,
				'value' => null,
				'effective_value' => $defaults[$key],
				'source' => $defaultMode === self::MODE_USER_CHOICE ? self::MODE_USER_CHOICE : 'default',
				'default_mode' => $defaultMode,
				'group_id' => $groupId,
				'group_priority' => $priority,
			];
		}

		return [
			'priority' => $priority,
			'items' => $items,
		];
	}

	/**
	 * @param array<string, array<string, mixed>>|null $items
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForUser(string $userId, ?array $items = null, ?array $templateAssetPreview = null): array {
		$items ??= $this->getUserSettings($userId);
		$templateAssetPreview = $this->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}
			$item = $items[$key] ?? null;
			if (!is_array($item)) {
				$assets[$key] = [];
				continue;
			}
			$currentValue = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (($item['mode'] ?? 'inherit') === self::MODE_FORCED
					? (string)($item['value'] ?? '')
					: (string)($item['effective_value'] ?? $definition['default'] ?? ''));
			$assets[$key] = $this->buildTemplateAssetMap('user-' . $userId . '-' . $key, $currentValue);
		}
		return $assets;
	}

	/**
	 * @param array<string, array<string, mixed>>|null $items
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForGroup(string $groupId, ?array $items = null, ?array $templateAssetPreview = null): array {
		if ($items === null) {
			$groupSettings = $this->getGroupSettings($groupId);
			$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		}
		$templateAssetPreview = $this->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		foreach (self::DEFINITIONS as $key => $definition) {
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}
			$item = $items[$key] ?? null;
			if (!is_array($item)) {
				$assets[$key] = [];
				continue;
			}
			$currentValue = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (($item['mode'] ?? 'inherit') === self::MODE_FORCED
					? (string)($item['value'] ?? '')
					: (string)($item['effective_value'] ?? $definition['default'] ?? ''));
			$assets[$key] = $this->buildTemplateAssetMap('group-' . $groupId . '-' . $key, $currentValue);
		}
		return $assets;
	}

	public function setUserSettings(string $userId, array $overrides, ?string $updatedBy): array {
		$now = time();
		foreach ($overrides as $key => $payload) {
			$this->assertKnownSetting($key);
			if (!is_array($payload)) {
				throw new \InvalidArgumentException(sprintf('Override for "%s" must be an object', $key));
			}

			$mode = strtolower(trim((string)($payload['mode'] ?? '')));
			if ($mode === self::MODE_INHERIT) {
				$this->overrides->deleteForUserAndKey($userId, $key);
				continue;
			}

			if ($mode === self::MODE_USER_CHOICE) {
				$this->overrides->deleteForUserAndKey($userId, $key);
				continue;
			}

			if ($mode !== self::MODE_FORCED) {
				throw new \InvalidArgumentException(sprintf('Invalid mode for "%s"', $key));
			}
			if (!array_key_exists('value', $payload)) {
				throw new \InvalidArgumentException(sprintf('Missing value for "%s" in forced mode', $key));
			}

			$normalized = $this->normalizeValue($key, $payload['value']);
			$this->overrides->upsert(
				$userId,
				$key,
				self::MODE_FORCED,
				$this->serializeValue($key, $normalized),
				$now,
				$updatedBy
			);
		}

		return $this->getUserSettings($userId);
	}

	/**
	 * @return array{priority:int, items: array<string, array<string, mixed>>}
	 */
	public function setGroupSettings(string $groupId, int $priority, array $overrides, ?string $updatedBy): array {
		$priority = $this->normalizeGroupOverridePriority($priority);
		$now = time();
		foreach ($overrides as $key => $payload) {
			$this->assertKnownSetting($key);
			if (!is_array($payload)) {
				throw new \InvalidArgumentException(sprintf('Override for "%s" must be an object', $key));
			}

			$mode = strtolower(trim((string)($payload['mode'] ?? '')));
			if ($mode === self::MODE_INHERIT || $mode === self::MODE_USER_CHOICE) {
				$this->groupOverrides->deleteForGroupAndKey($groupId, $key);
				continue;
			}

			if ($mode !== self::MODE_FORCED) {
				throw new \InvalidArgumentException(sprintf('Invalid mode for "%s"', $key));
			}
			if (!array_key_exists('value', $payload)) {
				throw new \InvalidArgumentException(sprintf('Missing value for "%s" in forced mode', $key));
			}

			$normalized = $this->normalizeValue($key, $payload['value']);
			$this->groupOverrides->upsert(
				$groupId,
				$priority,
				$key,
				self::MODE_FORCED,
				$this->serializeValue($key, $normalized),
				$now,
				$updatedBy
			);
		}

		return $this->getGroupSettings($groupId);
	}

	public function getEffectiveForUser(string $userId): array {
		$items = $this->getUserSettings($userId);
		$settings = [];
		$sources = [];
		$policies = [];
		$addonEditable = [];
		foreach ($items as $key => $item) {
			$source = (string)($item['source'] ?? 'default');
			$settings[$key] = $item['effective_value'];
			$addonEditable[$key] = $source === self::MODE_USER_CHOICE;
			$policies[$key] = $addonEditable[$key] ? self::MODE_USER_CHOICE : 'managed';
			$sources[$key] = $item['source'];
		}
		$this->applyAttachmentPolicyDependency($settings);
		$this->applyTemplateLanguageDependency($settings);
		$this->applyEmailSignatureProfileVariables($settings, $userId);

		return [
			'settings' => $settings,
			'sources' => $sources,
			'policies' => $policies,
			'addon_editable' => $addonEditable,
		];
	}

	/**
	 * @param string[] $userIds
	 * @return array<string, list<array{group_id:string, display_name:string, priority:int}>>
	 */
	public function getUsersWithGroupOverrideDetails(array $userIds): array {
		$userIds = array_values(array_filter($userIds, static fn (mixed $userId): bool => is_string($userId) && $userId !== ''));
		if ($userIds === []) {
			return [];
		}

		$userGroups = [];
		$allGroupIds = [];
		foreach ($userIds as $userId) {
			$groupIds = $this->getGroupIdsForUser($userId);
			$userGroups[$userId] = $groupIds;
			foreach ($groupIds as $groupId) {
				$allGroupIds[$groupId] = true;
			}
		}

		$groupsWithForcedOverrides = [];
		if ($allGroupIds !== []) {
			foreach ($this->groupOverrides->getForGroups(array_keys($allGroupIds)) as $groupId => $overrideMap) {
				$hasForcedOverride = false;
				foreach ($overrideMap as $override) {
					if ($override instanceof GroupOverride && $override->getMode() === self::MODE_FORCED) {
						$hasForcedOverride = true;
						break;
					}
				}
				if (!$hasForcedOverride) {
					continue;
				}
				$group = $this->groupManager->get($groupId);
				$groupsWithForcedOverrides[$groupId] = [
					'group_id' => $groupId,
					'display_name' => $group instanceof IGroup ? $group->getDisplayName() : '',
					'priority' => $this->getStoredGroupPriority($overrideMap),
				];
			}
		}

		$details = [];
		foreach ($userGroups as $userId => $groupIds) {
			$details[$userId] = [];
			foreach ($groupIds as $groupId) {
				if (isset($groupsWithForcedOverrides[$groupId])) {
					$details[$userId][] = $groupsWithForcedOverrides[$groupId];
				}
			}
			usort($details[$userId], static function (array $left, array $right): int {
				$priorityCompare = ((int)($left['priority'] ?? self::GROUP_OVERRIDE_PRIORITY_DEFAULT))
					<=> ((int)($right['priority'] ?? self::GROUP_OVERRIDE_PRIORITY_DEFAULT));
				if ($priorityCompare !== 0) {
					return $priorityCompare;
				}

				$leftLabel = (string)($left['display_name'] ?: $left['group_id']);
				$rightLabel = (string)($right['display_name'] ?: $right['group_id']);
				return strcasecmp($leftLabel, $rightLabel);
			});
		}

		return $details;
	}

	/**
	 * @param string[] $userIds
	 * @return array<string, bool>
	 */
	public function getUsersWithGroupOverrides(array $userIds): array {
		$details = $this->getUsersWithGroupOverrideDetails($userIds);
		$map = [];
		foreach ($details as $userId => $matches) {
			$map[$userId] = $matches !== [];
		}

		return $map;
	}

	private function normalizeGroupOverridePriority(mixed $priority): int {
		if (is_int($priority)) {
			$value = $priority;
		} elseif (is_string($priority) && trim($priority) !== '' && preg_match('/^\d+$/', trim($priority)) === 1) {
			$value = (int)trim($priority);
		} else {
			throw new \InvalidArgumentException('Invalid group override priority');
		}

		if ($value < 1 || $value > 9999) {
			throw new \InvalidArgumentException('Group override priority must be between 1 and 9999');
		}

		return $value;
	}

	/**
	 * @param array<string, GroupOverride> $overrideMap
	 */
	private function getStoredGroupPriority(array $overrideMap): int {
		$priorities = [];
		foreach ($overrideMap as $override) {
			if (!$override instanceof GroupOverride) {
				continue;
			}
			$priority = $override->getPriority();
			if ($priority >= 1) {
				$priorities[] = $priority;
			}
		}

		if ($priorities === []) {
			return self::GROUP_OVERRIDE_PRIORITY_DEFAULT;
		}

		sort($priorities, SORT_NUMERIC);
		return (int)$priorities[0];
	}

	/**
	 * @return string[]
	 */
	private function getGroupIdsForUser(string $userId): array {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return [];
		}

		$groups = $this->groupManager->getUserGroups($user);
		$groupIds = [];
		foreach ($groups as $group) {
			if ($group instanceof IGroup) {
				$groupIds[] = $group->getGID();
			}
		}

		$groupIds = array_values(array_unique(array_filter(
			$groupIds,
			static fn (mixed $groupId): bool => is_string($groupId) && $groupId !== ''
		)));
		sort($groupIds, SORT_STRING);
		return $groupIds;
	}

	/**
	 * @return array<string, array{group_id:string, priority:int, override: GroupOverride}>
	 */
	private function resolveGroupOverridesForUser(string $userId): array {
		$groupIds = $this->getGroupIdsForUser($userId);
		if ($groupIds === []) {
			return [];
		}

		$overrideMaps = $this->groupOverrides->getForGroups($groupIds);
		$resolved = [];
		foreach (self::DEFINITIONS as $key => $_definition) {
			$bestMatch = null;
			foreach ($groupIds as $groupId) {
				$override = $overrideMaps[$groupId][$key] ?? null;
				if (!$override instanceof GroupOverride || $override->getMode() !== self::MODE_FORCED) {
					continue;
				}

				$candidate = [
					'group_id' => $groupId,
					'priority' => $override->getPriority() > 0 ? $override->getPriority() : self::GROUP_OVERRIDE_PRIORITY_DEFAULT,
					'override' => $override,
				];

				if ($bestMatch === null
					|| $candidate['priority'] < $bestMatch['priority']
					|| ($candidate['priority'] === $bestMatch['priority'] && strcmp($candidate['group_id'], $bestMatch['group_id']) < 0)
				) {
					$bestMatch = $candidate;
				}
			}

			if ($bestMatch !== null) {
				$resolved[$key] = $bestMatch;
			}
		}

		return $resolved;
	}

	private function assertKnownSetting(string $key): void {
		if (!array_key_exists($key, self::DEFINITIONS)) {
			throw new \InvalidArgumentException(sprintf('Unknown setting key "%s"', $key));
		}
	}

	private function normalizeValue(string $key, mixed $value): mixed {
		$definition = self::DEFINITIONS[$key];
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

		return $normalized;
	}

	private function serializeValue(string $key, mixed $value): string {
		$type = self::DEFINITIONS[$key]['type'];
		return match ($type) {
			'bool' => $value ? '1' : '0',
			'int' => $value === null ? '' : (string)$value,
			default => (string)$value,
		};
	}

	private function parseStoredValue(string $key, string $stored): mixed {
		$type = self::DEFINITIONS[$key]['type'];
		if ($type === 'int' && $key === 'attachments_min_size_mb' && trim($stored) === '') {
			return null;
		}
		if ($key === 'share_html_block_template' || $key === 'share_password_template') {
			$stored = $this->normalizeTemplateBranding($stored);
		}
		return match ($type) {
			'bool' => $stored === '1',
			'int' => (int)$stored,
			default => $stored,
		};
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

		$talkTemplateFormat = $this->normalizeTalkTemplateFormat((string)($settings['talk_invitation_template_format'] ?? self::TALK_TEMPLATE_FORMAT_PLAIN_TEXT));
		$settings['talk_invitation_template_format'] = $talkTemplateFormat;
		$settings['talk_invitation_template'] = $this->renderTalkTemplateForPolicy(
			(string)($settings['talk_invitation_template'] ?? ''),
			$talkTemplateFormat
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function applyEmailSignatureProfileVariables(array &$settings, string $userId): void {
		if (!array_key_exists('email_signature_template', $settings)) {
			return;
		}

		$template = $settings['email_signature_template'];
		if ($template === null || $template === '') {
			$settings['email_signature_template'] = '';
			return;
		}

		$settings['email_signature_template'] = $this->renderEmailSignatureTemplateForPolicy((string)$template, $userId);
	}

	private function renderEmailSignatureTemplateForPolicy(string $template, string $userId): string {
		$variables = $this->getEmailSignatureTemplateVariables($userId);
		$replacements = [];

		foreach ($variables as $name => $value) {
			$replacements['{' . $name . '}'] = $this->escapeEmailSignatureTemplateValue($value);
		}

		return strtr($template, $replacements);
	}

	/**
	 * @return array<string, string>
	 */
	private function getEmailSignatureTemplateVariables(string $userId): array {
		$variables = [
			'NAME' => '',
			'EMAIL' => '',
			'PHONE' => '',
			'ABOUT' => '',
			'FUNCTION' => '',
			'ORGANISATION' => '',
		];

		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			return $variables;
		}

		$variables['NAME'] = (string)$user->getDisplayName();
		$variables['EMAIL'] = (string)($user->getEMailAddress() ?? '');

		try {
			$account = $this->accountManager->getAccount($user);
		} catch (\Throwable $e) {
			$this->logError('Email signature profile lookup failed.', [
				'userId' => $userId,
				'exception' => $e,
			]);
			return $variables;
		}

		if ($variables['EMAIL'] === '') {
			$variables['EMAIL'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_EMAIL);
		}

		$variables['PHONE'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_PHONE);
		$variables['ABOUT'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_BIOGRAPHY);
		$variables['FUNCTION'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_ROLE);
		$variables['ORGANISATION'] = $this->getAccountPropertyValue($account, IAccountManager::PROPERTY_ORGANISATION);

		return $variables;
	}

	private function getAccountPropertyValue(IAccount $account, string $property): string {
		try {
			return (string)$account->getProperty($property)->getValue();
		} catch (PropertyDoesNotExistException) {
			return '';
		}
	}

	private function escapeEmailSignatureTemplateValue(string $value): string {
		$normalized = trim((string)preg_replace('/\s+/u', ' ', $value));
		return htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function normalizeDefaultMode(string $key, string $mode): string {
		if (!$this->isAddonControllableSetting($key)) {
			return self::MODE_DEFAULT;
		}
		$normalized = strtolower(trim($mode));
		if ($normalized === self::MODE_USER_CHOICE) {
			return self::MODE_USER_CHOICE;
		}
		return self::MODE_DEFAULT;
	}

	private function getBuiltInDefaultMode(string $key): string {
		if (!$this->isAddonControllableSetting($key)) {
			return self::MODE_DEFAULT;
		}

		return self::MODE_USER_CHOICE;
	}

	private function isAddonControllableSetting(string $key): bool {
		return !$this->isTemplateManagedSetting($key);
	}

	private function isTemplateManagedSetting(string $key): bool {
		return $this->isTemplateEditorSetting($key) || $key === 'talk_invitation_template_format';
	}

	private function isTemplateEditorSetting(string $key): bool {
		return $key === 'share_html_block_template'
			|| $key === 'share_password_template'
			|| $key === 'talk_invitation_template'
			|| $key === 'email_signature_template';
	}

	private function normalizeTalkTemplateFormat(string $format): string {
		return strtolower(trim($format)) === self::TALK_TEMPLATE_FORMAT_HTML
			? self::TALK_TEMPLATE_FORMAT_HTML
			: self::TALK_TEMPLATE_FORMAT_PLAIN_TEXT;
	}

	private function renderTalkTemplateForPolicy(string $template, string $format): string {
		$normalizedFormat = $this->normalizeTalkTemplateFormat($format);
		if ($normalizedFormat === self::TALK_TEMPLATE_FORMAT_HTML) {
			return $template;
		}

		return $this->convertTalkTemplateHtmlToPlainText($template);
	}

	private function convertTalkTemplateHtmlToPlainText(string $template): string {
		if (trim($template) === '') {
			return '';
		}

		$previousUseInternalErrors = libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previousUseInternalErrors);

		if ($loaded === false || !$document->documentElement) {
			return $this->fallbackTalkTemplateHtmlToPlainText($template);
		}

		$plain = $this->renderTalkTemplateDocumentAsPlainText($document);
		return $this->normalizeTalkPlainText($plain);
	}

	private function renderTalkTemplateDocumentAsPlainText(\DOMDocument $document): string {
		$result = '';
		foreach ($document->childNodes as $node) {
			if ($node instanceof \DOMDocumentType || $node instanceof \DOMProcessingInstruction) {
				continue;
			}

			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'html') {
				$result .= $this->renderTalkTemplateHtmlElementAsPlainText($node);
				continue;
			}

			$result .= $this->renderTalkTemplateNodeAsPlainText($node);
		}

		return $result;
	}

	private function renderTalkTemplateHtmlElementAsPlainText(\DOMElement $htmlElement): string {
		$result = '';
		foreach ($htmlElement->childNodes as $node) {
			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'head') {
				continue;
			}

			if ($node instanceof \DOMElement && strtolower($node->tagName) === 'body') {
				$result .= $this->renderTalkTemplateNodesAsPlainText($node->childNodes);
				continue;
			}

			$result .= $this->renderTalkTemplateNodeAsPlainText($node);
		}

		return $result;
	}

	private function fallbackTalkTemplateHtmlToPlainText(string $template): string {
		$withPreservedLinks = preg_replace_callback(
			'/<a\b[^>]*(?:href|data-mce-href)=(["\'])(.*?)\\1[^>]*>(.*?)<\/a>/is',
			function (array $matches): string {
				$href = html_entity_decode(trim((string)($matches[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$linkText = $this->normalizeTalkPlainText(strip_tags((string)($matches[3] ?? '')));
				if ($href === '') {
					return $linkText;
				}

				if ($linkText === '' || $linkText === $href) {
					return $href;
				}

				return sprintf('%s: %s', $linkText, $href);
			},
			$template
		) ?? $template;

		return $this->normalizeTalkPlainText(strip_tags($withPreservedLinks));
	}

	private function renderTalkTemplateNodesAsPlainText(\DOMNodeList $nodes): string {
		$result = '';
		foreach ($nodes as $node) {
			$result .= $this->renderTalkTemplateNodeAsPlainText($node);
		}
		return $result;
	}

	private function renderTalkTemplateNodeAsPlainText(\DOMNode $node): string {
		if ($node instanceof \DOMText) {
			return $node->nodeValue ?? '';
		}

		if ($node instanceof \DOMElement) {
			$tagName = strtolower($node->tagName);
			if ($tagName === 'br') {
				return "\n";
			}

			if ($tagName === 'a') {
				$linkText = $this->normalizeTalkPlainText($this->renderTalkTemplateNodesAsPlainText($node->childNodes));
				$href = trim((string)($node->getAttribute('href') ?: $node->getAttribute('data-mce-href')));
				if ($href === '') {
					return $linkText;
				}
				if ($linkText === '' || $linkText === $href) {
					return $href;
				}
				return sprintf('%s: %s', $linkText, $href);
			}

			$content = $this->renderTalkTemplateNodesAsPlainText($node->childNodes);
			if (in_array($tagName, ['p', 'div', 'section', 'article', 'header', 'footer', 'aside', 'blockquote', 'pre', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
				$content = $this->normalizeTalkPlainText($content);
				if ($content === '') {
					return '';
				}
				if ($tagName === 'li') {
					return '- ' . $content . "\n";
				}
				return $content . "\n\n";
			}

			return $content;
		}

		return '';
	}

	private function normalizeTalkPlainText(string $value): string {
		$normalized = str_replace(["\r\n", "\r"], "\n", $value);
		$normalized = preg_replace("/[ \t]+\n/u", "\n", $normalized) ?? $normalized;
		$normalized = preg_replace("/\n[ \t]+/u", "\n", $normalized) ?? $normalized;
		$normalized = preg_replace("/[ \t]{2,}/u", ' ', $normalized) ?? $normalized;
		$normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;
		return trim($normalized);
	}

	/**
	 * @param array<string, mixed>|null $templateAssetPreview
	 * @return array<string, string>
	 */
	private function normalizeTemplateAssetPreview(?array $templateAssetPreview): array {
		if (!is_array($templateAssetPreview)) {
			return [];
		}

		$normalized = [];
		foreach ($templateAssetPreview as $key => $value) {
			$key = (string)$key;
			if (!$this->isTemplateEditorSetting($key)) {
				continue;
			}

			$template = (string)$value;
			if ($key === 'share_html_block_template' || $key === 'share_password_template') {
				$template = $this->normalizeTemplateBranding($template);
			}

			$normalized[$key] = $template;
		}

		return $normalized;
	}

	/**
	 * @return array<string, string>
	 */
	private function buildTemplateAssetMap(string $contextKey, string $template): array {
		$sources = $this->extractExternalImageSources($template);
		$assets = [];
		foreach ($sources as $index => $source) {
			$localUrl = $this->cacheTemplateImage($contextKey, $index, $source);
			if ($localUrl !== null) {
				$assets[$source] = $localUrl;
			}
		}
		return $assets;
	}

	/**
	 * @return list<string>
	 */
	private function extractExternalImageSources(string $template): array {
		if (trim($template) === '') {
			return [];
		}

		$previousUseInternalErrors = libxml_use_internal_errors(true);
		$document = new \DOMDocument();
		$loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $template, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		libxml_use_internal_errors($previousUseInternalErrors);

		if ($loaded === false) {
			return [];
		}

		$sources = [];
		foreach ($document->getElementsByTagName('img') as $img) {
			$src = trim((string)$img->getAttribute('src'));
			if ($src === '' || !preg_match('#^https?://#i', $src)) {
				continue;
			}
			$sources[] = $src;
		}

		return array_values(array_unique($sources));
	}

	private function cacheTemplateImage(string $contextKey, int $index, string $source): ?string {
		try {
			$response = $this->clientService->newClient()->get($source, [
				'timeout' => 15,
				'headers' => [
					'Accept' => 'image/*,*/*;q=0.8',
				],
			]);
			$statusCode = $response->getStatusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$this->logError('Template image cache received non-success response', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'status_code' => $statusCode,
				]);
				return null;
			}

			$headers = $response->getHeaders();
			$contentTypeHeader = $headers['Content-Type'][0] ?? $headers['content-type'][0] ?? '';
			$contentType = strtolower(trim(explode(';', (string)$contentTypeHeader)[0]));
			$extension = $this->mapImageContentTypeToExtension($contentType);
			if ($extension === null) {
				$this->logError('Template image cache received unsupported content type', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
					'content_type' => $contentTypeHeader,
				]);
				return null;
			}

			$body = $response->getBody();
			if (is_resource($body)) {
				$body = stream_get_contents($body);
			}
			if (!is_string($body) || $body === '') {
				$this->logError('Template image cache received empty image body', [
					'app' => Application::APP_ID,
					'context_key' => $contextKey,
					'src' => $source,
				]);
				return null;
			}

			$runtimeDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'runtime';
			if (!is_dir($runtimeDir) && !mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
				$this->logError('Template image cache failed to create runtime directory', [
					'app' => Application::APP_ID,
					'runtime_dir' => $runtimeDir,
				]);
				return null;
			}

			$fileBaseName = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($contextKey)) . '_' . $index;
			foreach (glob($runtimeDir . DIRECTORY_SEPARATOR . $fileBaseName . '.*') ?: [] as $existingFile) {
				if (is_file($existingFile)) {
					if (!unlink($existingFile) && is_file($existingFile)) {
						$this->logger->warning('Template image cache could not delete stale runtime file', [
							'file_path' => $existingFile,
							'context_key' => $contextKey,
							'src' => $source,
						]);
					}
				}
			}

			$fileName = $fileBaseName . '.' . $extension;
			$filePath = $runtimeDir . DIRECTORY_SEPARATOR . $fileName;
			if (file_put_contents($filePath, $body) === false) {
				$this->logError('Template image cache failed to write runtime file', [
					'app' => Application::APP_ID,
					'file_path' => $filePath,
					'src' => $source,
				]);
				return null;
			}

			return $this->urlGenerator->imagePath(Application::APP_ID, 'runtime/' . $fileName)
				. '?v=' . substr(sha1($body), 0, 12);
		} catch (\Throwable $exception) {
			$this->logError('Template image cache failed', [
				'app' => Application::APP_ID,
				'context_key' => $contextKey,
				'src' => $source,
				'exception' => $exception,
			]);
			return null;
		}
	}

	private function mapImageContentTypeToExtension(string $contentType): ?string {
		return match ($contentType) {
			'image/png' => 'png',
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			default => null,
		};
	}

	private function logError(string $message, array $context = []): void {
		$this->logger->error($message, $context);
	}
}
