/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function createTemplateAssetRefreshHandler(options = {}) {
		const {
			api,
			extractExternalImageSourcesFromHtml,
			mergeSchema,
			readSettingControl,
			refs,
			root,
			showTemplateAssetWarnings,
			state,
			templateEditor,
		} = options

		return async (wrapper) => {
			if (!(wrapper instanceof HTMLElement)) {
				return
			}

			const control = templateEditor.getControl(wrapper)
			const settingKey = String(wrapper.dataset.settingKey || '')
			const prefix = String(wrapper.dataset.prefix || '')
			if (!control || !settingKey || !state.schema[settingKey]) {
				return
			}
			if (wrapper.dataset.templateAssetSync === '1') {
				return
			}

			const isModalDraft = templateEditor.isModalDraft(wrapper)
			const refreshValue = templateEditor.getRefreshValue(wrapper, control)
			const externalSources = extractExternalImageSourcesFromHtml(refreshValue)
			if (externalSources.length === 0) {
				return
			}

			const currentAssetMap = templateEditor.getEffectiveAssetMap(wrapper)
			const hasMissingAssets = externalSources.some((source) => !currentAssetMap[source])
			if (!hasMissingAssets) {
				return
			}

			wrapper.dataset.templateAssetSync = '1'
			try {
				let response = null
				if (prefix === 'default') {
					response = isModalDraft
						? await api.previewDefaultTemplateAssets(settingKey, refreshValue)
						: await api.saveDefaults({
							[settingKey]: {
								mode: 'default',
								value: readSettingControl(root, 'default', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						state.defaults = response.defaults || state.defaults
						state.defaultModes = response.default_modes || state.defaultModes
						state.defaultTemplateAssets = response.template_assets || state.defaultTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						state.defaultTemplateAssetWarnings = response.template_asset_warnings || state.defaultTemplateAssetWarnings
						state.schemaTemplateAssetWarnings = response.schema_template_asset_warnings || state.schemaTemplateAssetWarnings
						control.value = String(state.defaults?.[settingKey] ?? control.value)
						templateEditor.setAssetMap(wrapper, state.defaultTemplateAssets?.[settingKey] || {})
						templateEditor.setDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditor.setModalAssetMap(wrapper, response.template_assets?.[settingKey] || {})
					}
				} else if (prefix === 'override' && refs.overrideUser.value) {
					const modeSelect = root.querySelector(`.nccb-user-mode[data-setting-key="${settingKey}"]`)
					if (!(modeSelect instanceof HTMLSelectElement) || modeSelect.value !== 'forced') {
						return
					}
					response = isModalDraft
						? await api.previewUserTemplateAssets(refs.overrideUser.value, settingKey, refreshValue)
						: await api.saveUserOverrides(refs.overrideUser.value, {
							[settingKey]: {
								mode: 'forced',
								value: readSettingControl(root, 'override', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						mergeSchema(state, response.schema)
						state.overrides = response.items || state.overrides
						state.overrideTemplateAssets = response.template_assets || state.overrideTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						state.overrideTemplateAssetWarnings = response.template_asset_warnings || state.overrideTemplateAssetWarnings
						state.schemaTemplateAssetWarnings = response.schema_template_asset_warnings || state.schemaTemplateAssetWarnings
						const updatedItem = state.overrides?.[settingKey]
						if (updatedItem?.mode === 'forced') {
							control.value = String(updatedItem.value ?? control.value)
						}
						templateEditor.setAssetMap(wrapper, state.overrideTemplateAssets?.[settingKey] || {})
						templateEditor.setDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditor.setModalAssetMap(wrapper, response.template_assets?.[settingKey] || {})
					}
				} else if (prefix === 'group-override' && refs.groupOverrideGroup.value) {
					const modeSelect = root.querySelector(`.nccb-group-mode[data-setting-key="${settingKey}"]`)
					if (!(modeSelect instanceof HTMLSelectElement) || modeSelect.value !== 'forced') {
						return
					}
					const priority = Number.parseInt(String(refs.groupOverridePriority.value || state.groupOverridePriority || 100), 10) || 100
					response = isModalDraft
						? await api.previewGroupTemplateAssets(refs.groupOverrideGroup.value, priority, settingKey, refreshValue)
						: await api.saveGroupOverrides(refs.groupOverrideGroup.value, priority, {
							[settingKey]: {
								mode: 'forced',
								value: readSettingControl(root, 'group-override', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						mergeSchema(state, response.schema)
						state.groupOverridePriority = Number.parseInt(String(response.priority ?? priority), 10) || priority
						refs.groupOverridePriority.value = String(state.groupOverridePriority)
						state.groupOverrides = response.items || state.groupOverrides
						state.groupOverrideTemplateAssets = response.template_assets || state.groupOverrideTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						state.groupOverrideTemplateAssetWarnings = response.template_asset_warnings || state.groupOverrideTemplateAssetWarnings
						state.schemaTemplateAssetWarnings = response.schema_template_asset_warnings || state.schemaTemplateAssetWarnings
						const updatedItem = state.groupOverrides?.[settingKey]
						if (updatedItem?.mode === 'forced') {
							control.value = String(updatedItem.value ?? control.value)
						}
						templateEditor.setAssetMap(wrapper, state.groupOverrideTemplateAssets?.[settingKey] || {})
						templateEditor.setDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditor.setModalAssetMap(wrapper, response.template_assets?.[settingKey] || {})
					}
				} else {
					return
				}

				const editor = templateEditor.getActiveEditor(wrapper)
				if (editor) {
					editor.setContent(templateEditor.toEditorHtml(refreshValue, templateEditor.getEffectiveAssetMap(wrapper)))
				}
				showTemplateAssetWarnings(prefix, response, settingKey)
			} catch (error) {
				console.error('nccb template asset refresh failed', settingKey, error)
			} finally {
				delete wrapper.dataset.templateAssetSync
			}
		}
	}

	window.NCCBackendTemplateAssetRefresh = {
		createTemplateAssetRefreshHandler,
	}
})()
