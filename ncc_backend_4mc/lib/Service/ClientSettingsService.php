<?php

/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

declare(strict_types=1);

namespace OCA\NcConnector\Service;

use OCA\NcConnector\Db\ClientOverride;
use OCA\NcConnector\Db\ClientOverrideMapper;
use OCA\NcConnector\Db\GroupOverride;
use OCA\NcConnector\Db\GroupOverrideMapper;
use OCA\NcConnector\Db\SettingMapper;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUserManager;

class ClientSettingsService {
	private const DEFAULT_KEY_PREFIX = 'client.default.';
	private const DEFAULT_MODE_KEY_PREFIX = 'client.default_mode.';
	private const MODE_INHERIT = 'inherit';
	private const MODE_FORCED = 'forced';
	private const MODE_USER_CHOICE = 'user_choice';
	private const MODE_DEFAULT = 'default';
	private const GROUP_OVERRIDE_PRIORITY_DEFAULT = 100;

	public function __construct(
		private ClientSettingsDefinitionService $settingDefinitions,
		private SettingMapper $settings,
		private ClientOverrideMapper $overrides,
		private GroupOverrideMapper $groupOverrides,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private TemplateAssetService $templateAssets,
		private ClientPolicyRuntimeService $runtimePolicy,
	) {
	}

