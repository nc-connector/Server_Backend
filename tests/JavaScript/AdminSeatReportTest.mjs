import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import test from 'node:test'
import vm from 'node:vm'

function loadSeatReport() {
  const context = {
    window: {},
  }
  vm.runInNewContext(
    readFileSync(join(process.cwd(), 'ncc_backend_4mc', 'js', 'adminSeatReport.js'), 'utf8'),
    context,
    { filename: 'adminSeatReport.js' },
  )
  return context.window.NCCBackendSeatReport
}

test('seat CSV includes Share, Talk, and email signature policy values', async () => {
  const report = loadSeatReport()
  const csv = await report.buildSeatReportCsv(
    [{
      user_id: 'alice',
      display_name: 'Alice Example',
      assigned_at: 123,
      assigned_by: 'admin',
      has_group_overrides: false,
      has_overrides: false,
    }],
    {
      share_permission_upload: { type: 'bool' },
      talk_add_users: { type: 'bool' },
      email_signature_on_compose: { type: 'bool' },
      email_signature_template: { type: 'string' },
    },
    {
      loadStatus: async () => ({
        status: {
          seat_assigned: true,
          seat_state: 'active',
          mode: 'pro',
          is_valid: true,
          overlicensed: false,
        },
        policy: {
          share: { share_permission_upload: false },
          talk: { talk_add_users: true },
          email_signature: {
            email_signature_on_compose: true,
            email_signature_template: '<p>Private signature HTML</p>',
          },
        },
      }),
    },
    {
      formatDateTime: (value) => String(value ?? ''),
      isUserOverrideOnlySettingKey: () => false,
      isTemplateEditorSettingKey: (key) => key === 'email_signature_template',
      sortedSettingKeys: (schema) => Object.keys(schema),
      tr: (value) => value,
    },
  )

  const [header, row] = csv.split('\r\n')
  assert.match(header, /"policy_share_permission_upload"/)
  assert.match(header, /"policy_talk_add_users"/)
  assert.match(header, /"policy_email_signature_on_compose"/)
  assert.match(header, /"policy_email_signature_template"/)
  assert.match(row, /"false","true","true","Custom"$/)
  assert.doesNotMatch(row, /Private signature HTML/)
})
