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

function render(overrides = {}) {
  const context = { window: {}, HTMLElement: Element }
  vm.runInNewContext(readFileSync('ncc_backend_4mc/js/adminGeneralStatusUi.js', 'utf8'), context)
  const refs = { licenseStatus: new Element(), licenseHint: new Element(), proFunnel: new Element() }
  const snapshot = {
    mode: 'pro', has_credentials: true, is_valid: true,
    status_effective: 'ACTIVE', license_status_effective: 'ACTIVE',
    purchased_seats: 20, expires_at: 2000000000, grace_until: 2001209600,
    last_sync_at: 1900000000, activation: null, ...overrides,
  }
  const helpers = {
    tr: (key) => key,
    escapeHtml: (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;'),
    formatDate: String, formatDateTime: String, assignedSeats: 6,
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
  assert.match(community.licenseStatus.textContent, /1 free seat/)
  assert.match(community.proFunnel.innerHTML, /https:\/\/nc-connector.de\/testlizenz\//)
  assert.doesNotMatch(community.proFunnel.innerHTML, /mailto:/)
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
