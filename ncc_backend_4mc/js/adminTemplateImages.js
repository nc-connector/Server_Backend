/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const DEFAULT_TEMPLATE_LOGO_URL = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png'

	function normalizeImageSourceValue(src) {
		const value = String(src || '').trim()
		if (!value) {
			return value
		}
		if (/^https?:\/\//i.test(value) || value.startsWith('data:')) {
			return value
		}
		if (
			value.startsWith('blob:')
			|| value.startsWith('cid:')
			|| value.includes('/apps/ncc_backend_4mc/img/header.png')
			|| value.endsWith('/img/header.png')
		) {
			return DEFAULT_TEMPLATE_LOGO_URL
		}
		return value
	}

	function getImageOriginalSource(img) {
		const currentSrc = normalizeImageSourceValue(img.getAttribute('src') || '')
		const currentMceSrc = normalizeImageSourceValue(img.getAttribute('data-mce-src') || '')
		const storedOriginalSrc = normalizeImageSourceValue(img.getAttribute('data-nccb-original-src') || '')
		if (/^https?:\/\//i.test(currentSrc) && currentSrc !== storedOriginalSrc) {
			return currentSrc
		}
		if (/^https?:\/\//i.test(currentMceSrc) && currentMceSrc !== storedOriginalSrc) {
			return currentMceSrc
		}

		const candidates = [
			storedOriginalSrc,
			currentMceSrc,
			normalizeImageSourceValue(img.getAttribute('data-src') || ''),
			currentSrc,
		]

		for (const candidate of candidates) {
			const normalized = normalizeImageSourceValue(candidate || '')
			if (normalized) {
				return normalized
			}
		}

		return ''
	}

	function extractExternalImageSourcesFromHtml(rawHtml) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(rawHtml || ''), 'text/html')
		const sources = []
		doc.querySelectorAll('img').forEach((img) => {
			const source = getImageOriginalSource(img)
			if (/^https?:\/\//i.test(source)) {
				sources.push(source)
			}
		})
		return [...new Set(sources)]
	}

	function normalizeImageElementSource(img, targetMode = 'editor', assetMap = {}) {
		const originalSource = getImageOriginalSource(img)
		if (!originalSource) {
			return false
		}

		let changed = false
		if (targetMode === 'storage') {
			if (img.getAttribute('src') !== originalSource) {
				img.setAttribute('src', originalSource)
				changed = true
			}
			if (img.hasAttribute('data-mce-src')) {
				img.removeAttribute('data-mce-src')
				changed = true
			}
			if (img.hasAttribute('data-nccb-original-src')) {
				img.removeAttribute('data-nccb-original-src')
				changed = true
			}
			return changed
		}

		const renderSource = String(assetMap?.[originalSource] || originalSource)
		if (/^https?:\/\//i.test(originalSource) && renderSource !== originalSource) {
			if (img.getAttribute('data-nccb-original-src') !== originalSource) {
				img.setAttribute('data-nccb-original-src', originalSource)
				changed = true
			}
		} else if (img.hasAttribute('data-nccb-original-src')) {
			img.removeAttribute('data-nccb-original-src')
			changed = true
		}

		if (img.getAttribute('src') !== renderSource) {
			img.setAttribute('src', renderSource)
			changed = true
		}
		if (img.getAttribute('data-mce-src') !== renderSource) {
			img.setAttribute('data-mce-src', renderSource)
			changed = true
		}

		return changed
	}

	function rewriteImageSources(root, targetMode, assetMap = {}) {
		if (!root?.querySelectorAll) {
			return
		}
		root.querySelectorAll('img').forEach((img) => {
			normalizeImageElementSource(img, targetMode, assetMap)
		})
	}

	function normalizeEditorImageSources(editor, assetMap = {}) {
		const body = editor?.getBody?.()
		if (body) {
			rewriteImageSources(body, 'editor', assetMap)
		}
	}

	function sanitizeTemplateHtml(rawHtml) {
		if (typeof window.NCCBTemplateSanitizer?.sanitizeHtml !== 'function') {
			throw new Error('template_sanitizer_unavailable')
		}
		return window.NCCBTemplateSanitizer.sanitizeHtml(rawHtml)
	}

	window.NCCBackendTemplateImages = {
		extractExternalImageSourcesFromHtml,
		normalizeEditorImageSources,
		rewriteImageSources,
		sanitizeTemplateHtml,
	}
})()
