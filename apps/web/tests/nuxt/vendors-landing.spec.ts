import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeTokenPayload } from '../fixtures/api'
import { pageOf } from '../fixtures/billing'
import { makeVendor } from '../fixtures/integrations'
import VendorsPage from '~/pages/dashboard/vendors/index.vue'

const integrations = vi.hoisted(() => ({
  listVendors: vi.fn(),
  createVendor: vi.fn(),
  fetchVendor: vi.fn(),
  updateVendor: vi.fn(),
  archiveVendor: vi.fn(),
}))
const billing = vi.hoisted(() => ({ fetchPlans: vi.fn() }))
const platform = vi.hoisted(() => ({ submitContact: vi.fn(), fetchAppConfig: vi.fn(), fetchServerTime: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/integrations', () => integrations)
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/platform', () => platform)
vi.mock('~/services/catalog', () => catalog)
mockNuxtImport('navigateTo', () => navigate)

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

/** vue-i18n renders a missing key as the key itself: none may reach the page. */
function expectNoRawKeys(text: string): void {
  expect(text).not.toMatch(/\b(landing|billing|integrations|vendors|common|errors|validation)\.[a-z_]+\.[a-z_]+/)
}

async function flush(times = 6): Promise<void> {
  for (let i = 0; i < times; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  for (const group of [integrations, billing, platform, catalog]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  catalog.fetchLookups.mockResolvedValue({ status: 'fresh', lookups: { regions: [{ id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' }], categories: [], close_reasons: [], presets: [] }, etag: null })
  platform.fetchServerTime.mockResolvedValue({ server_time: new Date().toISOString() })
  useAuthStore().setSession(makeTokenPayload())
})

describe('W25 vendors', () => {
  it('is forbidden without competitions.create', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['billing.view'] }))
    const wrapper = await mountSuspended(VendorsPage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.forbidden'))
    expect(integrations.listVendors).not.toHaveBeenCalled()
  })

  it('lists vendors with the linked badge and external references', async () => {
    integrations.listVendors.mockResolvedValue(pageOf([makeVendor({ linked_organization: { id: 'o', name: 'الريادة', logo_url: null, verified: true } })]))
    const wrapper = await mountSuspended(VendorsPage)
    await flush()
    expect(wrapper.text()).toContain('شركة الريادة')
    expect(wrapper.text()).toContain(t('vendors.linked'))
    expect(wrapper.text()).toContain(t('vendors.refs_count', { count: 1 }, 1))
    expect(wrapper.text()).toContain(t('vendors.sources.web'))
    expectNoRawKeys(wrapper.text())
  })

  it('searches with a debounce and shows the no-results state', async () => {
    integrations.listVendors.mockResolvedValue(pageOf([]))
    const wrapper = await mountSuspended(VendorsPage)
    await flush()
    expect(wrapper.text()).toContain(t('vendors.list.empty.title'))
    await wrapper.get('input[type="search"]').setValue('ريادة')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flush()
    expect(integrations.listVendors).toHaveBeenLastCalledWith(expect.objectContaining({ q: 'ريادة', page: 1 }))
    expect(wrapper.text()).toContain(t('vendors.no_results.title'))
  })

  it('offers to open the existing vendor on vendor_email_taken', async () => {
    integrations.listVendors.mockResolvedValue(pageOf([]))
    integrations.createVendor.mockRejectedValue(new ApiError({ status: 409, code: 'vendor_email_taken', message: '', errors: {}, details: { existing_id: '01j9ven0000000000000000009' } }))
    integrations.fetchVendor.mockResolvedValue(makeVendor({ id: '01j9ven0000000000000000009', name: 'الجهة الموجودة' }))
    const wrapper = await mountSuspended(VendorsPage, { attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('vendors.actions.add')))!.trigger('click')
    await flush()
    const form = wrapper.get('form#vendor-form')
    const inputs = form.findAll('input')
    await inputs[0]!.setValue('جهة جديدة')
    await form.get('input[type="email"]').setValue('sales@riyada.sa')
    await form.trigger('submit')
    await flush()
    expect(integrations.createVendor).toHaveBeenCalledWith(expect.objectContaining({ name: 'جهة جديدة', email: 'sales@riyada.sa', status: 'active', external_refs: [] }))
    expect(wrapper.text()).toContain(t('vendors.conflict.email'))
    await wrapper.findAll('button').find(button => button.text().includes(t('vendors.conflict.open_existing')))!.trigger('click')
    await flush()
    expect(integrations.fetchVendor).toHaveBeenCalledWith('01j9ven0000000000000000009')
    expect(wrapper.text()).toContain(t('vendors.drawer.edit_title'))
    wrapper.unmount()
  })

  it('checks the CR and VAT formats before sending', async () => {
    integrations.listVendors.mockResolvedValue(pageOf([]))
    const wrapper = await mountSuspended(VendorsPage, { attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('vendors.actions.add')))!.trigger('click')
    await flush()
    const form = wrapper.get('form#vendor-form')
    await form.findAll('input')[0]!.setValue('جهة')
    await form.get('input[type="email"]').setValue('a@b.sa')
    await form.get('input[maxlength="10"]').setValue('123')
    await form.trigger('submit')
    await flush()
    expect(integrations.createVendor).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('validation.cr_number'))
    wrapper.unmount()
  })
})
