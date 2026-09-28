import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'
import vm from 'node:vm'

class Element {
  hidden = false
  innerHTML = ''
  textContent = ''
}

test('license facts override Nextcloud definition-list layout within their own scope', () => {
  const css = readFileSync('ncc_backend_4mc/css/adminStatus.css', 'utf8')
  const reset = css.match(/\.nccb-license-facts dt,\s*\.nccb-license-facts dd\s*\{([^}]+)\}/)?.[1]
  assert.ok(reset, 'Both labels and values need the scoped layout reset')
  for (const declaration of [
    /display:\s*block;/, /width:\s*auto;/, /padding:\s*0;/,
    /white-space:\s*normal;/, /text-align:\s*start;/, /overflow-wrap:\s*anywhere;/,
  ]) {
    assert.match(reset, declaration)
  }
  const facts = css.match(/\.nccb-license-facts\s*\{([^}]+)\}/)?.[1]
  assert.match(facts, /grid-template-columns:\s*minmax\(0, 1fr\);/)
  assert.match(css, /@media \(max-width: 600px\)\s*\{\s*\.nccb-license-facts > div\s*\{\s*grid-template-columns:\s*minmax\(0, 1fr\);/)
})

test('activation help stays inside the status row instead of extending beyond its icon', () => {
  const css = readFileSync('ncc_backend_4mc/css/adminStatus.css', 'utf8')
  const row = css.match(/\.nccb-license-facts > div\s*\{([^}]+)\}/)?.[1]
  assert.match(row, /position:\s*relative;/)
  assert.match(css, /\.nccb-license-facts \.nccb-help-wrap\s*\{\s*position:\s*static;/)
  const tooltip = css.match(/\.nccb-license-facts \.nccb-help-tooltip\s*\{([^}]+)\}/)?.[1]
  assert.match(tooltip, /\bwidth:\s*100%;/)
  assert.match(tooltip, /min-width:\s*0;/)
  assert.match(tooltip, /bottom:\s*100%;/)
  assert.match(css, /\.nccb-license-facts > div:hover \.nccb-help-tooltip\s*\{\s*display:\s*block;/)
})

function render(overrides = {}, seatStatus = null, previousRefs = null) {
  const context = { window: {}, HTMLElement: Element }
  vm.runInNewContext(readFileSync('ncc_backend_4mc/js/adminGeneralStatusUi.js', 'utf8'), context)
  const refs = previousRefs || { licenseStatus: new Element(), licenseHint: new Element(), proFunnel: new Element() }
  const snapshot = {
    mode: 'pro', has_credentials: true, is_valid: true,
    status_effective: 'ACTIVE', license_status_effective: 'ACTIVE',
    purchased_seats: 20, expires_at: 2000000000, grace_until: 2001209600,
    last_sync_at: 1900000000, activation: null, ...overrides,
  }
  const helpers = {
    tr: (key) => key,
    escapeHtml: (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;'),
    formatDate: String, formatDateTime: String, assignedSeats: seatStatus?.assigned ?? 6, seatStatus,
    renderInlineHelp: (_title, lines) => '<span role="tooltip">' + lines.join(' ') + '</span>',
  }
  context.window.NCCBackendGeneralStatusUi.renderLicenseStatus(refs, snapshot, helpers)
  context.window.NCCBackendGeneralStatusUi.renderProFunnel(refs, snapshot, helpers)
  return refs
}

test('active licenses show capacity and assignments separately without a sales panel', () => {
  const refs = render()
  assert.match(refs.licenseStatus.innerHTML, /License capacity<\/dt><dd>20/)
  assert.match(refs.licenseStatus.innerHTML, /Assigned seats<\/dt><dd>6/)
  assert.match(refs.licenseStatus.innerHTML, /Pro features are available/)
  assert.match(refs.licenseStatus.innerHTML, /Last successful sync/)
  assert.equal(refs.proFunnel.hidden, true)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /License activation|Grace period ends/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /nccb-license-warning/)
})

