/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const TAB_SCOPES = {
		main: ['data-main-tab-button', 'data-main-tab-panel'],
		group: ['data-group-tab-button', 'data-group-tab-panel'],
		defaults: ['data-default-tab-button', 'data-default-tab-panel'],
		override: ['data-override-tab-button', 'data-override-tab-panel'],
		groupOverride: ['data-group-override-tab-button', 'data-group-override-tab-panel'],
		advanced: ['data-advanced-tab-button', 'data-advanced-tab-panel'],
	}

	function setTab(root, scope, name) {
		const config = TAB_SCOPES[scope]
		if (!root || !config) {
			return
		}
		const [buttonAttribute, panelAttribute] = config
		root.querySelectorAll(`[${buttonAttribute}]`).forEach((button) => {
			button.classList.toggle('active', button.getAttribute(buttonAttribute) === name)
		})
		root.querySelectorAll(`[${panelAttribute}]`).forEach((panel) => {
			panel.hidden = panel.getAttribute(panelAttribute) !== name
		})
	}

	window.NCCBackendAdminTabs = {
		setTab,
	}
})()
