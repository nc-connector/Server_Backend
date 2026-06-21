/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const APP_ID = 'ncc_backend_4mc'
	const tr = (text, vars = []) => {
		if (typeof t === 'function') {
			return t(APP_ID, text, vars)
		}
		return text
	}

	const adminSettingsMeta = window.NCCBackendAdminSettingsMeta || {}
	const SETTING_META = adminSettingsMeta.settingMeta || {}
	const ENUM_OPTION_LABELS = adminSettingsMeta.enumOptionLabels || {}
	const TEMPLATE_TRANSLATION_LOCALES = adminSettingsMeta.templateTranslationLocales || []
	const TEMPLATE_TRANSLATION_PHRASES = adminSettingsMeta.templateTranslationPhrases || {}

	const SHARE_HTML_TEMPLATE_KEY = 'share_html_block_template'
	const SHARE_PASSWORD_TEMPLATE_KEY = 'share_password_template'
	const SHARE_SEND_PASSWORD_SEPARATELY_KEY = 'share_send_password_separately'
	const SHARE_SEND_PASSWORD_MODE_KEY = 'share_send_password_mode'
	const SHARE_SEND_PASSWORD_MODE_SECRETS = 'secrets'
	const SHARE_SECRETS_EXPIRE_DAYS_KEY = 'share_secrets_expire_days'
	const TALK_INVITATION_TEMPLATE_KEY = 'talk_invitation_template'
	const TALK_INVITATION_TEMPLATE_FORMAT_KEY = 'talk_invitation_template_format'
	const EMAIL_SIGNATURE_ON_COMPOSE_KEY = 'email_signature_on_compose'
	const EMAIL_SIGNATURE_ON_REPLY_KEY = 'email_signature_on_reply'
	const EMAIL_SIGNATURE_ON_FORWARD_KEY = 'email_signature_on_forward'
	const EMAIL_SIGNATURE_TEMPLATE_KEY = 'email_signature_template'
	const EMAIL_SIGNATURE_EMAIL_ADDRESS_KEY = 'email_signature_email_address'
	const EMAIL_SIGNATURE_PHONE_MOBILE_KEY = 'email_signature_phone_mobile'
	const EMAIL_SIGNATURE_CUSTOM1_KEY = 'email_signature_custom1'
	const EMAIL_SIGNATURE_CUSTOM2_KEY = 'email_signature_custom2'
	const TALK_TEMPLATE_FORMAT_HTML = 'html'
	const TALK_TEMPLATE_FORMAT_PLAIN_TEXT = 'plain_text'
	const TEMPLATE_EDITOR_CONTENT_CSP = "default-src 'none'; img-src * data: blob:; style-src 'unsafe-inline';"
	const TEMPLATE_EDITOR_SETTING_KEYS = new Set([
		SHARE_HTML_TEMPLATE_KEY,
		SHARE_PASSWORD_TEMPLATE_KEY,
		TALK_INVITATION_TEMPLATE_KEY,
		EMAIL_SIGNATURE_TEMPLATE_KEY,
	])
	const TEMPLATE_VARIABLES_BY_SETTING = {
		[SHARE_HTML_TEMPLATE_KEY]: ['URL', 'PASSWORD', 'EXPIRATIONDATE', 'RIGHTS', 'NOTE'],
		[SHARE_PASSWORD_TEMPLATE_KEY]: ['PASSWORD'],
		[TALK_INVITATION_TEMPLATE_KEY]: ['MEETING_URL', 'PASSWORD'],
		[EMAIL_SIGNATURE_TEMPLATE_KEY]: ['NAME', 'EMAIL', 'PHONE', 'PHONE_MOBILE', 'ABOUT', 'FUNCTION', 'ORGANISATION', 'CUSTOM1', 'CUSTOM2'],
	}
	const adminPermissions = window.NCCBackendAdminPermissions
	if (!adminPermissions) {
		console.error('nccb admin permissions module missing')
		return
	}
	const ADMIN_PERMISSION_MATRIX = adminPermissions.permissionMatrix
	const ADMIN_PERMISSION_COLUMNS = adminPermissions.permissionColumns
	const settingCategory = adminPermissions.settingCategory
	const canEditDefaultSetting = adminPermissions.canEditDefaultSetting
	const canEditUserOverrideSetting = adminPermissions.canEditUserOverrideSetting
	const canEditGroupOverrideSetting = adminPermissions.canEditGroupOverrideSetting
	const canReadAssignedSeatOverview = adminPermissions.canReadAssignedSeatOverview
	const permissionsForCategory = adminPermissions.permissionsForCategory
	const userOverridePermissionsForCategory = adminPermissions.userOverridePermissionsForCategory
	const canUseAnyUserOverridePanel = adminPermissions.canUseAnyUserOverridePanel
	const hasAnyAdminPermission = adminPermissions.hasAnyAdminPermission
	const isUserOverrideOnlySettingKey = adminPermissions.isUserOverrideOnlySettingKey
	const templatePreview = window.NCCBackendTemplatePreview
	if (!templatePreview) {
		console.error('nccb template preview module missing')
		return
	}
	const buildTemplatePreviewDocument = templatePreview.buildTemplatePreviewDocument
	const talkTemplateHtmlToPlainText = templatePreview.talkTemplateHtmlToPlainText
	const plainTextToPreviewHtml = templatePreview.plainTextToPreviewHtml
	const templateImages = window.NCCBackendTemplateImages
	if (!templateImages) {
		console.error('nccb template image module missing')
		return
	}
	const extractExternalImageSourcesFromHtml = templateImages.extractExternalImageSourcesFromHtml
	const normalizeEditorImageSources = templateImages.normalizeEditorImageSources
	const rewriteImageSources = templateImages.rewriteImageSources
	const sanitizeTemplateHtml = templateImages.sanitizeTemplateHtml

	function escapeHtml(value) {
		return String(value)
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;')
	}

	function htmlToElement(html) {
		const template = document.createElement('template')
		template.innerHTML = String(html || '').trim()
		return template.content.firstElementChild || document.createElement('div')
	}

	function formatDateTime(ts) {
		if (!ts) {
			return '—'
		}
		try {
			return new Date(ts * 1000).toLocaleString(undefined, {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
			})
		} catch (error) {
			console.error('nccb formatDateTime failed', ts, error)
			return '—'
		}
	}

	function formatDate(ts) {
		if (!ts) {
			return '—'
		}
		try {
			return new Date(ts * 1000).toLocaleDateString(undefined, {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
			})
		} catch (error) {
			console.error('nccb formatDate failed', ts, error)
			return '—'
		}
	}

	function setMessage(element, text, type) {
		if (!element) {
			return
		}
		element.className = type === 'error' ? 'nccb-error' : type === 'success' ? 'nccb-success' : 'nccb-muted'
		element.textContent = text || ''
	}

	function settingLabel(settingKey) {
		const meta = SETTING_META[settingKey]
		if (meta?.label) {
			return tr(meta.label)
		}
		return tr(settingKey
			.split('_')
			.map((part) => part.charAt(0).toUpperCase() + part.slice(1))
			.join(' ')
		)
	}

	function settingTooltipData(settingKey) {
		const meta = SETTING_META[settingKey] || {}
		const lines = Array.isArray(meta.tooltip) ? meta.tooltip.map((line) => tr(line)) : []
		const title = String(tr(meta.tooltipTitle || meta.label || settingLabel(settingKey)))
		return { title, lines }
	}

	function renderSettingHelp(settingKey) {
		const meta = SETTING_META[settingKey] || {}
		const { title, lines } = settingTooltipData(settingKey)
		if (lines.length === 0) {
			return ''
		}
		const items = lines.map((line) => `<li>${escapeHtml(line)}</li>`).join('')
		const action = meta.tooltipLinkHref && meta.tooltipLinkLabel
			? `<div class="nccb-help-tooltip-actions"><a class="nccb-help-tooltip-action" href="${escapeHtml(String(meta.tooltipLinkHref))}" target="_blank" rel="noopener noreferrer">${escapeHtml(tr(String(meta.tooltipLinkLabel)))}</a></div>`
			: ''
		return `
			<span class="nccb-help-wrap" tabindex="0">
				<span class="nccb-help" aria-label="${escapeHtml(title)}">ⓘ</span>
				<div class="nccb-help-tooltip" role="tooltip">
					<div class="nccb-help-tooltip-title">${escapeHtml(title)}</div>
					<ul class="nccb-help-tooltip-list">${items}</ul>
					${action}
				</div>
			</span>
		`
	}

	function renderInlineHelp(title, lines = []) {
		const normalizedLines = Array.isArray(lines)
			? lines.map((line) => String(tr(line))).filter((line) => line !== '')
			: []
		if (normalizedLines.length === 0) {
			return ''
		}
		const items = normalizedLines.map((line) => `<li>${escapeHtml(line)}</li>`).join('')
		return `
			<span class="nccb-help-wrap" tabindex="0">
				<span class="nccb-help" aria-label="${escapeHtml(tr(title))}">ⓘ</span>
				<div class="nccb-help-tooltip" role="tooltip">
					<div class="nccb-help-tooltip-title">${escapeHtml(tr(title))}</div>
					<ul class="nccb-help-tooltip-list">${items}</ul>
				</div>
			</span>
		`
	}

	function enumOptionLabel(settingKey, optionValue) {
		const labels = ENUM_OPTION_LABELS[settingKey]
		const raw = String(optionValue)
		if (labels && labels[raw]) {
			return tr(labels[raw])
		}
		return raw
	}

	function getTemplateTranslationOptions() {
		return TEMPLATE_TRANSLATION_LOCALES.map((locale) => ({
			value: locale,
			label: enumOptionLabel('language_share_html_block', locale),
		}))
	}

	function getTemplateTranslationEntries(settingKey) {
		const phrases = TEMPLATE_TRANSLATION_PHRASES[String(settingKey || '')]
		if (!phrases || typeof phrases !== 'object') {
			return []
		}

		return Object.values(phrases)
			.map((translations) => {
				if (!translations || typeof translations !== 'object') {
					return null
				}

				const targets = {}
				const variants = []
				for (const locale of TEMPLATE_TRANSLATION_LOCALES) {
					const value = String(translations[locale] || '').trim()
					if (!value) {
						continue
					}
					targets[locale] = value
					variants.push(value)
				}
				if (variants.length === 0) {
					return null
				}

				return {
					targets,
					variants: [...new Set(variants)].sort((left, right) => right.length - left.length),
				}
			})
			.filter(Boolean)
			.sort((left, right) => {
				const leftLength = Math.max(...left.variants.map((value) => value.length))
				const rightLength = Math.max(...right.variants.map((value) => value.length))
				return rightLength - leftLength
			})
	}

	function isTemplateTranslationToken(segment) {
		return /^\{[A-Z0-9_]+\}$/.test(segment) || /^https?:\/\//i.test(segment)
	}

	function replaceLiteral(value, search, replacement) {
		const rawValue = String(value || '')
		if (!search || search === replacement || !rawValue.includes(search)) {
			return rawValue
		}
		return rawValue.split(search).join(replacement)
	}

	/**
	 * Rewrites known template phrases to the selected locale while leaving variables and links untouched.
	 *
	 * @param {string} segment
	 * @param {string} settingKey
	 * @param {string} targetLocale
	 * @returns {string}
	 */
	function translateTemplateTextSegment(segment, settingKey, targetLocale) {
		const text = String(segment || '')
		if (!text.trim() || isTemplateTranslationToken(text.trim())) {
			return text
		}

		let translated = text
		for (const entry of getTemplateTranslationEntries(settingKey)) {
			const replacement = entry.targets[targetLocale] || entry.targets.en || ''
			if (!replacement) {
				continue
			}
			for (const variant of entry.variants) {
				translated = replaceLiteral(translated, variant, replacement)
			}
		}
		return translated
	}

	/**
	 * Applies phrase-based template translation to visible text nodes only.
	 *
	 * @param {string} rawHtml
	 * @param {string} settingKey
	 * @param {string} targetLocale
	 * @returns {{html: string, changed: boolean}}
	 */
	function translateTemplateHtml(rawHtml, settingKey, targetLocale) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(rawHtml || ''), 'text/html')
		const body = doc.body
		if (!body) {
			return { html: String(rawHtml || ''), changed: false }
		}

		const walker = doc.createTreeWalker(body, NodeFilter.SHOW_TEXT)
		const textNodes = []
		let currentNode = walker.nextNode()
		while (currentNode) {
			textNodes.push(currentNode)
			currentNode = walker.nextNode()
		}

		let changed = false
		for (const node of textNodes) {
			const parent = node.parentElement
			if (!parent || parent.closest('a') || parent.closest('script') || parent.closest('style')) {
				continue
			}

			const rawText = String(node.nodeValue || '')
			const translatedText = rawText
				.split(/(\{[A-Z0-9_]+\}|https?:\/\/[^\s<>"']+)/g)
				.map((segment) => translateTemplateTextSegment(segment, settingKey, targetLocale))
				.join('')
			if (translatedText !== rawText) {
				node.nodeValue = translatedText
				changed = true
			}
		}

		return {
			html: body.innerHTML,
			changed,
		}
	}

	function populateTemplateLanguageSelect(select) {
		if (!(select instanceof HTMLSelectElement)) {
			return
		}

		const options = ['<option value="">—</option>']
		for (const option of getTemplateTranslationOptions()) {
			options.push(`<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`)
		}
		select.innerHTML = options.join('')
		select.value = ''
	}

	function translateModalTemplateEditor(targetLocale) {
		const wrapper = templateEditorModalState.wrapper
		const editor = getModalTemplateEditor()
		if (!(wrapper instanceof HTMLElement) || !editor || !targetLocale) {
			return
		}

		const settingKey = String(wrapper.dataset.settingKey || '')
		const translated = translateTemplateHtml(editor.getContent(), settingKey, targetLocale)
		if (!translated.changed) {
			return
		}
		editor.setContent(translated.html)
	}

	/**
	 * Returns settings in a stable UI order (instead of plain alphabetical order).
	 *
	 * @param {Record<string, any>} schema
	 * @returns {string[]}
	 */
	function sortedSettingKeys(schema) {
		const order = [
			'share_base_directory',
			'share_name_template',
			'share_permission_upload',
			'share_permission_edit',
			'share_permission_delete',
			'share_set_password',
			'share_send_password_separately',
			SHARE_SEND_PASSWORD_MODE_KEY,
			SHARE_SECRETS_EXPIRE_DAYS_KEY,
			'share_expire_days',
			'attachments_always_via_ncconnector',
			'attachments_min_size_mb',
			'language_share_html_block',
			SHARE_HTML_TEMPLATE_KEY,
			SHARE_PASSWORD_TEMPLATE_KEY,
			'language_talk_description',
			TALK_INVITATION_TEMPLATE_FORMAT_KEY,
			TALK_INVITATION_TEMPLATE_KEY,
			'talk_generate_password',
			'talk_title',
			'talk_lobby_active',
			'talk_show_in_search',
			'talk_add_users',
			'talk_add_guests',
			'talk_set_password',
			'talk_delete_room_on_event_delete',
			'talk_room_type',
			EMAIL_SIGNATURE_ON_COMPOSE_KEY,
			EMAIL_SIGNATURE_ON_REPLY_KEY,
			EMAIL_SIGNATURE_ON_FORWARD_KEY,
			EMAIL_SIGNATURE_TEMPLATE_KEY,
			EMAIL_SIGNATURE_EMAIL_ADDRESS_KEY,
			EMAIL_SIGNATURE_PHONE_MOBILE_KEY,
			EMAIL_SIGNATURE_CUSTOM1_KEY,
			EMAIL_SIGNATURE_CUSTOM2_KEY,
		]
		const positions = new Map(order.map((key, index) => [key, index]))
		return Object.keys(schema || {}).sort((left, right) => {
			const leftPos = positions.has(left) ? positions.get(left) : Number.MAX_SAFE_INTEGER
			const rightPos = positions.has(right) ? positions.get(right) : Number.MAX_SAFE_INTEGER
			if (leftPos !== rightPos) {
				return leftPos - rightPos
			}
			return left.localeCompare(right)
		})
	}


	function mergeSchema(state, schema) {
		if (schema && typeof schema === 'object') {
			state.schema = {
				...(state.schema || {}),
				...schema,
			}
		}
	}

	function isTemplateEditorSettingKey(settingKey) {
		return TEMPLATE_EDITOR_SETTING_KEYS.has(String(settingKey || ''))
	}

	function getTemplateInactiveNote(settingKey) {
		return String(settingKey || '') === EMAIL_SIGNATURE_TEMPLATE_KEY
			? 'Only active when signatures for new messages are enabled.'
			: 'Only active when language is set to Custom.'
	}

	function renderSettingDependencyNotes(prefix, settingKey, definition) {
		const key = String(settingKey || '')
		const notes = []
		if (key === SHARE_SEND_PASSWORD_MODE_KEY) {
			notes.push(['separate-password-disabled', 'Only relevant when separate password delivery is enabled.'])
			if (definition?.disabled_note) {
				notes.push(['secrets-unavailable', definition.disabled_note])
			}
		}
		if (key === SHARE_SECRETS_EXPIRE_DAYS_KEY) {
			notes.push(['separate-password-disabled', 'Only relevant when separate password delivery is enabled.'])
			notes.push(['secrets-mode-required', 'Only relevant when Nextcloud Secret Link mode is selected.'])
			if (definition?.disabled_note) {
				notes.push(['secrets-unavailable', definition.disabled_note])
			}
		}
		if (notes.length === 0) {
			return ''
		}
		return notes.map(([reason, text]) => `
			<div class="nccb-muted nccb-setting-note" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" data-note-reason="${escapeHtml(reason)}" hidden>${escapeHtml(tr(text))}</div>
		`).join('')
	}

	function isEmbeddedTalkTemplateSettingKey(settingKey) {
		return String(settingKey || '') === TALK_INVITATION_TEMPLATE_FORMAT_KEY
	}

	function shouldRenderStandaloneSettingRow(settingKey) {
		return !isEmbeddedTalkTemplateSettingKey(settingKey)
	}

	function getTemplateModeControlKey(settingKey) {
		return isEmbeddedTalkTemplateSettingKey(settingKey)
			? TALK_INVITATION_TEMPLATE_KEY
			: String(settingKey || '')
	}

	function getTemplateVariablesForSetting(settingKey) {
		const key = String(settingKey || '')
		return TEMPLATE_VARIABLES_BY_SETTING[key] || []
	}

	const templateAssetRefreshTimers = new WeakMap()
	let templateAssetRefreshHandler = null
	const templateEditorModalState = {
		wrapper: null,
		editorId: '',
		textarea: null,
		assetMap: {},
		languageSelect: null,
	}

	function getTinyMce() {
		if (typeof window.tinymce === 'object' && typeof window.tinymce.init === 'function') {
			return window.tinymce
		}
		return null
	}

	/**
	 * Wraps rendered template HTML in a standalone preview document with CSP.
	 *
	 * @param {string} html
	 * @returns {string}
	 */

	function ensureTemplatePreviewModal() {
		let modal = document.getElementById('nccb-template-preview-modal')
		if (modal) {
			return modal
		}

		modal = document.createElement('div')
		modal.id = 'nccb-template-preview-modal'
		modal.className = 'nccb-template-preview-modal'
		modal.innerHTML = `
			<div class="nccb-template-preview-backdrop" data-action="close"></div>
			<div class="nccb-template-preview-dialog" role="dialog" aria-modal="true" aria-label="${escapeHtml(tr('Preview'))}">
				<div class="nccb-template-preview-header">
					<div class="nccb-template-preview-title">${escapeHtml(tr('Preview'))}</div>
					<button type="button" class="nccb-template-preview-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">×</button>
				</div>
				<div class="nccb-template-preview-body">
					<iframe class="nccb-template-preview-frame" sandbox=""></iframe>
				</div>
			</div>
		`
		modal.addEventListener('click', (event) => {
			const target = event.target
			if (target instanceof HTMLElement && target.dataset.action === 'close') {
				modal.classList.remove('nccb-template-preview-modal--open')
			}
		})
		document.body.appendChild(modal)
		return modal
	}

	function getTalkTemplateFormatForWrapper(wrapper) {
		if (!(wrapper instanceof HTMLElement)) {
			return TALK_TEMPLATE_FORMAT_HTML
		}

		const control = wrapper.querySelector(`.nccb-setting-control[data-setting-key="${TALK_INVITATION_TEMPLATE_FORMAT_KEY}"]`)
		const value = String(control?.value || '').toLowerCase()
		return value === TALK_TEMPLATE_FORMAT_PLAIN_TEXT
			? TALK_TEMPLATE_FORMAT_PLAIN_TEXT
			: TALK_TEMPLATE_FORMAT_HTML
	}

	function openTemplatePreview(editor, wrapper = null) {
		const modal = ensureTemplatePreviewModal()
		const frame = modal.querySelector('.nccb-template-preview-frame')
		if (!(frame instanceof HTMLIFrameElement)) {
			return
		}

		const sourceHtml = editor.getBody()?.innerHTML || editor.getContent()
		const isTalkTemplate = wrapper instanceof HTMLElement
			&& String(wrapper.dataset.settingKey || '') === TALK_INVITATION_TEMPLATE_KEY
		const previewContent = isTalkTemplate && getTalkTemplateFormatForWrapper(wrapper) === TALK_TEMPLATE_FORMAT_PLAIN_TEXT
			? plainTextToPreviewHtml(talkTemplateHtmlToPlainText(sourceHtml))
			: toPreviewTemplateHtml(sourceHtml)
		const previewHtml = buildTemplatePreviewDocument(previewContent)
		frame.setAttribute('srcdoc', previewHtml)
		modal.classList.add('nccb-template-preview-modal--open')
	}

	function ensureTemplateEditorModal() {
		let modal = document.getElementById('nccb-template-editor-modal')
		if (modal) {
			return modal
		}

		modal = document.createElement('div')
		modal.id = 'nccb-template-editor-modal'
		modal.className = 'nccb-template-editor-modal'
		modal.innerHTML = `
			<div class="nccb-template-editor-backdrop" data-action="close"></div>
			<div class="nccb-template-editor-dialog" role="dialog" aria-modal="true">
				<div class="nccb-template-editor-header">
					<div class="nccb-template-editor-title"></div>
					<div class="nccb-template-editor-header-actions">
						<label class="nccb-template-editor-language">
							<span>${escapeHtml(tr('Languages'))}</span>
							<select class="nccb-template-editor-language-select" data-action="translate-language"></select>
						</label>
						<button type="button" class="nccb-template-editor-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">×</button>
					</div>
				</div>
				<div class="nccb-template-editor-body"></div>
				<div class="nccb-template-editor-footer">
					<button type="button" class="button button-small nccb-template-editor-reset" data-action="reset">${escapeHtml(tr('Reset to default'))}</button>
					<button type="button" class="button button-small nccb-template-editor-save" data-action="save">${escapeHtml(tr('Save'))}</button>
					<button type="button" class="button button-small" data-action="close">${escapeHtml(tr('Close'))}</button>
				</div>
			</div>
		`
		modal.addEventListener('click', (event) => {
			const target = event.target
			if (!(target instanceof HTMLElement)) {
				return
			}

			if (target.dataset.action === 'close') {
				closeTemplateEditorModal()
				return
			}

			if (target.dataset.action === 'reset') {
				resetTemplateEditorModalContent()
				return
			}

			if (target.dataset.action === 'save') {
				saveTemplateEditorModalContent()
			}
		})
		modal.addEventListener('change', (event) => {
			const target = event.target
			if (!(target instanceof HTMLSelectElement) || target.dataset.action !== 'translate-language') {
				return
			}
			translateModalTemplateEditor(String(target.value || ''))
		})
		document.body.appendChild(modal)
		return modal
	}

	function resetTemplateEditorModalContent() {
		const wrapper = templateEditorModalState.wrapper
		if (!(wrapper instanceof HTMLElement)) {
			return
		}

		const control = getTemplateControl(wrapper)
		if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
			return
		}

		const defaultControl = wrapper.querySelector('.nccb-template-default')
		const defaultValue = String(defaultControl?.value ?? '')
		templateEditorModalState.assetMap = { ...getTemplateDefaultAssetMap(wrapper) }
		if (templateEditorModalState.languageSelect instanceof HTMLSelectElement) {
			templateEditorModalState.languageSelect.value = ''
		}
		const editor = getModalTemplateEditor()
		if (editor) {
			editor.setContent(toEditorTemplateHtml(defaultValue, templateEditorModalState.assetMap))
			if (templateEditorModalState.textarea instanceof HTMLTextAreaElement) {
				templateEditorModalState.textarea.value = defaultValue
			}
			return
		}
		if (templateEditorModalState.textarea instanceof HTMLTextAreaElement) {
			templateEditorModalState.textarea.value = defaultValue
		}
	}

	function saveTemplateEditorModalContent() {
		const wrapper = templateEditorModalState.wrapper
		if (!(wrapper instanceof HTMLElement)) {
			return
		}

		const control = getTemplateControl(wrapper)
		if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
			return
		}
		const editor = getModalTemplateEditor()
		if (editor) {
			control.value = fromEditorTemplateHtml(editor.getContent())
		} else if (templateEditorModalState.textarea instanceof HTMLTextAreaElement) {
			control.value = fromEditorTemplateHtml(templateEditorModalState.textarea.value || '')
		}

		const prefix = String(wrapper.dataset.prefix || 'default')
		const targetButtonId = getSaveButtonIdForTemplatePrefix(prefix)
		const targetButton = document.getElementById(targetButtonId)
		if (targetButton instanceof HTMLButtonElement) {
			targetButton.click()
		}
	}

	function getSaveButtonIdForTemplatePrefix(prefix) {
		if (prefix === 'override') {
			return 'nccb-override-save'
		}
		if (prefix === 'group-override') {
			return 'nccb-group-override-save'
		}
		return 'nccb-default-save'
	}

	function closeTemplateEditorModal() {
		const modal = document.getElementById('nccb-template-editor-modal')
		const editor = getModalTemplateEditor()
		if (editor) {
			editor.remove()
		}
		if (modal instanceof HTMLElement) {
			modal.classList.remove('nccb-template-editor-modal--open')
			const languageWrapper = modal.querySelector('.nccb-template-editor-language')
			if (languageWrapper instanceof HTMLElement) {
				languageWrapper.hidden = false
				languageWrapper.style.display = ''
			}
			const saveButton = modal.querySelector('.nccb-template-editor-save')
			if (saveButton instanceof HTMLButtonElement) {
				saveButton.textContent = tr('Save')
			}
			const container = modal.querySelector('.nccb-template-editor-body')
			if (container instanceof HTMLElement) {
				container.innerHTML = ''
			}
		}

		templateEditorModalState.wrapper = null
		templateEditorModalState.editorId = ''
		templateEditorModalState.textarea = null
		templateEditorModalState.assetMap = {}
		templateEditorModalState.languageSelect = null
	}

	function openTemplateEditorModal(wrapper) {
		if (!(wrapper instanceof HTMLElement)) {
			return
		}

		const control = getTemplateControl(wrapper)
		if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
			return
		}

		const modal = ensureTemplateEditorModal()
		const title = modal.querySelector('.nccb-template-editor-title')
		const container = modal.querySelector('.nccb-template-editor-body')
		const saveButton = modal.querySelector('.nccb-template-editor-save')
		const languageWrapper = modal.querySelector('.nccb-template-editor-language')
		const languageSelect = modal.querySelector('.nccb-template-editor-language-select')
		if (!(title instanceof HTMLElement) || !(container instanceof HTMLElement)) {
			return
		}

		if (templateEditorModalState.wrapper === wrapper) {
			modal.classList.add('nccb-template-editor-modal--open')
			return
		}

		closeTemplateEditorModal()

		templateEditorModalState.wrapper = wrapper
		templateEditorModalState.editorId = `${control.id}--modal`
		templateEditorModalState.assetMap = { ...getTemplateAssetMap(wrapper) }
		const supportsLanguageSelect = String(wrapper.dataset.settingKey || '') !== EMAIL_SIGNATURE_TEMPLATE_KEY
		if (languageWrapper instanceof HTMLElement) {
			languageWrapper.hidden = !supportsLanguageSelect
			languageWrapper.style.display = supportsLanguageSelect ? '' : 'none'
		}
		templateEditorModalState.languageSelect = supportsLanguageSelect && languageSelect instanceof HTMLSelectElement ? languageSelect : null

		title.textContent = settingLabel(wrapper.dataset.settingKey || '')
		if (saveButton instanceof HTMLButtonElement) {
			saveButton.textContent = tr('Save')
		}
		if (supportsLanguageSelect) {
			populateTemplateLanguageSelect(templateEditorModalState.languageSelect)
		} else if (languageSelect instanceof HTMLSelectElement) {
			languageSelect.innerHTML = ''
		}
		const modalTextarea = document.createElement('textarea')
		modalTextarea.id = templateEditorModalState.editorId
		modalTextarea.className = 'nccb-template-modal-control'
		modalTextarea.rows = 14
		modalTextarea.value = control.value || ''
		container.appendChild(modalTextarea)
		templateEditorModalState.textarea = modalTextarea
		modal.classList.add('nccb-template-editor-modal--open')
		initializeTemplateModalEditor(wrapper, control, modalTextarea)

		window.setTimeout(() => {
			const editor = getModalTemplateEditor()
			if (editor) {
				editor.focus()
				normalizeEditorImageSources(editor, getEffectiveTemplateAssetMap(wrapper))
			}
		}, 50)
	}

	function parseTemplateAssetMap(value) {
		if (typeof value !== 'string' || value.trim() === '') {
			return {}
		}
		try {
			const parsed = JSON.parse(value)
			if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
				return {}
			}
			return parsed
		} catch (error) {
			console.error('nccb template asset map parse failed', error)
			return {}
		}
	}

	function getTemplateAssetMap(wrapper) {
		return parseTemplateAssetMap(wrapper?.dataset?.templateAssets || '')
	}

	/**
	 * Resolves the asset map that should be used for the current editor render cycle.
	 *
	 * @param {HTMLElement} wrapper
	 * @returns {Object<string, string>}
	 */
	function getEffectiveTemplateAssetMap(wrapper) {
		if (templateEditorModalState.wrapper === wrapper) {
			return templateEditorModalState.assetMap || {}
		}
		return getTemplateAssetMap(wrapper)
	}

	function getTemplateDefaultAssetMap(wrapper) {
		return parseTemplateAssetMap(wrapper?.dataset?.templateDefaultAssets || '')
	}

	function setTemplateAssetMap(wrapper, assetMap) {
		if (wrapper?.dataset) {
			wrapper.dataset.templateAssets = JSON.stringify(assetMap || {})
		}
	}

	function setTemplateDefaultAssetMap(wrapper, assetMap) {
		if (wrapper?.dataset) {
			wrapper.dataset.templateDefaultAssets = JSON.stringify(assetMap || {})
		}
	}

	/**
	 * Refreshes runtime image assets for the current template draft without persisting the template value.
	 *
	 * @param {HTMLElement} wrapper
	 * @returns {void}
	 */
	function scheduleTemplateAssetRefresh(wrapper) {
		if (typeof templateAssetRefreshHandler !== 'function' || !(wrapper instanceof HTMLElement)) {
			return
		}
		const existingTimer = templateAssetRefreshTimers.get(wrapper)
		if (existingTimer) {
			window.clearTimeout(existingTimer)
		}
		const timer = window.setTimeout(() => {
			templateAssetRefreshTimers.delete(wrapper)
			void templateAssetRefreshHandler(wrapper)
		}, 350)
		templateAssetRefreshTimers.set(wrapper, timer)
	}

	function getTemplateRefreshValue(wrapper, control) {
		if (
			templateEditorModalState.wrapper === wrapper
			&& templateEditorModalState.textarea instanceof HTMLTextAreaElement
		) {
			return String(templateEditorModalState.textarea.value || '')
		}
		return String(control?.value || '')
	}

	function toEditorTemplateHtml(rawHtml, assetMap = {}) {
		const sanitizedHtml = sanitizeTemplateHtml(rawHtml)
		const parser = new DOMParser()
		const doc = parser.parseFromString(sanitizedHtml, 'text/html')
		rewriteImageSources(doc, 'editor', assetMap)
		return doc.body ? doc.body.innerHTML : sanitizedHtml
	}

	function toPreviewTemplateHtml(rawHtml) {
		const sanitizedHtml = sanitizeTemplateHtml(rawHtml)
		const parser = new DOMParser()
		const doc = parser.parseFromString(sanitizedHtml, 'text/html')
		doc.querySelectorAll('img').forEach((img) => {
			img.removeAttribute('data-nccb-original-src')
		})
		return doc.body ? doc.body.innerHTML : sanitizedHtml
	}

	function fromEditorTemplateHtml(editorHtml) {
		const rawHtml = String(editorHtml || '')
		const parser = new DOMParser()
		const doc = parser.parseFromString(rawHtml, 'text/html')
		rewriteImageSources(doc, 'storage')
		return sanitizeTemplateHtml(doc.body ? doc.body.innerHTML : rawHtml)
	}

	function getTemplateControl(wrapper) {
		return wrapper.querySelector('.nccb-setting-control')
	}

	function getTemplateEditor(wrapper) {
		const control = getTemplateControl(wrapper)
		if (!control || !control.id || typeof window.tinymce !== 'object') {
			return null
		}
		return window.tinymce.get(control.id) || null
	}

	function getModalTemplateEditor() {
		if (!templateEditorModalState.editorId || typeof window.tinymce !== 'object') {
			return null
		}
		return window.tinymce.get(templateEditorModalState.editorId) || null
	}

	function getActiveTemplateEditor(wrapper) {
		if (templateEditorModalState.wrapper === wrapper) {
			return getModalTemplateEditor()
		}
		return getTemplateEditor(wrapper)
	}

	function setTemplateExpanded(wrapper, expanded) {
		const toggleButton = wrapper.querySelector('.nccb-template-toggle')
		wrapper.classList.toggle('nccb-template-collapsed', !expanded)
		if (toggleButton) {
			toggleButton.dataset.expanded = expanded ? '1' : '0'
			toggleButton.textContent = tr(expanded ? 'Hide editor' : 'Show editor')
		}
	}

	function syncTemplateEditorMode(wrapper) {
		const control = getTemplateControl(wrapper)
		const editor = getActiveTemplateEditor(wrapper)
		if (!control || !editor) {
			return
		}
		editor.mode.set(control.disabled ? 'readonly' : 'design')
	}

	function updateTemplateEditorButtons(wrapper) {
		const control = getTemplateControl(wrapper)
		const disabled = Boolean(control?.disabled)
		wrapper.querySelectorAll('.nccb-template-action').forEach((button) => {
			button.disabled = disabled
		})
	}

	function initializeTemplateModalEditor(wrapper, sourceControl, modalControl) {
		const tinymce = getTinyMce()
		if (!tinymce || !(modalControl instanceof HTMLTextAreaElement)) {
			return
		}

		const existingEditor = getModalTemplateEditor()
		if (existingEditor) {
			existingEditor.remove()
		}

		tinymce.init({
			target: modalControl,
			license_key: 'gpl',
			skin: false,
			content_css: false,
			content_security_policy: TEMPLATE_EDITOR_CONTENT_CSP,
			height: 620,
			menubar: false,
			branding: false,
			promotion: false,
			convert_urls: false,
			relative_urls: false,
			remove_script_host: false,
			plugins: 'code link lists autolink table image',
			toolbar: [
				'undo redo | fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor',
				'alignleft aligncenter alignright | bullist numlist | link image table tablecellprops tableprops | templatevars | code nccbpreview',
			],
			readonly: sourceControl.disabled,
			setup: (editor) => {
				const templateVariables = getTemplateVariablesForSetting(wrapper.dataset.settingKey)
				editor.ui.registry.addMenuButton('templatevars', {
					text: tr('Insert variable'),
					fetch: (callback) => {
						const items = templateVariables.map((variableName) => ({
							type: 'menuitem',
							text: `{${variableName}}`,
							onAction: () => editor.insertContent(`{${variableName}}`),
						}))
						callback(items)
					},
				})
				editor.ui.registry.addButton('nccbpreview', {
					text: tr('Preview'),
					onAction: () => openTemplatePreview(editor, wrapper),
				})

				editor.on('init', () => {
					editor.setContent(toEditorTemplateHtml(sourceControl.value || '', getEffectiveTemplateAssetMap(wrapper)))
					normalizeEditorImageSources(editor, getEffectiveTemplateAssetMap(wrapper))
				})

				const sync = () => {
					modalControl.value = fromEditorTemplateHtml(editor.getContent())
					scheduleTemplateAssetRefresh(wrapper)
				}
				editor.on('SetContent change undo redo', () => normalizeEditorImageSources(editor, getEffectiveTemplateAssetMap(wrapper)))
				editor.on('blur change input undo redo keyup SetContent', sync)
			},
		}).catch((error) => {
			console.error('nccb modal tiny editor load failed', error)
		})
	}

	function attachTemplateEditorHandlers(root) {
		root.querySelectorAll('.nccb-template-editor').forEach((wrapper) => {
			const control = getTemplateControl(wrapper)
			if (!control) {
				return
			}

			if (wrapper.dataset.nccbTemplateEditorBound !== '1') {
				wrapper.dataset.nccbTemplateEditorBound = '1'
				setTemplateExpanded(wrapper, false)

				const toggleButton = wrapper.querySelector('.nccb-template-toggle')
				if (toggleButton) {
					toggleButton.addEventListener('click', () => {
						if (control.disabled) {
							return
						}
						openTemplateEditorModal(wrapper)
					})
				}
			}

			updateTemplateEditorButtons(wrapper)
			syncTemplateEditorMode(wrapper)
		})
	}

	function syncTemplateEditorState(root, prefix) {
		root.querySelectorAll(`.nccb-template-editor[data-prefix="${prefix}"]`).forEach((wrapper) => {
			updateTemplateEditorButtons(wrapper)
			syncTemplateEditorMode(wrapper)
			const control = getTemplateControl(wrapper)
			if (templateEditorModalState.wrapper === wrapper && control?.disabled) {
				closeTemplateEditorModal()
			}
		})
	}

	function removeTinyMceEditorById(editorId) {
		if (!editorId || typeof window.tinymce !== 'object') {
			return
		}
		const editor = window.tinymce.get(editorId)
		if (editor) {
			editor.remove()
		}
	}

	function removeTemplateEditorsByPrefix(prefix) {
		if (templateEditorModalState.wrapper?.dataset?.prefix === prefix) {
			closeTemplateEditorModal()
		}
		TEMPLATE_EDITOR_SETTING_KEYS.forEach((settingKey) => {
			removeTinyMceEditorById(`${prefix}-${settingKey}`)
		})
	}

	function syncAttachmentMinSizeDependency(container, prefix) {
		const alwaysViaConnector = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_always_via_ncconnector"]`)
		const minSizeControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
		const minSizeToggle = container.querySelector(`.nccb-threshold-enabled[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
		const minSizeWrapper = minSizeControl?.closest('.nccb-threshold-control')
		if (!alwaysViaConnector || !minSizeControl || !minSizeToggle) {
			return
		}
		const disabledByMode = minSizeControl.dataset.disabledByMode === '1' || minSizeToggle.dataset.disabledByMode === '1'
		if (alwaysViaConnector.checked) {
			if (!Object.prototype.hasOwnProperty.call(minSizeToggle.dataset, 'lockedChecked')) {
				minSizeToggle.dataset.lockedChecked = minSizeToggle.checked ? '1' : '0'
			}
			minSizeToggle.checked = false
			minSizeToggle.disabled = true
			minSizeControl.disabled = true
			minSizeWrapper?.classList.add('nccb-threshold-control--disabled')
			return
		}
		if (Object.prototype.hasOwnProperty.call(minSizeToggle.dataset, 'lockedChecked')) {
			minSizeToggle.checked = minSizeToggle.dataset.lockedChecked === '1'
			delete minSizeToggle.dataset.lockedChecked
		}
		minSizeToggle.disabled = disabledByMode
		minSizeControl.disabled = disabledByMode || !minSizeToggle.checked
		minSizeWrapper?.classList.toggle('nccb-threshold-control--disabled', minSizeControl.disabled)
	}

	function setSettingDependencyNote(container, prefix, key, reason, visible) {
		container.querySelectorAll(`.nccb-setting-note[data-prefix="${prefix}"][data-setting-key="${key}"][data-note-reason="${reason}"]`).forEach((note) => {
			if (note instanceof HTMLElement) {
				note.hidden = !visible
			}
		})
	}

	function setPolicyDependencyDisabled(container, prefix, key, disabled) {
		const control = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`)
		if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
			return
		}

		const disabledByMode = control.dataset.disabledByMode === '1'
		control.disabled = disabledByMode || disabled
		const row = control.closest('tr')
		if (row instanceof HTMLElement) {
			row.classList.toggle('nccb-default-value-cell--disabled', disabled)
		}

		if (prefix === 'default') {
			const addonToggle = container.querySelector(`.nccb-addon-changeable[data-setting-key="${key}"]`)
			if (addonToggle instanceof HTMLInputElement) {
				addonToggle.disabled = disabled
			}
			return
		}

		const modeSelect = container.querySelector(`.${prefix === 'group-override' ? 'nccb-group-mode' : 'nccb-user-mode'}[data-setting-key="${key}"]`)
		if (modeSelect instanceof HTMLSelectElement) {
			modeSelect.disabled = disabled
		}
	}

	function isSharePasswordSeparateEnabled(container, prefix) {
		const control = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${SHARE_SEND_PASSWORD_SEPARATELY_KEY}"]`)
		return control instanceof HTMLInputElement ? control.checked : true
	}

	function isSecretsOptionAvailable(modeControl) {
		if (!(modeControl instanceof HTMLSelectElement)) {
			return true
		}
		const option = modeControl.querySelector(`option[value="${SHARE_SEND_PASSWORD_MODE_SECRETS}"]`)
		return !(option instanceof HTMLOptionElement) || !option.disabled
	}

	function applySharePasswordDependency(container, prefix) {
		const separateEnabled = isSharePasswordSeparateEnabled(container, prefix)
		const modeControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${SHARE_SEND_PASSWORD_MODE_KEY}"]`)
		const modeSelected = modeControl instanceof HTMLSelectElement
			? String(modeControl.value || '')
			: ''
		const secretsAvailable = isSecretsOptionAvailable(modeControl)
		const secretsSelected = modeSelected === SHARE_SEND_PASSWORD_MODE_SECRETS

		setPolicyDependencyDisabled(container, prefix, SHARE_SEND_PASSWORD_MODE_KEY, !separateEnabled)
		setPolicyDependencyDisabled(container, prefix, SHARE_SECRETS_EXPIRE_DAYS_KEY, !separateEnabled || !secretsSelected || !secretsAvailable)

		setSettingDependencyNote(container, prefix, SHARE_SEND_PASSWORD_MODE_KEY, 'separate-password-disabled', !separateEnabled)
		setSettingDependencyNote(container, prefix, SHARE_SEND_PASSWORD_MODE_KEY, 'secrets-unavailable', separateEnabled && !secretsAvailable)
		setSettingDependencyNote(container, prefix, SHARE_SECRETS_EXPIRE_DAYS_KEY, 'separate-password-disabled', !separateEnabled)
		setSettingDependencyNote(container, prefix, SHARE_SECRETS_EXPIRE_DAYS_KEY, 'secrets-mode-required', separateEnabled && secretsAvailable && !secretsSelected)
		setSettingDependencyNote(container, prefix, SHARE_SECRETS_EXPIRE_DAYS_KEY, 'secrets-unavailable', separateEnabled && !secretsAvailable)
	}

	function applyTemplateLanguageDependency(container, prefix) {
		const shareLanguageControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="language_share_html_block"]`)
		const talkLanguageControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="language_talk_description"]`)
		const shareLanguageIsCustom = String(shareLanguageControl?.value || '').toLowerCase() === 'custom'
		const talkLanguageIsCustom = String(talkLanguageControl?.value || '').toLowerCase() === 'custom'

		const dependencyMap = [
			{ key: SHARE_HTML_TEMPLATE_KEY, enabled: shareLanguageIsCustom },
			{ key: SHARE_PASSWORD_TEMPLATE_KEY, enabled: shareLanguageIsCustom },
			{ key: TALK_INVITATION_TEMPLATE_KEY, enabled: talkLanguageIsCustom },
		]

		for (const dependency of dependencyMap) {
			const templateControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${dependency.key}"]`)
			if (!templateControl) {
				continue
			}
			const disabledByMode = templateControl.dataset.disabledByMode === '1'
			templateControl.disabled = disabledByMode || !dependency.enabled
			const templateEditor = templateControl.closest('.nccb-template-editor')
			if (templateEditor instanceof HTMLElement) {
				templateEditor.classList.toggle('nccb-template-editor--inactive', !dependency.enabled)
				templateEditor.querySelectorAll('.nccb-template-note').forEach((note) => {
					if (note instanceof HTMLElement) {
						note.hidden = dependency.enabled
					}
				})
			}
			const templateRow = templateControl.closest('.nccb-template-row')
			if (templateRow instanceof HTMLElement) {
				templateRow.querySelectorAll('.nccb-template-row-mode, .nccb-template-row-head-select-label, .nccb-template-format').forEach((element) => {
					if (element instanceof HTMLElement || element instanceof HTMLSelectElement) {
						element.hidden = !dependency.enabled
						if (element instanceof HTMLSelectElement) {
							element.disabled = !dependency.enabled
						}
					}
				})
				const talkFormatControl = templateRow.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${TALK_INVITATION_TEMPLATE_FORMAT_KEY}"]`)
				if (talkFormatControl instanceof HTMLInputElement || talkFormatControl instanceof HTMLSelectElement || talkFormatControl instanceof HTMLTextAreaElement) {
					const disabledByMode = talkFormatControl.dataset.disabledByMode === '1'
					talkFormatControl.disabled = disabledByMode || !dependency.enabled
				}
				templateRow.querySelectorAll('.nccb-template-row-head-note').forEach((note) => {
					if (note instanceof HTMLElement) {
						note.hidden = dependency.enabled
					}
				})
			}
		}
	}

	function attachTemplateLanguageDependencyHandlers(container, prefix) {
		const keys = ['language_share_html_block', 'language_talk_description']
		keys.forEach((key) => {
			const control = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`)
			if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
				return
			}
			if (control.dataset.nccbTemplateLanguageBound === '1') {
				return
			}
			control.dataset.nccbTemplateLanguageBound = '1'
			control.addEventListener('change', () => {
				if (prefix === 'default') {
					syncDefaultControlState(container)
					return
				}
				if (prefix === 'group-override') {
					syncGroupOverrideControlState(container)
					return
				}
				syncOverrideControlState(container)
			})
		})
	}

	function isEmailSignatureComposeEnabled(container, prefix) {
		const composeControl = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${EMAIL_SIGNATURE_ON_COMPOSE_KEY}"]`)
		return composeControl instanceof HTMLInputElement ? composeControl.checked : true
	}

	function applyEmailSignatureDependency(container, prefix) {
		const composeEnabled = isEmailSignatureComposeEnabled(container, prefix)
		const dependentKeys = [
			EMAIL_SIGNATURE_ON_REPLY_KEY,
			EMAIL_SIGNATURE_ON_FORWARD_KEY,
			EMAIL_SIGNATURE_TEMPLATE_KEY,
		]

		for (const key of dependentKeys) {
			const control = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`)
			if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
				continue
			}

			const disabledByMode = control.dataset.disabledByMode === '1'
			control.disabled = disabledByMode || !composeEnabled
			const row = control.closest('tr')
			if (row instanceof HTMLElement) {
				row.classList.toggle('nccb-default-value-cell--disabled', !composeEnabled)
			}

			if (prefix === 'default') {
				const addonToggle = container.querySelector(`.nccb-addon-changeable[data-setting-key="${key}"]`)
				if (addonToggle instanceof HTMLInputElement) {
					addonToggle.disabled = !composeEnabled
				}
			} else {
				const modeSelect = container.querySelector(`.${prefix === 'group-override' ? 'nccb-group-mode' : 'nccb-user-mode'}[data-setting-key="${key}"]`)
				if (modeSelect instanceof HTMLSelectElement) {
					modeSelect.disabled = !composeEnabled
				}
			}

			if (key === EMAIL_SIGNATURE_TEMPLATE_KEY) {
				const templateEditor = control.closest('.nccb-template-editor')
				if (templateEditor instanceof HTMLElement) {
					templateEditor.classList.toggle('nccb-template-editor--inactive', !composeEnabled)
					templateEditor.querySelectorAll('.nccb-template-note').forEach((note) => {
						if (note instanceof HTMLElement) {
							note.hidden = composeEnabled
						}
					})
				}
				const templateRow = control.closest('.nccb-template-row')
				if (templateRow instanceof HTMLElement) {
					templateRow.querySelectorAll('.nccb-template-row-head-note').forEach((note) => {
						if (note instanceof HTMLElement) {
							note.hidden = composeEnabled
						}
					})
				}
			}
		}
	}

	function attachEmailSignatureDependencyHandlers(container, prefix) {
		const control = container.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${EMAIL_SIGNATURE_ON_COMPOSE_KEY}"]`)
		if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
			return
		}
		if (control.dataset.nccbEmailSignatureDependencyBound === '1') {
			return
		}
		control.dataset.nccbEmailSignatureDependencyBound = '1'
		control.addEventListener('change', () => {
			if (prefix === 'default') {
				syncDefaultControlState(container)
				return
			}
			if (prefix === 'group-override') {
				syncGroupOverrideControlState(container)
				return
			}
			syncOverrideControlState(container)
		})
	}

	function attachAttachmentDependencyHandlers(root) {
		root.querySelectorAll('.nccb-setting-control[data-setting-key="attachments_always_via_ncconnector"], .nccb-threshold-enabled[data-setting-key="attachments_min_size_mb"]').forEach((control) => {
			if (control.dataset.nccbDependencyBound === '1') {
				return
			}
			control.dataset.nccbDependencyBound = '1'
			control.addEventListener('change', () => {
				syncDefaultControlState(root)
				syncOverrideControlState(root)
				syncGroupOverrideControlState(root)
			})
		})
	}

	function attachSharePasswordDependencyHandlers(root) {
		root.querySelectorAll(`.nccb-setting-control[data-setting-key="${SHARE_SEND_PASSWORD_SEPARATELY_KEY}"], .nccb-setting-control[data-setting-key="${SHARE_SEND_PASSWORD_MODE_KEY}"]`).forEach((control) => {
			if (control.dataset.nccbSharePasswordDependencyBound === '1') {
				return
			}
			control.dataset.nccbSharePasswordDependencyBound = '1'
			control.addEventListener('change', () => {
				syncDefaultControlState(root)
				syncOverrideControlState(root)
				syncGroupOverrideControlState(root)
			})
		})
	}

	const api = window.NCCBackendAdminApi
	if (!api) {
		console.error('nccb admin api module missing')
		return
	}

	function renderSettingControl(prefix, key, definition, value, disabled, assetMap = {}, defaultAssetMap = {}) {
		const type = definition?.type || 'string'
		const id = `${prefix}-${key}`
		const disabledAttr = disabled ? 'disabled' : ''
		const common = `class="nccb-setting-control" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" ${disabledAttr}`

		if (type === 'bool') {
			return `<input id="${escapeHtml(id)}" type="checkbox" ${common} ${value ? 'checked' : ''}>`
		}

		if (type === 'int') {
			const min = Number.isInteger(definition?.min) ? `min="${definition.min}"` : ''
			const max = Number.isInteger(definition?.max) ? `max="${definition.max}"` : ''
			const numeric = Number.isInteger(value) ? value : Number.parseInt(String(value ?? 0), 10) || 0
			const notes = renderSettingDependencyNotes(prefix, key, definition)
			if (key === 'attachments_min_size_mb') {
				const enabled = value !== null && typeof value !== 'undefined'
				return `
					<div class="nccb-threshold-control">
						<label class="nccb-inline-option nccb-threshold-toggle">
							<input type="checkbox" class="nccb-threshold-enabled" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" ${enabled ? 'checked' : ''} ${disabledAttr}>
							${escapeHtml(tr('Enabled'))}
						</label>
						<input id="${escapeHtml(id)}" type="number" ${common} value="${numeric}" ${min} ${max}>
					</div>
				`
			}
			return `<input id="${escapeHtml(id)}" type="number" ${common} value="${numeric}" ${min} ${max}>${notes}`
		}

		if (type === 'enum') {
			const options = Array.isArray(definition?.options) ? definition.options : []
			const disabledOptions = definition?.disabled_options && typeof definition.disabled_options === 'object'
				? definition.disabled_options
				: {}
			const selected = String(value ?? '')
			const html = options.map((option) => {
				const raw = String(option)
				const disabledOption = disabledOptions?.[raw] ? 'disabled' : ''
				return `<option value="${escapeHtml(raw)}" ${raw === selected ? 'selected' : ''} ${disabledOption}>${escapeHtml(enumOptionLabel(key, raw))}</option>`
			}).join('')
			return `<select id="${escapeHtml(id)}" ${common}>${html}</select>${renderSettingDependencyNotes(prefix, key, definition)}`
		}

		const maxLength = Number.isInteger(definition?.max_length) ? definition.max_length : null
		const valueText = String(value ?? '')
		if (isTemplateEditorSettingKey(key)) {
			const defaultTemplate = String(definition?.default ?? '')
			const maxLengthAttr = maxLength !== null ? `maxlength="${maxLength}"` : ''
			const assetsJson = escapeHtml(JSON.stringify(assetMap || {}))
			const defaultAssetsJson = escapeHtml(JSON.stringify(defaultAssetMap || {}))
			return `
				<div class="nccb-template-editor" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" data-template-assets="${assetsJson}" data-template-default-assets="${defaultAssetsJson}">
					<div class="nccb-template-toolbar">
						<button type="button" class="button button-small nccb-template-toggle nccb-template-action" data-expanded="0">${escapeHtml(tr('Show editor'))}</button>
						<span class="nccb-template-note" hidden>${escapeHtml(tr(getTemplateInactiveNote(key)))}</span>
					</div>
					<div class="nccb-template-body">
						<textarea id="${escapeHtml(id)}" ${common} rows="14" ${maxLengthAttr}>${escapeHtml(valueText)}</textarea>
					</div>
					<textarea class="nccb-template-default" hidden readonly>${escapeHtml(defaultTemplate)}</textarea>
				</div>
			`
		}
		if (maxLength !== null && maxLength > 255) {
			return `<textarea id="${escapeHtml(id)}" ${common} rows="3" maxlength="${maxLength}">${escapeHtml(valueText)}</textarea>`
		}
		return `<input id="${escapeHtml(id)}" type="text" ${common} value="${escapeHtml(valueText)}" ${maxLength !== null ? `maxlength="${maxLength}"` : ''}>`
	}

	function renderTalkTemplateFormatControl(prefix, schema, value, disabled) {
		const definition = schema?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]
		if (!definition) {
			return ''
		}

		return `
			<label class="nccb-template-format">
				<span class="nccb-template-row-head-label">${escapeHtml(tr('Output'))}</span>
				${renderSettingControl(prefix, TALK_INVITATION_TEMPLATE_FORMAT_KEY, definition, value, disabled)}
			</label>
		`
	}

	function readSettingControl(root, prefix, key, definition) {
		const selector = `.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`
		const control = root.querySelector(selector)
		if (!control) {
			throw new Error(`${tr('Missing field')}: ${key}`)
		}

		const type = definition?.type || 'string'
		if (type === 'bool') {
			return Boolean(control.checked)
		}
		if (type === 'int') {
			const value = Number.parseInt(String(control.value ?? ''), 10)
			if (!Number.isInteger(value)) {
				throw new Error(`${tr('Invalid number for')} ${key}`)
			}
			return value
		}
		return String(control.value ?? '')
	}

	const SETTING_LAYER_UI = {
		defaults: {
			prefix: 'default',
			modeSelector: '.nccb-addon-changeable',
			modeClass: 'nccb-addon-changeable',
			rowAttribute: 'data-default-setting-key',
			isDisabledByMode: (control) => Boolean(control.checked),
			updateDefaultValueCell: true,
			disableAttachmentToggleByMode: true,
		},
		userOverride: {
			prefix: 'override',
			modeSelector: '.nccb-user-mode',
			modeClass: 'nccb-user-mode',
			rowAttribute: 'data-user-setting-key',
			isDisabledByMode: (control) => control.value !== 'forced',
			updateDefaultValueCell: false,
			disableAttachmentToggleByMode: false,
		},
		groupOverride: {
			prefix: 'group-override',
			modeSelector: '.nccb-group-mode',
			modeClass: 'nccb-group-mode',
			rowAttribute: 'data-group-setting-key',
			isDisabledByMode: (control) => control.value !== 'forced',
			updateDefaultValueCell: false,
			disableAttachmentToggleByMode: false,
		},
	}

	function renderForcedModeSelect(config, key, mode, extraClass = '') {
		const className = extraClass ? `${config.modeClass} ${extraClass}` : config.modeClass
		return `
			<select class="${escapeHtml(className)}" data-setting-key="${escapeHtml(key)}">
				<option value="inherit" ${mode === 'inherit' ? 'selected' : ''}>${escapeHtml(tr('Inherit default'))}</option>
				<option value="forced" ${mode === 'forced' ? 'selected' : ''}>${escapeHtml(tr('Forced value'))}</option>
			</select>
		`
	}

	function renderForcedOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category, config, options = {}) {
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category
			&& shouldRenderStandaloneSettingRow(key)
			&& (!options.skipUserOverrideOnly || !isUserOverrideOnlySettingKey(key)))
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const item = items?.[key] || { mode: 'inherit', value: null, effective_value: definition.default, source: 'default', default_mode: 'default' }
			const mode = item.mode === 'forced' ? 'forced' : 'inherit'
			const currentValue = mode === 'forced' ? item.value : item.effective_value
			if (isTemplateEditorSettingKey(key)) {
				const talkTemplateFormat = key === TALK_INVITATION_TEMPLATE_KEY
					? renderTalkTemplateFormatControl(
						config.prefix,
						schema,
						items?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]?.mode === 'forced'
							? items?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]?.value
							: items?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]?.effective_value ?? schema?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]?.default,
						mode !== 'forced'
					)
					: ''
				return `
					<tr class="nccb-template-row" ${config.rowAttribute}="${escapeHtml(key)}">
						<td>
							<div class="nccb-key-cell">
								<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							<div class="nccb-template-row-head">
								<span class="nccb-template-row-head-label nccb-template-row-head-select-label">${escapeHtml(tr('Preset'))}</span>
								${renderForcedModeSelect(config, key, mode, 'nccb-template-row-mode')}
								${talkTemplateFormat}
								<span class="nccb-template-row-head-note" hidden>${escapeHtml(tr(getTemplateInactiveNote(key)))}</span>
							</div>
							${renderSettingControl(config.prefix, key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}
			return `
				<tr ${config.rowAttribute}="${escapeHtml(key)}">
					<td>
						<div class="nccb-key-cell">
							<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>${renderForcedModeSelect(config, key, mode)}</td>
					<td>${renderSettingControl(config.prefix, key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function syncSettingLayerControlState(container, config) {
		const modeControls = container.querySelectorAll(config.modeSelector)
		for (const modeControl of modeControls) {
			const key = modeControl.getAttribute('data-setting-key')
			const disabledByMode = config.isDisabledByMode(modeControl)
			const control = container.querySelector(`.nccb-setting-control[data-prefix="${config.prefix}"][data-setting-key="${key}"]`)
			const attachmentToggle = container.querySelector(`.nccb-threshold-enabled[data-prefix="${config.prefix}"][data-setting-key="${key}"]`)
			const valueCell = modeControl.closest('tr')?.querySelector('.nccb-default-value-cell')
			if (control) {
				control.dataset.disabledByMode = disabledByMode ? '1' : '0'
				control.disabled = disabledByMode
			}
			if (attachmentToggle) {
				attachmentToggle.dataset.disabledByMode = disabledByMode ? '1' : '0'
				if (config.disableAttachmentToggleByMode) {
					attachmentToggle.disabled = disabledByMode
				}
			}
			if (config.updateDefaultValueCell && valueCell instanceof HTMLElement) {
				valueCell.classList.toggle('nccb-default-value-cell--disabled', disabledByMode)
			}
			if (key === TALK_INVITATION_TEMPLATE_KEY) {
				const talkFormatControl = container.querySelector(`.nccb-setting-control[data-prefix="${config.prefix}"][data-setting-key="${TALK_INVITATION_TEMPLATE_FORMAT_KEY}"]`)
				if (talkFormatControl) {
					talkFormatControl.dataset.disabledByMode = disabledByMode ? '1' : '0'
					talkFormatControl.disabled = disabledByMode
				}
			}
		}
		syncAttachmentMinSizeDependency(container, config.prefix)
		applySharePasswordDependency(container, config.prefix)
		applyTemplateLanguageDependency(container, config.prefix)
		applyEmailSignatureDependency(container, config.prefix)
		syncTemplateEditorState(container, config.prefix)
	}

	function attachSettingLayerModeHandlers(container, config, syncCallback) {
		const modeControls = container.querySelectorAll(config.modeSelector)
		for (const modeControl of modeControls) {
			modeControl.addEventListener('change', () => syncCallback(container))
		}
	}

	function renderDefaultsRows(tbody, schema, defaults, defaultModes, templateAssets, defaultTemplateAssets, category) {
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category
			&& shouldRenderStandaloneSettingRow(key)
			&& !isUserOverrideOnlySettingKey(key))
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const value = Object.prototype.hasOwnProperty.call(defaults || {}, key) ? defaults[key] : definition.default
			const addonEditableSupported = definition?.addon_editable_supported !== false && !isTemplateEditorSettingKey(key)
			const addonChangeable = addonEditableSupported && defaultModes?.[key] === 'user_choice'
			const controlDisabled = false

			if (isTemplateEditorSettingKey(key)) {
				const talkTemplateFormat = key === TALK_INVITATION_TEMPLATE_KEY
					? renderTalkTemplateFormatControl(
						'default',
						schema,
						Object.prototype.hasOwnProperty.call(defaults || {}, TALK_INVITATION_TEMPLATE_FORMAT_KEY)
							? defaults[TALK_INVITATION_TEMPLATE_FORMAT_KEY]
							: schema?.[TALK_INVITATION_TEMPLATE_FORMAT_KEY]?.default,
						false
					)
					: ''
				return `
					<tr class="nccb-template-row" data-default-setting-key="${escapeHtml(key)}">
						<td>
							<div class="nccb-key-cell">
								<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							${talkTemplateFormat
								? `<div class="nccb-template-row-head">${talkTemplateFormat}</div>`
								: ''}
							${renderSettingControl('default', key, definition, value, controlDisabled, templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}

			return `
				<tr data-default-setting-key="${escapeHtml(key)}">
					<td>
						<div class="nccb-key-cell">
							<div class="nccb-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>
						${addonEditableSupported
							? `<label class="nccb-inline-option">
								<input type="checkbox" class="nccb-addon-changeable" data-setting-key="${escapeHtml(key)}" ${addonChangeable ? 'checked' : ''}>
								${escapeHtml(tr('Editable in add-on'))}
							</label>`
							: '<span class="nccb-muted">—</span>'}
					</td>
					<td class="nccb-default-value-cell ${addonChangeable ? 'nccb-default-value-cell--disabled' : ''}">${renderSettingControl('default', key, definition, value, controlDisabled || addonChangeable, templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function syncDefaultControlState(root) {
		syncSettingLayerControlState(root, SETTING_LAYER_UI.defaults)
	}

	function attachDefaultModeHandlers(root) {
		attachSettingLayerModeHandlers(root, SETTING_LAYER_UI.defaults, syncDefaultControlState)
	}

	function renderOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category) {
		renderForcedOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category, SETTING_LAYER_UI.userOverride)
	}

	function renderOverridePlaceholder(tbody) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('Please select a seat user.'))}</td></tr>`
	}

	function renderOverrideTables(root, refs, state, selectedUserId) {
		const hasSelectedUser = Boolean(selectedUserId)
		refs.overrideSave.disabled = !hasSelectedUser
		removeTemplateEditorsByPrefix('override')
		if (!hasSelectedUser) {
			renderOverridePlaceholder(refs.overrideTableShare)
			renderOverridePlaceholder(refs.overrideTableTalk)
			renderOverridePlaceholder(refs.overrideTableEmailSignature)
			return
		}
		renderOverrideRows(refs.overrideTableShare, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'share')
		renderOverrideRows(refs.overrideTableTalk, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'talk')
		renderOverrideRows(refs.overrideTableEmailSignature, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'email_signature')
		syncOverrideControlState(root)
		attachOverrideModeHandlers(root)
		attachAttachmentDependencyHandlers(root)
		attachSharePasswordDependencyHandlers(root)
		attachTemplateLanguageDependencyHandlers(root, 'override')
		attachEmailSignatureDependencyHandlers(root, 'override')
		attachTemplateEditorHandlers(root)
	}

	function syncOverrideControlState(tbody) {
		syncSettingLayerControlState(tbody, SETTING_LAYER_UI.userOverride)
	}

	function attachOverrideModeHandlers(tbody) {
		attachSettingLayerModeHandlers(tbody, SETTING_LAYER_UI.userOverride, syncOverrideControlState)
	}

	function renderGroupOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category) {
		renderForcedOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category, SETTING_LAYER_UI.groupOverride, { skipUserOverrideOnly: true })
	}

	function renderGroupOverridePlaceholder(tbody) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccb-muted">${escapeHtml(tr('Please select a group.'))}</td></tr>`
	}

	function renderGroupOverrideTables(root, refs, state, selectedGroupId) {
		const hasSelectedGroup = Boolean(selectedGroupId)
		refs.groupOverrideSave.disabled = !hasSelectedGroup
		refs.groupOverridePriority.disabled = !hasSelectedGroup
		removeTemplateEditorsByPrefix('group-override')
		if (!hasSelectedGroup) {
			renderGroupOverridePlaceholder(refs.groupOverrideTableShare)
			renderGroupOverridePlaceholder(refs.groupOverrideTableTalk)
			renderGroupOverridePlaceholder(refs.groupOverrideTableEmailSignature)
			return
		}
		renderGroupOverrideRows(refs.groupOverrideTableShare, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'share')
		renderGroupOverrideRows(refs.groupOverrideTableTalk, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'talk')
		renderGroupOverrideRows(refs.groupOverrideTableEmailSignature, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'email_signature')
		syncGroupOverrideControlState(root)
		attachGroupOverrideModeHandlers(root)
		attachAttachmentDependencyHandlers(root, 'group-override')
		attachSharePasswordDependencyHandlers(root)
		attachTemplateLanguageDependencyHandlers(root, 'group-override')
		attachEmailSignatureDependencyHandlers(root, 'group-override')
		attachTemplateEditorHandlers(root)
	}

	function syncGroupOverrideControlState(tbody) {
		syncSettingLayerControlState(tbody, SETTING_LAYER_UI.groupOverride)
	}

	function attachGroupOverrideModeHandlers(tbody) {
		attachSettingLayerModeHandlers(tbody, SETTING_LAYER_UI.groupOverride, syncGroupOverrideControlState)
	}

	function renderUsers(tbody, users) {
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

	function seatStateLabel(state) {
		if (state === 'suspended_overlimit') {
			return tr('Paused (overlicensed)')
		}
		if (state === 'active') {
			return tr('Active')
		}
		return tr('Not assigned')
	}

	function formatGroupOverrideTooltip(groups) {
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

	function formatUserOverrideTooltip(userId, displayName) {
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

	function csvEscape(value) {
		const normalized = String(value ?? '').replace(/\r\n/g, '\n').replace(/\r/g, '\n')
		return `"${normalized.replace(/"/g, '""')}"`
	}

	function reportValueForSetting(settingKey, value) {
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

	function groupOverrideSummary(groups) {
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

	async function buildSeatReportCsv(seats, schema, api) {
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
				groupOverrideSummary(seat.group_override_groups || []),
				String(Boolean(seat.has_overrides)),
				String(status.mode || ''),
				String(Boolean(status.is_valid)),
				String(Boolean(status.overlicensed)),
				...policyKeys.map((key) => reportValueForSetting(key, policy[key])),
			]
			lines.push(row.map(csvEscape).join(','))
		})

		return lines.join('\r\n')
	}

	/**
	 * Renders the assigned-access summary table.
	 *
	 * @param {HTMLElement} element
	 * @param {Array<Record<string, any>>} seats
	 * @returns {void}
	 */
	function renderAssignedSeats(element, seats) {
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
			const groupOverrideTooltip = groupOverrideEnabled ? formatGroupOverrideTooltip(seat.group_override_groups || []) : ''
			const userOverrideTooltip = userOverrideEnabled ? formatUserOverrideTooltip(seat.user_id || '', seat.display_name || '') : ''
			return `
				<tr>
					<td>${escapeHtml(seat.user_id || '')}</td>
					<td>${escapeHtml(seat.display_name || '—')}</td>
					<td><span class="${stateClass}">${escapeHtml(seatStateLabel(state))}</span></td>
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

	function setMainTab(root, name) {
		root.querySelectorAll('[data-main-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-main-tab-button') === name)
		})
		root.querySelectorAll('[data-main-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-main-tab-panel') !== name
		})
	}

	function setGroupTab(root, name) {
		root.querySelectorAll('[data-group-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-group-tab-button') === name)
		})
		root.querySelectorAll('[data-group-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-group-tab-panel') !== name
		})
	}

	function setDefaultsTab(root, name) {
		root.querySelectorAll('[data-default-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-default-tab-button') === name)
		})
		root.querySelectorAll('[data-default-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-default-tab-panel') !== name
		})
	}

	function setOverrideTab(root, name) {
		root.querySelectorAll('[data-override-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-override-tab-button') === name)
		})
		root.querySelectorAll('[data-override-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-override-tab-panel') !== name
		})
	}

	function setGroupOverrideTab(root, name) {
		root.querySelectorAll('[data-group-override-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-group-override-tab-button') === name)
		})
		root.querySelectorAll('[data-group-override-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-group-override-tab-panel') !== name
		})
	}

	function setAdvancedTab(root, name) {
		root.querySelectorAll('[data-advanced-tab-button]').forEach((button) => {
			button.classList.toggle('active', button.getAttribute('data-advanced-tab-button') === name)
		})
		root.querySelectorAll('[data-advanced-tab-panel]').forEach((panel) => {
			panel.hidden = panel.getAttribute('data-advanced-tab-panel') !== name
		})
	}

	function renderPermissionMatrix(selectedPermissions = []) {
		const selected = new Set(Array.isArray(selectedPermissions) ? selectedPermissions : [])
		const cards = ADMIN_PERMISSION_MATRIX.map((area) => {
			const options = ADMIN_PERMISSION_COLUMNS.map((column) => {
				const permission = `${area.area}.${column.suffix}`
				return `
					<label class="nccb-permission-card__option">
						<input type="checkbox" class="nccb-delegation-permission" value="${escapeHtml(permission)}" ${selected.has(permission) ? 'checked' : ''}>
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

	function renderDelegationPermissionGroups(permissions) {
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

	function renderDelegationOverview(container, delegations) {
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
							<td>${renderDelegationPermissionGroups(item.permissions || [])}</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`
	}

	function readDelegationPermissions(root) {
		return Array.from(root.querySelectorAll('.nccb-delegation-permission:checked'))
			.map((input) => String(input.value || ''))
			.filter((value) => value !== '')
	}

	function render(root) {
		root.innerHTML = `
			<h2>NC Connector Backend</h2>
			<div class="nccb-tabbar">
				<button class="button active" data-main-tab-button="general">${escapeHtml(tr('General'))}</button>
				<button class="button" data-main-tab-button="group">${escapeHtml(tr('Group Settings'))}</button>
				<button class="button" data-main-tab-button="advanced">${escapeHtml(tr('Advanced'))}</button>
			</div>

			<section class="nccb-main-panel" data-main-tab-panel="general">
				<div class="nccb-section">
					<h3>${escapeHtml(tr('Operating mode'))}</h3>
					<div class="nccb-row">
						<label class="nccb-inline-option">
							<input type="radio" name="nccb-license-mode" value="community">
							${escapeHtml(tr('Community'))}
							${renderInlineHelp('Community', ['Community mode: 1 seat included. Suitable for tests, proof of concept, or single-user setups.'])}
						</label>
						<label class="nccb-inline-option">
							<input type="radio" name="nccb-license-mode" value="pro">
							${escapeHtml(tr('Pro'))}
							${renderInlineHelp('Pro', ['The Pro version is available for teams starting at 5 seats. One seat always corresponds to one Nextcloud user and is assigned in the backend.'])}
						</label>
					</div>

					<div id="nccb-pro-settings" hidden>
						<div class="nccb-row">
							<label for="nccb-license-email">${escapeHtml(tr('License email'))}</label>
							<input id="nccb-license-email" type="email" autocomplete="email">
						</div>
						<div class="nccb-row">
							<label for="nccb-license-key">${escapeHtml(tr('License key'))}</label>
							<input id="nccb-license-key" type="password" autocomplete="off">
						</div>
						<div class="nccb-row">
							<button id="nccb-license-save" class="button">${escapeHtml(tr('Save license data'))}</button>
							<button id="nccb-license-sync" class="button">${escapeHtml(tr('Sync now'))}</button>
						</div>
					</div>

					<div id="nccb-license-status" class="nccb-muted"></div>
					<div id="nccb-license-hint" class="nccb-muted" hidden></div>
					<div id="nccb-license-message" class="nccb-muted" role="status"></div>
				</div>

				<div id="nccb-pro-funnel" class="nccb-pro-funnel" hidden></div>

				<div class="nccb-backend-update">
					<div id="nccb-backend-update-status" class="nccb-muted" role="status">${escapeHtml(tr('Checking backend version...'))}</div>
				</div>

				<div class="nccb-section">
					<h3>${escapeHtml(tr('Recommended Nextcloud apps'))}</h3>
					<div class="nccb-muted">${escapeHtml(tr('Optional apps that unlock extra NC Connector features.'))}</div>
					<div id="nccb-recommended-apps" class="nccb-recommended-apps"></div>
				</div>
			</section>

			<section class="nccb-main-panel" data-main-tab-panel="group" hidden>
				<div class="nccb-tabbar">
					<button class="button active" data-group-tab-button="defaults">${escapeHtml(tr('Default Settings'))}</button>
					<button class="button" data-group-tab-button="seats">${escapeHtml(tr('Seat Assignment'))}</button>
					<button class="button" data-group-tab-button="assigned">${escapeHtml(tr('Assigned Seats'))}</button>
					<button class="button" data-group-tab-button="group-overrides">${escapeHtml(tr('Group overrides'))}</button>
					<button class="button" data-group-tab-button="overrides">${escapeHtml(tr('User overrides'))}</button>
				</div>

				<section class="nccb-group-panel" data-group-tab-panel="defaults">
					<div class="nccb-section">
						<h3>${escapeHtml(tr('Default settings for mail clients'))}</h3>
						<div class="nccb-muted">${escapeHtml(tr('These values apply to all users with an assigned seat unless a user override is set.'))}</div>
						<div id="nccb-default-message" class="nccb-muted" role="status"></div>
						<div class="nccb-tabbar nccb-tabbar--sub">
							<button class="button active" data-default-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-default-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
							<button class="button" data-default-tab-button="email_signature">${escapeHtml(tr('Email signature'))}</button>
						</div>
						<section data-default-tab-panel="share">
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width: 360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width: 220px;">${escapeHtml(tr('Editable in add-on'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-default-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-default-tab-panel="talk" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width: 360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width: 220px;">${escapeHtml(tr('Editable in add-on'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-default-tbody-talk"></tbody>
								</table>
							</div>
						</section>
						<section data-default-tab-panel="email_signature" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width: 360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width: 220px;">${escapeHtml(tr('Editable in add-on'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-default-tbody-email-signature"></tbody>
								</table>
							</div>
						</section>
						<div class="nccb-row">
							<button id="nccb-default-save" class="button">${escapeHtml(tr('Save default settings'))}</button>
						</div>
					</div>
				</section>

				<section class="nccb-group-panel" data-group-tab-panel="seats" hidden>
					<div class="nccb-section">
						<h3>${escapeHtml(tr('Seat assignment'))}</h3>
						<div id="nccb-seat-usage" class="nccb-muted"></div>
						<div id="nccb-seat-message" class="nccb-muted" role="status"></div>
						<div class="nccb-row">
							<label for="nccb-group-select">${escapeHtml(tr('Group'))}</label>
							<select id="nccb-group-select">
								<option value="">${escapeHtml(tr('All users'))}</option>
							</select>
							<label for="nccb-user-search">${escapeHtml(tr('Search'))}</label>
							<input id="nccb-user-search" type="text" placeholder="${escapeHtml(tr('Username or display name'))}">
						</div>
						<div class="nccb-row">
							<button id="nccb-seat-bulk-assign" class="button">${escapeHtml(tr('Assign seats to all filtered users'))}</button>
							<button id="nccb-seat-bulk-unassign" class="button">${escapeHtml(tr('Remove seats from all filtered users'))}</button>
						</div>
						<div class="nccb-scroll nccb-scroll--users">
							<table class="nccb-table">
								<thead>
									<tr>
										<th style="width:240px;">${escapeHtml(tr('User ID'))}</th>
										<th>${escapeHtml(tr('Name'))}</th>
										<th style="width:120px;">${escapeHtml(tr('Seat'))}</th>
									</tr>
								</thead>
								<tbody id="nccb-user-tbody"></tbody>
							</table>
						</div>
						<div class="nccb-pagination">
							<button id="nccb-user-prev" class="button">${escapeHtml(tr('Previous'))}</button>
							<span id="nccb-user-page" class="nccb-muted">${escapeHtml(tr('Page 1'))}</span>
							<button id="nccb-user-next" class="button">${escapeHtml(tr('Next'))}</button>
						</div>
					</div>
				</section>

				<section class="nccb-group-panel" data-group-tab-panel="assigned" hidden>
					<div class="nccb-section">
						<h3>${escapeHtml(tr('Assigned seats'))}</h3>
						<div id="nccb-assigned-seat-usage" class="nccb-muted"></div>
						<div class="nccb-row">
							<button id="nccb-seat-report-download" class="button">${escapeHtml(tr('Download CSV report'))}</button>
						</div>
						<div id="nccb-assigned-message" class="nccb-muted" role="status"></div>
						<div id="nccb-assigned-seats" class="nccb-scroll nccb-scroll--seats"></div>
					</div>
				</section>

				<section class="nccb-group-panel" data-group-tab-panel="group-overrides" hidden>
					<div class="nccb-section">
						<h3>${escapeHtml(tr('Group overrides'))}</h3>
						<div class="nccb-muted">${escapeHtml(tr('Group overrides can be configured for any group. They only affect users with an assigned Seat in that group. If a user override exists, it wins.'))}</div>
						<div class="nccb-row">
							<label for="nccb-group-override-group">${escapeHtml(tr('Group'))}</label>
							<select id="nccb-group-override-group">
								<option value="">${escapeHtml(tr('Please select a group'))}</option>
							</select>
							<label for="nccb-group-override-priority">${escapeHtml(tr('Priority'))}</label>
							<input id="nccb-group-override-priority" type="number" min="1" max="9999" value="100">
							<button id="nccb-group-override-save" class="button">${escapeHtml(tr('Save group overrides'))}</button>
						</div>
						<div class="nccb-muted">${escapeHtml(tr('Lower number wins if multiple group overrides match a user.'))}</div>
						<div id="nccb-group-override-message" class="nccb-muted" role="status"></div>
						<div class="nccb-tabbar nccb-tabbar--sub">
							<button class="button active" data-group-override-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-group-override-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
							<button class="button" data-group-override-tab-button="email_signature">${escapeHtml(tr('Email signature'))}</button>
						</div>
						<section data-group-override-tab-panel="share">
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-group-override-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-group-override-tab-panel="talk" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-group-override-tbody-talk"></tbody>
								</table>
							</div>
						</section>
						<section data-group-override-tab-panel="email_signature" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-group-override-tbody-email-signature"></tbody>
								</table>
							</div>
						</section>
					</div>
				</section>

				<section class="nccb-group-panel" data-group-tab-panel="overrides" hidden>
					<div class="nccb-section">
						<h3>${escapeHtml(tr('User overrides'))}</h3>
						<div class="nccb-muted">${escapeHtml(tr('Individual seat users can differ from defaults.'))}</div>
						<div class="nccb-row">
							<label for="nccb-override-user">${escapeHtml(tr('Seat user'))}</label>
							<select id="nccb-override-user">
								<option value="">${escapeHtml(tr('Please select a seat user'))}</option>
							</select>
							<button id="nccb-override-save" class="button">${escapeHtml(tr('Save overrides'))}</button>
						</div>
						<div id="nccb-override-message" class="nccb-muted" role="status"></div>
						<div class="nccb-tabbar nccb-tabbar--sub">
							<button class="button active" data-override-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-override-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
							<button class="button" data-override-tab-button="email_signature">${escapeHtml(tr('Email signature'))}</button>
						</div>
						<section data-override-tab-panel="share">
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-override-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-override-tab-panel="talk" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-override-tbody-talk"></tbody>
								</table>
							</div>
						</section>
						<section data-override-tab-panel="email_signature" hidden>
							<div class="nccb-scroll nccb-scroll--settings">
								<table class="nccb-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccb-override-tbody-email-signature"></tbody>
								</table>
							</div>
						</section>
					</div>
				</section>
			</section>

			<section class="nccb-main-panel" data-main-tab-panel="advanced" hidden>
				<div class="nccb-tabbar">
					<button class="button active" data-advanced-tab-button="delegation">${escapeHtml(tr('NCC Admin Delegation'))}</button>
					<button class="button" data-advanced-tab-button="delegation-overview">${escapeHtml(tr('Delegation overview'))}</button>
				</div>

				<section class="nccb-group-panel" data-advanced-tab-panel="delegation">
					<div class="nccb-section">
						<h3>${escapeHtml(tr('NCC Admin Delegation'))}</h3>
						<div class="nccb-muted">${escapeHtml(tr('Delegate NC Connector administration to selected users. Nextcloud administrators always keep full access.'))}</div>
						<div id="nccb-delegation-message" class="nccb-muted" role="status"></div>
						<div class="nccb-row">
							<label for="nccb-delegation-user">${escapeHtml(tr('Delegation user'))}</label>
							<select id="nccb-delegation-user" class="nccb-delegation-user-select">
								<option value="">${escapeHtml(tr('Select user'))}</option>
							</select>
						</div>
						<div class="nccb-delegation-permissions">
							${renderPermissionMatrix([])}
						</div>
						<div class="nccb-row">
							<button id="nccb-delegation-save" class="button">${escapeHtml(tr('Save delegation'))}</button>
							<button id="nccb-delegation-remove" class="button">${escapeHtml(tr('Remove delegation'))}</button>
						</div>
					</div>
				</section>

				<section class="nccb-group-panel" data-advanced-tab-panel="delegation-overview" hidden>
					<div class="nccb-section">
						<h3>${escapeHtml(tr('Delegation overview'))}</h3>
						<div class="nccb-muted">${escapeHtml(tr('Shows which users can administer NC Connector and which areas they can edit.'))}</div>
						<div id="nccb-delegation-overview" class="nccb-scroll nccb-scroll--settings"></div>
					</div>
				</section>
			</section>
		`
	}

	/**
	 * Bootstraps the complete admin settings screen:
	 * rendering, initial data load and event wiring.
	 *
	 * @returns {Promise<void>}
	 */
	async function main() {
		const root = document.getElementById('nccb-admin-settings')
		if (!root) {
			return
		}

		root.hidden = true
		render(root)
		setMainTab(root, 'general')
		setGroupTab(root, 'defaults')
		setDefaultsTab(root, 'share')
		setOverrideTab(root, 'share')
		setGroupOverrideTab(root, 'share')
		setAdvancedTab(root, 'delegation')

		const state = {
			admin: { is_nextcloud_admin: false, permissions: [] },
			schema: {},
			defaults: {},
			defaultModes: {},
			overrides: {},
			groupOverrides: {},
			groupOverridePriority: 100,
			defaultTemplateAssets: {},
			overrideTemplateAssets: {},
			groupOverrideTemplateAssets: {},
			schemaTemplateAssets: {},
			recommendedApps: [],
			assignedSeats: [],
			delegations: [],
			delegationUsers: [],
			userPaging: {
				limit: 20,
				offset: 0,
				hasNext: false,
			},
		}

		const refs = {
			mainTabs: root.querySelectorAll('[data-main-tab-button]'),
			groupTabs: root.querySelectorAll('[data-group-tab-button]'),
			defaultTabs: root.querySelectorAll('[data-default-tab-button]'),
			overrideTabs: root.querySelectorAll('[data-override-tab-button]'),
			groupOverrideTabs: root.querySelectorAll('[data-group-override-tab-button]'),
			advancedTabs: root.querySelectorAll('[data-advanced-tab-button]'),
			modeInputs: root.querySelectorAll('input[name="nccb-license-mode"]'),
			proSettings: root.querySelector('#nccb-pro-settings'),
			licenseEmail: root.querySelector('#nccb-license-email'),
			licenseKey: root.querySelector('#nccb-license-key'),
			licenseSave: root.querySelector('#nccb-license-save'),
			licenseSync: root.querySelector('#nccb-license-sync'),
			licenseStatus: root.querySelector('#nccb-license-status'),
			licenseHint: root.querySelector('#nccb-license-hint'),
			licenseMessage: root.querySelector('#nccb-license-message'),
			proFunnel: root.querySelector('#nccb-pro-funnel'),
			backendUpdateStatus: root.querySelector('#nccb-backend-update-status'),
			recommendedApps: root.querySelector('#nccb-recommended-apps'),
			defaultMessage: root.querySelector('#nccb-default-message'),
			defaultTableShare: root.querySelector('#nccb-default-tbody-share'),
			defaultTableTalk: root.querySelector('#nccb-default-tbody-talk'),
			defaultTableEmailSignature: root.querySelector('#nccb-default-tbody-email-signature'),
			defaultSave: root.querySelector('#nccb-default-save'),
			seatUsage: root.querySelector('#nccb-seat-usage'),
			assignedSeatUsage: root.querySelector('#nccb-assigned-seat-usage'),
			assignedMessage: root.querySelector('#nccb-assigned-message'),
			seatMessage: root.querySelector('#nccb-seat-message'),
			groupSelect: root.querySelector('#nccb-group-select'),
			userSearch: root.querySelector('#nccb-user-search'),
			userTable: root.querySelector('#nccb-user-tbody'),
			userPrev: root.querySelector('#nccb-user-prev'),
			userNext: root.querySelector('#nccb-user-next'),
			userPage: root.querySelector('#nccb-user-page'),
			bulkAssign: root.querySelector('#nccb-seat-bulk-assign'),
			bulkUnassign: root.querySelector('#nccb-seat-bulk-unassign'),
			assignedSeats: root.querySelector('#nccb-assigned-seats'),
			seatReportDownload: root.querySelector('#nccb-seat-report-download'),
			groupOverrideGroup: root.querySelector('#nccb-group-override-group'),
			groupOverridePriority: root.querySelector('#nccb-group-override-priority'),
			groupOverrideSave: root.querySelector('#nccb-group-override-save'),
			groupOverrideMessage: root.querySelector('#nccb-group-override-message'),
			groupOverrideTableShare: root.querySelector('#nccb-group-override-tbody-share'),
			groupOverrideTableTalk: root.querySelector('#nccb-group-override-tbody-talk'),
			groupOverrideTableEmailSignature: root.querySelector('#nccb-group-override-tbody-email-signature'),
			overrideUser: root.querySelector('#nccb-override-user'),
			overrideSave: root.querySelector('#nccb-override-save'),
			overrideMessage: root.querySelector('#nccb-override-message'),
			overrideTableShare: root.querySelector('#nccb-override-tbody-share'),
			overrideTableTalk: root.querySelector('#nccb-override-tbody-talk'),
			overrideTableEmailSignature: root.querySelector('#nccb-override-tbody-email-signature'),
			delegationUser: root.querySelector('#nccb-delegation-user'),
			delegationSave: root.querySelector('#nccb-delegation-save'),
			delegationRemove: root.querySelector('#nccb-delegation-remove'),
			delegationMessage: root.querySelector('#nccb-delegation-message'),
			delegationOverview: root.querySelector('#nccb-delegation-overview'),
		}

		let licenseSnapshot = null
		let searchTimer = null
		const fullAdminFallback = root.dataset.fullAdminFallback === '1'

		const setTabVisibility = (buttonAttr, panelAttr, name, visible) => {
			const button = root.querySelector(`[${buttonAttr}="${name}"]`)
			const panel = root.querySelector(`[${panelAttr}="${name}"]`)
			if (button instanceof HTMLElement) {
				button.hidden = !visible
			}
			if (panel instanceof HTMLElement) {
				if (!visible) {
					panel.hidden = true
				} else if (button instanceof HTMLElement && button.classList.contains('active')) {
					panel.hidden = false
				}
			}
		}

		const setFirstVisibleTab = (buttonAttr, setTab) => {
			const button = Array.from(root.querySelectorAll(`[${buttonAttr}]`))
				.find((candidate) => candidate instanceof HTMLElement && !candidate.hidden)
			if (button instanceof HTMLElement) {
				setTab(root, button.getAttribute(buttonAttr))
			}
		}

		const applySettingRowVisibility = () => {
			if (state.admin?.is_nextcloud_admin) {
				root.querySelectorAll('[data-default-setting-key], [data-user-setting-key], [data-group-setting-key]').forEach((row) => {
					if (row instanceof HTMLElement) {
						row.hidden = false
					}
				})
				return
			}
			root.querySelectorAll('[data-default-setting-key]').forEach((row) => {
				if (row instanceof HTMLElement) {
					row.hidden = !canEditDefaultSetting(state, row.dataset.defaultSettingKey || '')
				}
			})
			root.querySelectorAll('[data-user-setting-key]').forEach((row) => {
				if (row instanceof HTMLElement) {
					row.hidden = !canEditUserOverrideSetting(state, row.dataset.userSettingKey || '')
				}
			})
			root.querySelectorAll('[data-group-setting-key]').forEach((row) => {
				if (row instanceof HTMLElement) {
					row.hidden = !canEditGroupOverrideSetting(state, row.dataset.groupSettingKey || '')
				}
			})
		}

		const applyAdminUiVisibility = () => {
			const isFullAdmin = Boolean(state.admin?.is_nextcloud_admin)
			setTabVisibility('data-main-tab-button', 'data-main-tab-panel', 'general', isFullAdmin)
			setTabVisibility('data-main-tab-button', 'data-main-tab-panel', 'advanced', isFullAdmin)
			if (!isFullAdmin) {
				setMainTab(root, 'group')
			}

			const defaultCategories = ['share', 'talk', 'email_signature']
			defaultCategories.forEach((category) => {
				setTabVisibility(
					'data-default-tab-button',
					'data-default-tab-panel',
					category,
					hasAnyAdminPermission(state, permissionsForCategory(category, ['policy', 'templates']))
				)
			})
			setTabVisibility('data-group-tab-button', 'data-group-tab-panel', 'defaults', defaultCategories.some((category) => hasAnyAdminPermission(state, permissionsForCategory(category, ['policy', 'templates']))))
			setTabVisibility('data-group-tab-button', 'data-group-tab-panel', 'seats', isFullAdmin)
			setTabVisibility('data-group-tab-button', 'data-group-tab-panel', 'assigned', canReadAssignedSeatOverview(state))
			setTabVisibility('data-group-tab-button', 'data-group-tab-panel', 'group-overrides', defaultCategories.some((category) => hasAnyAdminPermission(state, permissionsForCategory(category, ['group_overrides']))))
			setTabVisibility('data-group-tab-button', 'data-group-tab-panel', 'overrides', canUseAnyUserOverridePanel(state))
			if (refs.seatReportDownload instanceof HTMLButtonElement) {
				const reportRow = refs.seatReportDownload.closest('.nccb-row')
				if (reportRow instanceof HTMLElement) {
					reportRow.hidden = !isFullAdmin
				}
			}
			setFirstVisibleTab('data-group-tab-button', setGroupTab)

			defaultCategories.forEach((category) => {
				setTabVisibility('data-group-override-tab-button', 'data-group-override-tab-panel', category, hasAnyAdminPermission(state, permissionsForCategory(category, ['group_overrides'])))
				setTabVisibility('data-override-tab-button', 'data-override-tab-panel', category, hasAnyAdminPermission(state, userOverridePermissionsForCategory(category)))
			})
			setFirstVisibleTab('data-default-tab-button', setDefaultsTab)
			setFirstVisibleTab('data-group-override-tab-button', setGroupOverrideTab)
			setFirstVisibleTab('data-override-tab-button', setOverrideTab)
			applySettingRowVisibility()
		}

		templateAssetRefreshHandler = async (wrapper) => {
			if (!(wrapper instanceof HTMLElement)) {
				return
			}

			const control = getTemplateControl(wrapper)
			const settingKey = String(wrapper.dataset.settingKey || '')
			const prefix = String(wrapper.dataset.prefix || '')
			if (!control || !settingKey || !state.schema[settingKey]) {
				return
			}
			if (wrapper.dataset.templateAssetSync === '1') {
				return
			}

			const isModalDraft = templateEditorModalState.wrapper === wrapper
			const refreshValue = getTemplateRefreshValue(wrapper, control)
			const externalSources = extractExternalImageSourcesFromHtml(refreshValue)
			if (externalSources.length === 0) {
				return
			}

			const currentAssetMap = getEffectiveTemplateAssetMap(wrapper)
			const hasMissingAssets = externalSources.some((source) => !currentAssetMap[source])
			if (!hasMissingAssets) {
				return
			}

			wrapper.dataset.templateAssetSync = '1'
			try {
				if (prefix === 'default') {
					const response = isModalDraft
						? await api.previewDefaultTemplateAssets(settingKey, refreshValue)
						: await api.saveDefaults({
							[settingKey]: {
								mode: 'default',
								value: readSettingControl(root, 'default', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						state.defaults = response.defaults || state.defaults
						state.defaultModes = response.default_modes || state.defaultModes
						state.defaultTemplateAssets = response.template_assets || state.defaultTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						control.value = String(state.defaults?.[settingKey] ?? control.value)
						setTemplateAssetMap(wrapper, state.defaultTemplateAssets?.[settingKey] || {})
						setTemplateDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditorModalState.assetMap = response.template_assets?.[settingKey] || {}
					}
				} else if (prefix === 'override' && refs.overrideUser.value) {
					const modeSelect = root.querySelector(`.nccb-user-mode[data-setting-key="${settingKey}"]`)
					if (!(modeSelect instanceof HTMLSelectElement) || modeSelect.value !== 'forced') {
						return
					}
					const response = isModalDraft
						? await api.previewUserTemplateAssets(refs.overrideUser.value, settingKey, refreshValue)
						: await api.saveUserOverrides(refs.overrideUser.value, {
							[settingKey]: {
								mode: 'forced',
								value: readSettingControl(root, 'override', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						mergeSchema(state, response.schema)
						state.overrides = response.items || state.overrides
						state.overrideTemplateAssets = response.template_assets || state.overrideTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						const updatedItem = state.overrides?.[settingKey]
						if (updatedItem?.mode === 'forced') {
							control.value = String(updatedItem.value ?? control.value)
						}
						setTemplateAssetMap(wrapper, state.overrideTemplateAssets?.[settingKey] || {})
						setTemplateDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditorModalState.assetMap = response.template_assets?.[settingKey] || {}
					}
				} else if (prefix === 'group-override' && refs.groupOverrideGroup.value) {
					const modeSelect = root.querySelector(`.nccb-group-mode[data-setting-key="${settingKey}"]`)
					if (!(modeSelect instanceof HTMLSelectElement) || modeSelect.value !== 'forced') {
						return
					}
					const priority = Number.parseInt(String(refs.groupOverridePriority.value || state.groupOverridePriority || 100), 10) || 100
					const response = isModalDraft
						? await api.previewGroupTemplateAssets(refs.groupOverrideGroup.value, priority, settingKey, refreshValue)
						: await api.saveGroupOverrides(refs.groupOverrideGroup.value, priority, {
							[settingKey]: {
								mode: 'forced',
								value: readSettingControl(root, 'group-override', settingKey, state.schema[settingKey]),
							},
						})
					if (!isModalDraft) {
						mergeSchema(state, response.schema)
						state.groupOverridePriority = Number.parseInt(String(response.priority ?? priority), 10) || priority
						refs.groupOverridePriority.value = String(state.groupOverridePriority)
						state.groupOverrides = response.items || state.groupOverrides
						state.groupOverrideTemplateAssets = response.template_assets || state.groupOverrideTemplateAssets
						state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
						const updatedItem = state.groupOverrides?.[settingKey]
						if (updatedItem?.mode === 'forced') {
							control.value = String(updatedItem.value ?? control.value)
						}
						setTemplateAssetMap(wrapper, state.groupOverrideTemplateAssets?.[settingKey] || {})
						setTemplateDefaultAssetMap(wrapper, state.schemaTemplateAssets?.[settingKey] || {})
					} else {
						templateEditorModalState.assetMap = response.template_assets?.[settingKey] || {}
					}
				} else {
					return
				}

				const editor = getActiveTemplateEditor(wrapper)
				if (editor) {
					editor.setContent(toEditorTemplateHtml(refreshValue, getEffectiveTemplateAssetMap(wrapper)))
				}
			} catch (error) {
				console.error('nccb template asset refresh failed', settingKey, error)
			} finally {
				delete wrapper.dataset.templateAssetSync
			}
		}

		const updatePager = (count) => {
			const page = Math.floor(state.userPaging.offset / state.userPaging.limit) + 1
			const from = count > 0 ? state.userPaging.offset + 1 : 0
			const to = state.userPaging.offset + count
			refs.userPage.textContent = `${tr('Page')} ${page} (${from}-${to})`
			refs.userPrev.disabled = state.userPaging.offset <= 0
			refs.userNext.disabled = !state.userPaging.hasNext
		}

		const updateModeUi = (snapshot) => {
			const mode = snapshot?.mode === 'pro' ? 'pro' : 'community'
			refs.modeInputs.forEach((radio) => {
				radio.checked = radio.value === mode
			})
			refs.proSettings.hidden = mode !== 'pro'
			refs.licenseSync.disabled = mode !== 'pro' || !snapshot?.has_credentials
		}

		const renderLicenseStatus = (snapshot) => {
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

		const renderProFunnel = (snapshot) => {
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

		const renderBackendUpdateStatus = (status) => {
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

		const renderRecommendedApps = (apps) => {
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

		const renderSeatUsage = (seatStatus) => {
			const seats = seatStatus || { total: 0, assigned: 0, active_assigned: 0, suspended_assigned: 0, free: 0, overlicensed: false, overlicensed_by: 0 }
			let text = `${tr('Seats available')}: ${seats.total} | ${tr('Active used')}: ${seats.active_assigned ?? seats.assigned} | ${tr('Paused')}: ${seats.suspended_assigned ?? 0} | ${tr('Free')}: ${seats.free}`
			if (seats.overlicensed) {
				text += ` | ${tr('Overlicensed by')}: ${seats.overlicensed_by}`
			}
			refs.seatUsage.textContent = text
			refs.assignedSeatUsage.textContent = text
		}

		const fillOverrideUsers = (assignedSeats) => {
			const prev = refs.overrideUser.value
			refs.overrideUser.innerHTML = `<option value="">${escapeHtml(tr('Please select a seat user'))}</option>`
			const sorted = [...assignedSeats].sort((a, b) => (a.display_name || a.user_id).localeCompare(b.display_name || b.user_id))
			sorted.forEach((seat) => {
				const option = document.createElement('option')
				option.value = seat.user_id
				option.textContent = seat.display_name ? `${seat.display_name} (${seat.user_id})` : seat.user_id
				refs.overrideUser.appendChild(option)
			})
			if (prev && sorted.some((seat) => seat.user_id === prev)) {
				refs.overrideUser.value = prev
			} else {
				refs.overrideUser.value = ''
				state.overrides = {}
				state.overrideTemplateAssets = {}
			}
			renderOverrideTables(root, refs, state, refs.overrideUser.value)
		}

		const fillGroupOverrideGroups = (groups) => {
			const prev = refs.groupOverrideGroup.value
			refs.groupOverrideGroup.innerHTML = `<option value="">${escapeHtml(tr('Please select a group'))}</option>`
			const sorted = [...groups].sort((a, b) => (a.display_name || a.group_id).localeCompare(b.display_name || b.group_id))
			sorted.forEach((group) => {
				const option = document.createElement('option')
				option.value = group.group_id
				option.textContent = group.display_name ? `${group.display_name} (${group.group_id})` : group.group_id
				refs.groupOverrideGroup.appendChild(option)
			})
			if (prev && sorted.some((group) => group.group_id === prev)) {
				refs.groupOverrideGroup.value = prev
			} else {
				refs.groupOverrideGroup.value = ''
				state.groupOverrides = {}
				state.groupOverrideTemplateAssets = {}
				state.groupOverridePriority = 100
				refs.groupOverridePriority.value = '100'
			}
			renderGroupOverrideTables(root, refs, state, refs.groupOverrideGroup.value)
		}

		const selectedDelegation = () => {
			const userId = refs.delegationUser?.value || ''
			return state.delegations.find((item) => item.user_id === userId) || null
		}

		const renderDelegationSelection = () => {
			const delegation = selectedDelegation()
			const permissions = delegation?.permissions || []
			root.querySelector('[data-delegation-permissions]')?.replaceWith(htmlToElement(renderPermissionMatrix(permissions)))
			refs.delegationSave.disabled = !refs.delegationUser.value
			refs.delegationRemove.disabled = !refs.delegationUser.value || !delegation
		}

		const fillDelegationUsers = (users) => {
			const prev = refs.delegationUser.value
			refs.delegationUser.innerHTML = `<option value="">${escapeHtml(tr('Select user'))}</option>`
			;(users || []).forEach((user) => {
				const option = document.createElement('option')
				option.value = user.user_id
				option.textContent = user.display_name ? `${user.display_name} (${user.user_id})` : user.user_id
				option.disabled = Boolean(user.is_nextcloud_admin)
				refs.delegationUser.appendChild(option)
			})
			if (prev && Array.from(refs.delegationUser.options).some((option) => option.value === prev && !option.disabled)) {
				refs.delegationUser.value = prev
			}
			renderDelegationSelection()
		}

		const refreshDelegations = async () => {
			if (!state.admin?.is_nextcloud_admin) {
				return
			}
			const response = await api.loadDelegations()
			state.delegations = response.items || []
			renderDelegationOverview(refs.delegationOverview, state.delegations)
			renderDelegationSelection()
		}

		const loadDelegationUsers = async () => {
			if (!state.admin?.is_nextcloud_admin) {
				return
			}
			const users = []
			let offset = 0
			const limit = 200
			while (true) {
				const response = await api.loadUsers('', '', limit, offset)
				const items = response.items || []
				users.push(...items)
				if (items.length < limit) {
					break
				}
				offset += limit
			}
			state.delegationUsers = users
			fillDelegationUsers(state.delegationUsers)
		}

		const attachSeatHandlers = () => {
			refs.userTable.querySelectorAll('tr[data-user-id]').forEach((row) => {
				const userId = row.getAttribute('data-user-id')
				const checkbox = row.querySelector('.nccb-seat-toggle')
				checkbox.addEventListener('change', async () => {
					setMessage(refs.seatMessage, '', '')
					try {
						await api.setSeat(userId, checkbox.checked)
						await refreshSeatsAndUsers(false)
						setMessage(refs.seatMessage, tr('Seat saved.'), 'success')
					} catch (error) {
						console.error('nccb seat save failed', userId, error)
						checkbox.checked = !checkbox.checked
						setMessage(refs.seatMessage, error.message || tr('Failed to save seat.'), 'error')
					}
				})
			})
		}

		const refreshLicense = async () => {
			licenseSnapshot = await api.loadLicense()
			if (licenseSnapshot.email && !refs.licenseEmail.value) {
				refs.licenseEmail.value = licenseSnapshot.email
			}
			updateModeUi(licenseSnapshot)
			renderLicenseStatus(licenseSnapshot)
			renderProFunnel(licenseSnapshot)
			setMessage(refs.licenseMessage, '', '')
		}

		const refreshBackendUpdateStatus = async () => {
			const status = await api.loadBackendUpdate()
			renderBackendUpdateStatus(status)
		}

		const refreshGroups = async () => {
			const response = await api.loadGroups()
			refs.groupSelect.innerHTML = `<option value="">${escapeHtml(tr('All users'))}</option>`
			;(response.items || []).forEach((group) => {
				const option = document.createElement('option')
				option.value = group.group_id
				option.textContent = `${group.display_name} (${group.group_id})`
				refs.groupSelect.appendChild(option)
			})
			fillGroupOverrideGroups(response.items || [])
		}

		const refreshUsers = async (resetPaging = false) => {
			if (resetPaging) {
				state.userPaging.offset = 0
			}
			const response = await api.loadUsers(refs.userSearch.value, refs.groupSelect.value, state.userPaging.limit + 1, state.userPaging.offset)
			const adminSelfExcludedMessage = `${tr('Your own admin account is not shown here because administrator accounts cannot receive seats.')} ${tr('Use a separate non-admin daily-work account for seat assignment, and keep a dedicated admin account for administration tasks.')}`
			if (response?.hints?.admin_self_excluded) {
				setMessage(refs.seatMessage, adminSelfExcludedMessage, '')
			} else if (refs.seatMessage.textContent === adminSelfExcludedMessage) {
				setMessage(refs.seatMessage, '', '')
			}
			const fetched = response.items || []
			state.userPaging.hasNext = fetched.length > state.userPaging.limit
			const visible = state.userPaging.hasNext ? fetched.slice(0, state.userPaging.limit) : fetched
			renderUsers(refs.userTable, visible)
			updatePager(visible.length)
			attachSeatHandlers()
		}

		const loadAssignedSeats = async () => {
			const seats = []
			let offset = 0
			const limit = 200
			let seatStatus = null
			while (true) {
				const seatsPayload = await api.loadSeats(limit, offset)
				if (seatStatus === null) {
					seatStatus = seatsPayload?.seat_status || null
				}
				const items = seatsPayload.items || []
				seats.push(...items)
				if (items.length < limit) {
					break
				}
				offset += limit
			}
			return { seats, seatStatus }
		}

		const refreshAssignedSeatOverview = async () => {
			const { seats, seatStatus } = await loadAssignedSeats()
			state.assignedSeats = seats
			renderSeatUsage(seatStatus)
			renderAssignedSeats(refs.assignedSeats, seats)
			if (canUseAnyUserOverridePanel(state)) {
				fillOverrideUsers(seats)
			}
		}

		const refreshSeatsAndUsers = async (resetPaging = false) => {
			await refreshUsers(resetPaging)
			await refreshAssignedSeatOverview()
		}

		const refreshDefaults = async () => {
			removeTemplateEditorsByPrefix('default')
			const response = await api.loadDefaults()
			state.schema = response.schema || {}
			state.defaults = response.defaults || {}
			state.defaultModes = response.default_modes || {}
			state.defaultTemplateAssets = response.template_assets || {}
			state.schemaTemplateAssets = response.schema_template_assets || {}
			state.recommendedApps = response.recommended_apps || []
			renderRecommendedApps(state.recommendedApps)
			renderDefaultsRows(refs.defaultTableShare, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'share')
			renderDefaultsRows(refs.defaultTableTalk, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'talk')
			renderDefaultsRows(refs.defaultTableEmailSignature, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'email_signature')
			syncDefaultControlState(root)
			attachDefaultModeHandlers(root)
			attachAttachmentDependencyHandlers(root)
			attachSharePasswordDependencyHandlers(root)
			attachTemplateLanguageDependencyHandlers(root, 'default')
			attachEmailSignatureDependencyHandlers(root, 'default')
			attachTemplateEditorHandlers(root)
			renderGroupOverrideTables(root, refs, state, refs.groupOverrideGroup.value)
			renderOverrideTables(root, refs, state, refs.overrideUser.value)
			applySettingRowVisibility()
		}

		const refreshOverrides = async (userId) => {
			if (!userId) {
				state.overrides = {}
				state.overrideTemplateAssets = {}
			} else {
				const response = await api.loadUserOverrides(userId)
				mergeSchema(state, response.schema)
				state.overrides = response.items || {}
				state.overrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
			}
			renderOverrideTables(root, refs, state, userId)
			applySettingRowVisibility()
		}

		const refreshGroupOverrides = async (groupId) => {
			if (!groupId) {
				state.groupOverrides = {}
				state.groupOverrideTemplateAssets = {}
				state.groupOverridePriority = 100
				refs.groupOverridePriority.value = '100'
			} else {
				const response = await api.loadGroupOverrides(groupId)
				mergeSchema(state, response.schema)
				state.groupOverridePriority = Number.parseInt(String(response.priority ?? 100), 10) || 100
				refs.groupOverridePriority.value = String(state.groupOverridePriority)
				state.groupOverrides = response.items || {}
				state.groupOverrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
			}
			renderGroupOverrideTables(root, refs, state, groupId)
			applySettingRowVisibility()
		}

		const isAttachmentThresholdEnabled = (prefix) => {
			const alwaysViaConnector = root.querySelector(`.nccb-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_always_via_ncconnector"]`)
			if (alwaysViaConnector?.checked) {
				return false
			}
			const toggle = root.querySelector(`.nccb-threshold-enabled[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
			return toggle ? Boolean(toggle.checked) : true
		}

		const readLayerValue = (prefix, key) => key === 'attachments_min_size_mb'
			&& !isAttachmentThresholdEnabled(prefix)
			? null
			: readSettingControl(root, prefix, key, state.schema[key])

		const collectSettingLayerPayload = (config, options = {}) => {
			const payload = {}
			sortedSettingKeys(state.schema).forEach((key) => {
				if (options.skipUserOverrideOnly && isUserOverrideOnlySettingKey(key)) {
					return
				}
				if (!options.canEditSetting?.(state, key)) {
					return
				}
				if (options.defaultLayer) {
					const addonToggle = root.querySelector(`.nccb-addon-changeable[data-setting-key="${key}"]`)
					const isAddonChangeable = !isTemplateEditorSettingKey(key) && Boolean(addonToggle?.checked)
					payload[key] = {
						mode: isAddonChangeable ? 'user_choice' : 'default',
						value: readLayerValue(config.prefix, key),
					}
					return
				}
				const modeKey = getTemplateModeControlKey(key)
				const mode = root.querySelector(`${config.modeSelector}[data-setting-key="${modeKey}"]`)?.value || 'inherit'
				if (mode !== 'forced') {
					payload[key] = { mode: 'inherit' }
					return
				}
				payload[key] = {
					mode: 'forced',
					value: readLayerValue(config.prefix, key),
				}
			})
			return payload
		}

		const collectDefaultPayload = () => collectSettingLayerPayload(SETTING_LAYER_UI.defaults, {
			skipUserOverrideOnly: true,
			canEditSetting: canEditDefaultSetting,
			defaultLayer: true,
		})

		const collectOverridePayload = () => collectSettingLayerPayload(SETTING_LAYER_UI.userOverride, {
			canEditSetting: canEditUserOverrideSetting,
		})

		const collectGroupOverridePayload = () => collectSettingLayerPayload(SETTING_LAYER_UI.groupOverride, {
			skipUserOverrideOnly: true,
			canEditSetting: canEditGroupOverrideSetting,
		})

		const loadAllUsersInScope = async () => {
			const users = []
			let offset = 0
			const limit = 200
			while (true) {
				const response = await api.loadUsers(refs.userSearch.value, refs.groupSelect.value, limit, offset)
				const items = response.items || []
				users.push(...items)
				if (items.length < limit) {
					break
				}
				offset += limit
			}
			return users
		}

		refs.mainTabs.forEach((button) => button.addEventListener('click', () => setMainTab(root, button.getAttribute('data-main-tab-button'))))
		refs.groupTabs.forEach((button) => button.addEventListener('click', () => setGroupTab(root, button.getAttribute('data-group-tab-button'))))
		refs.defaultTabs.forEach((button) => button.addEventListener('click', () => setDefaultsTab(root, button.getAttribute('data-default-tab-button'))))
		refs.overrideTabs.forEach((button) => button.addEventListener('click', () => setOverrideTab(root, button.getAttribute('data-override-tab-button'))))
		refs.groupOverrideTabs.forEach((button) => button.addEventListener('click', () => setGroupOverrideTab(root, button.getAttribute('data-group-override-tab-button'))))
		refs.advancedTabs.forEach((button) => button.addEventListener('click', () => setAdvancedTab(root, button.getAttribute('data-advanced-tab-button'))))
		root.addEventListener('click', async (event) => {
			const target = event.target instanceof Element ? event.target.closest('[data-group-override-link], [data-user-override-link]') : null
			if (!(target instanceof HTMLElement)) {
				return
			}
			event.preventDefault()
			const groupId = String(target.dataset.groupOverrideLink || '')
			const userId = String(target.dataset.userOverrideLink || '')
			if (groupId) {
				setMainTab(root, 'group')
				setGroupTab(root, 'group-overrides')
				refs.groupOverrideGroup.value = groupId
				await refreshGroupOverrides(groupId).catch((error) => {
					console.error('nccb linked group override load failed', groupId, error)
					setMessage(refs.groupOverrideMessage, error.message || tr('Failed to load group overrides.'), 'error')
				})
				refs.groupOverrideGroup.focus()
				refs.groupOverrideGroup.scrollIntoView({ block: 'center', behavior: 'smooth' })
				return
			}
			if (!userId) {
				return
			}
			setMainTab(root, 'group')
			setGroupTab(root, 'overrides')
			refs.overrideUser.value = userId
			await refreshOverrides(userId).catch((error) => {
				console.error('nccb linked user override load failed', userId, error)
				setMessage(refs.overrideMessage, error.message || tr('Failed to load overrides.'), 'error')
			})
			refs.overrideUser.focus()
			refs.overrideUser.scrollIntoView({ block: 'center', behavior: 'smooth' })
		})

		refs.modeInputs.forEach((radio) => radio.addEventListener('change', async () => {
			if (!radio.checked) {
				return
			}
			setMessage(refs.licenseMessage, '', '')
			try {
				licenseSnapshot = await api.saveMode(radio.value)
				updateModeUi(licenseSnapshot)
				renderLicenseStatus(licenseSnapshot)
				renderProFunnel(licenseSnapshot)
				await refreshSeatsAndUsers(false)
				setMessage(refs.licenseMessage, tr('Operating mode saved.'), 'success')
			} catch (error) {
				console.error('nccb operating mode save failed', radio.value, error)
				updateModeUi(licenseSnapshot)
				setMessage(refs.licenseMessage, error.message || tr('Failed to save operating mode.'), 'error')
			}
		}))

		refs.licenseSave.addEventListener('click', async () => {
			setMessage(refs.licenseMessage, '', '')
			try {
				await api.saveCredentials(refs.licenseEmail.value || '', refs.licenseKey.value || '')
				let syncError = null
				try {
					await api.syncLicense()
				} catch (error) {
					console.error('nccb automatic license sync failed after credential save', error)
					syncError = error
				}

				refs.licenseKey.value = ''
				await refreshLicense()
				await refreshSeatsAndUsers(false)

				if (syncError) {
					setMessage(refs.licenseMessage, `${tr('License data saved, automatic sync failed')}: ${syncError.message || tr('Unknown error')}`, 'error')
					return
				}
				setMessage(refs.licenseMessage, tr('License data saved and synchronized.'), 'success')
			} catch (error) {
				console.error('nccb license credential save failed', error)
				setMessage(refs.licenseMessage, error.message || tr('Failed to save license data.'), 'error')
			}
		})

		refs.licenseSync.addEventListener('click', async () => {
			setMessage(refs.licenseMessage, '', '')
			try {
				await api.syncLicense()
				await refreshLicense()
				await refreshSeatsAndUsers(false)
				setMessage(refs.licenseMessage, tr('License synchronized.'), 'success')
			} catch (error) {
				console.error('nccb manual license sync failed', error)
				setMessage(refs.licenseMessage, error.message || tr('Synchronization failed.'), 'error')
			}
		})

		refs.delegationUser.addEventListener('change', renderDelegationSelection)
		refs.delegationSave.addEventListener('click', async () => {
			if (!refs.delegationUser.value) {
				setMessage(refs.delegationMessage, tr('Select a user to edit delegation.'), 'error')
				return
			}
			const permissions = readDelegationPermissions(root)
			if (permissions.length === 0) {
				setMessage(refs.delegationMessage, `${tr('Permissions')}: ${tr('No permissions')}`, 'error')
				return
			}
			setMessage(refs.delegationMessage, '', '')
			try {
				await api.saveDelegation(
					refs.delegationUser.value,
					true,
					permissions
				)
				await refreshDelegations()
				setMessage(refs.delegationMessage, tr('Delegation saved.'), 'success')
			} catch (error) {
				console.error('nccb delegation save failed', refs.delegationUser.value, error)
				setMessage(refs.delegationMessage, error.message || tr('Failed to save delegation.'), 'error')
			}
		})
		refs.delegationRemove.addEventListener('click', async () => {
			if (!refs.delegationUser.value) {
				setMessage(refs.delegationMessage, tr('Select a user to edit delegation.'), 'error')
				return
			}
			setMessage(refs.delegationMessage, '', '')
			try {
				await api.deleteDelegation(refs.delegationUser.value)
				await refreshDelegations()
				renderDelegationSelection()
				setMessage(refs.delegationMessage, tr('Delegation removed.'), 'success')
			} catch (error) {
				console.error('nccb delegation remove failed', refs.delegationUser.value, error)
				setMessage(refs.delegationMessage, error.message || tr('Failed to remove delegation.'), 'error')
			}
		})

		refs.groupSelect.addEventListener('change', async () => refreshUsers(true).catch((error) => {
			console.error('nccb user refresh failed after group change', error)
			setMessage(refs.seatMessage, error.message || tr('Failed to load users.'), 'error')
		}))
		refs.userSearch.addEventListener('input', () => {
			if (searchTimer !== null) {
				clearTimeout(searchTimer)
			}
			searchTimer = window.setTimeout(() => {
				searchTimer = null
				refreshUsers(true).catch((error) => {
					console.error('nccb user refresh failed after search', error)
					setMessage(refs.seatMessage, error.message || tr('Failed to load users.'), 'error')
				})
			}, 250)
		})

		refs.userPrev.addEventListener('click', async () => {
			if (state.userPaging.offset <= 0) {
				return
			}
			state.userPaging.offset = Math.max(0, state.userPaging.offset - state.userPaging.limit)
			await refreshUsers(false).catch((error) => {
				console.error('nccb previous user page load failed', error)
				setMessage(refs.seatMessage, error.message || tr('Failed to change page.'), 'error')
			})
		})
		refs.userNext.addEventListener('click', async () => {
			if (!state.userPaging.hasNext) {
				return
			}
			state.userPaging.offset += state.userPaging.limit
			await refreshUsers(false).catch((error) => {
				console.error('nccb next user page load failed', error)
				setMessage(refs.seatMessage, error.message || tr('Failed to change page.'), 'error')
			})
		})

		refs.bulkAssign.addEventListener('click', async () => {
			setMessage(refs.seatMessage, '', '')
			try {
				const users = await loadAllUsersInScope()
				const targets = users.filter((user) => !user.has_seat)
				if (targets.length === 0) {
					setMessage(refs.seatMessage, tr('No additional seats required.'), 'success')
					return
				}
				const seatPayload = await api.loadSeats(1, 0)
				const free = Number.parseInt(String(seatPayload?.seat_status?.free ?? 0), 10)
				if (targets.length > free) {
					throw new Error(`${tr('Not enough free seats')}: ${free} ${tr('free')}, ${targets.length} ${tr('needed')}.`)
				}
				for (const user of targets) {
					await api.setSeat(user.user_id, true)
				}
				await refreshSeatsAndUsers(false)
				setMessage(refs.seatMessage, `${tr('Seats assigned')}: ${targets.length}`, 'success')
			} catch (error) {
				console.error('nccb bulk seat assignment failed', error)
				setMessage(refs.seatMessage, error.message || tr('Bulk assignment failed.'), 'error')
			}
		})

		refs.bulkUnassign.addEventListener('click', async () => {
			setMessage(refs.seatMessage, '', '')
			try {
				const users = await loadAllUsersInScope()
				const targets = users.filter((user) => user.has_seat)
				for (const user of targets) {
					await api.setSeat(user.user_id, false)
				}
				await refreshSeatsAndUsers(false)
				setMessage(refs.seatMessage, `${tr('Seats removed')}: ${targets.length}`, 'success')
			} catch (error) {
				console.error('nccb bulk seat removal failed', error)
				setMessage(refs.seatMessage, error.message || tr('Bulk removal failed.'), 'error')
			}
		})

		refs.seatReportDownload.addEventListener('click', async () => {
			setMessage(refs.assignedMessage, '', '')
			refs.seatReportDownload.disabled = true
			try {
				setMessage(refs.assignedMessage, tr('Generating seat report...'), '')
				const csv = await buildSeatReportCsv(state.assignedSeats || [], state.schema || {}, api)
				const timestamp = new Date().toISOString().replace(/[:T]/g, '-').replace(/\..+$/, '')
				downloadTextFile(`ncc_backend_4mc-seat-report-${timestamp}.csv`, csv, 'text/csv;charset=utf-8')
				setMessage(refs.assignedMessage, tr('Seat report downloaded.'), 'success')
			} catch (error) {
				console.error('nccb seat report download failed', error)
				setMessage(refs.assignedMessage, error.message || tr('Failed to download seat report.'), 'error')
			} finally {
				refs.seatReportDownload.disabled = false
			}
		})

		refs.defaultSave.addEventListener('click', async () => {
			setMessage(refs.defaultMessage, '', '')
			try {
				const response = await api.saveDefaults(collectDefaultPayload())
				state.defaults = response.defaults || state.defaults
				state.schema = response.schema || state.schema
				state.defaultModes = response.default_modes || state.defaultModes
				state.defaultTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
				state.recommendedApps = response.recommended_apps || state.recommendedApps
				renderRecommendedApps(state.recommendedApps)
				removeTemplateEditorsByPrefix('default')
				renderDefaultsRows(refs.defaultTableShare, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'share')
				renderDefaultsRows(refs.defaultTableTalk, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'talk')
				renderDefaultsRows(refs.defaultTableEmailSignature, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'email_signature')
				syncDefaultControlState(root)
				attachDefaultModeHandlers(root)
				attachAttachmentDependencyHandlers(root)
				attachSharePasswordDependencyHandlers(root)
				attachTemplateLanguageDependencyHandlers(root, 'default')
				attachEmailSignatureDependencyHandlers(root, 'default')
				attachTemplateEditorHandlers(root)
				setMessage(refs.defaultMessage, tr('Default settings saved.'), 'success')
				if (refs.overrideUser.value) {
					await refreshOverrides(refs.overrideUser.value)
				}
				if (refs.groupOverrideGroup.value) {
					await refreshGroupOverrides(refs.groupOverrideGroup.value)
				}
			} catch (error) {
				console.error('nccb default settings save failed', error)
				setMessage(refs.defaultMessage, error.message || tr('Failed to save defaults.'), 'error')
			}
		})

		refs.groupOverrideGroup.addEventListener('change', async () => {
			await refreshGroupOverrides(refs.groupOverrideGroup.value).catch((error) => {
				console.error('nccb group override load failed', refs.groupOverrideGroup.value, error)
				setMessage(refs.groupOverrideMessage, error.message || tr('Failed to load group overrides.'), 'error')
			})
		})
		refs.groupOverrideSave.addEventListener('click', async () => {
			if (!refs.groupOverrideGroup.value) {
				setMessage(refs.groupOverrideMessage, tr('Please select a group.'), 'error')
				return
			}
			setMessage(refs.groupOverrideMessage, '', '')
			try {
				const priority = Number.parseInt(String(refs.groupOverridePriority.value || state.groupOverridePriority || 100), 10) || 100
				const response = await api.saveGroupOverrides(refs.groupOverrideGroup.value, priority, collectGroupOverridePayload())
				mergeSchema(state, response.schema)
				state.groupOverridePriority = Number.parseInt(String(response.priority ?? priority), 10) || priority
				refs.groupOverridePriority.value = String(state.groupOverridePriority)
				state.groupOverrides = response.items || state.groupOverrides
				state.groupOverrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
				renderGroupOverrideTables(root, refs, state, refs.groupOverrideGroup.value)
				await refreshSeatsAndUsers(false)
				setMessage(refs.groupOverrideMessage, tr('Group overrides saved.'), 'success')
			} catch (error) {
				console.error('nccb group override save failed', refs.groupOverrideGroup.value, error)
				setMessage(refs.groupOverrideMessage, error.message || tr('Failed to save group overrides.'), 'error')
			}
		})

		refs.overrideUser.addEventListener('change', async () => {
			await refreshOverrides(refs.overrideUser.value).catch((error) => {
				console.error('nccb user override load failed', refs.overrideUser.value, error)
				setMessage(refs.overrideMessage, error.message || tr('Failed to load overrides.'), 'error')
			})
		})
		refs.overrideSave.addEventListener('click', async () => {
			if (!refs.overrideUser.value) {
				setMessage(refs.overrideMessage, tr('Please select a seat user.'), 'error')
				return
			}
			setMessage(refs.overrideMessage, '', '')
			try {
				const response = await api.saveUserOverrides(refs.overrideUser.value, collectOverridePayload())
				mergeSchema(state, response.schema)
				state.overrides = response.items || state.overrides
				state.overrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
				renderOverrideTables(root, refs, state, refs.overrideUser.value)
				await refreshSeatsAndUsers(false)
				setMessage(refs.overrideMessage, tr('Overrides saved.'), 'success')
			} catch (error) {
				console.error('nccb user override save failed', refs.overrideUser.value, error)
				setMessage(refs.overrideMessage, error.message || tr('Failed to save overrides.'), 'error')
			}
		})

		try {
			state.admin = await api.loadAdminMe()
		} catch (error) {
			console.error('nccb admin permission init failed', error)
			if (fullAdminFallback) {
				state.admin = { is_nextcloud_admin: true, permissions: [] }
			}
		}
		applyAdminUiVisibility()
		root.hidden = false
		if (state.admin?.is_nextcloud_admin) {
			try {
				await refreshLicense()
			} catch (error) {
				console.error('nccb license init failed', error)
			}
			try {
				await refreshBackendUpdateStatus()
			} catch (error) {
				console.error('nccb backend update status init failed', error)
				renderBackendUpdateStatus(null)
			}
			try {
				await refreshDelegations()
				await loadDelegationUsers()
			} catch (error) {
				console.error('nccb delegation init failed', error)
				setMessage(refs.delegationMessage, error.message || tr('Failed to load delegations.'), 'error')
			}
		}
		try {
			if (state.admin?.is_nextcloud_admin || hasAnyAdminPermission(state, [
				'share.group_overrides',
				'talk.group_overrides',
				'signature.group_overrides',
			])) {
				await refreshGroups()
			}
		} catch (error) {
			console.error('nccb group init failed', error)
			setMessage(refs.seatMessage, error.message || tr('Failed to load groups.'), 'error')
		}
		try {
			await refreshDefaults()
		} catch (error) {
			console.error('nccb defaults init failed', error)
			setMessage(refs.defaultMessage, error.message || tr('Failed to load default settings.'), 'error')
		}
		try {
			if (state.admin?.is_nextcloud_admin) {
				await refreshSeatsAndUsers(true)
			} else if (canReadAssignedSeatOverview(state)) {
				await refreshAssignedSeatOverview()
			}
		} catch (error) {
			console.error('nccb seat section init failed', error)
			setMessage(refs.seatMessage, error.message || tr('Failed to load seat section.'), 'error')
		}
	}

	void main()
})()
