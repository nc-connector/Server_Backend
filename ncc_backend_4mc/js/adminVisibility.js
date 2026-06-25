/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const DEFAULT_CATEGORIES = ['share', 'talk', 'email_signature']

	function setTabVisibility(root, buttonAttr, panelAttr, name, visible) {
		const button = root.querySelector(`[${buttonAttr}="${name}"]`)
		const panel = root.querySelector(`[${panelAttr}="${name}"]`)
		if (button instanceof HTMLElement) {
			button.hidden = !visible
		}
		if (panel instanceof HTMLElement) {
			if (!visible) {
				panel.hidden = true
			} else if (button instanceof HTMLElement && button.classList.contains('active')) {
				panel.hidden = false
			}
		}
	}

	function setFirstVisibleTab(root, buttonAttr, setTab) {
		const button = Array.from(root.querySelectorAll(`[${buttonAttr}]`))
			.find((candidate) => candidate instanceof HTMLElement && !candidate.hidden)
		if (button instanceof HTMLElement) {
			setTab(root, button.getAttribute(buttonAttr))
		}
	}

	function applySettingRowVisibility(root, state, permissions) {
		const {
			canEditDefaultSetting,
			canEditGroupOverrideSetting,
			canEditUserOverrideSetting,
		} = permissions

		if (state.admin?.is_nextcloud_admin) {
			root.querySelectorAll('[data-default-setting-key], [data-user-setting-key], [data-group-setting-key]').forEach((row) => {
				if (row instanceof HTMLElement) {
					row.hidden = false
				}
			})
			return
		}
		root.querySelectorAll('[data-default-setting-key]').forEach((row) => {
			if (row instanceof HTMLElement) {
				row.hidden = !canEditDefaultSetting(state, row.dataset.defaultSettingKey || '')
			}
		})
		root.querySelectorAll('[data-user-setting-key]').forEach((row) => {
			if (row instanceof HTMLElement) {
				row.hidden = !canEditUserOverrideSetting(state, row.dataset.userSettingKey || '')
			}
		})
		root.querySelectorAll('[data-group-setting-key]').forEach((row) => {
			if (row instanceof HTMLElement) {
				row.hidden = !canEditGroupOverrideSetting(state, row.dataset.groupSettingKey || '')
			}
		})
	}

	function applyAdminUiVisibility(root, refs, state, options) {
		const {
			canReadAssignedSeatOverview,
			canUseAnyUserOverridePanel,
			hasAnyAdminPermission,
			permissionsForCategory,
			setDefaultsTab,
			setGroupOverrideTab,
			setGroupTab,
			setMainTab,
			setOverrideTab,
			userOverridePermissionsForCategory,
		} = options
		const isFullAdmin = Boolean(state.admin?.is_nextcloud_admin)
		setTabVisibility(root, 'data-main-tab-button', 'data-main-tab-panel', 'general', isFullAdmin)
		setTabVisibility(root, 'data-main-tab-button', 'data-main-tab-panel', 'advanced', isFullAdmin)
		if (!isFullAdmin) {
			setMainTab(root, 'group')
		}

		DEFAULT_CATEGORIES.forEach((category) => {
			setTabVisibility(
				root,
				'data-default-tab-button',
				'data-default-tab-panel',
				category,
				hasAnyAdminPermission(state, permissionsForCategory(category, ['policy', 'templates']))
			)
		})
		setTabVisibility(root, 'data-group-tab-button', 'data-group-tab-panel', 'defaults', DEFAULT_CATEGORIES.some((category) => hasAnyAdminPermission(state, permissionsForCategory(category, ['policy', 'templates']))))
		setTabVisibility(root, 'data-group-tab-button', 'data-group-tab-panel', 'seats', isFullAdmin)
		setTabVisibility(root, 'data-group-tab-button', 'data-group-tab-panel', 'assigned', canReadAssignedSeatOverview(state))
		setTabVisibility(root, 'data-group-tab-button', 'data-group-tab-panel', 'group-overrides', DEFAULT_CATEGORIES.some((category) => hasAnyAdminPermission(state, permissionsForCategory(category, ['group_overrides']))))
		setTabVisibility(root, 'data-group-tab-button', 'data-group-tab-panel', 'overrides', canUseAnyUserOverridePanel(state))
		if (refs.seatReportDownload instanceof HTMLButtonElement) {
			const reportRow = refs.seatReportDownload.closest('.nccb-row')
			if (reportRow instanceof HTMLElement) {
				reportRow.hidden = !isFullAdmin
			}
		}
		setFirstVisibleTab(root, 'data-group-tab-button', setGroupTab)

		DEFAULT_CATEGORIES.forEach((category) => {
			setTabVisibility(root, 'data-group-override-tab-button', 'data-group-override-tab-panel', category, hasAnyAdminPermission(state, permissionsForCategory(category, ['group_overrides'])))
			setTabVisibility(root, 'data-override-tab-button', 'data-override-tab-panel', category, hasAnyAdminPermission(state, userOverridePermissionsForCategory(category)))
		})
		setFirstVisibleTab(root, 'data-default-tab-button', setDefaultsTab)
		setFirstVisibleTab(root, 'data-group-override-tab-button', setGroupOverrideTab)
		setFirstVisibleTab(root, 'data-override-tab-button', setOverrideTab)
		applySettingRowVisibility(root, state, options)
	}

	window.NCCBackendAdminVisibility = {
		applyAdminUiVisibility,
		applySettingRowVisibility,
	}
})()
