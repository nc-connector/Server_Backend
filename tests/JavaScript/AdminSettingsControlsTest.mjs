import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'

class Element {
  constructor(attributes = {}, children = []) {
    this.attributes = attributes
    this.children = children
    this.dataset = Object.fromEntries(Object.entries(attributes)
      .filter(([key]) => key.startsWith('data-'))
      .map(([key, value]) => [key.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()), value]))
    const classes = new Set((attributes.class || '').split(/\s+/))
    this.classList = {
      contains: (name) => classes.has(name),
      toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
    }
    this.hidden = false
    this.disabled = false
    this.checked = false
    this.value = ''
    this.innerHTML = ''
    for (const child of children) child.parent = this
  }

  getAttribute(name) { return this.attributes[name] ?? null }

  matches(selector) {
    return selector.split(',').some((part) => (
      [...part.matchAll(/\[([^=\]]+)(?:="([^"]*)")?\]/g)].every(([, key, value]) => (
        key in this.attributes && (value === undefined || this.attributes[key] === value)
      ))
      && [...part.matchAll(/\.([a-zA-Z0-9_-]+)/g)].every(([, name]) => this.classList.contains(name))
    ))
  }

  querySelectorAll(selector) {
    return this.children.flatMap((child) => [child, ...child.querySelectorAll('*')])
      .filter((child) => child.matches(selector))
  }

  querySelector(selector) { return this.querySelectorAll(selector)[0] || null }
  closest(selector) { return selector === 'tr' ? this.parent : null }
}

function controls() {
  const context = {
    window: {}, HTMLElement: Element, HTMLInputElement: Element, HTMLSelectElement: Element,
    HTMLTextAreaElement: Element, HTMLOptionElement: Element, HTMLButtonElement: Element,
    tr: (key) => key, templateEditor: { syncState() {} },
  }
  for (const module of ['adminSettingsMeta', 'adminPermissions', 'adminSettingsPayload', 'adminTabs', 'adminVisibility']) {
    vm.runInNewContext(readFileSync(`ncc_backend_4mc/js/${module}.js`, 'utf8'), context)
  }
  Object.assign(context, context.window.NCCBackendAdminPermissions)
  const source = readFileSync('ncc_backend_4mc/js/ncc_backend_4mc-adminSettings.js', 'utf8')
  // Exercise the existing controls without starting the full Nextcloud admin page.
  for (const [name, next] of [
    ['const adminSettingsMeta', 'const adminPermissions'],
    ['function escapeHtml(', 'function formatDate('],
    ['function settingLabel(', 'function mergeSchema('],
    ['function renderSettingDependencyNotes(', 'const api ='],
    ['function renderSettingControl(', 'const SETTING_LAYER_UI ='],
    ['const SETTING_LAYER_UI =', 'function renderOverrideTables('],
  ]) {
    const start = source.indexOf(name)
    const end = source.indexOf(next, start)
    assert.ok(start >= 0 && end > start, `Control boundaries: ${name}`)
    vm.runInNewContext(source.slice(start, end), context, { filename: `adminSettingsControls:${name}` })
  }
  vm.runInNewContext(`
    const isTemplateEditorSettingKey = (key) => TEMPLATE_EDITOR_SETTING_KEYS.has(key)
  `, context)
  context.settingLayerUi = vm.runInNewContext('SETTING_LAYER_UI', context)
  return context
}

