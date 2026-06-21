/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function fillGroupOverrideGroups(refs, groups, helpers) {
		const { tr, escapeHtml } = helpers
		const previousValue = refs.groupOverrideGroup.value
		refs.groupOverrideGroup.innerHTML = `<option value="">${escapeHtml(tr('Please select a group'))}</option>`
		const sorted = [...groups].sort((a, b) => (a.display_name || a.group_id).localeCompare(b.display_name || b.group_id))
		sorted.forEach((group) => {
			const option = document.createElement('option')
			option.value = group.group_id
			option.textContent = group.display_name ? `${group.display_name} (${group.group_id})` : group.group_id
			refs.groupOverrideGroup.appendChild(option)
		})
		const retained = Boolean(previousValue) && sorted.some((group) => group.group_id === previousValue)
		refs.groupOverrideGroup.value = retained ? previousValue : ''
		return {
			retained,
			selectedGroupId: refs.groupOverrideGroup.value,
		}
	}

	function fillOverrideUsers(refs, assignedSeats, helpers) {
		const { tr, escapeHtml } = helpers
		const previousValue = refs.overrideUser.value
		refs.overrideUser.innerHTML = `<option value="">${escapeHtml(tr('Please select a seat user'))}</option>`
		const sorted = [...assignedSeats].sort((a, b) => (a.display_name || a.user_id).localeCompare(b.display_name || b.user_id))
		sorted.forEach((seat) => {
			const option = document.createElement('option')
			option.value = seat.user_id
			option.textContent = seat.display_name ? `${seat.display_name} (${seat.user_id})` : seat.user_id
			refs.overrideUser.appendChild(option)
		})
		const retained = Boolean(previousValue) && sorted.some((seat) => seat.user_id === previousValue)
		refs.overrideUser.value = retained ? previousValue : ''
		return {
			retained,
			selectedUserId: refs.overrideUser.value,
		}
	}

	function renderForcedModeSelect(config, key, mode, helpers, extraClass = '') {
		const { tr, escapeHtml } = helpers
		const className = extraClass ? `${config.modeClass} ${extraClass}` : config.modeClass
		return `
			<select class="${escapeHtml(className)}" data-setting-key="${escapeHtml(key)}">
				<option value="inherit" ${mode === 'inherit' ? 'selected' : ''}>${escapeHtml(tr('Inherit default'))}</option>
				<option value="forced" ${mode === 'forced' ? 'selected' : ''}>${escapeHtml(tr('Forced value'))}</option>
			</select>
		`
	}

	function renderForcedOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category, config, helpers, options = {}) {
		const {
			escapeHtml,
			getTemplateInactiveNote,
			isTemplateEditorSettingKey,
			isUserOverrideOnlySettingKey,
			renderSettingControl,
			renderSettingHelp,
			renderTalkTemplateFormatControl,
			settingCategory,
			settingLabel,
			shouldRenderStandaloneSettingRow,
			sortedSettingKeys,
			talkInvitationTemplateFormatKey,
			talkInvitationTemplateKey,
			tr,
		} = helpers
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category
			&& shouldRenderStandaloneSettingRow(key)
			&& (!options.skipUserOverrideOnly || !isUserOverrideOnlySettingKey(key)))
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const item = items?.[key] || { mode: 'inherit', value: null, effective_value: definition.default, source: 'default', default_mode: 'default' }
			const mode = item.mode === 'forced' ? 'forced' : 'inherit'
			const currentValue = mode === 'forced' ? item.value : item.effective_value
			if (isTemplateEditorSettingKey(key)) {
				const talkTemplateFormat = key === talkInvitationTemplateKey
					? renderTalkTemplateFormatControl(
						config.prefix,
						schema,
						items?.[talkInvitationTemplateFormatKey]?.mode === 'forced'
							? items?.[talkInvitationTemplateFormatKey]?.value
							: items?.[talkInvitationTemplateFormatKey]?.effective_value ?? schema?.[talkInvitationTemplateFormatKey]?.default,
						mode !== 'forced'
					)
					: ''
				return `
					<tr class="nccb-template-row" ${config.rowAttribute}="${escapeHtml(key)}">
						<td>
							<div class="nccb-key-cell">
								<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							<div class="nccb-template-row-head">
								<span class="nccb-template-row-head-label nccb-template-row-head-select-label">${escapeHtml(tr('Preset'))}</span>
								${renderForcedModeSelect(config, key, mode, helpers, 'nccb-template-row-mode')}
								${talkTemplateFormat}
								<span class="nccb-template-row-head-note" hidden>${escapeHtml(tr(getTemplateInactiveNote(key)))}</span>
							</div>
							${renderSettingControl(config.prefix, key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}
			return `
				<tr ${config.rowAttribute}="${escapeHtml(key)}">
					<td>
						<div class="nccb-key-cell">
							<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>${renderForcedModeSelect(config, key, mode, helpers)}</td>
					<td>${renderSettingControl(config.prefix, key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function renderOverridePlaceholder(tbody, helpers) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${helpers.escapeHtml(helpers.tr('Please select a seat user.'))}</td></tr>`
	}

	function renderGroupOverridePlaceholder(tbody, helpers) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${helpers.escapeHtml(helpers.tr('Please select a group.'))}</td></tr>`
	}

	function renderOverrideTables(root, refs, state, selectedUserId, context) {
		const { helpers, callbacks, settingLayerUi } = context
		const hasSelectedUser = Boolean(selectedUserId)
		refs.overrideSave.disabled = !hasSelectedUser
		callbacks.removeTemplateEditorsByPrefix('override')
		if (!hasSelectedUser) {
			renderOverridePlaceholder(refs.overrideTableShare, helpers)
			renderOverridePlaceholder(refs.overrideTableTalk, helpers)
			renderOverridePlaceholder(refs.overrideTableEmailSignature, helpers)
			return
		}
		renderForcedOverrideRows(refs.overrideTableShare, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'share', settingLayerUi.userOverride, helpers)
		renderForcedOverrideRows(refs.overrideTableTalk, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'talk', settingLayerUi.userOverride, helpers)
		renderForcedOverrideRows(refs.overrideTableEmailSignature, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'email_signature', settingLayerUi.userOverride, helpers)
		callbacks.syncOverrideControlState(root)
		callbacks.attachOverrideModeHandlers(root)
		callbacks.attachAttachmentDependencyHandlers(root)
		callbacks.attachSharePasswordDependencyHandlers(root)
		callbacks.attachTemplateLanguageDependencyHandlers(root, 'override')
		callbacks.attachEmailSignatureDependencyHandlers(root, 'override')
		callbacks.attachTemplateEditorHandlers(root)
	}

	function renderGroupOverrideTables(root, refs, state, selectedGroupId, context) {
		const { helpers, callbacks, settingLayerUi } = context
		const hasSelectedGroup = Boolean(selectedGroupId)
		refs.groupOverrideSave.disabled = !hasSelectedGroup
		refs.groupOverridePriority.disabled = !hasSelectedGroup
		callbacks.removeTemplateEditorsByPrefix('group-override')
		if (!hasSelectedGroup) {
			renderGroupOverridePlaceholder(refs.groupOverrideTableShare, helpers)
			renderGroupOverridePlaceholder(refs.groupOverrideTableTalk, helpers)
			renderGroupOverridePlaceholder(refs.groupOverrideTableEmailSignature, helpers)
			return
		}
		renderForcedOverrideRows(refs.groupOverrideTableShare, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'share', settingLayerUi.groupOverride, helpers, { skipUserOverrideOnly: true })
		renderForcedOverrideRows(refs.groupOverrideTableTalk, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'talk', settingLayerUi.groupOverride, helpers, { skipUserOverrideOnly: true })
		renderForcedOverrideRows(refs.groupOverrideTableEmailSignature, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'email_signature', settingLayerUi.groupOverride, helpers, { skipUserOverrideOnly: true })
		callbacks.syncGroupOverrideControlState(root)
		callbacks.attachGroupOverrideModeHandlers(root)
		callbacks.attachAttachmentDependencyHandlers(root, 'group-override')
		callbacks.attachSharePasswordDependencyHandlers(root)
		callbacks.attachTemplateLanguageDependencyHandlers(root, 'group-override')
		callbacks.attachEmailSignatureDependencyHandlers(root, 'group-override')
		callbacks.attachTemplateEditorHandlers(root)
	}

	window.NCCBackendOverridesUi = {
		fillGroupOverrideGroups,
		fillOverrideUsers,
		renderGroupOverrideTables,
		renderOverrideTables,
	}
})()
