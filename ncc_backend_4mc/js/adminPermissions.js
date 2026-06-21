/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const SHARE_HTML_TEMPLATE_KEY = 'share_html_block_template'
	const SHARE_PASSWORD_TEMPLATE_KEY = 'share_password_template'
	const TALK_INVITATION_TEMPLATE_KEY = 'talk_invitation_template'
	const TALK_INVITATION_TEMPLATE_FORMAT_KEY = 'talk_invitation_template_format'
	const EMAIL_SIGNATURE_TEMPLATE_KEY = 'email_signature_template'
	const EMAIL_SIGNATURE_EMAIL_ADDRESS_KEY = 'email_signature_email_address'
	const EMAIL_SIGNATURE_PHONE_MOBILE_KEY = 'email_signature_phone_mobile'
	const EMAIL_SIGNATURE_CUSTOM1_KEY = 'email_signature_custom1'
	const EMAIL_SIGNATURE_CUSTOM2_KEY = 'email_signature_custom2'
	const EMAIL_SIGNATURE_ON_COMPOSE_KEY = 'email_signature_on_compose'
	const EMAIL_SIGNATURE_ON_REPLY_KEY = 'email_signature_on_reply'
	const EMAIL_SIGNATURE_ON_FORWARD_KEY = 'email_signature_on_forward'

	const USER_OVERRIDE_ONLY_SETTING_KEYS = new Set([
		EMAIL_SIGNATURE_EMAIL_ADDRESS_KEY,
		EMAIL_SIGNATURE_PHONE_MOBILE_KEY,
		EMAIL_SIGNATURE_CUSTOM1_KEY,
		EMAIL_SIGNATURE_CUSTOM2_KEY,
	])
	const SIGNATURE_TEMPLATE_USER_SETTING_KEYS = new Set([
		EMAIL_SIGNATURE_TEMPLATE_KEY,
		EMAIL_SIGNATURE_PHONE_MOBILE_KEY,
		EMAIL_SIGNATURE_CUSTOM1_KEY,
		EMAIL_SIGNATURE_CUSTOM2_KEY,
	])
	const SIGNATURE_POLICY_USER_SETTING_KEYS = new Set([
		EMAIL_SIGNATURE_ON_COMPOSE_KEY,
		EMAIL_SIGNATURE_ON_REPLY_KEY,
		EMAIL_SIGNATURE_ON_FORWARD_KEY,
	])
	const TEMPLATE_DEFAULT_PERMISSION_BY_KEY = {
		[SHARE_HTML_TEMPLATE_KEY]: 'share.templates',
		[SHARE_PASSWORD_TEMPLATE_KEY]: 'share.templates',
		language_share_html_block: 'share.templates',
		[TALK_INVITATION_TEMPLATE_KEY]: 'talk.templates',
		[TALK_INVITATION_TEMPLATE_FORMAT_KEY]: 'talk.templates',
		language_talk_description: 'talk.templates',
		[EMAIL_SIGNATURE_TEMPLATE_KEY]: 'signature.templates',
	}

	const permissionMatrix = [
		{ area: 'share', label: 'Shares' },
		{ area: 'talk', label: 'Talk' },
		{ area: 'signature', label: 'Email signature' },
	]
	const permissionColumns = [
		{ suffix: 'policy', label: 'Policies' },
		{ suffix: 'templates', label: 'Templates' },
		{ suffix: 'group_overrides', label: 'Group overrides' },
		{ suffix: 'user_overrides', label: 'User overrides' },
	]

	function settingCategory(settingKey) {
		if (String(settingKey).startsWith('email_signature_')) {
			return 'email_signature'
		}
		if (settingKey === 'language_talk_description' || String(settingKey).startsWith('talk_')) {
			return 'talk'
		}
		return 'share'
	}

	function adminPermissionAreaForSetting(settingKey) {
		const category = settingCategory(settingKey)
		return category === 'email_signature' ? 'signature' : category
	}

	function defaultAdminPermissionForSetting(settingKey) {
		const key = String(settingKey || '')
		return TEMPLATE_DEFAULT_PERMISSION_BY_KEY[key] || `${adminPermissionAreaForSetting(key)}.policy`
	}

	function userOverrideAdminPermissionForSetting(settingKey) {
		const key = String(settingKey || '')
		if (SIGNATURE_TEMPLATE_USER_SETTING_KEYS.has(key)) {
			return 'signature.templates'
		}
		if (SIGNATURE_POLICY_USER_SETTING_KEYS.has(key)) {
			return 'signature.policy'
		}
		return `${adminPermissionAreaForSetting(key)}.user_overrides`
	}

	function groupOverrideAdminPermissionForSetting(settingKey) {
		return `${adminPermissionAreaForSetting(settingKey)}.group_overrides`
	}

	function hasAdminPermission(state, permission) {
		if (state.admin?.is_nextcloud_admin) {
			return true
		}
		return Array.isArray(state.admin?.permissions) && state.admin.permissions.includes(permission)
	}

	function hasAnyAdminPermission(state, permissions) {
		if (state.admin?.is_nextcloud_admin) {
			return true
		}
		return permissions.some((permission) => hasAdminPermission(state, permission))
	}

	function canEditDefaultSetting(state, settingKey) {
		return hasAdminPermission(state, defaultAdminPermissionForSetting(settingKey))
	}

	function canEditUserOverrideSetting(state, settingKey) {
		return hasAdminPermission(state, userOverrideAdminPermissionForSetting(settingKey))
	}

	function canEditGroupOverrideSetting(state, settingKey) {
		return hasAdminPermission(state, groupOverrideAdminPermissionForSetting(settingKey))
	}

	function canReadAssignedSeatOverview(state) {
		return Boolean(state.admin?.is_nextcloud_admin) || hasAnyAdminPermission(state, [
			'share.group_overrides',
			'share.user_overrides',
			'talk.group_overrides',
			'talk.user_overrides',
			'signature.group_overrides',
			'signature.user_overrides',
			'signature.templates',
		])
	}

	function permissionsForCategory(category, suffixes) {
		const area = category === 'email_signature' ? 'signature' : category
		return suffixes.map((suffix) => `${area}.${suffix}`)
	}

	function userOverridePermissionsForCategory(category) {
		const suffixes = category === 'email_signature'
			? ['user_overrides', 'templates']
			: ['user_overrides']
		return permissionsForCategory(category, suffixes)
	}

	function canUseAnyUserOverridePanel(state) {
		return ['share', 'talk', 'email_signature'].some((category) => (
			hasAnyAdminPermission(state, userOverridePermissionsForCategory(category))
		))
	}

	function isUserOverrideOnlySettingKey(settingKey) {
		return USER_OVERRIDE_ONLY_SETTING_KEYS.has(String(settingKey || ''))
	}

	window.NCCBackendAdminPermissions = {
		permissionMatrix,
		permissionColumns,
		settingCategory,
		defaultAdminPermissionForSetting,
		userOverrideAdminPermissionForSetting,
		groupOverrideAdminPermissionForSetting,
		hasAdminPermission,
		hasAnyAdminPermission,
		canEditDefaultSetting,
		canEditUserOverrideSetting,
		canEditGroupOverrideSetting,
		canReadAssignedSeatOverview,
		permissionsForCategory,
		userOverridePermissionsForCategory,
		canUseAnyUserOverridePanel,
		isUserOverrideOnlySettingKey,
	}
})()