test('attachment controls preserve disabled state and submit a positive value when enabled', () => {
  const ui = controls()
  const key = 'attachments_min_size_mb'
  const definition = { type: 'int', min: 1, max: 10240, default: 5 }
  for (const prefix of ['default', 'override', 'group-override']) {
    for (const value of [null, undefined, 1, 19]) {
      const markup = ui.renderSettingControl(prefix, key, definition, value, false)
      const checkbox = markup.match(/<input type="checkbox"[^>]+>/)?.[0]
      const number = markup.match(/<input[^>]+type="number"[^>]+>/)?.[0]
      assert.ok(checkbox && number)
      assert.match(number, /min="1"/)
      assert.match(number, /max="10240"/)
      const enabled = value !== null && value !== undefined
      const toggle = { checked: /\bchecked\b/.test(checkbox), dataset: {} }
      const input = { value: number.match(/value="(\d+)"/)[1], dataset: {}, closest: () => null }
      const always = { checked: false }
      assert.equal(toggle.checked, enabled)
      assert.equal(Number(input.value), enabled ? value : 5)
      const root = {
        querySelector(selector) {
          if (selector.includes('attachments_always_via_ncconnector')) return always
          if (selector.startsWith('.nccb-threshold-enabled')) return toggle
          if (selector.startsWith('.nccb-setting-control')) return input
          if (selector.startsWith('.nccb-addon-changeable')) return { checked: false }
          return { value: 'forced' }
        },
      }
      const layer = { prefix, modeSelector: '.mode' }
      const payload = ui.window.NCCBackendSettingsPayload.createPayloadHelpers({
        root, state: { schema: { [key]: definition } },
        settingLayerUi: { defaults: layer, userOverride: layer, groupOverride: layer },
        canEditDefaultSetting: () => true,
        canEditGroupOverrideSetting: () => true,
        canEditUserOverrideSetting: () => true,
        getModeControlKey: (setting) => setting,
        isTemplateEditorSettingKey: () => false,
        isUserOverrideOnlySettingKey: () => false,
        readSettingControl: ui.readSettingControl,
        sortedSettingKeys: Object.keys,
      })
      const collect = prefix === 'default' ? payload.collectDefaultPayload
        : prefix === 'override' ? payload.collectOverridePayload : payload.collectGroupOverridePayload
      ui.syncAttachmentMinSizeDependency(root, prefix)
      assert.equal(input.disabled, !enabled)
      assert.equal(collect()[key].value, enabled ? value : null)
      toggle.checked = true
      ui.syncAttachmentMinSizeDependency(root, prefix)
      assert.equal(input.disabled, false)
      assert.equal(collect()[key].value, enabled ? value : 5)
      always.checked = true
      ui.syncAttachmentMinSizeDependency(root, prefix)
      assert.equal(collect()[key].value, null)
      assert.equal(input.disabled, true)
      always.checked = false
      ui.syncAttachmentMinSizeDependency(root, prefix)
      assert.equal(collect()[key].value, enabled ? value : 5)
    }
  }
})

const sourceKey = 'defaults_source'
const sourceDefinition = { type: 'enum', options: ['inherit', 'local', 'backend'], default: 'inherit', addon_editable_supported: true }
const sourceSchema = {
  [sourceKey]: sourceDefinition,
  share_permission_upload: { type: 'bool', default: false },
}

function defaultControls(key, value, editable) {
  const toggle = new Element({ class: 'nccb-addon-changeable', 'data-setting-key': key })
  toggle.checked = editable
  const input = new Element({ class: 'nccb-setting-control', 'data-prefix': 'default', 'data-setting-key': key })
  input.value = value
  const cell = new Element({ class: 'nccb-default-value-cell' })
  const row = new Element({ 'data-default-setting-key': key }, [toggle, input, cell])
  return { row, toggle, input, cell }
}

function payloadHelpers(ui, root, state) {
  return ui.window.NCCBackendSettingsPayload.createPayloadHelpers({
    root, state, settingLayerUi: ui.settingLayerUi,
    canEditDefaultSetting: ui.canEditDefaultSetting,
    canEditGroupOverrideSetting: ui.canEditGroupOverrideSetting,
    canEditUserOverrideSetting: ui.canEditUserOverrideSetting,
    getModeControlKey: ui.getTemplateModeControlKey,
    isTemplateEditorSettingKey: () => false,
    isUserOverrideOnlySettingKey: ui.isUserOverrideOnlySettingKey,
    readSettingControl: ui.readSettingControl,
    sortedSettingKeys: ui.sortedSettingKeys,
  })
}

test('global defaults source belongs to General and is editable only by full administrators', () => {
  const ui = controls()
  const permissions = ui.permissionMatrix.flatMap(({ area }) => ui.permissionColumns.map(({ suffix }) => `${area}.${suffix}`))
  assert.equal(ui.settingCategory(sourceKey), 'general')
  for (let selected = 0; selected < 2 ** permissions.length; selected++) {
    const state = { admin: { is_nextcloud_admin: false, permissions: permissions.filter((_, index) => selected & (1 << index)) } }
    assert.equal(ui.canEditDefaultSetting(state, sourceKey), false, `Delegated scope combination ${selected}`)
    assert.equal(ui.canEditUserOverrideSetting(state, sourceKey), false)
    assert.equal(ui.canEditGroupOverrideSetting(state, sourceKey), false)
  }
  for (const mode of ['community', 'pro']) {
    const state = { mode, admin: { is_nextcloud_admin: true, permissions: [] } }
    assert.equal(ui.canEditDefaultSetting(state, sourceKey), true)
    assert.equal(ui.canEditUserOverrideSetting(state, sourceKey), false)
    assert.equal(ui.canEditGroupOverrideSetting(state, sourceKey), false)
    assert.equal(ui.canEditDefaultSetting(state, 'share_permission_upload'), true)
  }
  const unrelated = { admin: { is_nextcloud_admin: false, permissions: ['general.policy', 'general.user_overrides', 'general.group_overrides'] } }
  assert.equal(ui.canEditDefaultSetting(unrelated, sourceKey), false, 'A synthetic delegated general scope cannot grant the global permission')
  assert.equal(ui.canEditDefaultSetting({}, sourceKey), false)
})

