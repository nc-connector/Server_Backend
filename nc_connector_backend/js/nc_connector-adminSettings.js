/**
 * Copyright (c) 2026 Bastian Kleinschmidt
 * Licensed under the GNU Affero General Public License v3.0.
 * See LICENSE.txt for details.
 */

(() => {
	'use strict'

	const APP_ID = 'nc_connector'
	const tr = (text, vars = []) => {
		if (typeof t === 'function') {
			return t(APP_ID, text, vars)
		}
		return text
	}

	const SETTING_META = {
		share_base_directory: {
			label: 'Base directory',
			tooltip: ['Files are stored below this folder (for example "90 Shares - external").'],
		},
		share_name_template: {
			label: 'Share name',
			tooltip: ['Default share name used by the mail add-on.'],
		},
		share_permission_upload: {
			label: 'Upload/Create',
			tooltip: [
				'These values are defaults for newly created shares.',
				'They can be changed in the share wizard.',
				'In attachment mode recipients always have read-only access.',
			],
		},
		share_permission_edit: {
			label: 'Edit',
			tooltip: [
				'These values are defaults for newly created shares.',
				'They can be changed in the share wizard.',
				'In attachment mode recipients always have read-only access.',
			],
		},
		share_permission_delete: {
			label: 'Delete',
			tooltip: [
				'These values are defaults for newly created shares.',
				'They can be changed in the share wizard.',
				'In attachment mode recipients always have read-only access.',
			],
		},
		share_set_password: {
			label: 'Set password',
			tooltip: ['Defines whether new shares should use a password by default.'],
		},
		share_send_password_separately: {
			label: 'Send password separately',
			tooltip: ['Send password in a separate follow-up email.'],
		},
		share_expire_days: {
			label: 'Expiration (days)',
			tooltip: ['Defines the default lifetime in days for new shares.'],
		},
		attachments_always_via_ncconnector: {
			label: 'Always share attachments via NC Connector',
			tooltip: [
				'New attachments are removed from the compose email and handled via NC Connector.',
				'The share wizard starts directly in the file step.',
				'The final message contains share links instead of physical attachments.',
			],
		},
		attachments_min_size_mb: {
			label: 'Offer upload for files larger than (MB)',
			tooltip: [
				'The total size of all attachments is evaluated.',
				'When exceeded, files can be shared via NC Connector or the latest selected attachment group can be removed.',
				'The remove action reduces the email below the threshold again.',
			],
		},
		share_html_block_template: {
			label: 'Email share template',
			tooltip: [
				'HTML template used for the share block inserted by mail clients.',
				'Use variables like {URL}, {PASSWORD}, {EXPIRATIONDATE}, {RIGHTS}, {NOTE}.',
			],
		},
		share_password_template: {
			label: 'Email password template',
			tooltip: [
				'Use this template for the separate password email.',
				'Available variable: {PASSWORD}.',
			],
		},
		language_share_html_block: {
			label: 'Language in share HTML block',
			tooltip: ['Controls the language of the inserted share HTML block.'],
		},
		language_talk_description: {
			label: 'Language in Talk description text',
			tooltip: ['Controls the language of the Talk text block in calendar events.'],
		},
		talk_invitation_template: {
			label: 'Talk invitation template',
			tooltip: [
				'Use this template for the Talk invitation text.',
				'Available variables: {MEETING_URL}, {PASSWORD}.',
			],
		},
		talk_generate_password: {
			label: 'Generate password for meetings',
			tooltip: ['Automatically generates a password when a Talk room is created.'],
		},
		talk_title: {
			label: 'Title',
			tooltip: ['Default title for newly created Talk rooms.'],
		},
		talk_lobby_active: {
			label: 'Lobby active until start time',
			tooltip: [
				'Participants stay in the lobby (waiting room) before the meeting starts and cannot enter the call yet.',
				'Joining is possible only at the configured start time (or when a moderator opens the lobby).',
				'Useful to prevent participants from joining too early.',
			],
		},
		talk_show_in_search: {
			label: 'Show in search',
			tooltip: [
				'The room is visible in Talk search.',
				'This makes rooms easier to find, but they are discoverable by users.',
			],
		},
		talk_add_users: {
			label: 'Add users',
			tooltip: [
				'Internal Nextcloud users are added directly to the room.',
				'The room appears in Nextcloud Talk immediately.',
			],
		},
		talk_add_guests: {
			label: 'Add guests',
			tooltip: [
				'Guests are also added directly to the room.',
				'They receive a separate (additional) email with a personal access link.',
			],
		},
		talk_set_password: {
			label: 'Set password',
			tooltip: ['Defines whether new Talk rooms should use a password by default.'],
		},
		talk_room_type: {
			label: 'Room type',
			tooltip: [
				'Event conversation: time-limited, lightweight, self-cleaning.',
				'Group conversation: persistent with full history.',
			],
		},
	}

	const SHARE_HTML_TEMPLATE_KEY = 'share_html_block_template'
	const SHARE_PASSWORD_TEMPLATE_KEY = 'share_password_template'
	const TALK_INVITATION_TEMPLATE_KEY = 'talk_invitation_template'
	const DEFAULT_TEMPLATE_LOGO_URL = 'https://raw.githubusercontent.com/nc-connector/.github/refs/heads/main/profile/header-solid-blue.png'
	const TEMPLATE_EDITOR_CONTENT_CSP = "default-src 'none'; img-src * data: blob:; style-src 'unsafe-inline';"
	const TEMPLATE_PREVIEW_CSP = "default-src 'none'; img-src * data: blob: https: http:; style-src 'unsafe-inline';"
	const TEMPLATE_EDITOR_SETTING_KEYS = new Set([
		SHARE_HTML_TEMPLATE_KEY,
		SHARE_PASSWORD_TEMPLATE_KEY,
		TALK_INVITATION_TEMPLATE_KEY,
	])
	const TEMPLATE_VARIABLES_BY_SETTING = {
		[SHARE_HTML_TEMPLATE_KEY]: ['URL', 'PASSWORD', 'EXPIRATIONDATE', 'RIGHTS', 'NOTE'],
		[SHARE_PASSWORD_TEMPLATE_KEY]: ['PASSWORD'],
		[TALK_INVITATION_TEMPLATE_KEY]: ['MEETING_URL', 'PASSWORD'],
	}
	const ENUM_OPTION_LABELS = {
		talk_room_type: {
			event: 'Event conversation',
			group: 'Group conversation',
		},
		language_share_html_block: {
			ui_default: 'Default (UI)',
			custom: 'Custom',
			en: 'English',
			de: 'German',
			fr: 'French',
			zh_cn: 'Chinese (China)',
			zh_tw: 'Chinese (Taiwan)',
			it: 'Italian',
			ja: 'Japanese',
			nl: 'Dutch',
			pl: 'Polish',
			pt_br: 'Portuguese (Brazil)',
			pt_pt: 'Portuguese (Portugal)',
			ru: 'Russian',
			es: 'Spanish',
			cs: 'Czech',
			hu: 'Hungarian',
		},
		language_talk_description: {
			ui_default: 'Default (UI)',
			custom: 'Custom',
			en: 'English',
			de: 'German',
			fr: 'French',
			zh_cn: 'Chinese (China)',
			zh_tw: 'Chinese (Taiwan)',
			it: 'Italian',
			ja: 'Japanese',
			nl: 'Dutch',
			pl: 'Polish',
			pt_br: 'Portuguese (Brazil)',
			pt_pt: 'Portuguese (Portugal)',
			ru: 'Russian',
			es: 'Spanish',
			cs: 'Czech',
			hu: 'Hungarian',
		},
	}
	const TEMPLATE_TRANSLATION_LOCALES = [
		'en',
		'de',
		'fr',
		'cs',
		'es',
		'hu',
		'it',
		'ja',
		'nl',
		'pl',
		'pt_br',
		'pt_pt',
		'ru',
		'zh_cn',
		'zh_tw',
	]
	const TEMPLATE_TRANSLATION_PHRASES = {
		[SHARE_HTML_TEMPLATE_KEY]: {
			share_intro_1: {
				en: 'I would like to securely share files with you while protecting your privacy.',
				de: 'Ich möchte Dateien sicher und unter Wahrung Ihrer Privatsphäre mit Ihnen teilen.',
				fr: 'Je souhaite partager des fichiers avec vous de manière sécurisée tout en protégeant votre vie privée.',
				cs: 'Rád bych s vámi bezpečně sdílel soubory při zachování vašeho soukromí.',
				es: 'Me gustaría compartir archivos con usted de forma segura y respetando su privacidad.',
				hu: 'Szeretnék biztonságosan megosztani Önnel fájlokat, az Ön adatainak védelmét szem előtt tartva.',
				it: 'Desidero condividere file con voi in modo sicuro e nel rispetto della vostra privacy.',
				ja: 'プライバシーを保護しながら、安全にファイルを共有したいと思います。',
				nl: 'Ik wil bestanden veilig met u delen met respect voor uw privacy.',
				pl: 'Chcę bezpiecznie udostępnić Ci pliki z poszanowaniem Twojej prywatności.',
				pt_br: 'Gostaria de compartilhar arquivos com você de forma segura e preservando sua privacidade.',
				pt_pt: 'Gostaria de partilhar ficheiros consigo de forma segura e preservando a sua privacidade.',
				ru: 'Я хотел бы безопасно поделиться с вами файлами с соблюдением вашей конфиденциальности.',
				zh_cn: '我希望在保护您隐私的前提下，安全地与您共享文件。',
				zh_tw: '我希望在保護您隱私的前提下，安全地與您分享檔案。',
			},
			share_intro_2: {
				en: 'Click the link below to download your files.',
				de: 'Klicken Sie auf den untenstehenden Link, um Ihre Dateien herunterzuladen.',
				fr: 'Cliquez sur le lien ci-dessous pour télécharger vos fichiers.',
				cs: 'Kliknutím na níže uvedený odkaz si stáhnete své soubory.',
				es: 'Haga clic en el siguiente enlace para descargar sus archivos.',
				hu: 'Kattintson az alábbi hivatkozásra a fájlok letöltéséhez.',
				it: 'Fate clic sul link seguente per scaricare i vostri file.',
				ja: '以下のリンクをクリックしてファイルをダウンロードしてください。',
				nl: 'Klik op de onderstaande link om uw bestanden te downloaden.',
				pl: 'Kliknij poniższy link, aby pobrać swoje pliki.',
				pt_br: 'Clique no link abaixo para baixar seus arquivos.',
				pt_pt: 'Clique na ligação abaixo para transferir os seus ficheiros.',
				ru: 'Нажмите на ссылку ниже, чтобы загрузить ваши файлы.',
				zh_cn: '点击下方链接下载您的文件。',
				zh_tw: '按一下下方連結以下載您的檔案。',
			},
			download_link: {
				en: 'Download link',
				de: 'Download-Link',
				fr: 'Lien de téléchargement',
				cs: 'Odkaz ke stažení',
				es: 'Enlace de descarga',
				hu: 'Letöltési hivatkozás',
				it: 'Link per il download',
				ja: 'ダウンロードリンク',
				nl: 'Downloadlink',
				pl: 'Link do pobrania',
				pt_br: 'Link para download',
				pt_pt: 'Ligação para download',
				ru: 'Ссылка для скачивания',
				zh_cn: '下载链接',
				zh_tw: '下載連結',
			},
			password: {
				en: 'Password',
				de: 'Passwort',
				fr: 'Mot de passe',
				cs: 'Heslo',
				es: 'Contraseña',
				hu: 'Jelszó',
				it: 'Password',
				ja: 'パスワード',
				nl: 'Wachtwoord',
				pl: 'Hasło',
				pt_br: 'Senha',
				pt_pt: 'Palavra-passe',
				ru: 'Пароль',
				zh_cn: '密码',
				zh_tw: '密碼',
			},
			expiration_date: {
				en: 'Expiration date',
				de: 'Ablaufdatum',
				fr: 'Date d’expiration',
				cs: 'Datum vypršení platnosti',
				es: 'Fecha de vencimiento',
				hu: 'Lejárati dátum',
				it: 'Data di scadenza',
				ja: '有効期限',
				nl: 'Vervaldatum',
				pl: 'Data wygaśnięcia',
				pt_br: 'Data de expiração',
				pt_pt: 'Data de expiração',
				ru: 'Дата истечения срока',
				zh_cn: '到期日期',
				zh_tw: '到期日期',
			},
			rights: {
				en: 'Rights',
				de: 'Berechtigungen',
				fr: 'Droits',
				cs: 'Oprávnění',
				es: 'Permisos',
				hu: 'Jogosultságok',
				it: 'Permessi',
				ja: '権限',
				nl: 'Rechten',
				pl: 'Uprawnienia',
				pt_br: 'Permissões',
				pt_pt: 'Permissões',
				ru: 'Права',
				zh_cn: '权限',
				zh_tw: '權限',
			},
			nextcloud_footer: {
				en: 'is a solution for secure email and data exchange.',
				de: 'ist eine Lösung für sicheren E-Mail- und Datenaustausch.',
				fr: 'est une solution pour l’échange sécurisé d’e-mails et de données.',
				cs: 'je řešení pro bezpečnou výměnu e-mailů a dat.',
				es: 'es una solución para el intercambio seguro de correos electrónicos y datos.',
				hu: 'biztonságos e-mail- és adatcserére szolgáló megoldás.',
				it: 'è una soluzione per lo scambio sicuro di e-mail e dati.',
				ja: 'は、安全なメールとデータ交換のためのソリューションです。',
				nl: 'is een oplossing voor veilige uitwisseling van e-mail en gegevens.',
				pl: 'to rozwiązanie do bezpiecznej wymiany wiadomości e-mail i danych.',
				pt_br: 'é uma solução para troca segura de e-mails e dados.',
				pt_pt: 'é uma solução para a troca segura de e-mails e dados.',
				ru: '— это решение для безопасного обмена электронной почтой и данными.',
				zh_cn: '是安全电子邮件和数据交换的解决方案。',
				zh_tw: '是安全電子郵件與資料交換的解決方案。',
			},
		},
		[SHARE_PASSWORD_TEMPLATE_KEY]: {
			password_intro: {
				en: 'Here is your password for the sent share.',
				de: 'Hier ist Ihr Passwort zur gesendeten Freigabe.',
				fr: 'Voici votre mot de passe pour le partage envoyé.',
				cs: 'Zde je vaše heslo k odeslanému sdílení.',
				es: 'Aquí tiene su contraseña para la compartición enviada.',
				hu: 'Itt találja az elküldött megosztáshoz tartozó jelszót.',
				it: 'Ecco la password per la condivisione inviata.',
				ja: '送信された共有に対するパスワードはこちらです。',
				nl: 'Hier is uw wachtwoord voor de verzonden share.',
				pl: 'Oto hasło do wysłanego udostępnienia.',
				pt_br: 'Aqui está sua senha para o compartilhamento enviado.',
				pt_pt: 'Aqui está a sua palavra-passe para a partilha enviada.',
				ru: 'Вот ваш пароль для отправленной публикации.',
				zh_cn: '这是您已发送共享的密码。',
				zh_tw: '這是您已傳送分享的密碼。',
			},
			password: {
				en: 'Password',
				de: 'Passwort',
				fr: 'Mot de passe',
				cs: 'Heslo',
				es: 'Contraseña',
				hu: 'Jelszó',
				it: 'Password',
				ja: 'パスワード',
				nl: 'Wachtwoord',
				pl: 'Hasło',
				pt_br: 'Senha',
				pt_pt: 'Palavra-passe',
				ru: 'Пароль',
				zh_cn: '密码',
				zh_tw: '密碼',
			},
		},
		[TALK_INVITATION_TEMPLATE_KEY]: {
			join_meeting_now: {
				en: 'Join the meeting now:',
				de: 'Jetzt an der Besprechung teilnehmen :',
				fr: 'Rejoindre la réunion maintenant :',
				cs: 'Připojit se ke schůzce:',
				es: 'Unirse ahora a la reunión:',
				hu: 'Csatlakozás a megbeszéléshez:',
				it: 'Partecipa ora alla riunione:',
				ja: '今すぐ会議に参加:',
				nl: 'Neem nu deel aan de vergadering:',
				pl: 'Dołącz do spotkania teraz:',
				pt_br: 'Participar da reunião agora:',
				pt_pt: 'Participar na reunião agora:',
				ru: 'Присоединиться к встрече сейчас:',
				zh_cn: '立即加入会议：',
				zh_tw: '立即加入會議：',
			},
			password_colon: {
				en: 'Password:',
				de: 'Passwort:',
				fr: 'Mot de passe :',
				cs: 'Heslo:',
				es: 'Contraseña:',
				hu: 'Jelszó:',
				it: 'Password:',
				ja: 'パスワード:',
				nl: 'Wachtwoord:',
				pl: 'Hasło:',
				pt_br: 'Senha:',
				pt_pt: 'Palavra-passe:',
				ru: 'Пароль:',
				zh_cn: '密码：',
				zh_tw: '密碼：',
			},
			need_help: {
				en: 'Need help?',
				de: 'Benötigen Sie Hilfe?',
				fr: 'Besoin d’aide ?',
				cs: 'Potřebujete pomoc?',
				es: '¿Necesita ayuda?',
				hu: 'Segítségre van szüksége?',
				it: 'Hai bisogno di aiuto?',
				ja: 'ヘルプが必要ですか？',
				nl: 'Hulp nodig?',
				pl: 'Potrzebujesz pomocy?',
				pt_br: 'Precisa de ajuda?',
				pt_pt: 'Precisa de ajuda?',
				ru: 'Нужна помощь?',
				zh_cn: '需要帮助吗？',
				zh_tw: '需要協助嗎？',
			},
		},
	}

	function escapeHtml(value) {
		return String(value)
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;')
			.replaceAll("'", '&#039;')
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
		} catch {
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
		} catch {
			return '—'
		}
	}

	function setMessage(element, text, type) {
		if (!element) {
			return
		}
		element.className = type === 'error' ? 'nccv-error' : type === 'success' ? 'nccv-success' : 'nccv-muted'
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
		const { title, lines } = settingTooltipData(settingKey)
		if (lines.length === 0) {
			return ''
		}
		const items = lines.map((line) => `<li>${escapeHtml(line)}</li>`).join('')
		return `
			<span class="nccv-help-wrap" tabindex="0">
				<span class="nccv-help" aria-label="${escapeHtml(title)}">ⓘ</span>
				<div class="nccv-help-tooltip" role="tooltip">
					<div class="nccv-help-tooltip-title">${escapeHtml(title)}</div>
					<ul class="nccv-help-tooltip-list">${items}</ul>
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
			<span class="nccv-help-wrap" tabindex="0">
				<span class="nccv-help" aria-label="${escapeHtml(tr(title))}">ⓘ</span>
				<div class="nccv-help-tooltip" role="tooltip">
					<div class="nccv-help-tooltip-title">${escapeHtml(tr(title))}</div>
					<ul class="nccv-help-tooltip-list">${items}</ul>
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
			'share_expire_days',
			'attachments_always_via_ncconnector',
			'attachments_min_size_mb',
			'language_share_html_block',
			SHARE_HTML_TEMPLATE_KEY,
			SHARE_PASSWORD_TEMPLATE_KEY,
			'language_talk_description',
			TALK_INVITATION_TEMPLATE_KEY,
			'talk_generate_password',
			'talk_title',
			'talk_lobby_active',
			'talk_show_in_search',
			'talk_add_users',
			'talk_add_guests',
			'talk_set_password',
			'talk_room_type',
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

	function settingCategory(settingKey) {
		if (settingKey === 'language_talk_description' || String(settingKey).startsWith('talk_')) {
			return 'talk'
		}
		return 'share'
	}

	function isTemplateEditorSettingKey(settingKey) {
		return TEMPLATE_EDITOR_SETTING_KEYS.has(String(settingKey || ''))
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

	function buildAppUrl(path, queryParams = null) {
		const normalizedPath = String(path || '').startsWith('/') ? String(path || '') : `/${String(path || '')}`
		const baseUrl = OC.generateUrl('/apps/nc_connector' + normalizedPath)
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

	/**
	 * Wraps rendered template HTML in a standalone preview document with CSP.
	 *
	 * @param {string} html
	 * @returns {string}
	 */
	function buildTemplatePreviewDocument(html) {
		return [
			'<!DOCTYPE html>',
			'<html>',
			'<head>',
			'<meta charset="utf-8">',
			`<meta http-equiv="Content-Security-Policy" content="${escapeHtml(TEMPLATE_PREVIEW_CSP)}">`,
			'<style>html,body{margin:0;padding:0;background:#fff}body{font-family:Arial,sans-serif;padding:16px;box-sizing:border-box}img{max-width:100%;height:auto}</style>',
			'</head>',
			`<body>${String(html || '')}</body>`,
			'</html>',
		].join('')
	}

	function ensureTemplatePreviewModal() {
		let modal = document.getElementById('nccv-template-preview-modal')
		if (modal) {
			return modal
		}

		modal = document.createElement('div')
		modal.id = 'nccv-template-preview-modal'
		modal.className = 'nccv-template-preview-modal'
		modal.innerHTML = `
			<div class="nccv-template-preview-backdrop" data-action="close"></div>
			<div class="nccv-template-preview-dialog" role="dialog" aria-modal="true" aria-label="${escapeHtml(tr('Preview'))}">
				<div class="nccv-template-preview-header">
					<div class="nccv-template-preview-title">${escapeHtml(tr('Preview'))}</div>
					<button type="button" class="nccv-template-preview-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">×</button>
				</div>
				<div class="nccv-template-preview-body">
					<iframe class="nccv-template-preview-frame" sandbox=""></iframe>
				</div>
			</div>
		`
		modal.addEventListener('click', (event) => {
			const target = event.target
			if (target instanceof HTMLElement && target.dataset.action === 'close') {
				modal.classList.remove('nccv-template-preview-modal--open')
			}
		})
		document.body.appendChild(modal)
		return modal
	}

	function openTemplatePreview(editor) {
		const modal = ensureTemplatePreviewModal()
		const frame = modal.querySelector('.nccv-template-preview-frame')
		if (!(frame instanceof HTMLIFrameElement)) {
			return
		}

		const previewHtml = buildTemplatePreviewDocument(
			toPreviewTemplateHtml(editor.getBody()?.innerHTML || editor.getContent())
		)
		frame.setAttribute('srcdoc', previewHtml)
		modal.classList.add('nccv-template-preview-modal--open')
	}

	function ensureTemplateEditorModal() {
		let modal = document.getElementById('nccv-template-editor-modal')
		if (modal) {
			return modal
		}

		modal = document.createElement('div')
		modal.id = 'nccv-template-editor-modal'
		modal.className = 'nccv-template-editor-modal'
		modal.innerHTML = `
			<div class="nccv-template-editor-backdrop" data-action="close"></div>
			<div class="nccv-template-editor-dialog" role="dialog" aria-modal="true">
				<div class="nccv-template-editor-header">
					<div class="nccv-template-editor-title"></div>
					<div class="nccv-template-editor-header-actions">
						<label class="nccv-template-editor-language">
							<span>${escapeHtml(tr('Languages'))}</span>
							<select class="nccv-template-editor-language-select" data-action="translate-language"></select>
						</label>
						<button type="button" class="nccv-template-editor-close" data-action="close" aria-label="${escapeHtml(tr('Close'))}">×</button>
					</div>
				</div>
				<div class="nccv-template-editor-body"></div>
				<div class="nccv-template-editor-footer">
					<button type="button" class="button button-small nccv-template-editor-reset" data-action="reset">${escapeHtml(tr('Reset to default'))}</button>
					<button type="button" class="button button-small nccv-template-editor-save" data-action="save">${escapeHtml(tr('Save'))}</button>
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

		const defaultControl = wrapper.querySelector('.nccv-template-default')
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
			control.value = String(templateEditorModalState.textarea.value || '')
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
			return 'nccv-override-save'
		}
		if (prefix === 'group-override') {
			return 'nccv-group-override-save'
		}
		return 'nccv-default-save'
	}

	function closeTemplateEditorModal() {
		const modal = document.getElementById('nccv-template-editor-modal')
		const editor = getModalTemplateEditor()
		if (editor) {
			editor.remove()
		}
		if (modal instanceof HTMLElement) {
			modal.classList.remove('nccv-template-editor-modal--open')
			const saveButton = modal.querySelector('.nccv-template-editor-save')
			if (saveButton instanceof HTMLButtonElement) {
				saveButton.textContent = tr('Save')
			}
			const container = modal.querySelector('.nccv-template-editor-body')
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
		const title = modal.querySelector('.nccv-template-editor-title')
		const container = modal.querySelector('.nccv-template-editor-body')
		const saveButton = modal.querySelector('.nccv-template-editor-save')
		const languageSelect = modal.querySelector('.nccv-template-editor-language-select')
		if (!(title instanceof HTMLElement) || !(container instanceof HTMLElement)) {
			return
		}

		if (templateEditorModalState.wrapper === wrapper) {
			modal.classList.add('nccv-template-editor-modal--open')
			return
		}

		closeTemplateEditorModal()

		templateEditorModalState.wrapper = wrapper
		templateEditorModalState.editorId = `${control.id}--modal`
		templateEditorModalState.assetMap = { ...getTemplateAssetMap(wrapper) }
		templateEditorModalState.languageSelect = languageSelect instanceof HTMLSelectElement ? languageSelect : null

		title.textContent = settingLabel(wrapper.dataset.settingKey || '')
		if (saveButton instanceof HTMLButtonElement) {
			saveButton.textContent = tr('Save')
		}
		populateTemplateLanguageSelect(templateEditorModalState.languageSelect)
		const modalTextarea = document.createElement('textarea')
		modalTextarea.id = templateEditorModalState.editorId
		modalTextarea.className = 'nccv-template-modal-control'
		modalTextarea.rows = 14
		modalTextarea.value = control.value || ''
		container.appendChild(modalTextarea)
		templateEditorModalState.textarea = modalTextarea
		modal.classList.add('nccv-template-editor-modal--open')
		initializeTemplateModalEditor(wrapper, control, modalTextarea)

		window.setTimeout(() => {
			const editor = getModalTemplateEditor()
			if (editor) {
				editor.focus()
				normalizeEditorImageSources(editor, getEffectiveTemplateAssetMap(wrapper))
			}
		}, 50)
	}

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
			|| value.includes('/apps/nc_connector/img/header.png')
			|| value.endsWith('/img/header.png')
		) {
			return DEFAULT_TEMPLATE_LOGO_URL
		}
		return value
	}

	function getImageOriginalSource(img) {
		const currentSrc = normalizeImageSourceValue(img.getAttribute('src') || '')
		const currentMceSrc = normalizeImageSourceValue(img.getAttribute('data-mce-src') || '')
		const storedOriginalSrc = normalizeImageSourceValue(img.getAttribute('data-nccv-original-src') || '')
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
		} catch {
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

	/**
	 * Rewrites one template image between stored source URLs and local runtime assets.
	 *
	 * @param {HTMLImageElement} img
	 * @param {'editor'|'preview'|'save'} [targetMode='editor']
	 * @param {Object<string, string>} [assetMap={}]
	 * @returns {void}
	 */
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
			if (img.hasAttribute('data-nccv-original-src')) {
				img.removeAttribute('data-nccv-original-src')
				changed = true
			}
			return changed
		}

		const renderSource = String(assetMap?.[originalSource] || originalSource)
		if (/^https?:\/\//i.test(originalSource) && renderSource !== originalSource) {
			if (img.getAttribute('data-nccv-original-src') !== originalSource) {
				img.setAttribute('data-nccv-original-src', originalSource)
				changed = true
			}
		} else if (img.hasAttribute('data-nccv-original-src')) {
			img.removeAttribute('data-nccv-original-src')
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

	function toEditorTemplateHtml(rawHtml, assetMap = {}) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(rawHtml || ''), 'text/html')
		rewriteImageSources(doc, 'editor', assetMap)
		return doc.body ? doc.body.innerHTML : String(rawHtml || '')
	}

	function toPreviewTemplateHtml(rawHtml) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(rawHtml || ''), 'text/html')
		doc.querySelectorAll('img').forEach((img) => {
			img.removeAttribute('data-nccv-original-src')
		})
		return doc.body ? doc.body.innerHTML : String(rawHtml || '')
	}

	function fromEditorTemplateHtml(editorHtml) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(String(editorHtml || ''), 'text/html')
		rewriteImageSources(doc, 'storage')
		return doc.body ? doc.body.innerHTML : String(editorHtml || '')
	}

	function getTemplateControl(wrapper) {
		return wrapper.querySelector('.nccv-setting-control')
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
		const toggleButton = wrapper.querySelector('.nccv-template-toggle')
		wrapper.classList.toggle('nccv-template-collapsed', !expanded)
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
		wrapper.querySelectorAll('.nccv-template-action').forEach((button) => {
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
				'alignleft aligncenter alignright | bullist numlist | link image table tablecellprops tableprops | templatevars | code nccvpreview',
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
				editor.ui.registry.addButton('nccvpreview', {
					text: tr('Preview'),
					onAction: () => openTemplatePreview(editor),
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
			console.error('nccv modal tiny editor load failed', error)
		})
	}

	function attachTemplateEditorHandlers(root) {
		root.querySelectorAll('.nccv-template-editor').forEach((wrapper) => {
			const control = getTemplateControl(wrapper)
			if (!control) {
				return
			}

			if (wrapper.dataset.nccvTemplateEditorBound !== '1') {
				wrapper.dataset.nccvTemplateEditorBound = '1'
				setTemplateExpanded(wrapper, false)

				const toggleButton = wrapper.querySelector('.nccv-template-toggle')
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
		root.querySelectorAll(`.nccv-template-editor[data-prefix="${prefix}"]`).forEach((wrapper) => {
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
		const alwaysViaConnector = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_always_via_ncconnector"]`)
		const minSizeControl = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
		const minSizeToggle = container.querySelector(`.nccv-threshold-enabled[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
		const minSizeWrapper = minSizeControl?.closest('.nccv-threshold-control')
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
			minSizeWrapper?.classList.add('nccv-threshold-control--disabled')
			return
		}
		if (Object.prototype.hasOwnProperty.call(minSizeToggle.dataset, 'lockedChecked')) {
			minSizeToggle.checked = minSizeToggle.dataset.lockedChecked === '1'
			delete minSizeToggle.dataset.lockedChecked
		}
		minSizeToggle.disabled = disabledByMode
		minSizeControl.disabled = disabledByMode || !minSizeToggle.checked
		minSizeWrapper?.classList.toggle('nccv-threshold-control--disabled', minSizeControl.disabled)
	}

	function applyTemplateLanguageDependency(container, prefix) {
		const shareLanguageControl = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="language_share_html_block"]`)
		const talkLanguageControl = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="language_talk_description"]`)
		const shareLanguageIsCustom = String(shareLanguageControl?.value || '').toLowerCase() === 'custom'
		const talkLanguageIsCustom = String(talkLanguageControl?.value || '').toLowerCase() === 'custom'

		const dependencyMap = [
			{ key: SHARE_HTML_TEMPLATE_KEY, enabled: shareLanguageIsCustom },
			{ key: SHARE_PASSWORD_TEMPLATE_KEY, enabled: shareLanguageIsCustom },
			{ key: TALK_INVITATION_TEMPLATE_KEY, enabled: talkLanguageIsCustom },
		]

		for (const dependency of dependencyMap) {
			const templateControl = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="${dependency.key}"]`)
			if (!templateControl) {
				continue
			}
			const disabledByMode = templateControl.dataset.disabledByMode === '1'
			templateControl.disabled = disabledByMode || !dependency.enabled
			const templateEditor = templateControl.closest('.nccv-template-editor')
			if (templateEditor instanceof HTMLElement) {
				templateEditor.classList.toggle('nccv-template-editor--inactive', !dependency.enabled)
				templateEditor.querySelectorAll('.nccv-template-note').forEach((note) => {
					if (note instanceof HTMLElement) {
						note.hidden = dependency.enabled
					}
				})
			}
			const templateRow = templateControl.closest('.nccv-template-row')
			if (templateRow instanceof HTMLElement) {
				templateRow.querySelectorAll('.nccv-template-row-mode, .nccv-template-row-head-select-label').forEach((element) => {
					if (element instanceof HTMLElement || element instanceof HTMLSelectElement) {
						element.hidden = !dependency.enabled
						if (element instanceof HTMLSelectElement) {
							element.disabled = !dependency.enabled
						}
					}
				})
				templateRow.querySelectorAll('.nccv-template-row-head-note').forEach((note) => {
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
			const control = container.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`)
			if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement)) {
				return
			}
			if (control.dataset.nccvTemplateLanguageBound === '1') {
				return
			}
			control.dataset.nccvTemplateLanguageBound = '1'
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

	function attachAttachmentDependencyHandlers(root) {
		root.querySelectorAll('.nccv-setting-control[data-setting-key="attachments_always_via_ncconnector"], .nccv-threshold-enabled[data-setting-key="attachments_min_size_mb"]').forEach((control) => {
			if (control.dataset.nccvDependencyBound === '1') {
				return
			}
			control.dataset.nccvDependencyBound = '1'
			control.addEventListener('change', () => {
				syncDefaultControlState(root)
				syncOverrideControlState(root)
				syncGroupOverrideControlState(root)
			})
		})
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
		} catch {
			// ignore
		}

		if (!response.ok) {
			throw new Error(payload?.error || `HTTP ${response.status}`)
		}

		return payload
	}

	const api = {
		loadLicense: () => apiRequest('GET', '/api/v1/admin/license'),
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

	function renderSettingControl(prefix, key, definition, value, disabled, assetMap = {}, defaultAssetMap = {}) {
		const type = definition?.type || 'string'
		const id = `${prefix}-${key}`
		const disabledAttr = disabled ? 'disabled' : ''
		const common = `class="nccv-setting-control" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" ${disabledAttr}`

		if (type === 'bool') {
			return `<input id="${escapeHtml(id)}" type="checkbox" ${common} ${value ? 'checked' : ''}>`
		}

		if (type === 'int') {
			const min = Number.isInteger(definition?.min) ? `min="${definition.min}"` : ''
			const max = Number.isInteger(definition?.max) ? `max="${definition.max}"` : ''
			const numeric = Number.isInteger(value) ? value : Number.parseInt(String(value ?? 0), 10) || 0
			if (key === 'attachments_min_size_mb') {
				const enabled = value !== null && typeof value !== 'undefined'
				return `
					<div class="nccv-threshold-control">
						<label class="nccv-inline-option nccv-threshold-toggle">
							<input type="checkbox" class="nccv-threshold-enabled" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" ${enabled ? 'checked' : ''} ${disabledAttr}>
							${escapeHtml(tr('Enabled'))}
						</label>
						<input id="${escapeHtml(id)}" type="number" ${common} value="${numeric}" ${min} ${max}>
					</div>
				`
			}
			return `<input id="${escapeHtml(id)}" type="number" ${common} value="${numeric}" ${min} ${max}>`
		}

		if (type === 'enum') {
			const options = Array.isArray(definition?.options) ? definition.options : []
			const selected = String(value ?? '')
			const html = options.map((option) => {
				const raw = String(option)
				return `<option value="${escapeHtml(raw)}" ${raw === selected ? 'selected' : ''}>${escapeHtml(enumOptionLabel(key, raw))}</option>`
			}).join('')
			return `<select id="${escapeHtml(id)}" ${common}>${html}</select>`
		}

		const maxLength = Number.isInteger(definition?.max_length) ? definition.max_length : null
		const valueText = String(value ?? '')
		if (isTemplateEditorSettingKey(key)) {
			const defaultTemplate = String(definition?.default ?? '')
			const maxLengthAttr = maxLength !== null ? `maxlength="${maxLength}"` : ''
			const assetsJson = escapeHtml(JSON.stringify(assetMap || {}))
			const defaultAssetsJson = escapeHtml(JSON.stringify(defaultAssetMap || {}))
			return `
				<div class="nccv-template-editor" data-prefix="${escapeHtml(prefix)}" data-setting-key="${escapeHtml(key)}" data-template-assets="${assetsJson}" data-template-default-assets="${defaultAssetsJson}">
					<div class="nccv-template-toolbar">
						<button type="button" class="button button-small nccv-template-toggle nccv-template-action" data-expanded="0">${escapeHtml(tr('Show editor'))}</button>
						<span class="nccv-template-note" hidden>${escapeHtml(tr('Only active when language is set to Custom.'))}</span>
					</div>
					<div class="nccv-template-body">
						<textarea id="${escapeHtml(id)}" ${common} rows="14" ${maxLengthAttr}>${escapeHtml(valueText)}</textarea>
					</div>
					<textarea class="nccv-template-default" hidden readonly>${escapeHtml(defaultTemplate)}</textarea>
				</div>
			`
		}
		if (maxLength !== null && maxLength > 255) {
			return `<textarea id="${escapeHtml(id)}" ${common} rows="3" maxlength="${maxLength}">${escapeHtml(valueText)}</textarea>`
		}
		return `<input id="${escapeHtml(id)}" type="text" ${common} value="${escapeHtml(valueText)}" ${maxLength !== null ? `maxlength="${maxLength}"` : ''}>`
	}

	function readSettingControl(root, prefix, key, definition) {
		const selector = `.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="${key}"]`
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

	/**
	 * Renders one default settings table section (share or talk).
	 *
	 * @param {HTMLTableSectionElement} tbody
	 * @param {Record<string, any>} schema
	 * @param {Record<string, any>} defaults
	 * @param {Record<string, string>} defaultModes
	 * @param {'share'|'talk'} category
	 * @returns {void}
	 */
	function renderDefaultsRows(tbody, schema, defaults, defaultModes, templateAssets, defaultTemplateAssets, category) {
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category)
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const value = Object.prototype.hasOwnProperty.call(defaults || {}, key) ? defaults[key] : definition.default
			const addonChangeable = defaultModes?.[key] === 'user_choice' && !isTemplateEditorSettingKey(key)
			const controlDisabled = false

			if (isTemplateEditorSettingKey(key)) {
				return `
					<tr class="nccv-template-row">
						<td>
							<div class="nccv-key-cell">
								<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							${renderSettingControl('default', key, definition, value, controlDisabled, templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}

			return `
				<tr>
					<td>
						<div class="nccv-key-cell">
							<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>
						<label class="nccv-inline-option">
							<input type="checkbox" class="nccv-addon-changeable" data-setting-key="${escapeHtml(key)}" ${addonChangeable ? 'checked' : ''}>
							${escapeHtml(tr('Editable in add-on'))}
						</label>
					</td>
					<td>${renderSettingControl('default', key, definition, value, controlDisabled, templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function syncDefaultControlState(root) {
		const toggles = root.querySelectorAll('.nccv-addon-changeable')
		for (const toggle of toggles) {
			const key = toggle.getAttribute('data-setting-key')
			const control = root.querySelector(`.nccv-setting-control[data-prefix="default"][data-setting-key="${key}"]`)
			if (control) {
				control.dataset.disabledByMode = '0'
				control.disabled = false
			}
		}
		root.querySelectorAll('.nccv-threshold-enabled[data-prefix="default"][data-setting-key="attachments_min_size_mb"]').forEach((toggle) => {
			toggle.dataset.disabledByMode = '0'
			toggle.disabled = false
		})
		syncAttachmentMinSizeDependency(root, 'default')
		applyTemplateLanguageDependency(root, 'default')
		syncTemplateEditorState(root, 'default')
	}

	function attachDefaultModeHandlers(root) {
		const toggles = root.querySelectorAll('.nccv-addon-changeable')
		for (const toggle of toggles) {
			toggle.addEventListener('change', () => syncDefaultControlState(root))
		}
	}

	/**
	 * Renders one user-override section (share or talk).
	 *
	 * @param {HTMLTableSectionElement} tbody
	 * @param {Record<string, any>} schema
	 * @param {Record<string, any>} items
	 * @param {'share'|'talk'} category
	 * @returns {void}
	 */
	function renderOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category) {
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category)
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const item = items?.[key] || { mode: 'inherit', value: null, effective_value: definition.default, source: 'default', default_mode: 'default' }
			const mode = item.mode === 'forced' ? 'forced' : 'inherit'
			const currentValue = mode === 'forced' ? item.value : item.effective_value
			const modeSelect = `
				<option value="inherit" ${mode === 'inherit' ? 'selected' : ''}>${escapeHtml(tr('Inherit default'))}</option>
				<option value="forced" ${mode === 'forced' ? 'selected' : ''}>${escapeHtml(tr('Forced value'))}</option>
			`
			if (isTemplateEditorSettingKey(key)) {
				return `
					<tr class="nccv-template-row">
						<td>
							<div class="nccv-key-cell">
								<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							<div class="nccv-template-row-head">
								<span class="nccv-template-row-head-label nccv-template-row-head-select-label">${escapeHtml(tr('Preset'))}</span>
								<select class="nccv-user-mode nccv-template-row-mode" data-setting-key="${escapeHtml(key)}">
									${modeSelect}
								</select>
								<span class="nccv-template-row-head-note" hidden>${escapeHtml(tr('Only active when language is set to Custom.'))}</span>
							</div>
							${renderSettingControl('override', key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}
			return `
				<tr>
					<td>
						<div class="nccv-key-cell">
							<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>
						<select class="nccv-user-mode" data-setting-key="${escapeHtml(key)}">
							${modeSelect}
						</select>
					</td>
					<td>${renderSettingControl('override', key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function renderOverridePlaceholder(tbody) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('Please select a seat user.'))}</td></tr>`
	}

	function renderOverrideTables(root, refs, state, selectedUserId) {
		const hasSelectedUser = Boolean(selectedUserId)
		refs.overrideSave.disabled = !hasSelectedUser
		removeTemplateEditorsByPrefix('override')
		if (!hasSelectedUser) {
			renderOverridePlaceholder(refs.overrideTableShare)
			renderOverridePlaceholder(refs.overrideTableTalk)
			return
		}
		renderOverrideRows(refs.overrideTableShare, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'share')
		renderOverrideRows(refs.overrideTableTalk, state.schema, state.overrides, state.overrideTemplateAssets, state.schemaTemplateAssets, 'talk')
		syncOverrideControlState(root)
		attachOverrideModeHandlers(root)
		attachAttachmentDependencyHandlers(root)
		attachTemplateLanguageDependencyHandlers(root, 'override')
		attachTemplateEditorHandlers(root)
	}

	function syncOverrideControlState(tbody) {
		const selects = tbody.querySelectorAll('.nccv-user-mode')
		for (const select of selects) {
			const key = select.getAttribute('data-setting-key')
			const control = tbody.querySelector(`.nccv-setting-control[data-prefix="override"][data-setting-key="${key}"]`)
			const attachmentToggle = tbody.querySelector(`.nccv-threshold-enabled[data-prefix="override"][data-setting-key="${key}"]`)
			if (control) {
				const disabledByMode = select.value !== 'forced'
				control.dataset.disabledByMode = disabledByMode ? '1' : '0'
				control.disabled = disabledByMode
				if (attachmentToggle) {
					attachmentToggle.dataset.disabledByMode = disabledByMode ? '1' : '0'
				}
			}
		}
		syncAttachmentMinSizeDependency(tbody, 'override')
		applyTemplateLanguageDependency(tbody, 'override')
		syncTemplateEditorState(tbody, 'override')
	}

	function attachOverrideModeHandlers(tbody) {
		const selects = tbody.querySelectorAll('.nccv-user-mode')
		for (const select of selects) {
			select.addEventListener('change', () => syncOverrideControlState(tbody))
		}
	}

	function renderGroupOverrideRows(tbody, schema, items, templateAssets, defaultTemplateAssets, category) {
		const keys = sortedSettingKeys(schema).filter((key) => settingCategory(key) === category)
		if (keys.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('No settings found.'))}</td></tr>`
			return
		}

		tbody.innerHTML = keys.map((key) => {
			const definition = schema[key] || {}
			const item = items?.[key] || { mode: 'inherit', value: null, effective_value: definition.default, source: 'default', default_mode: 'default' }
			const mode = item.mode === 'forced' ? 'forced' : 'inherit'
			const currentValue = mode === 'forced' ? item.value : item.effective_value
			const modeSelect = `
				<option value="inherit" ${mode === 'inherit' ? 'selected' : ''}>${escapeHtml(tr('Inherit default'))}</option>
				<option value="forced" ${mode === 'forced' ? 'selected' : ''}>${escapeHtml(tr('Forced value'))}</option>
			`
			if (isTemplateEditorSettingKey(key)) {
				return `
					<tr class="nccv-template-row">
						<td>
							<div class="nccv-key-cell">
								<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
							</div>
						</td>
						<td colspan="2">
							<div class="nccv-template-row-head">
								<span class="nccv-template-row-head-label nccv-template-row-head-select-label">${escapeHtml(tr('Preset'))}</span>
								<select class="nccv-group-mode nccv-template-row-mode" data-setting-key="${escapeHtml(key)}">
									${modeSelect}
								</select>
								<span class="nccv-template-row-head-note" hidden>${escapeHtml(tr('Only active when language is set to Custom.'))}</span>
							</div>
							${renderSettingControl('group-override', key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}
						</td>
					</tr>
				`
			}
			return `
				<tr>
					<td>
						<div class="nccv-key-cell">
							<div class="nccv-key-title">${escapeHtml(settingLabel(key))}${renderSettingHelp(key)}</div>
						</div>
					</td>
					<td>
						<select class="nccv-group-mode" data-setting-key="${escapeHtml(key)}">
							${modeSelect}
						</select>
					</td>
					<td>${renderSettingControl('group-override', key, definition, currentValue, mode !== 'forced', templateAssets?.[key] || {}, defaultTemplateAssets?.[key] || {})}</td>
				</tr>
			`
		}).join('')
	}

	function renderGroupOverridePlaceholder(tbody) {
		tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('Please select a group.'))}</td></tr>`
	}

	function renderGroupOverrideTables(root, refs, state, selectedGroupId) {
		const hasSelectedGroup = Boolean(selectedGroupId)
		refs.groupOverrideSave.disabled = !hasSelectedGroup
		refs.groupOverridePriority.disabled = !hasSelectedGroup
		removeTemplateEditorsByPrefix('group-override')
		if (!hasSelectedGroup) {
			renderGroupOverridePlaceholder(refs.groupOverrideTableShare)
			renderGroupOverridePlaceholder(refs.groupOverrideTableTalk)
			return
		}
		renderGroupOverrideRows(refs.groupOverrideTableShare, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'share')
		renderGroupOverrideRows(refs.groupOverrideTableTalk, state.schema, state.groupOverrides, state.groupOverrideTemplateAssets, state.schemaTemplateAssets, 'talk')
		syncGroupOverrideControlState(root)
		attachGroupOverrideModeHandlers(root)
		attachAttachmentDependencyHandlers(root, 'group-override')
		attachTemplateLanguageDependencyHandlers(root, 'group-override')
		attachTemplateEditorHandlers(root)
	}

	function syncGroupOverrideControlState(tbody) {
		const selects = tbody.querySelectorAll('.nccv-group-mode')
		for (const select of selects) {
			const key = select.getAttribute('data-setting-key')
			const control = tbody.querySelector(`.nccv-setting-control[data-prefix="group-override"][data-setting-key="${key}"]`)
			const attachmentToggle = tbody.querySelector(`.nccv-threshold-enabled[data-prefix="group-override"][data-setting-key="${key}"]`)
			if (control) {
				const disabledByMode = select.value !== 'forced'
				control.dataset.disabledByMode = disabledByMode ? '1' : '0'
				control.disabled = disabledByMode
				if (attachmentToggle) {
					attachmentToggle.dataset.disabledByMode = disabledByMode ? '1' : '0'
				}
			}
		}
		syncAttachmentMinSizeDependency(tbody, 'group-override')
		applyTemplateLanguageDependency(tbody, 'group-override')
		syncTemplateEditorState(tbody, 'group-override')
	}

	function attachGroupOverrideModeHandlers(tbody) {
		const selects = tbody.querySelectorAll('.nccv-group-mode')
		for (const select of selects) {
			select.addEventListener('change', () => syncGroupOverrideControlState(tbody))
		}
	}

	function renderUsers(tbody, users) {
		if (!users || users.length === 0) {
			tbody.innerHTML = `<tr><td colspan="3" class="nccv-muted">${escapeHtml(tr('No users found.'))}</td></tr>`
			return
		}
		tbody.innerHTML = users.map((user) => `
			<tr data-user-id="${escapeHtml(user.user_id)}">
				<td>${escapeHtml(user.user_id)}</td>
				<td>${escapeHtml(user.display_name || '—')}</td>
				<td><input class="nccv-seat-toggle" type="checkbox" ${user.has_seat ? 'checked' : ''}></td>
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
				<li class="nccv-help-tooltip-item">
					<button type="button" class="nccv-help-tooltip-action" data-group-override-link="${escapeHtml(groupId)}">${escapeHtml(label)}</button>
					<span class="nccv-help-tooltip-meta">${escapeHtml(`${tr('Priority')} ${priority}`)}</span>
				</li>
			`
		}).join('')

		return `
			<div class="nccv-help-tooltip" role="tooltip">
				<div class="nccv-help-tooltip-title">${escapeHtml(tr('Group overrides'))}</div>
				<ul class="nccv-help-tooltip-list">${items}</ul>
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
			<div class="nccv-help-tooltip" role="tooltip">
				<div class="nccv-help-tooltip-title">${escapeHtml(tr('User overrides'))}</div>
				<ul class="nccv-help-tooltip-list">
					<li class="nccv-help-tooltip-item">
						<button type="button" class="nccv-help-tooltip-action" data-user-override-link="${escapeHtml(normalizedUserId)}">${escapeHtml(label)}</button>
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
			const stateClass = state === 'suspended_overlimit' ? 'nccv-seat-state nccv-seat-state--paused' : 'nccv-seat-state nccv-seat-state--active'
			const userOverrideEnabled = Boolean(seat.has_overrides)
			const userOverrideClass = userOverrideEnabled ? 'nccv-override-state nccv-override-state--enabled' : 'nccv-override-state nccv-override-state--disabled'
			const groupOverrideEnabled = Boolean(seat.has_group_overrides)
			const groupOverrideClass = groupOverrideEnabled ? 'nccv-override-state nccv-override-state--enabled' : 'nccv-override-state nccv-override-state--disabled'
			const groupOverrideTooltip = groupOverrideEnabled ? formatGroupOverrideTooltip(seat.group_override_groups || []) : ''
			const userOverrideTooltip = userOverrideEnabled ? formatUserOverrideTooltip(seat.user_id || '', seat.display_name || '') : ''
			return `
				<tr>
					<td>${escapeHtml(seat.user_id || '')}</td>
					<td>${escapeHtml(seat.display_name || '—')}</td>
					<td><span class="${stateClass}">${escapeHtml(seatStateLabel(state))}</span></td>
					<td>${groupOverrideEnabled
						? `<span class="nccv-help-wrap nccv-help-wrap--badge" tabindex="0"><span class="${groupOverrideClass}">${escapeHtml(tr('Enabled'))}</span>${groupOverrideTooltip}</span>`
						: `<span class="${groupOverrideClass}">${escapeHtml(tr('Disabled'))}</span>`}</td>
					<td>${userOverrideEnabled
						? `<span class="nccv-help-wrap nccv-help-wrap--badge" tabindex="0"><span class="${userOverrideClass}">${escapeHtml(tr('Enabled'))}</span>${userOverrideTooltip}</span>`
						: `<span class="${userOverrideClass}">${escapeHtml(tr('Disabled'))}</span>`}</td>
					<td>${escapeHtml(formatDateTime(seat.assigned_at || null))}</td>
					<td>${escapeHtml(seat.assigned_by || '—')}</td>
				</tr>
			`
		}).join('')

		element.innerHTML = `
			<table class="nccv-table">
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

	function render(root) {
		root.innerHTML = `
			<h2>NC Connector</h2>
			<div class="nccv-tabbar">
				<button class="button active" data-main-tab-button="general">${escapeHtml(tr('General'))}</button>
				<button class="button" data-main-tab-button="group">${escapeHtml(tr('Group Settings'))}</button>
			</div>

			<section class="nccv-main-panel" data-main-tab-panel="general">
				<div class="nccv-section">
					<h3>${escapeHtml(tr('Operating mode'))}</h3>
					<div class="nccv-row">
						<label class="nccv-inline-option">
							<input type="radio" name="nccv-license-mode" value="community">
							${escapeHtml(tr('Community'))}
							${renderInlineHelp('Community', ['Community mode: 1 seat included. Suitable for tests, proof of concept, or single-user setups.'])}
						</label>
						<label class="nccv-inline-option">
							<input type="radio" name="nccv-license-mode" value="pro">
							${escapeHtml(tr('Pro'))}
							${renderInlineHelp('Pro', ['The Pro version is available for teams starting at 5 seats. One seat always corresponds to one Nextcloud user and is assigned in the backend.'])}
						</label>
					</div>

					<div id="nccv-pro-settings" hidden>
						<div class="nccv-row">
							<label for="nccv-license-email">${escapeHtml(tr('License email'))}</label>
							<input id="nccv-license-email" type="email" autocomplete="email">
						</div>
						<div class="nccv-row">
							<label for="nccv-license-key">${escapeHtml(tr('License key'))}</label>
							<input id="nccv-license-key" type="password" autocomplete="off">
						</div>
						<div class="nccv-row">
							<button id="nccv-license-save" class="button">${escapeHtml(tr('Save license data'))}</button>
							<button id="nccv-license-sync" class="button">${escapeHtml(tr('Sync now'))}</button>
						</div>
					</div>

					<div id="nccv-license-status" class="nccv-muted"></div>
					<div id="nccv-license-hint" class="nccv-muted" hidden></div>
					<div id="nccv-license-message" class="nccv-muted" role="status"></div>
				</div>
			</section>

			<section class="nccv-main-panel" data-main-tab-panel="group" hidden>
				<div class="nccv-tabbar">
					<button class="button active" data-group-tab-button="defaults">${escapeHtml(tr('Default Settings'))}</button>
					<button class="button" data-group-tab-button="seats">${escapeHtml(tr('Seat Assignment'))}</button>
					<button class="button" data-group-tab-button="assigned">${escapeHtml(tr('Assigned Seats'))}</button>
					<button class="button" data-group-tab-button="group-overrides">${escapeHtml(tr('Group overrides'))}</button>
					<button class="button" data-group-tab-button="overrides">${escapeHtml(tr('User overrides'))}</button>
				</div>

				<section class="nccv-group-panel" data-group-tab-panel="defaults">
					<div class="nccv-section">
						<h3>${escapeHtml(tr('Default settings for mail clients'))}</h3>
						<div class="nccv-muted">${escapeHtml(tr('These values apply to all users with an assigned seat unless a user override is set.'))}</div>
						<div id="nccv-default-message" class="nccv-muted" role="status"></div>
						<div class="nccv-tabbar nccv-tabbar--sub">
							<button class="button active" data-default-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-default-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
						</div>
						<section data-default-tab-panel="share">
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width: 360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width: 220px;">${escapeHtml(tr('Editable in add-on'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-default-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-default-tab-panel="talk" hidden>
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width: 360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width: 220px;">${escapeHtml(tr('Editable in add-on'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-default-tbody-talk"></tbody>
								</table>
							</div>
						</section>
						<div class="nccv-row">
							<button id="nccv-default-save" class="button">${escapeHtml(tr('Save default settings'))}</button>
						</div>
					</div>
				</section>

				<section class="nccv-group-panel" data-group-tab-panel="seats" hidden>
					<div class="nccv-section">
						<h3>${escapeHtml(tr('Seat assignment'))}</h3>
						<div id="nccv-seat-usage" class="nccv-muted"></div>
						<div id="nccv-seat-message" class="nccv-muted" role="status"></div>
						<div class="nccv-row">
							<label for="nccv-group-select">${escapeHtml(tr('Group'))}</label>
							<select id="nccv-group-select">
								<option value="">${escapeHtml(tr('All users'))}</option>
							</select>
							<label for="nccv-user-search">${escapeHtml(tr('Search'))}</label>
							<input id="nccv-user-search" type="text" placeholder="${escapeHtml(tr('Username or display name'))}">
						</div>
						<div class="nccv-row">
							<button id="nccv-seat-bulk-assign" class="button">${escapeHtml(tr('Assign seats to all filtered users'))}</button>
							<button id="nccv-seat-bulk-unassign" class="button">${escapeHtml(tr('Remove seats from all filtered users'))}</button>
						</div>
						<div class="nccv-scroll nccv-scroll--users">
							<table class="nccv-table">
								<thead>
									<tr>
										<th style="width:240px;">${escapeHtml(tr('User ID'))}</th>
										<th>${escapeHtml(tr('Name'))}</th>
										<th style="width:120px;">${escapeHtml(tr('Seat'))}</th>
									</tr>
								</thead>
								<tbody id="nccv-user-tbody"></tbody>
							</table>
						</div>
						<div class="nccv-pagination">
							<button id="nccv-user-prev" class="button">${escapeHtml(tr('Previous'))}</button>
							<span id="nccv-user-page" class="nccv-muted">${escapeHtml(tr('Page 1'))}</span>
							<button id="nccv-user-next" class="button">${escapeHtml(tr('Next'))}</button>
						</div>
					</div>
				</section>

				<section class="nccv-group-panel" data-group-tab-panel="assigned" hidden>
					<div class="nccv-section">
						<h3>${escapeHtml(tr('Assigned seats'))}</h3>
						<div id="nccv-assigned-seat-usage" class="nccv-muted"></div>
						<div class="nccv-row">
							<button id="nccv-seat-report-download" class="button">${escapeHtml(tr('Download CSV report'))}</button>
						</div>
						<div id="nccv-assigned-message" class="nccv-muted" role="status"></div>
						<div id="nccv-assigned-seats" class="nccv-scroll nccv-scroll--seats"></div>
					</div>
				</section>

				<section class="nccv-group-panel" data-group-tab-panel="group-overrides" hidden>
					<div class="nccv-section">
						<h3>${escapeHtml(tr('Group overrides'))}</h3>
						<div class="nccv-muted">${escapeHtml(tr('Group overrides can be configured for any group. They only affect users with an assigned Seat in that group. If a user override exists, it wins.'))}</div>
						<div class="nccv-row">
							<label for="nccv-group-override-group">${escapeHtml(tr('Group'))}</label>
							<select id="nccv-group-override-group">
								<option value="">${escapeHtml(tr('Please select a group'))}</option>
							</select>
							<label for="nccv-group-override-priority">${escapeHtml(tr('Priority'))}</label>
							<input id="nccv-group-override-priority" type="number" min="1" max="9999" value="100">
							<button id="nccv-group-override-save" class="button">${escapeHtml(tr('Save group overrides'))}</button>
						</div>
						<div class="nccv-muted">${escapeHtml(tr('Lower number wins if multiple group overrides match a user.'))}</div>
						<div id="nccv-group-override-message" class="nccv-muted" role="status"></div>
						<div class="nccv-tabbar nccv-tabbar--sub">
							<button class="button active" data-group-override-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-group-override-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
						</div>
						<section data-group-override-tab-panel="share">
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-group-override-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-group-override-tab-panel="talk" hidden>
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-group-override-tbody-talk"></tbody>
								</table>
							</div>
						</section>
					</div>
				</section>

				<section class="nccv-group-panel" data-group-tab-panel="overrides" hidden>
					<div class="nccv-section">
						<h3>${escapeHtml(tr('User overrides'))}</h3>
						<div class="nccv-muted">${escapeHtml(tr('Individual seat users can differ from defaults.'))}</div>
						<div class="nccv-row">
							<label for="nccv-override-user">${escapeHtml(tr('Seat user'))}</label>
							<select id="nccv-override-user">
								<option value="">${escapeHtml(tr('Please select a seat user'))}</option>
							</select>
							<button id="nccv-override-save" class="button">${escapeHtml(tr('Save overrides'))}</button>
						</div>
						<div id="nccv-override-message" class="nccv-muted" role="status"></div>
						<div class="nccv-tabbar nccv-tabbar--sub">
							<button class="button active" data-override-tab-button="share">${escapeHtml(tr('Shares'))}</button>
							<button class="button" data-override-tab-button="talk">${escapeHtml(tr('Talk'))}</button>
						</div>
						<section data-override-tab-panel="share">
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-override-tbody-share"></tbody>
								</table>
							</div>
						</section>
						<section data-override-tab-panel="talk" hidden>
							<div class="nccv-scroll nccv-scroll--settings">
								<table class="nccv-table">
									<thead>
										<tr>
											<th style="width:360px;">${escapeHtml(tr('Setting'))}</th>
											<th style="width:220px;">${escapeHtml(tr('Preset'))}</th>
											<th>${escapeHtml(tr('Value'))}</th>
										</tr>
									</thead>
									<tbody id="nccv-override-tbody-talk"></tbody>
								</table>
							</div>
						</section>
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
		const root = document.getElementById('nccv-admin-settings')
		if (!root) {
			return
		}

		render(root)
		setMainTab(root, 'general')
		setGroupTab(root, 'defaults')
		setDefaultsTab(root, 'share')
		setOverrideTab(root, 'share')
		setGroupOverrideTab(root, 'share')

		const state = {
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
			assignedSeats: [],
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
			modeInputs: root.querySelectorAll('input[name="nccv-license-mode"]'),
			proSettings: root.querySelector('#nccv-pro-settings'),
			licenseEmail: root.querySelector('#nccv-license-email'),
			licenseKey: root.querySelector('#nccv-license-key'),
			licenseSave: root.querySelector('#nccv-license-save'),
			licenseSync: root.querySelector('#nccv-license-sync'),
			licenseStatus: root.querySelector('#nccv-license-status'),
			licenseHint: root.querySelector('#nccv-license-hint'),
			licenseMessage: root.querySelector('#nccv-license-message'),
			defaultMessage: root.querySelector('#nccv-default-message'),
			defaultTableShare: root.querySelector('#nccv-default-tbody-share'),
			defaultTableTalk: root.querySelector('#nccv-default-tbody-talk'),
			defaultSave: root.querySelector('#nccv-default-save'),
			seatUsage: root.querySelector('#nccv-seat-usage'),
			assignedSeatUsage: root.querySelector('#nccv-assigned-seat-usage'),
			assignedMessage: root.querySelector('#nccv-assigned-message'),
			seatMessage: root.querySelector('#nccv-seat-message'),
			groupSelect: root.querySelector('#nccv-group-select'),
			userSearch: root.querySelector('#nccv-user-search'),
			userTable: root.querySelector('#nccv-user-tbody'),
			userPrev: root.querySelector('#nccv-user-prev'),
			userNext: root.querySelector('#nccv-user-next'),
			userPage: root.querySelector('#nccv-user-page'),
			bulkAssign: root.querySelector('#nccv-seat-bulk-assign'),
			bulkUnassign: root.querySelector('#nccv-seat-bulk-unassign'),
			assignedSeats: root.querySelector('#nccv-assigned-seats'),
			seatReportDownload: root.querySelector('#nccv-seat-report-download'),
			groupOverrideGroup: root.querySelector('#nccv-group-override-group'),
			groupOverridePriority: root.querySelector('#nccv-group-override-priority'),
			groupOverrideSave: root.querySelector('#nccv-group-override-save'),
			groupOverrideMessage: root.querySelector('#nccv-group-override-message'),
			groupOverrideTableShare: root.querySelector('#nccv-group-override-tbody-share'),
			groupOverrideTableTalk: root.querySelector('#nccv-group-override-tbody-talk'),
			overrideUser: root.querySelector('#nccv-override-user'),
			overrideSave: root.querySelector('#nccv-override-save'),
			overrideMessage: root.querySelector('#nccv-override-message'),
			overrideTableShare: root.querySelector('#nccv-override-tbody-share'),
			overrideTableTalk: root.querySelector('#nccv-override-tbody-talk'),
		}

		let licenseSnapshot = null
		let searchTimer = null

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
					const modeSelect = root.querySelector(`.nccv-user-mode[data-setting-key="${settingKey}"]`)
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
					const modeSelect = root.querySelector(`.nccv-group-mode[data-setting-key="${settingKey}"]`)
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
				console.error('nccv template asset refresh failed', settingKey, error)
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

		const attachSeatHandlers = () => {
			refs.userTable.querySelectorAll('tr[data-user-id]').forEach((row) => {
				const userId = row.getAttribute('data-user-id')
				const checkbox = row.querySelector('.nccv-seat-toggle')
				checkbox.addEventListener('change', async () => {
					setMessage(refs.seatMessage, '', '')
					try {
						await api.setSeat(userId, checkbox.checked)
						await refreshSeatsAndUsers(false)
						setMessage(refs.seatMessage, tr('Seat saved.'), 'success')
					} catch (error) {
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
			setMessage(refs.licenseMessage, '', '')
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
			const fetched = response.items || []
			state.userPaging.hasNext = fetched.length > state.userPaging.limit
			const visible = state.userPaging.hasNext ? fetched.slice(0, state.userPaging.limit) : fetched
			renderUsers(refs.userTable, visible)
			updatePager(visible.length)
			attachSeatHandlers()
		}

		const refreshSeatsAndUsers = async (resetPaging = false) => {
			await refreshUsers(resetPaging)
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
			state.assignedSeats = seats
			renderSeatUsage(seatStatus)
			renderAssignedSeats(refs.assignedSeats, seats)
			fillOverrideUsers(seats)
		}

		const refreshDefaults = async () => {
			removeTemplateEditorsByPrefix('default')
			const response = await api.loadDefaults()
			state.schema = response.schema || {}
			state.defaults = response.defaults || {}
			state.defaultModes = response.default_modes || {}
			state.defaultTemplateAssets = response.template_assets || {}
			state.schemaTemplateAssets = response.schema_template_assets || {}
			renderDefaultsRows(refs.defaultTableShare, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'share')
			renderDefaultsRows(refs.defaultTableTalk, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'talk')
			syncDefaultControlState(root)
			attachDefaultModeHandlers(root)
			attachAttachmentDependencyHandlers(root)
			attachTemplateLanguageDependencyHandlers(root, 'default')
			attachTemplateEditorHandlers(root)
			renderGroupOverrideTables(root, refs, state, refs.groupOverrideGroup.value)
			renderOverrideTables(root, refs, state, refs.overrideUser.value)
		}

		const refreshOverrides = async (userId) => {
			if (!userId) {
				state.overrides = {}
				state.overrideTemplateAssets = {}
			} else {
				const response = await api.loadUserOverrides(userId)
				state.overrides = response.items || {}
				state.overrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
			}
			renderOverrideTables(root, refs, state, userId)
		}

		const refreshGroupOverrides = async (groupId) => {
			if (!groupId) {
				state.groupOverrides = {}
				state.groupOverrideTemplateAssets = {}
				state.groupOverridePriority = 100
				refs.groupOverridePriority.value = '100'
			} else {
				const response = await api.loadGroupOverrides(groupId)
				state.groupOverridePriority = Number.parseInt(String(response.priority ?? 100), 10) || 100
				refs.groupOverridePriority.value = String(state.groupOverridePriority)
				state.groupOverrides = response.items || {}
				state.groupOverrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
			}
			renderGroupOverrideTables(root, refs, state, groupId)
		}

		const isAttachmentThresholdEnabled = (prefix) => {
			const alwaysViaConnector = root.querySelector(`.nccv-setting-control[data-prefix="${prefix}"][data-setting-key="attachments_always_via_ncconnector"]`)
			if (alwaysViaConnector?.checked) {
				return false
			}
			const toggle = root.querySelector(`.nccv-threshold-enabled[data-prefix="${prefix}"][data-setting-key="attachments_min_size_mb"]`)
			return toggle ? Boolean(toggle.checked) : true
		}

		const collectDefaultPayload = () => {
			const payload = {}
			sortedSettingKeys(state.schema).forEach((key) => {
				const addonToggle = root.querySelector(`.nccv-addon-changeable[data-setting-key="${key}"]`)
				const isAddonChangeable = !isTemplateEditorSettingKey(key) && Boolean(addonToggle?.checked)
				const value = key === 'attachments_min_size_mb'
					&& !isAttachmentThresholdEnabled('default')
					? null
					: readSettingControl(root, 'default', key, state.schema[key])
				payload[key] = {
					mode: isAddonChangeable ? 'user_choice' : 'default',
					value,
				}
			})
			return payload
		}

		const collectOverridePayload = () => {
			const payload = {}
			sortedSettingKeys(state.schema).forEach((key) => {
				const mode = root.querySelector(`.nccv-user-mode[data-setting-key="${key}"]`)?.value || 'inherit'
				if (mode !== 'forced') {
					payload[key] = { mode: 'inherit' }
					return
				}
				payload[key] = {
					mode: 'forced',
					value: key === 'attachments_min_size_mb'
						&& !isAttachmentThresholdEnabled('override')
						? null
						: readSettingControl(root, 'override', key, state.schema[key]),
				}
			})
			return payload
		}

		const collectGroupOverridePayload = () => {
			const payload = {}
			sortedSettingKeys(state.schema).forEach((key) => {
				const mode = root.querySelector(`.nccv-group-mode[data-setting-key="${key}"]`)?.value || 'inherit'
				if (mode !== 'forced') {
					payload[key] = { mode: 'inherit' }
					return
				}
				payload[key] = {
					mode: 'forced',
					value: key === 'attachments_min_size_mb'
						&& !isAttachmentThresholdEnabled('group-override')
						? null
						: readSettingControl(root, 'group-override', key, state.schema[key]),
				}
			})
			return payload
		}

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
				await refreshGroupOverrides(groupId).catch((error) => setMessage(refs.groupOverrideMessage, error.message || tr('Failed to load group overrides.'), 'error'))
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
			await refreshOverrides(userId).catch((error) => setMessage(refs.overrideMessage, error.message || tr('Failed to load overrides.'), 'error'))
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
				await refreshSeatsAndUsers(false)
				setMessage(refs.licenseMessage, tr('Operating mode saved.'), 'success')
			} catch (error) {
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
				setMessage(refs.licenseMessage, error.message || tr('Synchronization failed.'), 'error')
			}
		})

		refs.groupSelect.addEventListener('change', async () => refreshUsers(true).catch((error) => setMessage(refs.seatMessage, error.message || tr('Failed to load users.'), 'error')))
		refs.userSearch.addEventListener('input', () => {
			if (searchTimer !== null) {
				clearTimeout(searchTimer)
			}
			searchTimer = window.setTimeout(() => {
				searchTimer = null
				refreshUsers(true).catch((error) => setMessage(refs.seatMessage, error.message || tr('Failed to load users.'), 'error'))
			}, 250)
		})

		refs.userPrev.addEventListener('click', async () => {
			if (state.userPaging.offset <= 0) {
				return
			}
			state.userPaging.offset = Math.max(0, state.userPaging.offset - state.userPaging.limit)
			await refreshUsers(false).catch((error) => setMessage(refs.seatMessage, error.message || tr('Failed to change page.'), 'error'))
		})
		refs.userNext.addEventListener('click', async () => {
			if (!state.userPaging.hasNext) {
				return
			}
			state.userPaging.offset += state.userPaging.limit
			await refreshUsers(false).catch((error) => setMessage(refs.seatMessage, error.message || tr('Failed to change page.'), 'error'))
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
				downloadTextFile(`nc_connector-seat-report-${timestamp}.csv`, csv, 'text/csv;charset=utf-8')
				setMessage(refs.assignedMessage, tr('Seat report downloaded.'), 'success')
			} catch (error) {
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
				removeTemplateEditorsByPrefix('default')
				renderDefaultsRows(refs.defaultTableShare, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'share')
				renderDefaultsRows(refs.defaultTableTalk, state.schema, state.defaults, state.defaultModes, state.defaultTemplateAssets, state.schemaTemplateAssets, 'talk')
				syncDefaultControlState(root)
				attachDefaultModeHandlers(root)
				attachAttachmentDependencyHandlers(root)
				attachTemplateLanguageDependencyHandlers(root, 'default')
				attachTemplateEditorHandlers(root)
				setMessage(refs.defaultMessage, tr('Default settings saved.'), 'success')
				if (refs.overrideUser.value) {
					await refreshOverrides(refs.overrideUser.value)
				}
				if (refs.groupOverrideGroup.value) {
					await refreshGroupOverrides(refs.groupOverrideGroup.value)
				}
			} catch (error) {
				setMessage(refs.defaultMessage, error.message || tr('Failed to save defaults.'), 'error')
			}
		})

		refs.groupOverrideGroup.addEventListener('change', async () => {
			await refreshGroupOverrides(refs.groupOverrideGroup.value).catch((error) => setMessage(refs.groupOverrideMessage, error.message || tr('Failed to load group overrides.'), 'error'))
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
				state.groupOverridePriority = Number.parseInt(String(response.priority ?? priority), 10) || priority
				refs.groupOverridePriority.value = String(state.groupOverridePriority)
				state.groupOverrides = response.items || state.groupOverrides
				state.groupOverrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
				renderGroupOverrideTables(root, refs, state, refs.groupOverrideGroup.value)
				await refreshSeatsAndUsers(false)
				setMessage(refs.groupOverrideMessage, tr('Group overrides saved.'), 'success')
			} catch (error) {
				setMessage(refs.groupOverrideMessage, error.message || tr('Failed to save group overrides.'), 'error')
			}
		})

		refs.overrideUser.addEventListener('change', async () => {
			await refreshOverrides(refs.overrideUser.value).catch((error) => setMessage(refs.overrideMessage, error.message || tr('Failed to load overrides.'), 'error'))
		})
		refs.overrideSave.addEventListener('click', async () => {
			if (!refs.overrideUser.value) {
				setMessage(refs.overrideMessage, tr('Please select a seat user.'), 'error')
				return
			}
			setMessage(refs.overrideMessage, '', '')
			try {
				const response = await api.saveUserOverrides(refs.overrideUser.value, collectOverridePayload())
				state.overrides = response.items || state.overrides
				state.overrideTemplateAssets = response.template_assets || {}
				state.schemaTemplateAssets = response.schema_template_assets || state.schemaTemplateAssets
				renderOverrideTables(root, refs, state, refs.overrideUser.value)
				await refreshSeatsAndUsers(false)
				setMessage(refs.overrideMessage, tr('Overrides saved.'), 'success')
			} catch (error) {
				setMessage(refs.overrideMessage, error.message || tr('Failed to save overrides.'), 'error')
			}
		})

		try {
			await refreshLicense()
		} catch (error) {
			console.error('nccv license init failed', error)
		}
		try {
			await refreshGroups()
		} catch (error) {
			setMessage(refs.seatMessage, error.message || tr('Failed to load groups.'), 'error')
		}
		try {
			await refreshDefaults()
		} catch (error) {
			setMessage(refs.defaultMessage, error.message || tr('Failed to load default settings.'), 'error')
		}
		try {
			await refreshSeatsAndUsers(true)
		} catch (error) {
			setMessage(refs.seatMessage, error.message || tr('Failed to load seat section.'), 'error')
		}
	}

	void main()
})()
