/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	function csvEscape(value) {
		const normalized = String(value ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
		return `"${normalized.replace(/"/g, '""')}"`
	}

	function seatStateLabel(state, tr) {
		if (state === 'suspended_overlimit') {
			return tr('Paused (overlicensed)')
		}
		if (state === 'active') {
			return tr('Active')
		}
		return tr('Not assigned')
	}

	function formatGroupOverrideTooltip(groups, helpers) {
		const { tr, escapeHtml } = helpers
		if (!Array.isArray(groups) || groups.length === 0) {
			return ''
		}

		const items = groups.map((group) => {
			const groupId = String(group?.group_id || '')
			const displayName = String(group?.display_name || '')
			const priority = Number.parseInt(String(group?.priority ?? 100), 10) || 100
			const label = displayName && displayName !== groupId
				? `${displayName} (${groupId})`
				: (displayName || groupId)
			return `
				<li class="nccb-help-tooltip-item">
					<button type="button" class="nccb-help-tooltip-action" data-group-override-link="${escapeHtml(groupId)}">${escapeHtml(label)}</button>
					<span class="nccb-help-tooltip-meta">${escapeHtml(`${tr('Priority')} ${priority}`)}</span>
				</li>
			`
		}).join('')

		return `
			<div class="nccb-help-tooltip" role="tooltip">
				<div class="nccb-help-tooltip-title">${escapeHtml(tr('Group overrides'))}</div>
				<ul class="nccb-help-tooltip-list">${items}</ul>
			</div>
		`
	}

	function formatUserOverrideTooltip(userId, displayName, helpers) {
		const { tr, escapeHtml } = helpers
		const normalizedUserId = String(userId || '')
		if (!normalizedUserId) {
			return ''
		}

		const normalizedDisplayName = String(displayName || '')
		const label = normalizedDisplayName && normalizedDisplayName !== normalizedUserId
			? `${normalizedDisplayName} (${normalizedUserId})`
			: (normalizedDisplayName || normalizedUserId)

		return `
			<div class="nccb-help-tooltip" role="tooltip">
				<div class="nccb-help-tooltip-title">${escapeHtml(tr('User overrides'))}</div>
				<ul class="nccb-help-tooltip-list">
					<li class="nccb-help-tooltip-item">
						<button type="button" class="nccb-help-tooltip-action" data-user-override-link="${escapeHtml(normalizedUserId)}">${escapeHtml(label)}</button>
					</li>
				</ul>
			</div>
		`
	}

	function reportValueForSetting(settingKey, value, helpers) {
		const { tr, isTemplateEditorSettingKey } = helpers
		if (isTemplateEditorSettingKey(settingKey)) {
			return value ? tr('Custom') : ''
		}
		if (value === null || typeof value === 'undefined') {
			return ''
		}
		if (typeof value === 'boolean') {
			return value ? 'true' : 'false'
		}
		return String(value)
	}

	function groupOverrideSummary(groups, tr) {
		if (!Array.isArray(groups) || groups.length === 0) {
			return ''
		}

		return groups.map((group) => {
			const groupId = String(group?.group_id || '')
			const displayName = String(group?.display_name || '')
			const priority = Number.parseInt(String(group?.priority ?? 100), 10) || 100
			const label = displayName && displayName !== groupId
				? `${displayName} (${groupId})`
				: (displayName || groupId)
			return `${label} [${tr('Priority')} ${priority}]`
		}).join(' | ')
	}

	async function mapWithConcurrency(items, limit, worker) {
		const maxWorkers = Math.max(1, Math.min(limit, items.length || 1))
		const results = new Array(items.length)
		let nextIndex = 0

		const runners = Array.from({ length: maxWorkers }, async () => {
			while (true) {
				const currentIndex = nextIndex
				nextIndex += 1
				if (currentIndex >= items.length) {
					return
				}
				results[currentIndex] = await worker(items[currentIndex], currentIndex)
			}
		})

		await Promise.all(runners)
		return results
	}

	function downloadTextFile(filename, content, mimeType) {
		const blob = new Blob([content], { type: mimeType })
		const objectUrl = URL.createObjectURL(blob)
		const anchor = document.createElement('a')
		anchor.href = objectUrl
		anchor.download = filename
		document.body.appendChild(anchor)
		anchor.click()
		anchor.remove()
		window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
	}

	async function buildSeatReportCsv(seats, schema, api, helpers) {
		const {
			formatDateTime,
			isUserOverrideOnlySettingKey,
			sortedSettingKeys,
			tr,
		} = helpers
		const policyKeys = sortedSettingKeys(schema)
			.filter((key) => !isUserOverrideOnlySettingKey(key))
		const statusRows = await mapWithConcurrency(seats, 5, async (seat) => {
			const payload = await api.loadStatus(seat.user_id)
			const policy = {
				...(payload?.policy?.share || {}),
				...(payload?.policy?.talk || {}),
			}
			return { seat, payload, policy }
		})

		const header = [
			'user_id',
			'display_name',
			'seat_assigned',
			'seat_state',
			'assigned_at',
			'assigned_by',
			'has_group_overrides',
			'group_override_groups',
			'has_user_overrides',
			'mode',
			'is_valid',
			'overlicensed',
			...policyKeys.map((key) => `policy_${key}`),
		]

		const lines = [header.map(csvEscape).join(',')]
		statusRows.forEach(({ seat, payload, policy }) => {
			const status = payload?.status || {}
			const row = [
				seat.user_id || '',
				seat.display_name || '',
				String(Boolean(status.seat_assigned)),
				String(status.seat_state || ''),
				formatDateTime(seat.assigned_at || null),
				seat.assigned_by || '',
				String(Boolean(seat.has_group_overrides)),
				groupOverrideSummary(seat.group_override_groups || [], tr),
				String(Boolean(seat.has_overrides)),
				String(status.mode || ''),
				String(Boolean(status.is_valid)),
				String(Boolean(status.overlicensed)),
				...policyKeys.map((key) => reportValueForSetting(key, policy[key], helpers)),
			]
			lines.push(row.map(csvEscape).join(','))
		})

		return lines.join('\r\n')
	}

	function renderAssignedSeats(element, seats, helpers) {
		const { tr, escapeHtml, formatDateTime } = helpers
		if (!seats || seats.length === 0) {
			element.textContent = tr('No seats assigned.')
			return
		}
		const rows = seats.map((seat) => {
			const state = String(seat.seat_state || 'active')
			const stateClass = state === 'suspended_overlimit' ? 'nccb-seat-state nccb-seat-state--paused' : 'nccb-seat-state nccb-seat-state--active'
			const userOverrideEnabled = Boolean(seat.has_overrides)
			const userOverrideClass = userOverrideEnabled ? 'nccb-override-state nccb-override-state--enabled' : 'nccb-override-state nccb-override-state--disabled'
			const groupOverrideEnabled = Boolean(seat.has_group_overrides)
			const groupOverrideClass = groupOverrideEnabled ? 'nccb-override-state nccb-override-state--enabled' : 'nccb-override-state nccb-override-state--disabled'
			const groupOverrideTooltip = groupOverrideEnabled ? formatGroupOverrideTooltip(seat.group_override_groups || [], helpers) : ''
			const userOverrideTooltip = userOverrideEnabled ? formatUserOverrideTooltip(seat.user_id || '', seat.display_name || '', helpers) : ''
			return `
				<tr>
					<td>${escapeHtml(seat.user_id || '')}</td>
					<td>${escapeHtml(seat.display_name || '—')}</td>
					<td><span class="${stateClass}">${escapeHtml(seatStateLabel(state, tr))}</span></td>
					<td>${groupOverrideEnabled
						? `<span class="nccb-help-wrap nccb-help-wrap--badge" tabindex="0"><span class="${groupOverrideClass}">${escapeHtml(tr('Enabled'))}</span>${groupOverrideTooltip}</span>`
						: `<span class="${groupOverrideClass}">${escapeHtml(tr('Disabled'))}</span>`}</td>
					<td>${userOverrideEnabled
						? `<span class="nccb-help-wrap nccb-help-wrap--badge" tabindex="0"><span class="${userOverrideClass}">${escapeHtml(tr('Enabled'))}</span>${userOverrideTooltip}</span>`
						: `<span class="${userOverrideClass}">${escapeHtml(tr('Disabled'))}</span>`}</td>
					<td>${escapeHtml(formatDateTime(seat.assigned_at || null))}</td>
					<td>${escapeHtml(seat.assigned_by || '—')}</td>
				</tr>
			`
		}).join('')

		element.innerHTML = `
			<table class="nccb-table">
				<thead>
					<tr>
						<th style="width:220px;">${escapeHtml(tr('User'))}</th>
						<th>${escapeHtml(tr('Name'))}</th>
						<th style="width:220px;">${escapeHtml(tr('Status'))}</th>
						<th style="width:220px;">${escapeHtml(tr('Group overrides'))}</th>
						<th style="width:220px;">${escapeHtml(tr('User overrides'))}</th>
						<th style="width:180px;">${escapeHtml(tr('Assigned at'))}</th>
						<th style="width:180px;">${escapeHtml(tr('Assigned by'))}</th>
					</tr>
				</thead>
				<tbody>${rows}</tbody>
			</table>
		`
	}

	window.NCCBackendSeatReport = {
		buildSeatReportCsv,
		downloadTextFile,
		renderAssignedSeats,
	}
})()