test('grace remains usable and shows the deadline', () => {
  const refs = render({ status_effective: 'GRACE', license_status_effective: 'GRACE' })
  assert.match(refs.licenseStatus.innerHTML, /Grace period ends on/)
  assert.match(refs.licenseStatus.innerHTML, /Pro features are available/)
  assert.match(refs.licenseStatus.innerHTML, /role="status"><strong>Your license has expired\. Please renew your license\./)
  assert.ok(refs.licenseStatus.innerHTML.indexOf('Your license has expired') < refs.licenseStatus.innerHTML.indexOf('<dl'))
  assert.match(refs.licenseStatus.innerHTML, /Grace period ends on: 2001209600/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"/)
  assert.equal(refs.proFunnel.hidden, true)
})

test('expired licenses retain their capacity without promising usable Pro seats', () => {
  const refs = render({ status_effective: 'EXPIRED', license_status_effective: 'EXPIRED', is_valid: false })
  assert.match(refs.licenseStatus.innerHTML, /Grace until/)
  assert.match(refs.licenseStatus.innerHTML, /role="alert"><strong>Your license has expired\. Pro features are not available\./)
  assert.match(refs.licenseStatus.innerHTML, /Basic features remain available/)
  assert.match(refs.licenseStatus.innerHTML, /Seat assignments remain stored/)
  assert.match(refs.licenseStatus.innerHTML, /License capacity<\/dt><dd>20/)
  assert.match(refs.proFunnel.innerHTML, /Renew or check your license/)
  assert.doesNotMatch(refs.proFunnel.innerHTML, /Activate Pro for teams|trial key/)
})

test('an activation conflict does not pretend that the purchased license expired', () => {
  const refs = render({
    status_effective: 'ACTIVATION_REQUIRED', is_valid: false,
    activation: { required: true, enforced: true, verified: false, state: 'conflict' },
  })
  assert.match(refs.licenseStatus.innerHTML, /License<\/dt><dd>Active/)
  assert.match(refs.licenseStatus.innerHTML, /already activated for another Nextcloud/)
  assert.match(refs.licenseStatus.innerHTML, /replacement license key/)
  assert.match(refs.licenseStatus.innerHTML, /https:\/\/nc-connector.de\/support\//)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /after renewal|does not block access/)
  assert.equal(refs.proFunnel.hidden, true)
  assert.match(refs.licenseStatus.innerHTML, /role="alert"><strong>This license is already activated/)
})

test('rollout conflicts remain nonblocking and are not displayed as activated', () => {
  const refs = render({ activation: { required: true, enforced: false, verified: false, state: 'conflict' } })
  assert.match(refs.licenseStatus.innerHTML, /Pro features are available/)
  assert.match(refs.licenseStatus.innerHTML, /does not block access/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /Activated for this Nextcloud/)
  assert.match(refs.licenseStatus.innerHTML, /role="status"><strong>This license is already activated/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"/)
})

test('offline expiry keeps commercial and activation information separate from access', () => {
  const refs = render({
    status_effective: 'OFFLINE_EXPIRED', is_valid: false, last_error: 'license_sync_unavailable',
    activation: { required: true, enforced: true, verified: true, state: 'activated' },
  })
  assert.match(refs.licenseStatus.innerHTML, /License<\/dt><dd>Active/)
  assert.match(refs.licenseStatus.innerHTML, /Activated for this Nextcloud/)
  assert.match(refs.licenseStatus.innerHTML, /role="tooltip"/)
  assert.match(refs.licenseStatus.innerHTML, /Offline period ended/)
  assert.match(refs.licenseStatus.innerHTML, /could not be reached/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /after renewal/)
  assert.match(refs.licenseStatus.innerHTML, /role="alert"><strong>Offline period ended/)
})

test('manual trials have no activation warning; the trial action uses the form', () => {
  const manual = render({ activation: { required: false, enforced: false, verified: false, state: 'not_required' } })
  assert.doesNotMatch(manual.licenseStatus.innerHTML, /License activation|replacement license/)
  const community = render({ mode: 'community' })
  assert.match(community.licenseStatus.innerHTML, /1 free seat/)
  assert.match(community.proFunnel.innerHTML, /https:\/\/nc-connector.de\/testlizenz\//)
  assert.doesNotMatch(community.proFunnel.innerHTML, /mailto:/)
})

test('Community offers the same functions while Pro adds users', () => {
  for (const mode of ['community', 'pro']) {
    const refs = render({ mode, has_credentials: false })
    assert.match(refs.proFunnel.innerHTML, /Community includes all features for one user\. Activate Pro for additional users\./)
    assert.doesNotMatch(refs.proFunnel.innerHTML, /For teams, central policies and more Seats/)
  }
})

test('external VFS policy help describes the setting without repeating Seat requirements', () => {
  const context = { window: {} }
  vm.runInNewContext(readFileSync('ncc_backend_4mc/js/adminSettingsMeta.js', 'utf8'), context)
  const tooltip = context.window.NCCBackendAdminSettingsMeta.settingMeta.vfs_external_providers_enabled.tooltip.join(' ')
  assert.equal(tooltip, 'Controls whether users may add files from other Thunderbird VFS providers to the sharing queue.')
  assert.doesNotMatch(tooltip, /Seat|Community|NC Connector Pro/)
})

test('unknown status does not claim that a previously usable license expired', () => {
  const refs = render({ status_effective: 'UNKNOWN', license_status_effective: 'UNKNOWN', is_valid: false })
  assert.doesNotMatch(refs.proFunnel.innerHTML, /no longer usable/)
  assert.match(refs.proFunnel.innerHTML, /no valid license is active yet/)
})

test('raw connection errors are not rendered and displayed values are escaped', () => {
  const refs = render({ last_error: '<script>secret</script>', purchased_seats: '<img src=x>' })
  assert.match(refs.licenseStatus.innerHTML, /Synchronization failed/)
  assert.match(refs.licenseStatus.innerHTML, /&lt;img src=x&gt;/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /<script>|secret|<img/)
})

for (const state of ['INACTIVE', 'INVALID']) {
  test(state.toLowerCase() + ' licenses show blocking notices without a grace promise', () => {
    const refs = render({ status_effective: state, license_status_effective: state, is_valid: false })
    assert.match(refs.licenseStatus.innerHTML, /nccb-license-warning--error" role="alert"/)
    assert.match(refs.licenseStatus.innerHTML, /Pro features are not available/)
    assert.doesNotMatch(refs.licenseStatus.innerHTML, /Pro features are available|Grace period ends on/)
  })
}

test('unknown license state and missing credentials are advisory setup notices', () => {
  for (const overrides of [
    { status_effective: 'UNKNOWN', license_status_effective: 'UNKNOWN', is_valid: false },
    { has_credentials: false },
  ]) {
    const refs = render(overrides)
    assert.match(refs.licenseStatus.innerHTML, /nccb-license-warning" role="status"/)
    assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"|Your license has expired/)
  }
})

for (const reason of ['license_sync_unavailable', 'license_response_invalid', 'installation_proof_unavailable']) {
  test(reason + ' preserves confirmed access and appears as a separate warning', () => {
    const refs = render({ last_error: reason })
    assert.match(refs.licenseStatus.innerHTML, /nccb-license-warning" role="status"/)
    assert.match(refs.licenseStatus.innerHTML, /Pro features are available/)
    assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"|Your license has expired/)
  })
}

for (const state of ['proof_required', 'invalid_proof', 'conflict', 'license_unavailable']) {
  test(state + ' activation failure is visible and does not advertise unblocked access', () => {
    const refs = render({
      status_effective: 'ACTIVATION_REQUIRED', is_valid: false,
      activation: { required: true, enforced: true, verified: false, state },
    })
    assert.match(refs.licenseStatus.innerHTML, /nccb-license-warning--error" role="alert"/)
    assert.match(refs.licenseStatus.innerHTML, /https:\/\/nc-connector.de\/support\//)
    assert.doesNotMatch(refs.licenseStatus.innerHTML, /Pro features are available|does not block access/)
  })
}

test('credential replacement remains a refusal with enforcement off and an exempt activation', () => {
  const refs = render({
    status_effective: 'INVALID', is_valid: false,
    activation: { required: false, enforced: false, verified: false, state: 'credentials_changed' },
  })
  assert.match(refs.licenseStatus.innerHTML, /role="alert"><strong>License activation could not be confirmed/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /Pro features are available|does not block access/)
})

test('blocking access errors precede grace and synchronization notices', () => {
  for (const effective of ['OFFLINE_EXPIRED', 'INVALID', 'ACTIVATION_REQUIRED']) {
    const refs = render({
      license_status_effective: 'GRACE', status_effective: effective, is_valid: false,
      last_error: 'license_sync_unavailable',
    })
    const html = refs.licenseStatus.innerHTML
    assert.ok(html.indexOf('role="alert"') < html.indexOf('role="status"'))
    assert.match(html, /Grace period ends on/)
    assert.doesNotMatch(html, /Pro features are available|does not block access/)
  }
})

const overCapacity = {
  assigned: 108, total: 5, active_assigned: 5, suspended_assigned: 103,
  free: 0, overlicensed: true, overlicensed_by: 103,
}

test('overcapacity warns about paused Seats without blocking the active Seats', () => {
  const refs = render({ purchased_seats: 5 }, overCapacity)
  const html = refs.licenseStatus.innerHTML
  assert.match(html, /class="nccb-license-warning" role="status"><strong>License capacity exceeded: Seats are paused\./)
  assert.match(html, /Active used: 5 \| Paused: 103/)
  assert.match(html, /Assigned seats<\/dt><dd>108/)
  assert.match(html, /Users with active Seats can continue using all features\./)
  assert.match(html, /Reduce Seat assignments or increase the license capacity\./)
  assert.doesNotMatch(html, /role="alert"|Pro features are not available/)
  assert.equal(refs.proFunnel.hidden, true)
})

test('Community also warns when a downgrade leaves assignments beyond its free Seat', () => {
  const refs = render({ mode: 'community', has_credentials: false }, {
    ...overCapacity, total: 1, active_assigned: 1, suspended_assigned: 107, overlicensed_by: 107,
  })
  assert.match(refs.licenseStatus.innerHTML, /License capacity exceeded/)
  assert.match(refs.licenseStatus.innerHTML, /Active used: 1 \| Paused: 107/)
  assert.match(refs.licenseStatus.innerHTML, /1 free seat/)
  assert.match(refs.licenseStatus.innerHTML, /Users with active Seats can continue using all features/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"/)
})

test('capacity warnings disappear when refreshed Seats are no longer paused', () => {
  for (const mode of ['pro', 'community']) {
    const refs = render({ mode }, overCapacity)
    assert.match(refs.licenseStatus.innerHTML, /License capacity exceeded/)
    render({ mode }, {
      assigned: 1, total: 1, active_assigned: 1, suspended_assigned: 0,
      free: 0, overlicensed: false, overlicensed_by: 0,
    }, refs)
    assert.doesNotMatch(refs.licenseStatus.innerHTML, /nccb-license-warning|Paused:|License capacity exceeded/)
  }
})

test('capacity notices use server-provided paused counts, not purchased capacity arithmetic', () => {
  const missing = render({ purchased_seats: 0 })
  assert.doesNotMatch(missing.licenseStatus.innerHTML, /License capacity exceeded|Paused:/)
  const noPaused = render({ purchased_seats: 0 }, { ...overCapacity, suspended_assigned: 0 })
  assert.doesNotMatch(noPaused.licenseStatus.innerHTML, /License capacity exceeded|Paused:/)
  const paused = render({ purchased_seats: 200 }, { ...overCapacity, overlicensed: false })
  assert.match(paused.licenseStatus.innerHTML, /Active used: 5 \| Paused: 103/)
})

test('grace and paused Seats remain separate advisory notices', () => {
  const refs = render({ status_effective: 'GRACE', license_status_effective: 'GRACE' }, overCapacity)
  assert.match(refs.licenseStatus.innerHTML, /Your license has expired\. Please renew your license\./)
  assert.match(refs.licenseStatus.innerHTML, /Grace period ends on: 2001209600/)
  assert.match(refs.licenseStatus.innerHTML, /Paused: 103/)
  assert.match(refs.licenseStatus.innerHTML, /Users with active Seats can continue using all features/)
  assert.doesNotMatch(refs.licenseStatus.innerHTML, /role="alert"/)
})

test('license refusals precede capacity warnings and never promise usable active Seats', () => {
  const refusals = ['EXPIRED', 'INACTIVE', 'INVALID'].map((status) => ({
    license_status_effective: status, status_effective: status,
  }))
  refusals.push(
    { status_effective: 'OFFLINE_EXPIRED', last_error: 'license_sync_unavailable' },
    { status_effective: 'ACTIVATION_REQUIRED', activation: { required: true, enforced: true, verified: false, state: 'conflict' } },
  )
  for (const refusal of refusals) {
    const html = render({ ...refusal, is_valid: false }, overCapacity).licenseStatus.innerHTML
    assert.match(html, /License capacity exceeded/)
    assert.ok(html.indexOf('role="alert"') >= 0)
    assert.ok(html.indexOf('role="alert"') < html.indexOf('role="status"'))
    assert.match(html, /Basic features remain available/)
    assert.doesNotMatch(html, /Pro features are available|Users with active Seats can continue/)
  }
})

test('missing credentials do not hide known paused assignments or promise access', () => {
  const html = render({ has_credentials: false, is_valid: false }, {
    ...overCapacity, total: 0, active_assigned: 0, suspended_assigned: 108,
  }).licenseStatus.innerHTML
  assert.match(html, /Please provide license email and license key/)
  assert.match(html, /Active used: 0 \| Paused: 108/)
  assert.doesNotMatch(html, /Users with active Seats can continue|Pro features are available/)
})

test('Seat refresh forwards the complete server status and rerenders the General overview', async () => {
  const source = readFileSync('ncc_backend_4mc/js/ncc_backend_4mc-adminSettings.js', 'utf8')
  let payload = { items: Array.from({ length: 108 }, (_, i) => ({ user_id: `user-${i}` })), seat_status: overCapacity }
  const calls = []
  const context = vm.createContext({
    api: { loadSeats: async () => payload },
    refs: {}, state: { admin: { is_nextcloud_admin: true } },
    getGeneralStatusUiHelpers: () => ({}),
    generalStatusUi: { renderLicenseStatus: (_refs, _snapshot, helpers) => calls.push(helpers) },
    renderSeatUsage: () => {}, renderAssignedSeats: () => {},
    canUseAnyUserOverridePanel: () => false,
  })
  vm.runInContext('let assignedSeatCount = null; let assignedSeatStatus = null; const licenseSnapshot = { mode: "pro" };', context)
  for (const [name, next] of [
    ['renderLicenseStatus', 'renderProFunnel'],
    ['loadAssignedSeats', 'refreshAssignedSeatOverview'],
    ['refreshAssignedSeatOverview', 'refreshSeatsAndUsers'],
  ]) {
    const start = source.indexOf(`\t\tconst ${name} =`)
    const end = source.indexOf(`\t\tconst ${next} =`, start)
    assert.ok(start >= 0 && end > start, `Admin handler boundaries: ${name}`)
    vm.runInContext(source.slice(start, end), context)
  }
  await vm.runInContext('refreshAssignedSeatOverview()', context)
  assert.equal(calls.length, 1)
  assert.equal(calls[0].assignedSeats, 108)
  assert.equal(calls[0].seatStatus, overCapacity)
  payload = { items: payload.items, seat_status: { ...overCapacity, total: 150, active_assigned: 108, suspended_assigned: 0, overlicensed: false } }
  await vm.runInContext('refreshAssignedSeatOverview()', context)
  assert.equal(calls.length, 2)
  assert.equal(calls[1].seatStatus, payload.seat_status)
  assert.equal(calls[1].seatStatus.suspended_assigned, 0)
})
