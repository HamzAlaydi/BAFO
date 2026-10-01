import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { IntegrationsImportWizard } from '#components'
import { makeOrganization, makeTokenPayload } from '../fixtures/api'
import { makeApiClient, makeApiKey, makeDelivery, makeExportJob, makeImportJob, makeWebhookEndpoint } from '../fixtures/integrations'
import { pageOf } from '../fixtures/billing'
import type { Me } from '~/types/api/identity'
import OverviewPage from '~/pages/dashboard/integrations/index.vue'
import ClientsPage from '~/pages/dashboard/integrations/api-clients/index.vue'
import ClientPage from '~/pages/dashboard/integrations/api-clients/[id].vue'
import WebhooksPage from '~/pages/dashboard/integrations/webhooks/index.vue'
import WebhookPage from '~/pages/dashboard/integrations/webhooks/[id].vue'
import ExportsPage from '~/pages/dashboard/integrations/exports.vue'

const integrations = vi.hoisted(() => ({
  listApiClients: vi.fn(),
  createApiClient: vi.fn(),
  fetchApiClient: vi.fn(),
  updateApiClient: vi.fn(),
  revokeApiClient: vi.fn(),
  rotateApiClientSecret: vi.fn(),
  createApiKey: vi.fn(),
  revokeApiKey: vi.fn(),
  listWebhookEventTypes: vi.fn(),
  listWebhookEndpoints: vi.fn(),
  createWebhookEndpoint: vi.fn(),
  fetchWebhookEndpoint: vi.fn(),
  updateWebhookEndpoint: vi.fn(),
  deleteWebhookEndpoint: vi.fn(),
  testWebhookEndpoint: vi.fn(),
  rotateWebhookSecret: vi.fn(),
  listWebhookDeliveries: vi.fn(),
  redeliverWebhook: vi.fn(),
  createImportJob: vi.fn(),
  fetchImportJob: vi.fn(),
  createExportJob: vi.fn(),
  fetchExportJob: vi.fn(),
  importTemplatePath: (format: string) => `/integrations/imports/templates/vendors?format=${format}`,
}))
const competitions = vi.hoisted(() => ({ listIssuerCompetitions: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
const download = vi.hoisted(() => vi.fn())
vi.mock('~/services/integrations', () => integrations)
vi.mock('~/services/competitions', () => competitions)
mockNuxtImport('navigateTo', () => navigate)
mockNuxtImport('useFileDownload', () => () => ({ download, downloading: ref(false) }))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

/** vue-i18n renders a missing key as the key itself: none may reach the page. */
function expectNoRawKeys(text: string): void {
  expect(text).not.toMatch(/\b(landing|billing|integrations|vendors|common|errors|validation)\.[a-z_]+\.[a-z_]+/)
}

async function flush(times = 6): Promise<void> {
  for (let i = 0; i < times; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

const wait = (ms: number) => new Promise(resolve => setTimeout(resolve, ms))

function signIn(apiEnabled = true, overrides: Partial<Me> = {}): void {
  useAuthStore().setSession(makeTokenPayload({
    organization: makeOrganization({ features: { api_enabled: apiEnabled, auction_enabled: false, sponsorship_enabled: false } }),
    ...overrides,
  }))
}

const EVENT_TYPES = [
  { type: 'award.issued', description: 'صدرت ترسية' },
  { type: 'competition.closed', description: 'أُغلقت منافسة' },
]

beforeEach(() => {
  for (const group of [integrations, competitions]) {
    for (const fn of Object.values(group)) if (vi.isMockFunction(fn)) fn.mockReset()
  }
  navigate.mockReset()
  download.mockReset()
  sessionStorage.clear()
  signIn()
})

describe('W36 integrations overview', () => {
  it('shows the API callout instead of the API client and webhook links when the API is off', async () => {
    signIn(false)
    const wrapper = await mountSuspended(OverviewPage)
    const hrefs = wrapper.findAll('a').map(link => link.attributes('href') ?? '')
    expect(wrapper.text()).toContain(t('errors.api_access_disabled'))
    expect(hrefs.some(href => href.endsWith('/dashboard/integrations/api-clients'))).toBe(false)
    expect(hrefs.some(href => href.endsWith('/dashboard/integrations/import'))).toBe(true)
    expect(hrefs.some(href => href.endsWith('/dashboard/integrations/exports'))).toBe(true)
    expect(hrefs).toContain('http://api.bafo.test/docs/api')
    expectNoRawKeys(wrapper.text())
  })

  it('is forbidden without integrations.manage', async () => {
    signIn(true, { permissions: ['billing.view'] })
    const wrapper = await mountSuspended(OverviewPage)
    expect(wrapper.text()).toContain(t('errors.forbidden'))
  })
})

describe('W37 API clients', () => {
  it('never calls the API when the organization has no API access', async () => {
    signIn(false)
    const wrapper = await mountSuspended(ClientsPage)
    await flush()
    expect(integrations.listApiClients).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('integrations.disabled.title'))
  })

  it('creates a client and shows the secret once, closing only after the acknowledgement', async () => {
    integrations.listApiClients.mockResolvedValue([])
    integrations.createApiClient.mockResolvedValue({ ...makeApiClient({ name: 'ERP' }), client_secret: 'plain-secret-shown-once' })
    const wrapper = await mountSuspended(ClientsPage, { attachTo: document.body })
    await flush()
    expect(wrapper.text()).toContain(t('integrations.clients.empty.title'))

    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.clients.create')))!.trigger('click')
    await flush()
    const form = wrapper.findAll('form').at(-1)!
    await form.trigger('submit')
    await flush()
    expect(integrations.createApiClient).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('validation.required'))

    await form.find('input').setValue('ERP')
    await form.trigger('submit')
    await flush()
    const body = integrations.createApiClient.mock.calls[0]![0]
    expect(body.name).toBe('ERP')
    expect(body.scopes).toContain('competitions:read')
    expect(body.scopes).not.toContain('competitions:publish')

    expect(wrapper.text()).toContain('plain-secret-shown-once')
    expect(wrapper.text()).toContain('01j9cli0000000000000000001')
    const done = wrapper.findAll('button').find(button => button.text() === t('integrations.secret.done'))!
    expect(done.attributes('disabled')).toBeDefined()
    await wrapper.findAll('input[type="checkbox"]').at(-1)!.setValue(true)
    await done.trigger('click')
    await flush()
    expect(wrapper.text()).not.toContain('plain-secret-shown-once')
    expect(wrapper.text()).toContain('ERP')
    wrapper.unmount()
  })
})

describe('W38 API client detail', () => {
  it('creates a key with the chosen validity and shows it once', async () => {
    integrations.fetchApiClient.mockResolvedValue(makeApiClient())
    integrations.createApiKey.mockResolvedValue({ ...makeApiKey({ id: 'k2', prefix: 'zz99yy88' }), key: 'bafo_test_zz99yy88_secretsecretsecretsecretsecret12' })
    const wrapper = await mountSuspended(ClientPage, { route: '/ar/dashboard/integrations/api-clients/01j9cli0000000000000000001', attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.keys.create')))!.trigger('click')
    await flush()
    const days = wrapper.find('input[maxlength="3"]')
    await days.setValue('800')
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.keys.create_dialog.submit')))!.trigger('click')
    await flush()
    expect(integrations.createApiKey).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('integrations.keys.create_dialog.days_error'))
    await days.setValue('400')
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.keys.create_dialog.submit')))!.trigger('click')
    await flush()
    expect(integrations.createApiKey).toHaveBeenCalledWith('01j9cli0000000000000000001', 400)
    expect(wrapper.text()).toContain('bafo_test_zz99yy88_secretsecretsecretsecretsecret12')
    expect(wrapper.text()).toContain('zz99yy88')
    expectNoRawKeys(wrapper.text())
    wrapper.unmount()
  })

  it('revokes the client after a destructive confirmation and returns to the list', async () => {
    integrations.fetchApiClient.mockResolvedValue(makeApiClient())
    integrations.revokeApiClient.mockResolvedValue(undefined)
    const wrapper = await mountSuspended(ClientPage, { route: '/ar/dashboard/integrations/api-clients/01j9cli0000000000000000001', attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.clients.revoke.action')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('integrations.clients.revoke.description', { name: 'SAP CPI – PROD' }))
    const confirm = wrapper.findAll('button').filter(button => button.text().includes(t('integrations.clients.revoke.confirm'))).at(-1)!
    await confirm.trigger('click')
    await flush()
    expect(integrations.revokeApiClient).toHaveBeenCalledWith('01j9cli0000000000000000001')
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/\/dashboard\/integrations\/api-clients$/))
    wrapper.unmount()
  })

  it('shows not found for an unknown client', async () => {
    integrations.fetchApiClient.mockRejectedValue(new ApiError({ status: 404, code: 'not_found', message: '', errors: {} }))
    const wrapper = await mountSuspended(ClientPage, { route: '/ar/dashboard/integrations/api-clients/nope' })
    await flush()
    expect(wrapper.text()).toContain(t('errors.not_found'))
  })
})

describe('W39 webhook endpoints', () => {
  it('binds webhook_url_invalid to the URL field, then shows the signing secret once', async () => {
    integrations.listWebhookEndpoints.mockResolvedValue([])
    integrations.listWebhookEventTypes.mockResolvedValue(EVENT_TYPES)
    integrations.createWebhookEndpoint.mockRejectedValueOnce(new ApiError({ status: 422, code: 'webhook_url_invalid', message: '', errors: {} }))
    integrations.createWebhookEndpoint.mockResolvedValueOnce({ ...makeWebhookEndpoint({ event_types: ['*'] }), secret: 'whsec_c2VjcmV0LXNob3duLW9uY2U=' })
    const wrapper = await mountSuspended(WebhooksPage, { attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.webhooks.create')))!.trigger('click')
    await flush()
    const form = wrapper.findAll('form').at(-1)!
    await form.find('input[type="url"]').setValue('https://10.0.0.5/hook')
    await form.trigger('submit')
    await flush()
    expect(integrations.createWebhookEndpoint).toHaveBeenCalledWith({ url: 'https://10.0.0.5/hook', event_types: ['*'], description: null })
    expect(wrapper.text()).toContain(t('errors.webhook_url_invalid'))

    await form.find('input[type="url"]').setValue('https://erp.example.sa/bafo/webhooks')
    await form.trigger('submit')
    await flush()
    expect(wrapper.text()).toContain('whsec_c2VjcmV0LXNob3duLW9uY2U=')
    expect(wrapper.text()).toContain(t('integrations.webhooks.all_events'))
    wrapper.unmount()
  })
})

describe('W40 webhook endpoint detail', () => {
  beforeEach(() => {
    integrations.fetchWebhookEndpoint.mockResolvedValue(makeWebhookEndpoint())
    integrations.listWebhookEventTypes.mockResolvedValue(EVENT_TYPES)
  })

  it('sends a test event and refreshes the delivery log afterwards', async () => {
    integrations.listWebhookDeliveries.mockResolvedValue(pageOf([]))
    integrations.testWebhookEndpoint.mockResolvedValue({ event_id: 'e1' })
    const wrapper = await mountSuspended(WebhookPage, { route: '/ar/dashboard/integrations/webhooks/01j9whe0000000000000000001', props: { testRefreshMs: 5 } })
    await flush()
    expect(wrapper.text()).toContain(t('integrations.deliveries.empty.title'))
    integrations.listWebhookDeliveries.mockResolvedValue(pageOf([makeDelivery({ event: { id: 'e1', type: 'webhook.test', occurred_at: '2026-10-01T09:00:00.000Z' }, status: 'succeeded', last_http_status: 200 })]))
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.webhooks.detail.test')))!.trigger('click')
    await flush()
    expect(integrations.testWebhookEndpoint).toHaveBeenCalledWith('01j9whe0000000000000000001')
    await wait(20)
    await flush()
    expect(wrapper.text()).toContain('webhook.test')
    expect(wrapper.text()).toContain(t('integrations.deliveries.statuses.succeeded'))
  })

  it('redelivers from the row drawer and updates the row from the server answer', async () => {
    integrations.listWebhookDeliveries.mockResolvedValue(pageOf([makeDelivery()]))
    integrations.redeliverWebhook.mockResolvedValue(makeDelivery({ status: 'pending', next_attempt_at: '2026-10-02T09:00:05.000Z' }))
    const wrapper = await mountSuspended(WebhookPage, { route: '/ar/dashboard/integrations/webhooks/01j9whe0000000000000000001', attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('integrations.deliveries.details'))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain('HTTP 500')
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.deliveries.redeliver')))!.trigger('click')
    await flush()
    expect(integrations.redeliverWebhook).toHaveBeenCalledWith('01j9del0000000000000000001')
    expect(wrapper.get('table').text()).toContain(t('integrations.deliveries.statuses.pending'))
    wrapper.unmount()
  })

  it('disables the endpoint only when the server answers', async () => {
    integrations.listWebhookDeliveries.mockResolvedValue(pageOf([]))
    let resolveUpdate!: (value: unknown) => void
    integrations.updateWebhookEndpoint.mockReturnValue(new Promise((resolve) => {
      resolveUpdate = resolve
    }))
    const wrapper = await mountSuspended(WebhookPage, { route: '/ar/dashboard/integrations/webhooks/01j9whe0000000000000000001' })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.webhooks.detail.disable')))!.trigger('click')
    await flush()
    expect(integrations.updateWebhookEndpoint).toHaveBeenCalledWith('01j9whe0000000000000000001', { status: 'disabled' })
    expect(wrapper.text()).toContain(t('integrations.webhooks.statuses.active'))
    resolveUpdate(makeWebhookEndpoint({ status: 'disabled', disabled_reason: 'manual' }))
    await flush()
    expect(wrapper.text()).toContain(t('integrations.webhooks.statuses.disabled'))
    expect(wrapper.text()).toContain(t('integrations.webhooks.disabled_reasons.manual'))
  })
})

describe('W41 import wizard', () => {
  it('validates, shows row errors, commits the same file and reports the counts', async () => {
    const file = new File(['name,email\nالريادة,sales@riyada.sa\n'], 'vendors.csv', { type: 'text/csv' })
    integrations.createImportJob.mockImplementation((_file: File, mode: 'validate' | 'commit') => Promise.resolve(makeImportJob({ id: mode, mode })))
    integrations.fetchImportJob.mockImplementation(() => Promise.resolve(makeImportJob({
      id: 'validate',
      mode: 'validate',
      status: 'completed',
      total_rows: 3,
      valid_rows: 2,
      error_rows: 1,
      errors_preview: [{ row: 3, column: 'region_code', code: 'unknown_region', message: 'Unknown region XYZ' }],
      errors_file: { id: 'f1', name: 'errors.csv', mime_type: 'text/csv', extension: 'csv', size_bytes: 10, download_path: '/api/app/v1/files/f1/download', created_at: '2026-10-01T09:00:00.000Z' },
    })))
    const wrapper = await mountSuspended(IntegrationsImportWizard, { props: { pollIntervalMs: 5 } })
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await flush()
    expect(wrapper.emitted('dirty')?.at(-1)).toEqual([true])

    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.import.upload.validate')))!.trigger('click')
    await flush()
    expect(integrations.createImportJob).toHaveBeenCalledWith(file, 'validate')
    await wait(20)
    await flush()
    expect(wrapper.text()).toContain(t('integrations.import.row_codes.unknown_region'))
    expect(wrapper.text()).toContain(t('integrations.import.errors.row_number', { row: 3 }))
    expectNoRawKeys(wrapper.text())

    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.import.results.download_errors')))!.trigger('click')
    await flush()
    expect(download).toHaveBeenCalledWith('/api/app/v1/files/f1/download', 'errors.csv')

    integrations.fetchImportJob.mockImplementation(() => Promise.resolve(makeImportJob({ id: 'commit', mode: 'commit', status: 'completed', total_rows: 3, valid_rows: 2, created_rows: 2, updated_rows: 0, error_rows: 1 })))
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.import.commit', { count: 2 }, 2)))!.trigger('click')
    await flush()
    expect(integrations.createImportJob).toHaveBeenLastCalledWith(file, 'commit')
    await wait(20)
    await flush()
    expect(wrapper.text()).toContain(t('integrations.import.done.summary', { created: 2, updated: 0 }))
    expect(wrapper.emitted('dirty')?.at(-1)).toEqual([false])
  })
})

describe('W42 exports', () => {
  it('starts an export, polls it and downloads the file; the job stays listed for the session', async () => {
    integrations.createExportJob.mockResolvedValue(makeExportJob())
    integrations.fetchExportJob.mockResolvedValue(makeExportJob({
      status: 'completed',
      row_count: 12,
      file: { id: 'f2', name: 'vendors.xlsx', mime_type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', extension: 'xlsx', size_bytes: 2048, download_path: '/api/app/v1/files/f2/download', created_at: '2026-10-01T09:00:00.000Z' },
    }))
    const wrapper = await mountSuspended(ExportsPage, { route: '/ar/dashboard/integrations/exports?type=vendors', props: { pollIntervalMs: 5 } })
    await flush()
    expect(wrapper.text()).toContain(t('integrations.exports.jobs.empty.title'))
    await wrapper.findAll('form').at(0)!.trigger('submit')
    await flush()
    expect(integrations.createExportJob).toHaveBeenCalledWith({ type: 'vendors', format: 'xlsx' })
    expect(wrapper.text()).toContain(t('integrations.exports.types.vendors.label'))
    await wait(20)
    await flush()
    expect(wrapper.text()).toContain(t('integrations.jobs.statuses.completed'))
    await wrapper.findAll('button').find(button => button.text().includes(t('integrations.exports.jobs.download')))!.trigger('click')
    await flush()
    expect(download).toHaveBeenCalledWith('/api/app/v1/files/f2/download', 'vendors.xlsx')
    expect(JSON.parse(sessionStorage.getItem('bafo.export_jobs') ?? '[]')).toHaveLength(1)
  })

  it('requires a competition for results exports', async () => {
    competitions.listIssuerCompetitions.mockResolvedValue(pageOf([]))
    const wrapper = await mountSuspended(ExportsPage, { props: { pollIntervalMs: 5 } })
    await flush()
    await wrapper.findAll('form').at(0)!.trigger('submit')
    await flush()
    expect(integrations.createExportJob).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('integrations.exports.form.competition_required'))
  })
})
