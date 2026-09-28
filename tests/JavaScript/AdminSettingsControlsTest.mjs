import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'

function controls() {
  const context = { window: {} }
  const source = readFileSync('ncc_backend_4mc/js/ncc_backend_4mc-adminSettings.js', 'utf8')
  // Exercise the existing controls without starting the full Nextcloud admin page.
  for (const [name, next] of [
    ['escapeHtml', 'formatDate'],
    ['renderSettingDependencyNotes', 'isEmbeddedTalkTemplateSettingKey'],
    ['syncAttachmentMinSizeDependency', 'applySharePasswordDependency'],
    ['renderSettingControl', 'renderTalkTemplateFormatControl'],
    ['readSettingControl', null],
  ]) {
    const start = source.indexOf(`\tfunction ${name}(`)
    const end = source.indexOf(next ? `\tfunction ${next}(` : '\tconst SETTING_LAYER_UI =', start)
    assert.ok(start >= 0 && end > start, `Control boundaries: ${name}`)
    vm.runInNewContext(source.slice(start, end), context)
  }
  vm.runInNewContext(`
    const tr = (key) => key
    const SHARE_SEND_PASSWORD_MODE_KEY = 'share_send_password_mode'
    const SHARE_SECRETS_EXPIRE_DAYS_KEY = 'share_secrets_expire_days'
  `, context)
  vm.runInNewContext(readFileSync('ncc_backend_4mc/js/adminSettingsPayload.js', 'utf8'), context)
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
