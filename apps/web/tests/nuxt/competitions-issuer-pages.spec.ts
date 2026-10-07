import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeTokenPayload } from '../fixtures/api'
import { CATEGORY, COMPETITION_ID, makeIssuerCompetition, makeListItem, PRESETS, REGION } from '../fixtures/issuer'
import ListPage from '~/pages/dashboard/competitions/index.vue'
import NewPage from '~/pages/dashboard/competitions/new.vue'
import DetailParent from '~/pages/dashboard/competitions/[id].vue'

const competitions = vi.hoisted(() => ({
  listIssuerCompetitions: vi.fn(),
  createCompetition: vi.fn(),
  updateCompetition: vi.fn(),
  fetchCompetition: vi.fn(),
  listAttachments: vi.fn(),
  fetchHome: vi.fn(),
}))
const bidding = vi.hoisted(() => ({ fetchLive: vi.fn() }))
const billing = vi.hoisted(() => ({ fetchSponsorshipQuote: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/bidding', () => bidding)
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/catalog', () => catalog)
mockNuxtImport('navigateTo', () => navigate)
mockNuxtImport('useEcho', () => () => null)
mockNuxtImport('useRealtimeStatus', () => () => ({ available: ref(false), state: ref('idle') }))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

const PAGE = { type: 'page' as const, current_page: 1, per_page: 20, has_more: false, total: 1, last_page: 1 }

beforeEach(() => {
  for (const group of [competitions, bidding, billing, catalog]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  navigate.mockReset()
  catalog.fetchLookups.mockResolvedValue({
    status: 'fresh',
    lookups: { regions: [REGION], categories: [CATEGORY], close_reasons: [], presets: PRESETS },
    etag: null,
  })
  competitions.listAttachments.mockResolvedValue([])
  useAuthStore().setSession(makeTokenPayload({ entitlements: { can_issue: true, seats_used: 1, seats_total: 3 } }))
})

describe('W11 my competitions', () => {
  it('loads the tab from the query string and links drafts to the setup wizard', async () => {
    competitions.listIssuerCompetitions.mockResolvedValue({
      items: [makeListItem({ id: 'draft-1', status: 'draft', reference_no: null, title: 'مسودة المنافسة' }), makeListItem({ id: 'live-1', status: 'live', title: 'منافسة مفتوحة' })],
      pagination: PAGE,
    })
    const wrapper = await mountSuspended(ListPage, { route: '/ar/dashboard/competitions?status_group=draft&q=%D8%B7%D8%A7%D8%A8%D8%B9%D8%A9&sort=effective_close_at' })
    await flush()
    expect(competitions.listIssuerCompetitions).toHaveBeenCalledWith(expect.objectContaining({
      status_group: 'draft',
      q: 'طابعة',
      sort: 'effective_close_at',
      page: 1,
      per_page: 20,
    }))
    const links = wrapper.findAll('a').map(link => link.attributes('href'))
    expect(links).toContain('/ar/dashboard/competitions/draft-1/setup')
    expect(links).toContain('/ar/dashboard/competitions/live-1')
    expect(wrapper.text()).toContain('98,000.00')
    expect(wrapper.text()).toContain(t('competitions.status.draft'))
  })

  it('filters by the status sent from the home tiles instead of a tab', async () => {
    competitions.listIssuerCompetitions.mockResolvedValue({ items: [], pagination: { ...PAGE, total: 0 } })
    const wrapper = await mountSuspended(ListPage, { route: '/ar/dashboard/competitions?status=live' })
    await flush()
    expect(competitions.listIssuerCompetitions).toHaveBeenCalledWith(expect.objectContaining({ status: ['live'], status_group: undefined }))
    expect(wrapper.text()).toContain(t('competitions.list.filters.clear_status'))
    expect(wrapper.text()).toContain(t('competitions.list.empty.filtered.title'))
  })

  it('shows the drafts empty state with a create action, and disables creating without a plan', async () => {
    competitions.listIssuerCompetitions.mockResolvedValue({ items: [], pagination: { ...PAGE, total: 0 } })
    const wrapper = await mountSuspended(ListPage, { route: '/ar/dashboard/competitions?status_group=draft' })
    await flush()
    expect(wrapper.text()).toContain(t('competitions.list.empty.drafts.title'))
    expect(wrapper.findAll('a').some(link => link.attributes('href') === '/ar/dashboard/competitions/new')).toBe(true)

    useAuthStore().setSession(makeTokenPayload({ entitlements: { can_issue: false, seats_used: 1, seats_total: 1 } }))
    const noPlan = await mountSuspended(ListPage, { route: '/ar/dashboard/competitions?status_group=draft' })
    await flush()
    expect(noPlan.text()).toContain(t('nav.create_competition_requires_plan'))
    expect(noPlan.findAll('a').some(link => link.attributes('href') === '/ar/dashboard/competitions/new')).toBe(false)
  })
})

describe('W12 new competition', () => {
  it('creates the draft after step 1 (type and basics) and continues with the rules step', async () => {
    competitions.createCompetition.mockResolvedValue(makeIssuerCompetition())
    const wrapper = await mountSuspended(NewPage, { route: '/ar/dashboard/competitions/new' })
    await flush()
    // One step: the direction cards and the basics together; five steps in the stepper (RELEASE_SCOPE §2.1).
    expect(wrapper.text()).toContain(t('competitions.setup.titles.basics'))
    expect(wrapper.text()).toContain(t('competitions.setup.type.direction_legend'))
    expect(wrapper.text()).toContain(t('competitions.setup.basics.title_label'))
    expect(wrapper.text()).toContain(t('competitions.setup.steps.review'))
    expect(wrapper.text()).not.toContain(t('competitions.setup.steps.fees'))
    // Presets moved to the rules step.
    expect(wrapper.text()).not.toContain(PRESETS[0]!.name)

    // Nothing is sent while the basics are incomplete; the summary lists the problems with field links (FQ8).
    await wrapper.findAll('button').find(button => button.text().includes(t('competitions.setup.create_draft')))!.trigger('click')
    await flush()
    expect(competitions.createCompetition).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('competitions.setup.error_summary.title'))
    expect(wrapper.findAll('a[href="#wizard-title"]')).toHaveLength(1)
    expect(wrapper.text()).toContain(t('competitions.setup.basics.issues.title_required'))

    await wrapper.find('#wizard-title').setValue('توريد طابعات')
    const selects = wrapper.findAll('select')
    await selects[0]!.setValue('0')
    await selects[1]!.setValue('0')
    await wrapper.findAll('button').find(button => button.text().includes(t('competitions.setup.create_draft')))!.trigger('click')
    await flush()

    expect(competitions.createCompetition).toHaveBeenCalledWith(expect.objectContaining({
      title: 'توريد طابعات',
      category_id: CATEGORY.id,
      region_id: REGION.id,
      direction: 'tender',
      format: 'live',
      preset_code: 'standard_live_tender',
    }))
    expect(navigate).toHaveBeenCalledWith(`/ar/dashboard/competitions/${COMPETITION_ID}/setup/rules`, { replace: true })
  })

  it('explains issuer_plan_required from the server', async () => {
    competitions.createCompetition.mockRejectedValue(new ApiError({ status: 403, code: 'issuer_plan_required', message: 'plan', errors: {} }))
    const wrapper = await mountSuspended(NewPage, { route: '/ar/dashboard/competitions/new' })
    await flush()
    await wrapper.find('#wizard-title').setValue('توريد طابعات')
    const selects = wrapper.findAll('select')
    await selects[0]!.setValue('0')
    await selects[1]!.setValue('0')
    await wrapper.findAll('button').find(button => button.text().includes(t('competitions.setup.create_draft')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('errors.issuer_plan_required'))
    expect(navigate).not.toHaveBeenCalled()
  })

  it('renders the forbidden state without competitions.create', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['participation.submit_offers'] }))
    const wrapper = await mountSuspended(NewPage, { route: '/ar/dashboard/competitions/new' })
    await flush()
    expect(wrapper.text()).toContain(t('errors.forbidden'))
  })
})

