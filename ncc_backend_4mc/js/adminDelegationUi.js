/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const adminPermissions = window.NCCBackendAdminPermissions || {}
	const ADMIN_PERMISSION_MATRIX = adminPermissions.permissionMatrix || []
	const ADMIN_PERMISSION_COLUMNS = adminPermissions.permissionColumns || []

	function renderPermissionMatrix(selectedPermissions = [], helpers) {
		const { tr, escapeHtml } = helpers
		const selected = new Set(Array.isArray(selectedPermissions) ? selectedPermissions : [])
		const cards = ADMIN_PERMISSION_MATRIX.map((area) => {
			const hasContentPermission = selected.has(`${area.area}.policy`) || selected.has(`${area.area}.templates`)
			const options = ADMIN_PERMISSION_COLUMNS.map((column) => {
				const permission = `${area.area}.${column.suffix}`
				const isOverridePermission = column.suffix === 'group_overrides' || column.suffix === 'user_overrides'
				const disabled = isOverridePermission && !hasContentPermission
				const checked = selected.has(permission) && !disabled
				return `
					<label class="nccb-permission-card__option ${disabled ? 'nccb-permission-card__option--disabled' : ''}">
						<input
							type="checkbox"
							class="nccb-delegation-permission"
							value="${escapeHtml(permission)}"
							data-permission-area="${escapeHtml(area.area)}"
							data-permission-suffix="${escapeHtml(column.suffix)}"
							${checked ? 'checked' : ''}
							${disabled ? 'disabled' : ''}
						>
						<span>${escapeHtml(tr(column.label))}</span>
					</label>
				`
			}).join('')
			return `
				<div class="nccb-permission-card">
					<div class="nccb-permission-card__title">${escapeHtml(tr(area.label))}</div>
					<div class="nccb-permission-card__options">${options}</div>
				</div>
			`
		}).join('')

		return `
			<div class="nccb-permission-cards" data-delegation-permissions>
				${cards}
			</div>
		`
	}

	function syncPermissionDependencies(root) {
		const container = root.querySelector('[data-delegation-permissions]')
		if (!(container instanceof HTMLElement)) {
			return
		}

		ADMIN_PERMISSION_MATRIX.forEach((area) => {
			const policy = container.querySelector(`.nccb-delegation-permission[data-permission-area="${area.area}"][data-permission-suffix="policy"]`)
			const templates = container.querySelector(`.nccb-delegation-permission[data-permission-area="${area.area}"][data-permission-suffix="templates"]`)
			const hasContentPermission = Boolean(policy?.checked || templates?.checked)
			container
				.querySelectorAll(`.nccb-delegation-permission[data-permission-area="${area.area}"][data-permission-suffix$="_overrides"]`)
				.forEach((input) => {
					input.disabled = !hasContentPermission
					if (!hasContentPermission) {
						input.checked = false
					}
					input.closest('.nccb-permission-card__option')
						?.classList.toggle('nccb-permission-card__option--disabled', !hasContentPermission)
				})
		})
	}

	function bindPermissionMatrix(root) {
		const container = root.querySelector('[data-delegation-permissions]')
		if (!(container instanceof HTMLElement)) {
			return
		}
		syncPermissionDependencies(root)
		container.addEventListener('change', (event) => {
			const input = event.target
			if (!(input instanceof HTMLInputElement) || !input.classList.contains('nccb-delegation-permission')) {
				return
			}
			syncPermissionDependencies(root)
		})
	}

	function groupedDelegationPermissions(permissions) {
		const grouped = new Map(ADMIN_PERMISSION_MATRIX.map((area) => [area.area, []]))
		if (!Array.isArray(permissions)) {
			return grouped
		}
		permissions.forEach((permission) => {
			const [area, suffix] = String(permission).split('.')
			if (!grouped.has(area)) {
				return
			}
			const column = ADMIN_PERMISSION_COLUMNS.find((item) => item.suffix === suffix)
			if (column) {
				grouped.get(area).push(column)
			}
		})
		return grouped
	}

	function renderDelegationPermissionGroups(permissions, helpers) {
		const { tr, escapeHtml } = helpers
		if (!Array.isArray(permissions) || permissions.length === 0) {
			return `<span class="nccb-muted">${escapeHtml(tr('No permissions'))}</span>`
		}
		const grouped = groupedDelegationPermissions(permissions)
		return ADMIN_PERMISSION_MATRIX.map((area) => {
			const columns = grouped.get(area.area) || []
			if (columns.length === 0) {
				return ''
			}
			return `
				<div class="nccb-delegation-permission-group">
					<span class="nccb-delegation-permission-group__area">${escapeHtml(tr(area.label))}</span>
					<span class="nccb-delegation-permission-group__chips">
						${columns.map((column) => `<span class="nccb-permission-chip">${escapeHtml(tr(column.label))}</span>`).join('')}
					</span>
				</div>
			`
		}).join('')
	}

	function renderDelegationOverview(container, delegations, helpers) {
		const { tr, escapeHtml } = helpers
		if (!(container instanceof HTMLElement)) {
			return
		}
		const items = Array.isArray(delegations) ? delegations : []
		if (items.length === 0) {
			container.innerHTML = `<div class="nccb-muted">${escapeHtml(tr('No delegated admins configured.'))}</div>`
			return
		}

		container.innerHTML = `
			<table class="nccb-table">
				<thead>
					<tr>
						<th>${escapeHtml(tr('User ID'))}</th>
						<th>${escapeHtml(tr('Name'))}</th>
						<th>${escapeHtml(tr('Status'))}</th>
						<th>${escapeHtml(tr('Permissions'))}</th>
					</tr>
				</thead>
				<tbody>
					${items.map((item) => `
						<tr>
							<td>${escapeHtml(item.user_id || '')}</td>
							<td>${escapeHtml(item.display_name || '—')}</td>
							<td>${escapeHtml(item.enabled ? tr('Enabled') : tr('Disabled'))}</td>
							<td>${renderDelegationPermissionGroups(item.permissions || [], helpers)}</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`
	}

	function readDelegationPermissions(root) {
		return Array.from(root.querySelectorAll('.nccb-delegation-permission:checked'))
			.filter((input) => !input.disabled)
			.map((input) => String(input.value || ''))
			.filter((value) => value !== '')
	}

	window.NCCBackendDelegationUi = {
		bindPermissionMatrix,
		readDelegationPermissions,
		renderDelegationOverview,
		renderPermissionMatrix,
		syncPermissionDependencies,
	}
})()
