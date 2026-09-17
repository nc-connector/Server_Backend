/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function renderLicenseStatus(refs, snapshot, helpers) {
		const { tr, escapeHtml, formatDate, formatDateTime, renderInlineHelp } = helpers
		const notice = (message, body = '', error = false) => ({
			error,
			html: '<div class="nccb-license-warning' + (error ? ' nccb-license-warning--error' : '')
				+ '" role="' + (error ? 'alert' : 'status') + '"><strong>' + escapeHtml(message) + '</strong>' + body + '</div>',
		})
		if (refs.licenseHint instanceof HTMLElement) {
			refs.licenseHint.hidden = true
			refs.licenseHint.innerHTML = ''
		}
		if (!snapshot) {
			refs.licenseStatus.innerHTML = notice(tr('No license data available.')).html
			return
		}
		if (snapshot.mode === 'community') {
			refs.licenseStatus.textContent = tr('Community mode active: 1 free seat, no license login required.')
			return
		}
		if (!snapshot.has_credentials) {
			refs.licenseStatus.innerHTML = notice(tr('Pro mode active: Please provide license email and license key.')).html
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
		const commercial = String(snapshot.license_status_effective || snapshot.status_effective || 'UNKNOWN')
		const effective = String(snapshot.status_effective || commercial)
		const status = statusLabels[commercial] || tr('Unknown')
		const row = (label, value, help = '') => '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(String(value)) + help + '</dd></div>'
		const graceLabel = commercial === 'EXPIRED' ? tr('Grace until') : tr('Grace period ends on')
		const rows = [
			row(tr('License'), status),
			row(tr('Valid until'), formatDate(snapshot.expires_at)),
			row(tr('License capacity'), snapshot.purchased_seats ?? 0),
			row(tr('Assigned seats'), helpers.assignedSeats ?? '—'),
			row(tr('Last successful sync'), formatDateTime(snapshot.last_sync_at)),
		]
		if (snapshot.grace_until && (commercial === 'GRACE' || commercial === 'EXPIRED')) {
			rows.splice(2, 0, row(graceLabel, formatDate(snapshot.grace_until)))
		}
		const notices = []
		if (commercial === 'GRACE') {
			const deadline = snapshot.grace_until
				? '<p>' + escapeHtml(tr('Grace period ends on') + ': ' + formatDate(snapshot.grace_until)) + '</p>' : ''
			notices.push(notice(tr('Your license has expired. Please renew your license.'), deadline))
		} else if (commercial === 'EXPIRED') {
			notices.push(notice(tr('Your license has expired. Pro features are not available.'), '', true))
		} else if (commercial === 'INACTIVE' || commercial === 'INVALID') {
			notices.push(notice(tr('License') + ': ' + status, '', true))
		} else if (commercial !== 'ACTIVE') {
			notices.push(notice(tr('Pro is selected, but no valid license is active yet.')))
		}
		const availability = '<p>' + escapeHtml(snapshot.is_valid ? tr('Pro features are available.') : tr('Pro features are not available. Basic features remain available.')) + '</p>'
		let details = ''
		if (!snapshot.is_valid && commercial !== 'ACTIVE' && commercial !== 'GRACE') {
			details += '<p>' + escapeHtml(tr('Seat assignments remain stored and can be used again after renewal, subject to available capacity.')) + '</p>'
		}
		if (effective === 'OFFLINE_EXPIRED') {
			notices.push(notice(tr('Offline period ended. Synchronize the license to restore Pro access.'), '', true))
		}
		const syncErrors = {
			license_sync_unavailable: tr('The license server could not be reached. Check the connection and try again.'),
			license_response_invalid: tr('The license response could not be verified. Please try again or contact support.'),
			installation_proof_unavailable: tr('The installation proof could not be loaded. Please contact support.'),
		}
		if (snapshot.last_error) {
			notices.push(notice(syncErrors[snapshot.last_error] || tr('Synchronization failed.')))
		}
		const activation = snapshot.activation
		if (activation?.required || effective === 'ACTIVATION_REQUIRED' || activation?.state === 'credentials_changed') {
			const confirmed = activation?.verified && activation?.state === 'activated'
			const activationLabel = confirmed ? tr('Activated for this Nextcloud')
				: activation?.state === 'conflict' ? tr('This license is already activated for another Nextcloud.')
					: tr('License activation could not be confirmed. Check your license key or contact support.')
			const help = confirmed ? renderInlineHelp('License activation', [
				'The license is automatically assigned to this Nextcloud. A complete server migration preserving configuration and database normally keeps the activation.',
			]) : ''
			rows.push(row(tr('License activation'), activationLabel, help))
			if (!confirmed) {
				let explanation = ''
				if (!activation?.enforced && snapshot.is_valid && activation?.state !== 'credentials_changed') {
					explanation += '<p>' + escapeHtml(tr('Installation verification is not enforced yet. This notice does not block access.')) + '</p>'
				}
				if (activation?.state === 'conflict' || activation?.state === 'invalid_proof') {
					explanation += '<p>' + escapeHtml(tr('Moved your server or reinstalled Nextcloud? Contact support. We can help you with a replacement license key.')) + '</p>'
				}
				explanation += '<p><a class="button" href="https://nc-connector.de/support/" target="_blank" rel="noopener">' + escapeHtml(tr('Contact support')) + '</a></p>'
				notices.push(notice(activationLabel, explanation, !snapshot.is_valid))
			}
		}
		if (effective === 'INVALID' && commercial !== 'INVALID' && activation?.state !== 'credentials_changed') {
			notices.push(notice(tr('License') + ': ' + tr('Invalid'), '', true))
		}
		const warnings = notices.sort((left, right) => Number(right.error) - Number(left.error)).map((item) => item.html).join('')
		refs.licenseStatus.innerHTML = warnings + availability + '<dl class="nccb-license-facts">' + rows.join('') + '</dl>' + details
	}

	function renderProFunnel(refs, snapshot, helpers) {
		const { tr, escapeHtml } = helpers
		if (!(refs.proFunnel instanceof HTMLElement)) {
			return
		}
		const mode = snapshot?.mode === 'pro' ? 'pro' : 'community'
		const status = String(snapshot?.license_status_effective || snapshot?.status_effective || '').toUpperCase()
		const hasValidLicense = mode === 'pro'
			&& Boolean(snapshot?.has_credentials)
			&& (status === 'ACTIVE' || status === 'GRACE')

		refs.proFunnel.hidden = hasValidLicense
		if (hasValidLicense) {
			refs.proFunnel.innerHTML = ''
			return
		}

		if (mode === 'pro' && snapshot?.has_credentials) {
			refs.proFunnel.innerHTML = '<h3>' + escapeHtml(tr('Renew or check your license')) + '</h3><p>'
				+ escapeHtml(status === 'UNKNOWN'
					? tr('Pro is selected, but no valid license is active yet.')
					: tr('Your license is no longer usable. Check or renew it to use Pro features again.'))
				+ '</p><a class="button" href="https://nc-connector.de/support/" target="_blank" rel="noopener">'
				+ escapeHtml(tr('Contact support')) + '</a>'
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
				<a class="button" href="https://nc-connector.de/testlizenz/" target="_blank" rel="noopener">${escapeHtml(tr('Request 30-day trial key'))}</a>
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
