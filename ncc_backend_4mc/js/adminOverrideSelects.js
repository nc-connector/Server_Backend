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

	window.NCCBackendOverrideSelects = {
		fillGroupOverrideGroups,
		fillOverrideUsers,
	}
})()
