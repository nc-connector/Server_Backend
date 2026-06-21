/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function renderUsers(tbody, users, helpers) {
		const { tr, escapeHtml } = helpers
		if (!users || users.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('No users found.'))}</td></tr>`
			return
		}
		tbody.innerHTML = users.map((user) => `
			<tr data-user-id="${escapeHtml(user.user_id)}">
				<td>${escapeHtml(user.user_id)}</td>
				<td>${escapeHtml(user.display_name || '—')}</td>
				<td><input class="nccb-seat-toggle" type="checkbox" ${user.has_seat ? 'checked' : ''}></td>
			</tr>
		`).join('')
	}

	function updatePager(refs, userPaging, count, tr) {
		const page = Math.floor(userPaging.offset / userPaging.limit) + 1
		const from = count > 0 ? userPaging.offset + 1 : 0
		const to = userPaging.offset + count
		refs.userPage.textContent = `${tr('Page')} ${page} (${from}-${to})`
		refs.userPrev.disabled = userPaging.offset <= 0
		refs.userNext.disabled = !userPaging.hasNext
	}

	function renderSeatUsage(refs, seatStatus, tr) {
		const seats = seatStatus || { total: 0, assigned: 0, active_assigned: 0, suspended_assigned: 0, free: 0, overlicensed: false, overlicensed_by: 0 }
		let text = `${tr('Seats available')}: ${seats.total} | ${tr('Active used')}: ${seats.active_assigned ?? seats.assigned} | ${tr('Paused')}: ${seats.suspended_assigned ?? 0} | ${tr('Free')}: ${seats.free}`
		if (seats.overlicensed) {
			text += ` | ${tr('Overlicensed by')}: ${seats.overlicensed_by}`
		}
		refs.seatUsage.textContent = text
		refs.assignedSeatUsage.textContent = text
	}

	window.NCCBackendSeatUi = {
		renderSeatUsage,
		renderUsers,
		updatePager,
	}
})()
