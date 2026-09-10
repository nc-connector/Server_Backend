import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import test from 'node:test'
import vm from 'node:vm'

function createAdminApi(groups) {
  const offsets = []
  const context = {
    URLSearchParams,
    console,
    fetch: async (url) => {
      const requestUrl = new URL(url, 'https://nextcloud.example')
      const limit = Number.parseInt(requestUrl.searchParams.get('limit'), 10)
      const offset = Number.parseInt(requestUrl.searchParams.get('offset'), 10)
      offsets.push(offset)
      return {
        ok: true,
        status: 200,
        json: async () => ({
          items: groups.slice(offset, offset + limit),
          pagination: { limit, offset },
        }),
      }
    },
    OC: {
      generateUrl: (path) => path,
      requestToken: 'test-token',
    },
    window: {},
  }
  vm.runInNewContext(
    readFileSync(join(process.cwd(), 'ncc_backend_4mc', 'js', 'adminApi.js'), 'utf8'),
    context,
    { filename: 'adminApi.js' },
  )
  return {
    api: context.window.NCCBackendAdminApi,
    offsets,
  }
}

test('loadGroups returns groups beyond the first API page', async () => {
  const groups = Array.from({ length: 201 }, (_, index) => ({
    group_id: `group-${index + 1}`,
    display_name: `Group ${index + 1}`,
  }))
  const { api, offsets } = createAdminApi(groups)

  const response = await api.loadGroups()

  assert.equal(response.items.length, 201)
  assert.equal(response.items[200].group_id, 'group-201')
  assert.deepEqual(offsets, [0, 200])
  assert.equal(response.pagination.total, 201)
})
