import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { Ref } from 'vue'
import { AppSidebarNav } from '#components'
import { makeAppConfig, makeFlags, makeTokenPayload } from '../fixtures/api'
import { CATEGORY, COMPETITION_ID, makeIssuerCompetition, PRESETS, REGION } from '../fixtures/issuer'
import type { Preset } from '~/types/api/catalog'
import type { IssuerCompetition } from '~/types/api/competitions'
import RulesAside from '~/components/competitions/wizard/RulesAside.vue'
import StepRules from '~/components/competitions/wizard/StepRules.vue'
import StepSchedule from '~/components/competitions/wizard/StepSchedule.vue'
import StepPage from '~/pages/dashboard/competitions/[id]/setup/[step].vue'

/**
 * RELEASE_SCOPE.md on the web: the flags come from `GET /app-config`, nothing branches on the scope,
 * hidden surfaces come back when the flags are on, and the 5-step wizard is the same in both scopes.
 */
const competitions = vi.hoisted(() => ({ createCompetition: vi.fn(), updateCompetition: vi.fn(), listInvitations: vi.fn(), listAttachments: vi.fn(), fetchHome: vi.fn() }))
const billing = vi.hoisted(() => ({ fetchSponsorshipQuote: vi.fn(), fetchSponsorship: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/catalog', () => catalog)
mockNuxtImport('navigateTo', () => navigate)

const ctx = vi.hoisted(() => ({ competition: null as Ref<IssuerCompetition | null> | null, refetch: vi.fn(), resync: vi.fn() }))
mockNuxtImport('useCompetitionContext', () => () => ({
  id: computed(() => COMPETITION_ID),
  competition: ctx.competition!,
  viewerRole: computed(() => 'issuer'),
  loading: ref(false),
  error: ref(null),
  live: computed(() => null),
  connection: computed(() => 'connected'),
  canSubmit: computed(() => true),
  refetch: ctx.refetch,
  resync: ctx.resync,
  applyLive: () => true,
  on: () => () => {},
}))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

/** Six tiered presets of RELEASE_SCOPE.md §2.2 (two directions × three tiers) plus the untiered references. */
const TIERED: Preset[] = (['tender', 'auction'] as const).flatMap(direction => ([
  { id: `${direction}-simple`, code: `${direction}_live_simple`, name: 'بسيطة', description: 'يحسّن كل متنافس عرضه بحرّية.', direction, format: 'live' as const, tier: 'simple' as const, rules: { must_beat: 'own' as const, rank_visibility: 'leading_flag' as const, show_prices: false, auto_extend: { enabled: false }, min_participants: 1 } },
  { id: `${direction}-standard`, code: `${direction}_live_standard`, name: 'قياسية', description: 'حد أدنى للتحسين 0.5%.', direction, format: 'live' as const, tier: 'standard' as const, rules: { must_beat: 'own' as const, min_step_bps: 50, rank_visibility: 'leading_flag' as const, show_prices: false, auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 }, min_participants: 2 } },
  { id: `${direction}-protected`, code: `${direction}_live_protected`, name: 'حماية قصوى', description: 'لا يرى المتنافسون ترتيبهم.', direction, format: 'live' as const, tier: 'protected' as const, rules: { must_beat: 'own' as const, min_step_bps: 100, rank_visibility: 'none' as const, show_prices: false, auto_extend: { enabled: true, window_seconds: 300, by_seconds: 300, max_extensions: 20 }, min_participants: 2 } },
]))

function setScope(scope: 'core' | 'full'): void {
  useAppConfigStore().config = makeAppConfig({}, scope)
}

beforeEach(() => {
  for (const group of [competitions, billing, catalog]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  navigate.mockReset()
  ctx.refetch.mockReset()
  catalog.fetchLookups.mockResolvedValue({ status: 'fresh', lookups: { regions: [REGION], categories: [CATEGORY], close_reasons: [], presets: [...TIERED, ...PRESETS] }, etag: null })
  competitions.listInvitations.mockResolvedValue({ invitations: [] })
  competitions.listAttachments.mockResolvedValue([])
  useAuthStore().setSession(makeTokenPayload({ entitlements: { can_issue: true, seats_used: 1, seats_total: 3 } }))
  useAppConfigStore().config = null
  useToast().clear()
})

describe('app config flags (RELEASE_SCOPE §1.4)', () => {
  it('reads the core defaults before the config has loaded and the server map afterwards', () => {
    const store = useAppConfigStore()
    expect(store.flags.team_management).toBe(false)
    expect(store.flags.qa_comments).toBe(true)
    expect(store.releaseScope).toBe('core')
    expect(store.sponsorshipEnabled).toBe(false)

    setScope('full')
    expect(store.flags.team_management).toBe(true)
    expect(store.featureEnabled('dark_mode')).toBe(true)
    expect(store.releaseScope).toBe('full')
    expect(store.sponsorshipEnabled).toBe(true)

    store.config = makeAppConfig({ features: { release_scope: 'full', sponsorship: false, flags: makeFlags('full', { sponsorship: false }) } })
    expect(store.sponsorshipEnabled).toBe(false)
  })

  it('exposes one lookup path through useFeatures()', () => {
    const features = useFeatures()
    expect(features.ready.value).toBe(false)
    expect(features.enabled('vendor_directory')).toBe(false)
    expect(features.anyEnabled(['integrations_api', 'csv_import_export'])).toBe(false)
    setScope('full')
    expect(features.ready.value).toBe(true)
    expect(features.enabled('vendor_directory')).toBe(true)
    expect(features.anyEnabled(['integrations_api', 'csv_import_export'])).toBe(true)
    expect(features.flags.value.login_as).toBe(false)
  })
})

describe('navigation and theme gating (RELEASE_SCOPE §5)', () => {
  it('hides Vendors, Team and Integrations in core and shows them in full', async () => {
    setScope('core')
    const core = await mountSuspended(AppSidebarNav, { route: '/ar/dashboard' })
    const coreText = core.text()
    expect(coreText).toContain(t('nav.my_competitions'))
    expect(coreText).toContain(t('nav.billing'))
    expect(coreText).not.toContain(t('nav.vendors'))
    expect(coreText).not.toContain(t('nav.team'))
    expect(coreText).not.toContain(t('nav.integrations'))

    setScope('full')
    await nextTick()
    const fullText = core.text()
    expect(fullText).toContain(t('nav.vendors'))
    expect(fullText).toContain(t('nav.team'))
    expect(fullText).toContain(t('nav.integrations'))
  })

  it('forces the light theme while dark_mode is off and keeps the preference for later', () => {
    setScope('core')
    const theme = useTheme()
    theme.setPreference('dark')
    expect(theme.dataTheme.value).toBe('light')
    expect(theme.preference.value).toBe('dark')
    setScope('full')
    expect(theme.dataTheme.value).toBe('dark')
    theme.setPreference('system')
    expect(theme.dataTheme.value).toBeUndefined()
  })

  it('lets a hidden page render the 404 state in place, and sends invoice links to W30 (§5, §10)', async () => {
    const middleware = (await import('~/middleware/feature')).default as unknown as (to: unknown, from: unknown) => Promise<unknown>
    const route = (path: string, meta: Record<string, unknown>) => ({ path, fullPath: path, meta })

    setScope('core')
    expect(await middleware(route('/ar/dashboard/team', { feature: 'team_management' }), route('/ar/dashboard', {}))).toBeUndefined()
    expect(navigate).not.toHaveBeenCalled()
    expect(useToast().toasts.value).toEqual([])

    await middleware(route('/ar/dashboard/billing/invoices', { feature: 'billing_invoices', featureFallback: '/dashboard/billing' }), route('/ar/dashboard', {}))
    expect(navigate).toHaveBeenCalledWith('/ar/dashboard/billing', { replace: true })
    expect(useToast().toasts.value.map(toast => toast.message)).toEqual([t('errors.feature_disabled')])

    navigate.mockReset()
    setScope('full')
    expect(await middleware(route('/ar/dashboard/billing/invoices', { feature: 'billing_invoices', featureFallback: '/dashboard/billing' }), route('/ar/dashboard', {}))).toBeUndefined()
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('rules step per scope (RELEASE_SCOPE §2.2, §2.4)', () => {
  async function mountRules(scope: 'core' | 'full', competition = makeIssuerCompetition()) {
    setScope(scope)
    await useLookupsStore().load()
    const editor = useCompetitionEditorStore()
    editor.load({ ...competition, preset_code: 'tender_live_standard', rules: { ...competition.rules, reserve_price_minor: null, final_window_minutes: null } })
    const wrapper = await mountSuspended(StepRules)
    await flush()
    return { wrapper, editor }
  }

  it('writes the tier rules in the aside with Arabic counted nouns («3 دقائق», «10 مرات», «20 مرة»)', async () => {
    const { wrapper, editor } = await mountRules('core')
    wrapper.unmount()
    editor.form.rules.auto_extend = { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 }
    const aside = await mountSuspended(RulesAside, { props: { serverLines: null } })
    expect(aside.text()).toContain('في آخر 3 دقائق يمدّد الإغلاق 3 دقائق، حتى 10 مرات.')
    editor.form.rules.auto_extend = { enabled: true, window_seconds: 300, by_seconds: 120, max_extensions: 20 }
    await nextTick()
    expect(aside.text()).toContain('في آخر 5 دقائق يمدّد الإغلاق دقيقتين، حتى 20 مرة.')
    aside.unmount()
  })

  it('shows the three tier cards with the server sentences, and hides the advanced-only controls in core', async () => {
    const { wrapper } = await mountRules('core')
    const text = wrapper.text()
    expect(text).toContain(t('rules.tiers.names.simple'))
    expect(text).toContain(t('rules.tiers.names.standard'))
    expect(text).toContain(t('rules.tiers.names.protected'))
    expect(text).toContain('حد أدنى للتحسين 0.5%.')
    expect(text).toContain(t('rules.start_price.label.tender'))
    expect(text).not.toContain(t('rules.reserve_price.label.tender'))
    expect(text).not.toContain(t('rules.granularity.label'))
    expect(text).not.toContain(t('rules.tiers.other_presets'))
    // The disclosure starts collapsed; its gated blocks are absent even when opened.
    const toggle = wrapper.get('button[aria-expanded]')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    await flush()
    const opened = wrapper.text()
    expect(opened).toContain(t('rules.auto_extend.label'))
    expect(opened).not.toContain(t('rules.final_window.label'))
    expect(opened).not.toContain(t('rules.bafo.label'))
    expect(opened).not.toContain(t('rules.result.label'))
    expect(opened).not.toContain(t('rules.min_participants.label'))
  })

  it('titles each tier card with the server preset name (admin-editable) in simple → standard → protected order', async () => {
    const renamed = TIERED.map(preset => (preset.code === 'tender_live_simple' ? { ...preset, name: 'سريعة وبسيطة' } : preset))
    catalog.fetchLookups.mockResolvedValue({ status: 'fresh', lookups: { regions: [REGION], categories: [CATEGORY], close_reasons: [], presets: [...renamed].reverse() }, etag: null })
    const { wrapper } = await mountRules('core')
    const values = wrapper.findAll('input[type="radio"]').map(input => (input.element as HTMLInputElement).value)
    expect(values.slice(0, 3)).toEqual(['tender_live_simple', 'tender_live_standard', 'tender_live_protected'])
    expect(wrapper.text()).toContain('سريعة وبسيطة')
    expect((wrapper.find('input[value="tender_live_standard"]').element as HTMLInputElement).checked).toBe(true)
  })

  it('brings every gated control back in full, including the untiered templates', async () => {
    const { wrapper } = await mountRules('full')
    const text = wrapper.text()
    expect(text).toContain(t('rules.reserve_price.label.tender'))
    expect(text).toContain(t('rules.granularity.label'))
    expect(text).toContain(t('rules.tiers.other_presets'))
    await wrapper.get('button[aria-expanded]').trigger('click')
    await flush()
    const opened = wrapper.text()
    expect(opened).toContain(t('rules.final_window.label'))
    expect(opened).toContain(t('rules.bafo.label'))
    expect(opened).toContain(t('rules.result.label'))
    expect(opened).toContain(t('rules.min_participants.label'))
  })

  it('keeps a window an existing record already has, even in core (existing records always render)', async () => {
    const competition = makeIssuerCompetition()
    setScope('core')
    await useLookupsStore().load()
    useCompetitionEditorStore().load({ ...competition, rules: { ...competition.rules, final_window_minutes: 60 } })
    const wrapper = await mountSuspended(StepRules)
    await wrapper.get('button[aria-expanded]').trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('rules.final_window.label'))
  })

  it('selecting a tier replaces the rules and keeps the prices; editing shows the reset action', async () => {
    const { wrapper, editor } = await mountRules('core')
    editor.patchRules({ start_price_minor: 1_000_000 })
    const protectedRadio = wrapper.findAll('input[type="radio"]').find(input => (input.element as HTMLInputElement).value === 'tender_live_protected')!
    await protectedRadio.setValue(true)
    await flush()
    expect(editor.form.preset_code).toBe('tender_live_protected')
    expect(editor.form.rules.rank_visibility).toBe('none')
    expect(editor.form.rules.start_price_minor).toBe(1_000_000)
    expect(editor.customised).toBe(false)
    editor.patchRules({ rank_visibility: 'full' })
    await flush()
    expect(wrapper.text()).toContain(t('rules.tiers.reset_to', { tier: t('rules.tiers.names.protected') }))
    await wrapper.findAll('button').find(button => button.text().includes(t('rules.tiers.reset_to', { tier: t('rules.tiers.names.protected') })))!.trigger('click')
    await flush()
    expect(editor.form.rules.rank_visibility).toBe('none')
  })
})

describe('schedule step (RELEASE_SCOPE §2.3)', () => {
  it('offers the quick picks, explains the joining deadline and rejects a past close before saving', async () => {
    setScope('core')
    const editor = useCompetitionEditorStore()
    editor.load(makeIssuerCompetition())
    const clock = useServerTime()
    const wrapper = await mountSuspended(StepSchedule)
    await flush()
    const text = wrapper.text()
    for (const pick of ['hour', 'hours_3', 'day', 'days_3', 'week', 'custom']) expect(text).toContain(t(`competitions.setup.schedule.quick.${pick}`))
    expect(text).toContain(t('competitions.setup.schedule.join_deadline_title'))
    expect(text).toContain(t('competitions.setup.schedule.timeline.invitation_cutoff_note'))

    // A quick pick sets the close from "now" rounded up to 5 minutes (opens at publish).
    const dayChip = wrapper.findAll('input[type="radio"]').find(input => (input.element as HTMLInputElement).value === 'day')!
    await dayChip.setValue(true)
    await flush()
    const close = Date.parse(editor.form.scheduled_close_at ?? '')
    const expectedOpens = Math.ceil(clock.now() / 300_000) * 300_000
    expect(Math.abs(close - (expectedOpens + 24 * 3600_000))).toBeLessThan(300_000 + 1)
    expect(wrapper.text()).toContain(t('common.relative.in_days', { count: 1 }, 1))

    // A close in the past is a blocking, self-explaining error.
    editor.update('scheduled_close_at', new Date(clock.now() - 3600_000).toISOString())
    await flush()
    expect(wrapper.text()).toContain(t('competitions.setup.schedule.issues.close_in_past'))
    expect(editor.blockingIssues('schedule', clock.now()).map(issue => issue.key)).toEqual(['competitions.setup.schedule.issues.close_in_past'])
  })

  it('states the value and the bound for a too-short duration', async () => {
    setScope('core')
    const editor = useCompetitionEditorStore()
    editor.load(makeIssuerCompetition())
    const clock = useServerTime()
    editor.update('scheduled_close_at', new Date(clock.now() + 4 * 60_000 + 30_000).toISOString())
    const wrapper = await mountSuspended(StepSchedule, { props: { showAll: true } })
    await flush()
    expect(wrapper.text()).toContain(t('competitions.setup.schedule.issues.min_duration', { minutes: 4, min: 10 }, 4))
  })
})

describe('W15 step page (RELEASE_SCOPE §2.1)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('redirects legacy step keys to the step that holds their content', async () => {
    setScope('core')
    ctx.competition = ref(makeIssuerCompetition())
    const first = await mountSuspended(StepPage, { route: `/ar/dashboard/competitions/${COMPETITION_ID}/setup/type` })
    await flush()
    expect(navigate).toHaveBeenCalledWith(`/ar/dashboard/competitions/${COMPETITION_ID}/setup/basics`, { replace: true })
    first.unmount()
    navigate.mockReset()
    const second = await mountSuspended(StepPage, { route: `/ar/dashboard/competitions/${COMPETITION_ID}/setup/fees` })
    await flush()
    expect(navigate).toHaveBeenCalledWith(`/ar/dashboard/competitions/${COMPETITION_ID}/setup/participants`, { replace: true })
    second.unmount()
  })

  it('shows the five steps in both scopes and autosaves a changed form 1.5 s after the last edit (FQ6)', async () => {
    setScope('full')
    ctx.competition = ref(makeIssuerCompetition())
    competitions.updateCompetition.mockImplementation(async (_id: string, body: { title?: string }) => makeIssuerCompetition({ title: body.title ?? 'x' }))
    const wrapper = await mountSuspended(StepPage, { route: `/ar/dashboard/competitions/${COMPETITION_ID}/setup/basics` })
    await flush()
    const steps = wrapper.findAll('nav ol li').map(item => item.text())
    expect(steps).toHaveLength(5)
    expect(steps[0]).toContain(t('competitions.setup.steps.basics'))
    expect(steps[4]).toContain(t('competitions.setup.steps.review'))
    expect(wrapper.text()).not.toContain(t('competitions.setup.steps.fees'))
    // Full scope: the format cards are back inside step 1.
    expect(wrapper.text()).toContain(t('competitions.setup.type.format_legend'))

    const editor = useCompetitionEditorStore()
    editor.update('title', 'عنوان جديد')
    await vi.advanceTimersByTimeAsync(1000)
    expect(competitions.updateCompetition).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(700)
    await flush()
    expect(competitions.updateCompetition).toHaveBeenCalledTimes(1)
    expect(competitions.updateCompetition.mock.calls[0]![1]).toMatchObject({ title: 'عنوان جديد', direction: 'tender' })
    expect(editor.isDirty(['type', 'basics'])).toBe(false)
    expect(wrapper.text()).toContain(t('common.autosave.saved_at', { time: '' }).trim())
    wrapper.unmount()
  })

  it('does not autosave while the step has a blocking problem, and lists it with a field link on Continue (FQ8)', async () => {
    setScope('core')
    ctx.competition = ref(makeIssuerCompetition())
    const wrapper = await mountSuspended(StepPage, { route: `/ar/dashboard/competitions/${COMPETITION_ID}/setup/basics` })
    await flush()
    expect(wrapper.text()).not.toContain(t('competitions.setup.type.format_legend'))
    const editor = useCompetitionEditorStore()
    editor.update('title', '   ')
    await vi.advanceTimersByTimeAsync(2000)
    expect(competitions.updateCompetition).not.toHaveBeenCalled()
    await wrapper.findAll('button').find(button => button.text().includes(t('common.actions.continue')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('competitions.setup.error_summary.title'))
    expect(wrapper.find('a[href="#wizard-title"]').exists()).toBe(true)
    expect(navigate).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('blocks Continue while an amount cannot be read, instead of saving its last valid value (FQ2, FQ8)', async () => {
    setScope('core')
    // A fresh editor: the page keeps a dirty form of the same draft (earlier tests leave one behind).
    useCompetitionEditorStore().startNew()
    const base = makeIssuerCompetition()
    ctx.competition = ref(makeIssuerCompetition({ preset_code: 'tender_live_standard', rules: { ...base.rules, reserve_price_minor: null } }))
    competitions.updateCompetition.mockImplementation(async (_id: string, body: { rules?: Record<string, unknown> }) => makeIssuerCompetition({ preset_code: 'tender_live_standard', rules: { ...makeIssuerCompetition().rules, ...body.rules } as IssuerCompetition['rules'] }))
    const wrapper = await mountSuspended(StepPage, { route: `/ar/dashboard/competitions/${COMPETITION_ID}/setup/rules` })
    await flush()
    const editor = useCompetitionEditorStore()
    const input = wrapper.get('#wizard-start-price')
    // Typed character by character: «1.23» is valid, «1.234» is not; the model keeps 1.23 meanwhile.
    for (const text of ['1', '1.', '1.2', '1.23', '1.234']) await input.setValue(text)
    await flush()
    expect(editor.form.rules.start_price_minor).toBe(123)
    expect(editor.unreadable).toEqual(['rules.start_price_minor'])
    await vi.advanceTimersByTimeAsync(2000)
    expect(competitions.updateCompetition).not.toHaveBeenCalled()
    const continueButton = () => wrapper.findAll('button').find(button => button.text().includes(t('common.actions.continue')))!
    await continueButton().trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('competitions.setup.error_summary.title'))
    expect(wrapper.get('a[href="#wizard-start-price"]').text()).toContain(t('common.money.invalid'))
    expect(competitions.updateCompetition).not.toHaveBeenCalled()
    expect(navigate).not.toHaveBeenCalled()

    // Fixed text (whole riyals on this preset): the step saves the typed amount and moves on.
    await wrapper.get('#wizard-start-price').setValue('15')
    await flush()
    expect(editor.unreadable).toEqual([])
    await continueButton().trigger('click')
    await flush()
    expect(competitions.updateCompetition).toHaveBeenCalled()
    expect(competitions.updateCompetition.mock.calls.at(-1)![1]).toMatchObject({ rules: { start_price_minor: 1500 } })
    wrapper.unmount()
  })

  it('forgets an unreadable amount when its field leaves the page', async () => {
    setScope('core')
    await useLookupsStore().load()
    const editor = useCompetitionEditorStore()
    const competition = makeIssuerCompetition({ preset_code: 'tender_live_standard' })
    editor.load(competition)
    const wrapper = await mountSuspended(StepRules)
    await flush()
    await wrapper.get('#wizard-start-price').setValue('12abc')
    await flush()
    expect(editor.unreadable).toEqual(['rules.start_price_minor'])
    expect(editor.blockingIssues('rules', Date.now()).map(issue => issue.field)).toContain('rules.start_price_minor')
    wrapper.unmount()
    expect(editor.unreadable).toEqual([])
  })
})
