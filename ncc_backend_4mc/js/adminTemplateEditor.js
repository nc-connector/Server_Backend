/**
 * Template editor orchestration for the admin UI.
 * Keeps TinyMCE, modal draft state, asset maps, and editor-only translations out of the main settings file.
 */
(() => {
	'use strict'
	const LEGACY_LINK_INTRO_ATTRIBUTE = 'data-nccb-legacy-link-intro'
	const LEGACY_LINK_LABEL_ATTRIBUTE = 'data-nccb-legacy-link-label'

	function createTemplateEditor(options = {}) {
		const tr = typeof options.tr === 'function' ? options.tr : (text) => text
		const escapeHtml = typeof options.escapeHtml === 'function' ? options.escapeHtml : (value) => String(value)
		const settingLabel = typeof options.settingLabel === 'function' ? options.settingLabel : (key) => String(key || '')
		const enumOptionLabel = typeof options.enumOptionLabel === 'function' ? options.enumOptionLabel : (_key, value) => String(value || '')
		const sanitizeTemplateHtml = typeof options.sanitizeTemplateHtml === 'function' ? options.sanitizeTemplateHtml : (html) => String(html || '')
		const rewriteImageSources = typeof options.rewriteImageSources === 'function' ? options.rewriteImageSources : () => {}
		const normalizeEditorImageSources = typeof options.normalizeEditorImageSources === 'function' ? options.normalizeEditorImageSources : () => {}
		const openPreview = typeof options.openPreview === 'function' ? options.openPreview : () => {}
		const emailSignatureTemplateKey = String(options.emailSignatureTemplateKey || '')
		const settingKeys = new Set((Array.isArray(options.settingKeys) ? options.settingKeys : []).map((key) => String(key || '')))
		const variablesBySetting = options.variablesBySetting && typeof options.variablesBySetting === 'object'
			? options.variablesBySetting
			: {}
		const translationLocales = Array.isArray(options.templateTranslationLocales)
			? options.templateTranslationLocales
			: []
		const translationPhrases = options.templateTranslationPhrases && typeof options.templateTranslationPhrases === 'object'
			? options.templateTranslationPhrases
			: {}
		const contentSecurityPolicy = String(options.contentSecurityPolicy || "default-src 'none'; img-src 'self' data: blob:; style-src 'unsafe-inline';")
		const saveButtonIds = {
			default: 'nccb-default-save',
			override: 'nccb-override-save',
			'group-override': 'nccb-group-override-save',
			...(options.saveButtonIds || {}),
		}

		const assetRefreshTimers = new WeakMap()
		let assetRefreshHandler = null
		const modalState = {
			wrapper: null,
			editorId: '',
			textarea: null,
			assetMap: {},
			languageSelect: null,
		}

		function isSettingKey(settingKey) {
			return settingKeys.has(String(settingKey || ''))
		}

		function getInactiveNote(settingKey) {
			return String(settingKey || '') === emailSignatureTemplateKey
				? 'Only active when signatures for new messages are enabled.'
				: 'Only active when language is set to Custom.'
		}

		function getVariablesForSetting(settingKey) {
			const key = String(settingKey || '')
			return variablesBySetting[key] || []
		}

		function getTinyMce() {
			if (typeof window.tinymce === 'object' && typeof window.tinymce.init === 'function') {
				return window.tinymce
			}
			return null
		}

		function getTranslationOptions() {
			return translationLocales.map((locale) => ({
				value: locale,
				label: enumOptionLabel('language_share_html_block', locale),
			}))
		}

		function getTranslationEntries(settingKey) {
			const phrases = translationPhrases[String(settingKey || '')]
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
					for (const locale of translationLocales) {
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

		function applyLegacyShareCopyMetadata(body, settingKey, targetLocale) {
			const phrases = translationPhrases[String(settingKey || '')]
			const root = body.firstElementChild
			if (!phrases?.share_intro_1 || !phrases?.share_intro_2 || !phrases?.download_link || !root) {
				return false
			}

			const locale = String(targetLocale || '')
			const intro = [phrases.share_intro_1[locale], phrases.share_intro_2[locale]]
				.map((value) => String(value || '').trim())
				.filter(Boolean)
				.join(' ')
			const label = String(phrases.download_link[locale] || '').trim()
			if (!intro || !label) {
				return false
			}

			const languageTag = locale
				.split('_')
				.map((part, index) => index === 0 ? part.toLowerCase() : part.toUpperCase())
				.join('-')
			const attributes = {
				lang: languageTag,
				[LEGACY_LINK_INTRO_ATTRIBUTE]: intro,
				[LEGACY_LINK_LABEL_ATTRIBUTE]: label,
			}
			let changed = false
			for (const [name, value] of Object.entries(attributes)) {
				if (root.getAttribute(name) === value) {
					continue
				}
				root.setAttribute(name, value)
				changed = true
			}
			return changed
		}

		function isTranslationToken(segment) {
			return /^\{[A-Z0-9_]+\}$/.test(segment) || /^https?:\/\//i.test(segment)
		}

		function replaceLiteral(value, search, replacement) {
			const rawValue = String(value || '')
			if (!search || search === replacement || !rawValue.includes(search)) {
				return rawValue
			}
			return rawValue.split(search).join(replacement)
		}

		function translateTextSegment(segment, settingKey, targetLocale) {
			const text = String(segment || '')
			if (!text.trim() || isTranslationToken(text.trim())) {
				return text
			}

			let translated = text
			for (const entry of getTranslationEntries(settingKey)) {
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
					.map((segment) => translateTextSegment(segment, settingKey, targetLocale))
					.join('')
				if (translatedText !== rawText) {
					node.nodeValue = translatedText
					changed = true
				}
			}
			changed = applyLegacyShareCopyMetadata(body, settingKey, targetLocale) || changed

			return {
				html: body.innerHTML,
				changed,
			}
		}

		function populateLanguageSelect(select) {
			if (!(select instanceof HTMLSelectElement)) {
				return
			}

			const optionHtml = ['<option value="">&mdash;</option>']
			for (const option of getTranslationOptions()) {
				optionHtml.push(`<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`)
			}
			select.innerHTML = optionHtml.join('')
			select.value = ''
		}

		function translateModalEditor(targetLocale) {
			const wrapper = modalState.wrapper
			const editor = getModalEditor()
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

		function ensureModal() {
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
							<button type="button" class="nccb-template-editor-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">&times;</button>
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
					closeModal()
					return
				}

				if (target.dataset.action === 'reset') {
					resetModalContent()
					return
				}

				if (target.dataset.action === 'save') {
					saveModalContent()
				}
			})
			modal.addEventListener('change', (event) => {
				const target = event.target
				if (!(target instanceof HTMLSelectElement) || target.dataset.action !== 'translate-language') {
					return
				}
				translateModalEditor(String(target.value || ''))
			})
			document.body.appendChild(modal)
			return modal
		}

		function getControl(wrapper) {
			return wrapper.querySelector('.nccb-setting-control')
		}

		function parseAssetMap(value) {
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

		function getAssetMap(wrapper) {
			return parseAssetMap(wrapper?.dataset?.templateAssets || '')
		}

		function getDefaultAssetMap(wrapper) {
			return parseAssetMap(wrapper?.dataset?.templateDefaultAssets || '')
		}

		function getEffectiveAssetMap(wrapper) {
			if (modalState.wrapper === wrapper) {
				return modalState.assetMap || {}
			}
			return getAssetMap(wrapper)
		}

		function setAssetMap(wrapper, assetMap) {
			if (wrapper?.dataset) {
				wrapper.dataset.templateAssets = JSON.stringify(assetMap || {})
			}
		}

		function setDefaultAssetMap(wrapper, assetMap) {
			if (wrapper?.dataset) {
				wrapper.dataset.templateDefaultAssets = JSON.stringify(assetMap || {})
			}
		}

		function setModalAssetMap(wrapper, assetMap) {
			if (modalState.wrapper === wrapper) {
				modalState.assetMap = assetMap || {}
			}
		}

		function isModalDraft(wrapper) {
			return modalState.wrapper === wrapper
		}

		function getRefreshValue(wrapper, control) {
			if (
				modalState.wrapper === wrapper
				&& modalState.textarea instanceof HTMLTextAreaElement
			) {
				return String(modalState.textarea.value || '')
			}
			return String(control?.value || '')
		}

		function toEditorHtml(rawHtml, assetMap = {}) {
			const sanitizedHtml = sanitizeTemplateHtml(rawHtml)
			const parser = new DOMParser()
			const doc = parser.parseFromString(sanitizedHtml, 'text/html')
			rewriteImageSources(doc, 'editor', assetMap)
			return doc.body ? doc.body.innerHTML : sanitizedHtml
		}

		function toPreviewHtml(rawHtml) {
			const sanitizedHtml = sanitizeTemplateHtml(rawHtml)
			const parser = new DOMParser()
			const doc = parser.parseFromString(sanitizedHtml, 'text/html')
			doc.querySelectorAll('img').forEach((img) => {
				img.removeAttribute('data-nccb-original-src')
			})
			return doc.body ? doc.body.innerHTML : sanitizedHtml
		}

		function fromEditorHtml(editorHtml) {
			const rawHtml = String(editorHtml || '')
			const parser = new DOMParser()
			const doc = parser.parseFromString(rawHtml, 'text/html')
			rewriteImageSources(doc, 'storage')
			return sanitizeTemplateHtml(doc.body ? doc.body.innerHTML : rawHtml)
		}

		function getInlineEditor(wrapper) {
			const control = getControl(wrapper)
			if (!control || !control.id || typeof window.tinymce !== 'object') {
				return null
			}
			return window.tinymce.get(control.id) || null
		}

		function getModalEditor() {
			if (!modalState.editorId || typeof window.tinymce !== 'object') {
				return null
			}
			return window.tinymce.get(modalState.editorId) || null
		}

		function getActiveEditor(wrapper) {
			if (modalState.wrapper === wrapper) {
				return getModalEditor()
			}
			return getInlineEditor(wrapper)
		}

		function resetModalContent() {
			const wrapper = modalState.wrapper
			if (!(wrapper instanceof HTMLElement)) {
				return
			}

			const control = getControl(wrapper)
			if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
				return
			}

			const defaultControl = wrapper.querySelector('.nccb-template-default')
			const defaultValue = String(defaultControl?.value ?? '')
			const targetLocale = modalState.languageSelect instanceof HTMLSelectElement
				? modalState.languageSelect.value
				: ''
			const resetValue = targetLocale
				? translateTemplateHtml(defaultValue, String(wrapper.dataset.settingKey || ''), targetLocale).html
				: defaultValue
			modalState.assetMap = { ...getDefaultAssetMap(wrapper) }
			const editor = getModalEditor()
			if (editor) {
				editor.setContent(toEditorHtml(resetValue, modalState.assetMap))
				if (modalState.textarea instanceof HTMLTextAreaElement) {
					modalState.textarea.value = resetValue
				}
				return
			}
			if (modalState.textarea instanceof HTMLTextAreaElement) {
				modalState.textarea.value = resetValue
			}
		}

		function saveModalContent() {
			const wrapper = modalState.wrapper
			if (!(wrapper instanceof HTMLElement)) {
				return
			}

			const control = getControl(wrapper)
			if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
				return
			}
			const editor = getModalEditor()
			if (editor) {
				control.value = fromEditorHtml(editor.getContent())
			} else if (modalState.textarea instanceof HTMLTextAreaElement) {
				control.value = fromEditorHtml(modalState.textarea.value || '')
			}

			const prefix = String(wrapper.dataset.prefix || 'default')
			const targetButton = document.getElementById(saveButtonIds[prefix] || saveButtonIds.default)
			if (targetButton instanceof HTMLButtonElement) {
				targetButton.click()
			}
		}

		function closeModal() {
			const modal = document.getElementById('nccb-template-editor-modal')
			const editor = getModalEditor()
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

			modalState.wrapper = null
			modalState.editorId = ''
			modalState.textarea = null
			modalState.assetMap = {}
			modalState.languageSelect = null
		}

		function openModal(wrapper) {
			if (!(wrapper instanceof HTMLElement)) {
				return
			}

			const control = getControl(wrapper)
			if (!(control instanceof HTMLTextAreaElement) || control.disabled) {
				return
			}

			const modal = ensureModal()
			const title = modal.querySelector('.nccb-template-editor-title')
			const container = modal.querySelector('.nccb-template-editor-body')
			const saveButton = modal.querySelector('.nccb-template-editor-save')
			const languageWrapper = modal.querySelector('.nccb-template-editor-language')
			const languageSelect = modal.querySelector('.nccb-template-editor-language-select')
			if (!(title instanceof HTMLElement) || !(container instanceof HTMLElement)) {
				return
			}

			if (modalState.wrapper === wrapper) {
				modal.classList.add('nccb-template-editor-modal--open')
				return
			}

			closeModal()

			modalState.wrapper = wrapper
			modalState.editorId = `${control.id}--modal`
			modalState.assetMap = { ...getAssetMap(wrapper) }
			const supportsLanguageSelect = String(wrapper.dataset.settingKey || '') !== emailSignatureTemplateKey
			if (languageWrapper instanceof HTMLElement) {
				languageWrapper.hidden = !supportsLanguageSelect
				languageWrapper.style.display = supportsLanguageSelect ? '' : 'none'
			}
			modalState.languageSelect = supportsLanguageSelect && languageSelect instanceof HTMLSelectElement ? languageSelect : null

			title.textContent = settingLabel(wrapper.dataset.settingKey || '')
			if (saveButton instanceof HTMLButtonElement) {
				saveButton.textContent = tr('Save')
			}
			if (supportsLanguageSelect) {
				populateLanguageSelect(modalState.languageSelect)
			} else if (languageSelect instanceof HTMLSelectElement) {
				languageSelect.innerHTML = ''
			}
			const modalTextarea = document.createElement('textarea')
			modalTextarea.id = modalState.editorId
			modalTextarea.className = 'nccb-template-modal-control'
			modalTextarea.rows = 14
			modalTextarea.value = control.value || ''
			container.appendChild(modalTextarea)
			modalState.textarea = modalTextarea
			modal.classList.add('nccb-template-editor-modal--open')
			initializeModalEditor(wrapper, control, modalTextarea)

			window.setTimeout(() => {
				const editor = getModalEditor()
				if (editor) {
					editor.focus()
					normalizeEditorImageSources(editor, getEffectiveAssetMap(wrapper))
				}
			}, 50)
		}

		function scheduleAssetRefresh(wrapper) {
			if (typeof assetRefreshHandler !== 'function' || !(wrapper instanceof HTMLElement)) {
				return
			}
			const existingTimer = assetRefreshTimers.get(wrapper)
			if (existingTimer) {
				window.clearTimeout(existingTimer)
			}
			const timer = window.setTimeout(() => {
				assetRefreshTimers.delete(wrapper)
				void assetRefreshHandler(wrapper)
			}, 350)
			assetRefreshTimers.set(wrapper, timer)
		}

		function setExpanded(wrapper, expanded) {
			const toggleButton = wrapper.querySelector('.nccb-template-toggle')
			wrapper.classList.toggle('nccb-template-collapsed', !expanded)
			if (toggleButton) {
				toggleButton.dataset.expanded = expanded ? '1' : '0'
				toggleButton.textContent = tr(expanded ? 'Hide editor' : 'Show editor')
			}
		}

		function syncEditorMode(wrapper) {
			const control = getControl(wrapper)
			const editor = getActiveEditor(wrapper)
			if (!control || !editor) {
				return
			}
			editor.mode.set(control.disabled ? 'readonly' : 'design')
		}

		function updateButtons(wrapper) {
			const control = getControl(wrapper)
			const disabled = Boolean(control?.disabled)
			wrapper.querySelectorAll('.nccb-template-action').forEach((button) => {
				button.disabled = disabled
			})
		}

		function initializeModalEditor(wrapper, sourceControl, modalControl) {
			const tinymce = getTinyMce()
			if (!tinymce || !(modalControl instanceof HTMLTextAreaElement)) {
				return
			}

			const existingEditor = getModalEditor()
			if (existingEditor) {
				existingEditor.remove()
			}

			tinymce.init({
				target: modalControl,
				license_key: 'gpl',
				skin: false,
				content_css: false,
				content_security_policy: contentSecurityPolicy,
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
					const templateVariables = getVariablesForSetting(wrapper.dataset.settingKey)
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
						onAction: () => openPreview(editor, wrapper),
					})

					editor.on('init', () => {
						editor.setContent(toEditorHtml(sourceControl.value || '', getEffectiveAssetMap(wrapper)))
						normalizeEditorImageSources(editor, getEffectiveAssetMap(wrapper))
					})

					const sync = () => {
						modalControl.value = fromEditorHtml(editor.getContent())
						scheduleAssetRefresh(wrapper)
					}
					editor.on('SetContent change undo redo', () => normalizeEditorImageSources(editor, getEffectiveAssetMap(wrapper)))
					editor.on('blur change input undo redo keyup SetContent', sync)
				},
			}).catch((error) => {
				console.error('nccb modal tiny editor load failed', error)
			})
		}

		function attachHandlers(root) {
			root.querySelectorAll('.nccb-template-editor').forEach((wrapper) => {
				const control = getControl(wrapper)
				if (!control) {
					return
				}

				if (wrapper.dataset.nccbTemplateEditorBound !== '1') {
					wrapper.dataset.nccbTemplateEditorBound = '1'
					setExpanded(wrapper, false)

					const toggleButton = wrapper.querySelector('.nccb-template-toggle')
					if (toggleButton) {
						toggleButton.addEventListener('click', () => {
							if (control.disabled) {
								return
							}
							openModal(wrapper)
						})
					}
				}

				updateButtons(wrapper)
				syncEditorMode(wrapper)
			})
		}

		function syncState(root, prefix) {
			root.querySelectorAll(`.nccb-template-editor[data-prefix="${prefix}"]`).forEach((wrapper) => {
				updateButtons(wrapper)
				syncEditorMode(wrapper)
				const control = getControl(wrapper)
				if (modalState.wrapper === wrapper && control?.disabled) {
					closeModal()
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

		function removeByPrefix(prefix) {
			if (modalState.wrapper?.dataset?.prefix === prefix) {
				closeModal()
			}
			settingKeys.forEach((settingKey) => {
				removeTinyMceEditorById(`${prefix}-${settingKey}`)
			})
		}

		function setAssetRefreshHandler(handler) {
			assetRefreshHandler = typeof handler === 'function' ? handler : null
		}

		return {
			attachHandlers,
			getActiveEditor,
			getControl,
			getEffectiveAssetMap,
			getInactiveNote,
			getRefreshValue,
			isModalDraft,
			isSettingKey,
			removeByPrefix,
			setAssetMap,
			setAssetRefreshHandler,
			setDefaultAssetMap,
			setModalAssetMap,
			syncState,
			toEditorHtml,
			toPreviewHtml,
		}
	}

	window.NCCBackendTemplateEditor = {
		createTemplateEditor,
	}
})()