	public function getSchema(): array {
		$result = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if (!$this->settingDefinitions->isAddonControllableSetting($key)) {
				$definition['addon_editable_supported'] = false;
			}
			$result[$key] = $definition;
		}
		return $this->runtimePolicy->applySchemaAvailability($result);
	}

	public function getDefaults(): array {
		$defaults = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
				continue;
			}
			$stored = $this->settings->getValue(self::DEFAULT_KEY_PREFIX . $key, null);
			if ($stored === null) {
				$defaults[$key] = $definition['default'];
				continue;
			}
			$defaults[$key] = $this->settingDefinitions->parseStoredValue($key, $stored);
		}
		return $defaults;
	}

	/**
	 * @return list<array{id:string, name:string, enabled:bool, purpose:string}>
	 */
	public function getRecommendedApps(): array {
		return $this->runtimePolicy->getRecommendedApps();
	}

	/**
	 * @param array<string, mixed>|null $defaults
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForDefaults(?array $defaults = null, ?array $templateAssetPreview = null): array {
		return $this->getEditorTemplateAssetDataForDefaults($defaults, $templateAssetPreview)['assets'];
	}

	/**
	 * @param array<string, mixed>|null $defaults
	 * @return array{assets:array<string, array<string, string>>, warnings:array<string, list<array<string, mixed>>>}
	 */
	public function getEditorTemplateAssetDataForDefaults(?array $defaults = null, ?array $templateAssetPreview = null): array {
		$defaults ??= $this->getDefaults();
		$templateAssetPreview = $this->settingDefinitions->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		$warnings = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if (!$this->settingDefinitions->isTemplateEditorSetting($key)) {
				continue;
			}
			$value = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (string)($defaults[$key] ?? $definition['default'] ?? '');
			$result = $this->templateAssets->buildAssetResult('default-' . $key, $value);
			$assets[$key] = $result['assets'];
			$warnings[$key] = $result['warnings'];
		}
		return [
			'assets' => $assets,
			'warnings' => $warnings,
		];
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForSchemaDefaults(): array {
		return $this->getEditorTemplateAssetDataForSchemaDefaults()['assets'];
	}

	/**
	 * @return array{assets:array<string, array<string, string>>, warnings:array<string, list<array<string, mixed>>>}
	 */
	public function getEditorTemplateAssetDataForSchemaDefaults(): array {
		$assets = [];
		$warnings = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if (!$this->settingDefinitions->isTemplateEditorSetting($key)) {
				continue;
			}
			$result = $this->templateAssets->buildAssetResult('schema-' . $key, (string)($definition['default'] ?? ''));
			$assets[$key] = $result['assets'];
			$warnings[$key] = $result['warnings'];
		}
		return [
			'assets' => $assets,
			'warnings' => $warnings,
		];
	}

	public function getDefaultModes(): array {
		$modes = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
				continue;
			}
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
			$this->assertDefaultSetting($key);
			$mode = $this->getBuiltInDefaultMode($key);
			$valueToStore = $rawValue;

			if (is_array($rawValue) && array_key_exists('mode', $rawValue)) {
				$mode = $this->normalizeDefaultMode($key, (string)($rawValue['mode'] ?? self::MODE_DEFAULT));
				$this->settings->setValue(self::DEFAULT_MODE_KEY_PREFIX . $key, $mode, $now);

				if ($mode === self::MODE_USER_CHOICE) {
					if (array_key_exists('value', $rawValue)) {
						$normalized = $this->settingDefinitions->normalizeValue($key, $rawValue['value']);
						$this->settings->setValue(
							self::DEFAULT_KEY_PREFIX . $key,
							$this->settingDefinitions->serializeValue($key, $normalized),
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

			$normalized = $this->settingDefinitions->normalizeValue($key, $valueToStore);
			$this->settings->setValue(
				self::DEFAULT_KEY_PREFIX . $key,
				$this->settingDefinitions->serializeValue($key, $normalized),
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

		foreach ($this->settingDefinitions->all() as $key => $definition) {
			$defaultMode = $defaultModes[$key] ?? self::MODE_DEFAULT;
			$override = $overrideMap[$key] ?? null;
			if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
				if ($override instanceof ClientOverride && $override->getMode() === self::MODE_FORCED) {
					$forcedValue = $this->settingDefinitions->parseStoredValue($key, (string)$override->getSettingValue());
					$items[$key] = [
						'mode' => self::MODE_FORCED,
						'value' => $forcedValue,
						'effective_value' => $forcedValue,
						'source' => 'user',
						'default_mode' => self::MODE_DEFAULT,
					];
					continue;
				}

				$items[$key] = [
					'mode' => self::MODE_INHERIT,
					'value' => null,
					'effective_value' => $this->getUserOnlyFallbackValue($key, $userId),
					'source' => 'default',
					'default_mode' => self::MODE_DEFAULT,
				];
				continue;
			}

			if ($override instanceof ClientOverride && $override->getMode() === self::MODE_FORCED) {
				$forcedValue = $this->settingDefinitions->parseStoredValue($key, (string)$override->getSettingValue());
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
				$forcedValue = $this->settingDefinitions->parseStoredValue($key, (string)$groupOverride->getSettingValue());
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

		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
				continue;
			}
			$defaultMode = $defaultModes[$key] ?? self::MODE_DEFAULT;
			$override = $overrideMap[$key] ?? null;
			if ($override instanceof GroupOverride && $override->getMode() === self::MODE_FORCED) {
				$forcedValue = $this->settingDefinitions->parseStoredValue($key, (string)$override->getSettingValue());
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
		return $this->getEditorTemplateAssetDataForUser($userId, $items, $templateAssetPreview)['assets'];
	}

	/**
	 * @param array<string, array<string, mixed>>|null $items
	 * @return array{assets:array<string, array<string, string>>, warnings:array<string, list<array<string, mixed>>>}
	 */
	public function getEditorTemplateAssetDataForUser(string $userId, ?array $items = null, ?array $templateAssetPreview = null): array {
		$items ??= $this->getUserSettings($userId);
		$templateAssetPreview = $this->settingDefinitions->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		$warnings = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if (!$this->settingDefinitions->isTemplateEditorSetting($key)) {
				continue;
			}
			$item = $items[$key] ?? null;
			if (!is_array($item)) {
				$assets[$key] = [];
				$warnings[$key] = [];
				continue;
			}
			$currentValue = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (($item['mode'] ?? 'inherit') === self::MODE_FORCED
					? (string)($item['value'] ?? '')
					: (string)($item['effective_value'] ?? $definition['default'] ?? ''));
			$result = $this->templateAssets->buildAssetResult('user-' . $userId . '-' . $key, $currentValue);
			$assets[$key] = $result['assets'];
			$warnings[$key] = $result['warnings'];
		}
		return [
			'assets' => $assets,
			'warnings' => $warnings,
		];
	}

	/**
	 * @param array<string, array<string, mixed>>|null $items
	 * @return array<string, array<string, string>>
	 */
	public function getEditorTemplateAssetsForGroup(string $groupId, ?array $items = null, ?array $templateAssetPreview = null): array {
		return $this->getEditorTemplateAssetDataForGroup($groupId, $items, $templateAssetPreview)['assets'];
	}

	/**
	 * @param array<string, array<string, mixed>>|null $items
	 * @return array{assets:array<string, array<string, string>>, warnings:array<string, list<array<string, mixed>>>}
	 */
	public function getEditorTemplateAssetDataForGroup(string $groupId, ?array $items = null, ?array $templateAssetPreview = null): array {
		if ($items === null) {
			$groupSettings = $this->getGroupSettings($groupId);
			$items = is_array($groupSettings['items'] ?? null) ? $groupSettings['items'] : [];
		}
		$templateAssetPreview = $this->settingDefinitions->normalizeTemplateAssetPreview($templateAssetPreview);
		$assets = [];
		$warnings = [];
		foreach ($this->settingDefinitions->all() as $key => $definition) {
			if (!$this->settingDefinitions->isTemplateEditorSetting($key)) {
				continue;
			}
			$item = $items[$key] ?? null;
			if (!is_array($item)) {
				$assets[$key] = [];
				$warnings[$key] = [];
				continue;
			}
			$currentValue = array_key_exists($key, $templateAssetPreview)
				? $templateAssetPreview[$key]
				: (($item['mode'] ?? 'inherit') === self::MODE_FORCED
					? (string)($item['value'] ?? '')
					: (string)($item['effective_value'] ?? $definition['default'] ?? ''));
			$result = $this->templateAssets->buildAssetResult('group-' . $groupId . '-' . $key, $currentValue);
			$assets[$key] = $result['assets'];
			$warnings[$key] = $result['warnings'];
		}
		return [
			'assets' => $assets,
			'warnings' => $warnings,
		];
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

			$normalized = $this->settingDefinitions->normalizeValue($key, $payload['value']);
			$this->overrides->upsert(
				$userId,
				$key,
				self::MODE_FORCED,
				$this->settingDefinitions->serializeValue($key, $normalized),
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
			$this->assertGroupOverrideSetting($key);
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

			$normalized = $this->settingDefinitions->normalizeValue($key, $payload['value']);
			$this->groupOverrides->upsert(
				$groupId,
				$priority,
				$key,
				self::MODE_FORCED,
				$this->settingDefinitions->serializeValue($key, $normalized),
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
		$this->runtimePolicy->applyForUser($settings, $sources, $policies, $addonEditable, $userId);

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
				foreach ($overrideMap as $settingKey => $override) {
					if ($this->settingDefinitions->isUserOverrideOnlySetting((string)$settingKey)) {
						continue;
					}
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
		foreach ($this->settingDefinitions->all() as $key => $_definition) {
			if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
				continue;
			}
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
		if (!$this->settingDefinitions->has($key)) {
			throw new \InvalidArgumentException(sprintf('Unknown setting key "%s"', $key));
		}
	}

	private function assertDefaultSetting(string $key): void {
		if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
			throw new \InvalidArgumentException(sprintf('Setting "%s" is only available as a user override', $key));
		}
	}

	private function assertGroupOverrideSetting(string $key): void {
		if ($this->settingDefinitions->isUserOverrideOnlySetting($key)) {
			throw new \InvalidArgumentException(sprintf('Setting "%s" is only available as a user override', $key));
		}
	}

	public function getEmailSignatureUserEmail(string $userId): string {
		return $this->runtimePolicy->getEmailSignatureUserEmail($userId);
	}

	private function getUserOnlyFallbackValue(string $key, string $userId): string {
		return $this->runtimePolicy->getUserOnlyFallbackValue($key, $userId);
	}

	private function normalizeDefaultMode(string $key, string $mode): string {
		if (!$this->settingDefinitions->isAddonControllableSetting($key)) {
			return self::MODE_DEFAULT;
		}
		$normalized = strtolower(trim($mode));
		if ($normalized === self::MODE_USER_CHOICE) {
			return self::MODE_USER_CHOICE;
		}
		return self::MODE_DEFAULT;
	}

	private function getBuiltInDefaultMode(string $key): string {
		if (!$this->settingDefinitions->isAddonControllableSetting($key)) {
			return self::MODE_DEFAULT;
		}

		return self::MODE_USER_CHOICE;
	}

}