describe('W13 detail parent (issuer)', () => {
  it('shows the issuer chrome and tabs by status', async () => {
    competitions.fetchCompetition.mockResolvedValue(makeIssuerCompetition({
      status: 'closed',
      reference_no: 'BAFO-T-2026-000123',
      permissions: { ...makeIssuerCompetition().permissions, can_edit: false, can_delete: false, can_publish: false, can_award: true },
    }))
    bidding.fetchLive.mockResolvedValue(null)
    const wrapper = await mountSuspended(DetailParent, { route: `/ar/dashboard/competitions/${COMPETITION_ID}` })
    await flush()
    const text = wrapper.text()
    expect(text).toContain('BAFO-T-2026-000123')
    expect(text).toContain(t('competitions.status.closed'))
    const tabs = wrapper.findAll('nav a').map(link => link.text())
    expect(tabs).toEqual(expect.arrayContaining([
      t('competitions.detail.tabs.overview'),
      t('competitions.detail.tabs.participants'),
      t('competitions.detail.tabs.live'),
      t('competitions.detail.tabs.offers'),
      t('competitions.detail.tabs.award'),
    ]))
    expect(text).toContain(t('competitions.issuer.actions.award'))
  })

  it('hides published-only tabs on a draft', async () => {
    competitions.fetchCompetition.mockResolvedValue(makeIssuerCompetition())
    const wrapper = await mountSuspended(DetailParent, { route: `/ar/dashboard/competitions/${COMPETITION_ID}` })
    await flush()
    const tabs = wrapper.findAll('nav a').map(link => link.text())
    expect(tabs).toContain(t('competitions.detail.tabs.participants'))
    expect(tabs).not.toContain(t('competitions.detail.tabs.live'))
    expect(tabs).not.toContain(t('competitions.detail.tabs.award'))
    expect(wrapper.text()).toContain(t('competitions.issuer.actions.continue_setup'))
  })

  it('never reveals existence on 404', async () => {
    competitions.fetchCompetition.mockRejectedValue(new ApiError({ status: 404, code: 'not_found', message: 'x', errors: {} }))
    const wrapper = await mountSuspended(DetailParent, { route: `/ar/dashboard/competitions/${COMPETITION_ID}` })
    await flush()
    expect(wrapper.text()).toContain(t('competitions.detail.not_found'))
  })
})
