/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const TEMPLATE_PREVIEW_CSP = "default-src 'none'; img-src 'self' data: blob:; style-src 'unsafe-inline';"

	function escapeHtml(value) {
		return String(value)
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;')
	}

	function buildTemplatePreviewDocument(html) {
		return [
			'<!doctype html>',
			'<html><head>',
			'<meta charset="utf-8">',
			`<meta http-equiv="Content-Security-Policy" content="${escapeHtml(TEMPLATE_PREVIEW_CSP)}">`,
			'</head>',
			`<body>${String(html || '')}</body></html>`,
		].join('')
	}

	function normalizeTalkTemplatePlainText(value) {
		return String(value || '')
			.replace(/\r\n/g, '\n')
			.replace(/\r/g, '\n')
			.replace(/[ \t]+\n/g, '\n')
			.replace(/\n{3,}/g, '\n\n')
			.trim()
	}

	function renderTalkTemplateNodeAsPlainText(node) {
		if (!node) {
			return ''
		}
		if (node.nodeType === Node.TEXT_NODE) {
			return node.nodeValue || ''
		}
		if (node.nodeType !== Node.ELEMENT_NODE) {
			return ''
		}
		const tagName = String(node.tagName || '').toLowerCase()
		if (tagName === 'br') {
			return '\n'
		}
		if (tagName === 'a') {
			const linkText = normalizeTalkTemplatePlainText(renderTalkTemplateNodesAsPlainText(node.childNodes))
			const href = String(node.getAttribute('href') || node.getAttribute('data-mce-href') || '').trim()
			if (!href) {
				return linkText
			}
			if (!linkText || linkText === href) {
				return href
			}
			return `${linkText} (${href})`
		}
		const blockTags = new Set(['p', 'div', 'section', 'article', 'header', 'footer', 'li', 'ul', 'ol', 'table', 'tr'])
		const content = renderTalkTemplateNodesAsPlainText(node.childNodes)
		if (blockTags.has(tagName)) {
			const normalized = normalizeTalkTemplatePlainText(content)
			return normalized ? `\n${normalized}\n` : ''
		}
		if (tagName === 'td' || tagName === 'th') {
			return `${content} `
		}
		return content
	}

	function renderTalkTemplateNodesAsPlainText(nodes) {
		return Array.from(nodes || []).map((node) => renderTalkTemplateNodeAsPlainText(node)).join('')
	}

	function talkTemplateHtmlToPlainText(rawHtml) {
		if (typeof DOMParser === 'undefined') {
			return normalizeTalkTemplatePlainText(String(rawHtml || ''))
		}
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(rawHtml || ''), 'text/html')
		return normalizeTalkTemplatePlainText(renderTalkTemplateNodesAsPlainText(doc.body.childNodes))
	}

	function plainTextToPreviewHtml(value) {
		const parts = String(value || '').split(/(https?:\/\/[^\s<>"']+)/g)
		const html = parts.map((part) => {
			if (/^https?:\/\//i.test(part)) {
				const href = escapeHtml(part)
				return `<a href="${href}" target="_blank" rel="noreferrer noopener">${href}</a>`
			}
			return escapeHtml(part)
		}).join('')
		return `<pre style="white-space:pre-wrap;font:14px/1.45 sans-serif;margin:0;">${html}</pre>`
	}

	function ensureTemplatePreviewModal(helpers) {
		const { tr } = helpers
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
					<button type="button" class="nccb-template-preview-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">&times;</button>
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

	function getTalkTemplateFormatForWrapper(wrapper, helpers) {
		const {
			talkInvitationTemplateFormatKey,
			talkTemplateFormatHtml,
			talkTemplateFormatPlainText,
		} = helpers
		if (!(wrapper instanceof HTMLElement)) {
			return talkTemplateFormatHtml
		}

		const control = wrapper.querySelector(`.nccb-setting-control[data-setting-key="${talkInvitationTemplateFormatKey}"]`)
		const value = String(control?.value || '').toLowerCase()
		return value === talkTemplateFormatPlainText
			? talkTemplateFormatPlainText
			: talkTemplateFormatHtml
	}

	function openTemplatePreview(editor, wrapper = null, helpers) {
		const {
			talkInvitationTemplateKey,
			talkTemplateFormatPlainText,
			toPreviewTemplateHtml,
		} = helpers
		const modal = ensureTemplatePreviewModal(helpers)
		const frame = modal.querySelector('.nccb-template-preview-frame')
		if (!(frame instanceof HTMLIFrameElement)) {
			return
		}

		const sourceHtml = editor.getBody()?.innerHTML || editor.getContent()
		const isTalkTemplate = wrapper instanceof HTMLElement
			&& String(wrapper.dataset.settingKey || '') === talkInvitationTemplateKey
		const previewContent = isTalkTemplate && getTalkTemplateFormatForWrapper(wrapper, helpers) === talkTemplateFormatPlainText
			? plainTextToPreviewHtml(talkTemplateHtmlToPlainText(sourceHtml))
			: toPreviewTemplateHtml(sourceHtml)
		frame.setAttribute('srcdoc', buildTemplatePreviewDocument(previewContent))
		modal.classList.add('nccb-template-preview-modal--open')
	}

	window.NCCBackendTemplatePreview = {
		buildTemplatePreviewDocument,
		openTemplatePreview,
		plainTextToPreviewHtml,
		talkTemplateHtmlToPlainText,
	}
})()
