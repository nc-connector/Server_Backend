/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const APP_ID = 'ncc_backend_4mc'

	function buildAppUrl(path, queryParams = null) {
		const normalizedPath = String(path || '').startsWith('/') ? String(path || '') : `/${String(path || '')}`
		const baseUrl = OC.generateUrl('/apps/' + APP_ID + normalizedPath)
		if (queryParams === null) {
			return baseUrl
		}

		const params = queryParams instanceof URLSearchParams
			? queryParams
			: new URLSearchParams(Object.entries(queryParams).flatMap(([key, value]) => {
				if (value === null || typeof value === 'undefined') {
					return []
				}
				return [[key, String(value)]]
			}))
		const query = params.toString()
		return query ? `${baseUrl}?${query}` : baseUrl
	}

	function isWriteMethod(method) {
		const m = String(method).toUpperCase()
		return m === 'POST' || m === 'PUT' || m === 'PATCH' || m === 'DELETE'
	}

	async function apiRequest(method, path, body = null) {
		const idx = path.indexOf('?')
		const pathOnly = idx >= 0 ? path.slice(0, idx) : path
		const query = idx >= 0 ? path.slice(idx + 1) : ''
		const url = buildAppUrl(pathOnly, query ? new URLSearchParams(query) : null)

		const headers = { Accept: 'application/json' }
		if (isWriteMethod(method)) {
			headers['Content-Type'] = 'application/json'
			headers.requesttoken = OC.requestToken || window.oc_requesttoken || ''
			headers['X-Requested-With'] = 'XMLHttpRequest'
		}

		const response = await fetch(url, {
			method,
			headers,
			credentials: 'same-origin',
			body: body === null ? undefined : JSON.stringify(body),
		})

		let payload = null
		try {
			payload = await response.json()
		} catch (error) {
			console.error('nccb admin api response parse failed', method, path, error)
		}

		if (!response.ok) {
			throw new Error(payload?.error || `HTTP ${response.status} (${path})`)
		}

		return payload
	}

	window.NCCBackendAdminApi = {
		loadAdminMe: () => apiRequest('GET', '/api/v1/admin/me'),
		loadLicense: () => apiRequest('GET', '/api/v1/admin/license'),
		loadBackendUpdate: () => apiRequest('GET', '/api/v1/admin/update-check'),
		saveMode: (mode) => apiRequest('PUT', '/api/v1/admin/license/mode', { mode }),
		saveCredentials: (email, licenseKey) => apiRequest('PUT', '/api/v1/admin/license/credentials', { email, license_key: licenseKey }),
		syncLicense: () => apiRequest('POST', '/api/v1/admin/license/sync'),
		loadGroups: () => apiRequest('GET', '/api/v1/admin/groups?limit=200&offset=0'),
		loadUsers: (search, groupId, limit, offset) => {
			const qs = new URLSearchParams({
				search: search || '',
				group_id: groupId || '',
				limit: String(limit),
				offset: String(offset),
			})
			return apiRequest('GET', '/api/v1/admin/users?' + qs.toString())
		},
		loadSeats: (limit, offset) => apiRequest('GET', `/api/v1/admin/seats?limit=${limit}&offset=${offset}`),
		loadStatus: (userId) => apiRequest('GET', `/api/v1/status?user_id=${encodeURIComponent(userId)}`),
		setSeat: (userId, assigned) => apiRequest('PUT', `/api/v1/admin/seats/${encodeURIComponent(userId)}`, { assigned }),
		loadDefaults: () => apiRequest('GET', '/api/v1/admin/client-settings/schema'),
		saveDefaults: (defaults) => apiRequest('PUT', '/api/v1/admin/client-settings/defaults', { defaults }),
		previewDefaultTemplateAssets: (settingKey, value) => apiRequest('PUT', '/api/v1/admin/client-settings/defaults', {
			defaults: {},
			template_asset_preview: {
				[settingKey]: value,
			},
		}),
		loadUserOverrides: (userId) => apiRequest('GET', `/api/v1/admin/client-settings/users/${encodeURIComponent(userId)}`),
		saveUserOverrides: (userId, overrides) => apiRequest('PUT', `/api/v1/admin/client-settings/users/${encodeURIComponent(userId)}`, { overrides }),
		previewUserTemplateAssets: (userId, settingKey, value) => apiRequest('PUT', `/api/v1/admin/client-settings/users/${encodeURIComponent(userId)}`, {
			overrides: {},
			template_asset_preview: {
				[settingKey]: value,
			},
		}),
		loadGroupOverrides: (groupId) => {
			const qs = new URLSearchParams({ group_id: groupId || '' })
			return apiRequest('GET', '/api/v1/admin/client-settings/groups?' + qs.toString())
		},
		loadDelegations: () => apiRequest('GET', '/api/v1/admin/delegations'),
		saveDelegation: (userId, enabled, permissions) => apiRequest('PUT', `/api/v1/admin/delegations/${encodeURIComponent(userId)}`, {
			enabled,
			permissions,
		}),
		deleteDelegation: (userId) => apiRequest('DELETE', `/api/v1/admin/delegations/${encodeURIComponent(userId)}`),
		saveGroupOverrides: (groupId, priority, overrides) => apiRequest('PUT', '/api/v1/admin/client-settings/groups', {
			group_id: groupId,
			priority,
			overrides,
		}),
		previewGroupTemplateAssets: (groupId, priority, settingKey, value) => apiRequest('PUT', '/api/v1/admin/client-settings/groups', {
			group_id: groupId,
			priority,
			overrides: {},
			template_asset_preview: {
				[settingKey]: value,
			},
		}),
	}
})()
