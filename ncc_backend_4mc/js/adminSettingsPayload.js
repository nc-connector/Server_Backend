/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function createPayloadHelpers(context) {
		const {
			root,
			state,
			settingLayerUi,
			canEditDefaultSetting,
			canEditGroupOverrideSetting,
			canEditUserOverrideSetting,
			getModeControlKey,
			isTemplateEditorSettingKey,
			isUserOverrideOnlySettingKey,
			readSettingControl,
			sortedSettingKeys,
		} = context

		const isAttachmentThresholdEnabled = (prefix) => {
			const alwaysViaConnector = root.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_always_via_ncconnector"]`)
			if (alwaysViaConnector?.checked) {
				return false
			}
			const toggle = root.querySelector(`.nccb-threshold-enabled[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
			return toggle ? Boolean(toggle.checked) : true
		}

		const readLayerValue = (prefix, key) => key === 'attachments_min_size_mb'
			&& !isAttachmentThresholdEnabled(prefix)
			? null
			: readSettingControl(root, prefix, key, state.schema[key])

		const collectSettingLayerPayload = (config, options = {}) => {
			const payload = {}
			sortedSettingKeys(state.schema).forEach((key) => {
				if (options.skipUserOverrideOnly && isUserOverrideOnlySettingKey(key)) {
					return
				}
				if (!options.canEditSetting?.(state, key)) {
					return
				}
				if (options.defaultLayer) {
					const addonToggle = root.querySelector(`.nccb-addon-changeable[data-setting-key="${key}"]`)
					const isAddonChangeable = !isTemplateEditorSettingKey(key) && Boolean(addonToggle?.checked)
					payload[key] = {
						mode: isAddonChangeable ? 'user_choice' : 'default',
						value: readLayerValue(config.prefix, key),
					}
					return
				}
				const modeKey = getModeControlKey(key)
				const mode = root.querySelector(`${config.modeSelector}[data-setting-key="${modeKey}"]`)?.value || 'inherit'
				if (mode !== 'forced') {
					payload[key] = { mode: 'inherit' }
					return
				}
				payload[key] = {
					mode: 'forced',
					value: readLayerValue(config.prefix, key),
				}
			})
			return payload
		}

		const collectDefaultPayload = () => collectSettingLayerPayload(settingLayerUi.defaults, {
			skipUserOverrideOnly: true,
			canEditSetting: canEditDefaultSetting,
			defaultLayer: true,
		})

		const collectOverridePayload = () => collectSettingLayerPayload(settingLayerUi.userOverride, {
			canEditSetting: canEditUserOverrideSetting,
		})

		const collectGroupOverridePayload = () => collectSettingLayerPayload(settingLayerUi.groupOverride, {
			skipUserOverrideOnly: true,
			canEditSetting: canEditGroupOverrideSetting,
		})

		return {
			collectDefaultPayload,
			collectGroupOverridePayload,
			collectOverridePayload,
		}
	}

	window.NCCBackendSettingsPayload = {
		createPayloadHelpers,
	}
})()