test('source row renders three starting values and keeps its value enabled with either editability mode', () => {
  const ui = controls()
  for (const value of [undefined, ...sourceDefinition.options]) {
    for (const mode of ['default', 'user_choice']) {
      const tbody = new Element()
      const defaults = value === undefined ? {} : { [sourceKey]: value }
      ui.renderDefaultsRows(tbody, sourceSchema, defaults, { [sourceKey]: mode }, {}, {}, 'general')
      assert.match(tbody.innerHTML, /data-default-setting-key="defaults_source"/)
      assert.doesNotMatch(tbody.innerHTML, /data-default-setting-key="share_permission_upload"/)
      const select = tbody.innerHTML.match(/<select[^>]*data-setting-key="defaults_source"[^>]*>([\s\S]*?)<\/select>/)
      const toggle = tbody.innerHTML.match(/<input[^>]*class="nccb-addon-changeable"[^>]*>/)?.[0]
      assert.ok(select && toggle)
      assert.deepEqual([...select[1].matchAll(/<option value="([^"]+)"/g)].map((match) => match[1]), sourceDefinition.options)
      assert.equal(select[1].match(/<option value="([^"]+)"[^>]*\bselected\b/)?.[1], value ?? 'inherit')
      assert.doesNotMatch(select[0], /\bdisabled\b/)
      assert.equal(/\bchecked\b/.test(toggle), mode === 'user_choice')
      assert.doesNotMatch(tbody.innerHTML, /nccb-default-value-cell--disabled/)
      assert.match(tbody.innerHTML, /role="tooltip"/)
      assert.match(tbody.innerHTML, /Editable in add-on/)
      for (const category of ['share', 'talk', 'email_signature']) {
        ui.renderDefaultsRows(tbody, sourceSchema, defaults, { [sourceKey]: mode }, {}, {}, category)
        assert.doesNotMatch(tbody.innerHTML, /data-default-setting-key="defaults_source"/)
      }
    }
  }
})

test('live source editability changes preserve the starting value and ordinary policy control behavior', () => {
  const ui = controls()
  for (const value of sourceDefinition.options) {
    const source = defaultControls(sourceKey, value, false)
    const ordinary = defaultControls('share_permission_upload', '', false)
    const root = new Element({}, [source.row, ordinary.row])
    for (const editable of [true, false, true]) {
      source.toggle.checked = editable
      ordinary.toggle.checked = editable
      source.input.disabled = true
      source.input.dataset.disabledByMode = '1'
      source.cell.classList.toggle('nccb-default-value-cell--disabled', true)
      ui.syncDefaultControlState(root)
      assert.equal(source.input.disabled, false)
      assert.equal(source.input.dataset.disabledByMode, '0')
      assert.equal(source.cell.classList.contains('nccb-default-value-cell--disabled'), false)
      assert.equal(source.input.value, value)
      assert.equal(source.toggle.checked, editable)
      assert.equal(ordinary.input.disabled, editable)
      assert.equal(ordinary.cell.classList.contains('nccb-default-value-cell--disabled'), editable)
    }
  }
})

test('default payload retains source and editability while delegates and override payloads omit it', () => {
  const ui = controls()
  const allScopes = ui.permissionMatrix.flatMap(({ area }) => ui.permissionColumns.map(({ suffix }) => `${area}.${suffix}`))
  for (const fullAdmin of [false, true]) {
    for (const mode of ['community', 'pro']) {
      for (const value of sourceDefinition.options) {
        for (const editable of [false, true]) {
          const source = defaultControls(sourceKey, value, editable)
          const ordinary = defaultControls('share_permission_upload', '', false)
          const root = new Element({}, [source.row, ordinary.row])
          const state = { mode, schema: sourceSchema, admin: { is_nextcloud_admin: fullAdmin, permissions: allScopes } }
          const helpers = payloadHelpers(ui, root, state)
          const defaults = helpers.collectDefaultPayload()
          assert.equal(Object.hasOwn(defaults, sourceKey), fullAdmin)
          if (fullAdmin) {
            assert.deepEqual(JSON.parse(JSON.stringify(defaults[sourceKey])), { mode: editable ? 'user_choice' : 'default', value })
          }
          assert.equal(defaults.share_permission_upload.value, false, 'Explicit false stays in ordinary policy payloads')
          for (const collect of [helpers.collectOverridePayload, helpers.collectGroupOverridePayload]) {
            const overrides = collect()
            assert.equal(Object.hasOwn(overrides, sourceKey), false)
            assert.deepEqual(JSON.parse(JSON.stringify(overrides.share_permission_upload)), { mode: 'inherit' })
          }
        }
      }
    }
  }
  const absentControls = { querySelector() { throw new Error('A forbidden source field must not be read') } }
  for (const fullAdmin of [false, true]) {
    const helpers = payloadHelpers(ui, absentControls, { schema: { [sourceKey]: sourceDefinition }, admin: { is_nextcloud_admin: fullAdmin, permissions: allScopes } })
    assert.equal(Object.keys(helpers.collectOverridePayload()).length, 0)
    assert.equal(Object.keys(helpers.collectGroupOverridePayload()).length, 0)
    if (!fullAdmin) assert.equal(Object.keys(helpers.collectDefaultPayload()).length, 0)
  }
})

test('the General defaults tab and global row are visible only to full administrators', () => {
  const ui = controls()
  const tabs = ui.window.NCCBackendAdminTabs
  const visibility = ui.window.NCCBackendAdminVisibility
  const allScopes = ui.permissionMatrix.flatMap(({ area }) => ui.permissionColumns.map(({ suffix }) => `${area}.${suffix}`))
  const nodes = ['general', 'share', 'talk', 'email_signature'].flatMap((name) => [
    new Element({ 'data-default-tab-button': name, class: name === 'general' ? 'active' : '' }),
    new Element({ 'data-default-tab-panel': name }),
  ])
  const source = new Element({ 'data-default-setting-key': sourceKey })
  const ordinary = new Element({ 'data-default-setting-key': 'share_permission_upload' })
  const root = new Element({}, [...nodes, source, ordinary])
  const options = { ...ui.window.NCCBackendAdminPermissions,
    setDefaultsTab: (element, name) => tabs.setTab(element, 'defaults', name),
    setGroupTab() {}, setGroupOverrideTab() {}, setMainTab() {}, setOverrideTab() {},
  }
  for (const fullAdmin of [true, false, true, false]) {
    tabs.setTab(root, 'defaults', 'general')
    visibility.applyAdminUiVisibility(root, {}, { admin: { is_nextcloud_admin: fullAdmin, permissions: allScopes } }, options)
    const button = root.querySelector('[data-default-tab-button="general"]')
    const panel = root.querySelector('[data-default-tab-panel="general"]')
    assert.equal(button.hidden, !fullAdmin)
    assert.equal(panel.hidden, !fullAdmin)
    assert.equal(source.hidden, !fullAdmin)
    assert.equal(ordinary.hidden, false)
    assert.equal(root.querySelector('[data-default-tab-button="share"]').hidden, false)
    if (!fullAdmin) {
      assert.equal(root.querySelector('[data-default-tab-button="share"]').classList.contains('active'), true)
      assert.equal(root.querySelector('[data-default-tab-panel="share"]').hidden, false)
    }
  }
})

test('General defaults markup, table reference, refresh and save all use the existing settings path', () => {
  const source = readFileSync('ncc_backend_4mc/js/ncc_backend_4mc-adminSettings.js', 'utf8')
  assert.match(source, /data-default-tab-button="general"/)
  assert.match(source, /data-default-tab-panel="general"[\s\S]*?<tbody id="nccb-default-tbody-general"><\/tbody>/)
  assert.match(source, /defaultTableGeneral:\s*root\.querySelector\('#nccb-default-tbody-general'\)/)
  assert.equal((source.match(/renderDefaultsRows\(refs\.defaultTableGeneral,[^\n]+'general'\)/g) || []).length, 2)
  assert.doesNotMatch(source, /data-(?:group-override|override)-tab-(?:button|panel)="general"/)
})
