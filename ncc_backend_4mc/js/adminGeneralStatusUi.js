/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function renderLicenseStatus(refs, snapshot, helpers) {
		const { tr, escapeHtml, formatDate, formatDateTime } = helpers
		if (refs.licenseHint instanceof HTMLElement) {
			refs.licenseHint.hidden = true
			refs.licenseHint.innerHTML = ''
		}
		if (!snapshot) {
			refs.licenseStatus.textContent = tr('No license data available.')
			return
		}
		if (snapshot.mode === 'community') {
			refs.licenseStatus.textContent = tr('Community mode active: 1 free seat, no license login required.')
			return
		}
		if (!snapshot.has_credentials) {
			refs.licenseStatus.textContent = tr('Pro mode active: Please provide license email and license key.')
			if (refs.licenseHint instanceof HTMLElement) {
				refs.licenseHint.hidden = false
				refs.licenseHint.innerHTML = `${escapeHtml(tr('Ready for productive team use? You can get your license key at'))} <a href="https://nc-connector.de" target="_blank" rel="noopener">nc-connector.de</a>`
			}
			return
		}
		const statusLabels = {
			ACTIVE: tr('Active'),
			GRACE: tr('Grace period'),
			EXPIRED: tr('Expired'),
			INACTIVE: tr('Inactive'),
			INVALID: tr('Invalid'),
			UNKNOWN: tr('Unknown'),
		}
		const status = statusLabels[String(snapshot.status_effective || 'UNKNOWN')] || tr('Unknown')
		refs.licenseStatus.textContent = [
			`${tr('License')}: ${status}`,
			`${tr('Valid until')}: ${formatDate(snapshot.expires_at)}`,
			`${tr('Grace until')}: ${formatDate(snapshot.grace_until)}`,
			`${tr('Seats')}: ${snapshot.purchased_seats} ${tr('purchased')}, ${snapshot.total_seats} ${tr('available')}`,
			`${tr('Last sync')}: ${formatDateTime(snapshot.last_sync_at)}`,
		].join(' | ')
	}

	function renderProFunnel(refs, snapshot, helpers) {
		const { tr, escapeHtml } = helpers
		if (!(refs.proFunnel instanceof HTMLElement)) {
			return
		}
		const mode = snapshot?.mode === 'pro' ? 'pro' : 'community'
		const status = String(snapshot?.status_effective || '').toUpperCase()
		const hasValidLicense = mode === 'pro'
			&& Boolean(snapshot?.has_credentials)
			&& (status === 'ACTIVE' || status === 'GRACE')

		refs.proFunnel.hidden = hasValidLicense
		if (hasValidLicense) {
			refs.proFunnel.innerHTML = ''
			return
		}

		const intro = mode === 'pro'
			? 'Pro is selected, but no valid license is active yet.'
			: 'NC Connector currently runs in Community mode with one free Seat.'
		refs.proFunnel.innerHTML = `
			<h3>${escapeHtml(tr('Activate Pro for teams'))}</h3>
			<p>${escapeHtml(tr(intro))}</p>
			<p>${escapeHtml(tr('For teams, central policies and more Seats, activate Pro.'))}</p>
			<div class="nccb-pro-funnel-actions">
				<a class="button primary" href="https://nc-connector.de/preise-lizenzierung/#pro-checkout" target="_blank" rel="noopener">${escapeHtml(tr('Buy Pro license'))}</a>
				<a class="button" href="mailto:info@nc-connector.de">${escapeHtml(tr('Request 30-day trial key'))}</a>
			</div>
			<p class="nccb-muted">${escapeHtml(tr('You can keep using Community mode for tests and single-user setups.'))}</p>
		`
	}

	function renderBackendUpdateStatus(refs, status, helpers) {
		const { tr, escapeHtml } = helpers
		if (!(refs.backendUpdateStatus instanceof HTMLElement)) {
			return
		}

		if (!status) {
			refs.backendUpdateStatus.textContent = tr('Update status unavailable.')
			return
		}

		const currentVersion = String(status.current_version || tr('Unknown'))
		const latestVersion = String(status.latest_version || tr('Unknown'))
		if (status.is_current) {
			refs.backendUpdateStatus.innerHTML = `
				<span class="nccb-update-status-ok" aria-hidden="true">&#10003;</span>
				${escapeHtml(tr('Backend version'))}:
				${escapeHtml(tr('Installed version'))} ${escapeHtml(currentVersion)} |
				${escapeHtml(tr('Available version'))} ${escapeHtml(latestVersion)} |
				${escapeHtml(tr('Current'))}
			`
			return
		}

		if (status.update_available) {
			refs.backendUpdateStatus.innerHTML = `
				${escapeHtml(tr('Backend version'))}:
				${escapeHtml(tr('Installed version'))} ${escapeHtml(currentVersion)} |
				${escapeHtml(tr('Available version'))} ${escapeHtml(latestVersion)} |
				${escapeHtml(tr('Update available'))}
			`
			return
		}

		refs.backendUpdateStatus.textContent = `${tr('Backend version')}: ${tr('Installed version')} ${currentVersion} | ${tr('Available version')} ${latestVersion} | ${tr('Update status unavailable.')}`
	}

	function renderRecommendedApps(refs, apps, helpers) {
		const { tr, escapeHtml, renderInlineHelp } = helpers
		if (!(refs.recommendedApps instanceof HTMLElement)) {
			return
		}
		const items = Array.isArray(apps) ? apps : []
		if (items.length === 0) {
			refs.recommendedApps.innerHTML = `<div class="nccb-muted">${escapeHtml(tr('No recommended apps found.'))}</div>`
			return
		}

		refs.recommendedApps.innerHTML = items.map((app) => {
			const enabled = Boolean(app?.enabled)
			const statusText = enabled ? 'Installed and active' : 'Not installed or disabled'
			const statusClass = enabled ? 'nccb-recommended-app-status--ok' : 'nccb-recommended-app-status--missing'
			const statusIcon = enabled ? '&#10003;' : '&#10005;'
			const purpose = String(app?.purpose || '')
			return `
				<div class="nccb-recommended-app">
					<span class="nccb-recommended-app-status ${statusClass}" aria-hidden="true">${statusIcon}</span>
					<div>
						<div class="nccb-recommended-app-name">
							${escapeHtml(String(app?.name || app?.id || ''))}
							${purpose ? renderInlineHelp(String(app?.name || app?.id || ''), [purpose]) : ''}
						</div>
						<div class="nccb-muted">${escapeHtml(tr(statusText))}</div>
					</div>
				</div>
			`
		}).join('')
	}

	window.NCCBackendGeneralStatusUi = {
		renderBackendUpdateStatus,
		renderLicenseStatus,
		renderProFunnel,
		renderRecommendedApps,
	}
})()
