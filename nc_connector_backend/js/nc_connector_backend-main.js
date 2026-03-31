/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const APP_ID = 'nc_connector_backend'
	const tr = (text, vars = []) => {
		if (typeof t === 'function') {
			return t(APP_ID, text, vars)
		}
		return text
	}

	/**
	 * Performs a GET request against the app API and returns parsed JSON.
	 *
	 * @param {string} path
	 * @returns {Promise<any>}
	 */
	async function apiRequest(path) {
		const url = OC.generateUrl('/apps/nc_connector_backend' + path)
		const res = await fetch(url, {
			method: 'GET',
			headers: { Accept: 'application/json' },
		})

		let data = null
		try {
			data = await res.json()
		} catch (error) {
			console.error('nccb status api response parse failed', path, error)
		}

		if (!res.ok) {
			throw new Error(data?.error || `HTTP ${res.status}`)
		}

		return data
	}

	/**
	 * Renders a compact status snapshot for end users.
	 *
	 * @param {HTMLElement} root
	 * @param {any} status
	 * @returns {void}
	 */
	function renderStatus(root, status) {
		const statusBlock = status?.status || {}
		const policyBlock = status?.policy || {}
		const shareCount = Object.keys(policyBlock.share || {}).length
		const talkCount = Object.keys(policyBlock.talk || {}).length
		const seatStateMap = {
			none: tr('Not assigned'),
			active: tr('Active'),
			suspended_overlimit: tr('Paused (overlicensed)'),
		}
		const seatStateLabel = seatStateMap[String(statusBlock.seat_state || 'none')] || String(statusBlock.seat_state || tr('Unknown'))
		root.innerHTML = `
			<h2>NC Connector</h2>
			<div class="nccb-section">
				<div class="nccb-muted">${tr('User ID')}: ${statusBlock.user_id || '—'}</div>
				<div class="nccb-muted">${tr('Seat assigned')}: ${statusBlock.seat_assigned ? tr('Yes') : tr('No')}</div>
				<div class="nccb-muted">${tr('Seat state')}: ${seatStateLabel}</div>
				<div class="nccb-muted">${tr('Overlicensed')}: ${statusBlock.overlicensed ? tr('Yes') : tr('No')}</div>
				<div class="nccb-muted">${tr('Policy share settings')}: ${shareCount}</div>
				<div class="nccb-muted">${tr('Policy talk settings')}: ${talkCount}</div>
			</div>
		`
	}

	/**
	 * Initializes the user page and loads `/api/v1/status`.
	 *
	 * @returns {Promise<void>}
	 */
	async function main() {
		const root = document.getElementById('nccb-app')
		if (!root) return

		root.innerHTML = `<h2>NC Connector</h2><p class="nccb-muted">${tr('Loading...')}</p>`

		try {
			const status = await apiRequest('/api/v1/status')
			renderStatus(root, status)
		} catch (e) {
			console.error('nccb status page init failed', e)
			root.innerHTML = `<h2>NC Connector</h2><p class="nccb-error">${e.message || tr('Error')}</p>`
		}
	}

	void main()
})()
