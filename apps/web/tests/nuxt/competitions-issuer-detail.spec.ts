import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { Ref } from 'vue'
import { makeAppConfig, makeTokenPayload } from '../fixtures/api'
import {
  COMPETITION_ID,
  makeInvitation,
  makeIssuerCompetition,
  makeLiveSnapshot,
  makeOfferEntry,
  makePayment,
  makeQuote,
  makeStandingRow,
} from '../fixtures/issuer'
import type { IssuerCompetition } from '~/types/api/competitions'
import type { LiveSnapshot } from '~/types/api/bidding'
import AwardDetail from '~/components/competitions/issuer/AwardDetail.vue'
import AwardWorkspace from '~/components/competitions/issuer/AwardWorkspace.vue'
import BafoShortlistDialog from '~/components/competitions/issuer/BafoShortlistDialog.vue'
import ReportButton from '~/components/competitions/issuer/ReportButton.vue'
import ExtendDialog from '~/components/competitions/issuer/ExtendDialog.vue'
import InviteDrawer from '~/components/competitions/issuer/InviteDrawer.vue'
import LiveConsole from '~/components/competitions/issuer/LiveConsole.vue'
import StepReview from '~/components/competitions/wizard/StepReview.vue'

const bidding = vi.hoisted(() => ({
  fetchOfferLog: vi.fn(),
  fetchStandings: vi.fn(),
  fetchAward: vi.fn(),
  issueAward: vi.fn(),
  startBafoRound: vi.fn(),
  revokeAward: vi.fn(),
  fetchReport: vi.fn(),
}))
const competitions = vi.hoisted(() => ({
  publishCompetition: vi.fn(),
  extendCompetition: vi.fn(),
  cancelCompetition: vi.fn(),
  closeCompetitionWithoutAward: vi.fn(),
  createInvitations: vi.fn(),
  fetchSuggestions: vi.fn(),
}))
const billing = vi.hoisted(() => ({
  fetchSponsorshipQuote: vi.fn(),
  checkoutSponsorship: vi.fn(),
  checkoutReturnUrl: (origin: string, locale: string) => `${origin}/${locale}/dashboard/billing/checkout/return`,
  validateCoupon: vi.fn(),
}))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const integrations = vi.hoisted(() => ({ listVendors: vi.fn(), createExportJob: vi.fn(), fetchExportJob: vi.fn() }))
vi.mock('~/services/bidding', () => bidding)
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/catalog', () => catalog)
vi.mock('~/services/integrations', () => integrations)

/** A stand-in for the detail parent's `provideCompetition()` context. */
const ctx = vi.hoisted(() => ({
  state: null as null | {
    competition: Ref<IssuerCompetition | null>
    live: Ref<LiveSnapshot | null>
    listeners: Map<string, Array<(payload: unknown) => void>>
  },
  refetch: vi.fn(),
  resync: vi.fn(),
}))

const download = vi.hoisted(() => vi.fn())
mockNuxtImport('useFileDownload', () => () => ({ download, downloading: ref(false) }))

mockNuxtImport('useCompetitionContext', () => () => {
  const state = ctx.state!
  return {
    id: computed(() => COMPETITION_ID),
    competition: state.competition,
    viewerRole: computed(() => 'issuer'),
    loading: ref(false),
    error: ref(null),
    live: computed(() => state.live.value),
    connection: computed(() => 'connected'),
    canSubmit: computed(() => true),
    refetch: ctx.refetch,
    resync: ctx.resync,
    applyLive: () => true,
    on: (event: string, listener: (payload: unknown) => void) => {
      const list = state.listeners.get(event) ?? []
      list.push(listener)
      state.listeners.set(event, list)
      return () => {}
    },
  }
})

function setContext(competition: IssuerCompetition, live: LiveSnapshot | null = null) {
  ctx.state = { competition: ref(competition) as Ref<IssuerCompetition | null>, live: ref(live) as Ref<LiveSnapshot | null>, listeners: new Map() }
  return ctx.state
}

function emitEvent(event: string, payload: unknown): void {
  for (const listener of ctx.state?.listeners.get(event) ?? []) listener(payload)
}

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  for (const group of [bidding, competitions, billing, catalog, integrations]) {
    for (const fn of Object.values(group)) if (typeof fn === 'function' && 'mockReset' in fn) (fn as ReturnType<typeof vi.fn>).mockReset()
  }
  ctx.refetch.mockReset()
  ctx.resync.mockReset()
  download.mockReset()
  catalog.fetchLookups.mockResolvedValue({
    status: 'fresh',
    lookups: {
      regions: [],
      categories: [],
      presets: [],
      close_reasons: [
        { id: 'r-commercial', code: 'award_commercial_terms', kind: 'award_justification', name: 'شروط تجارية أفضل', requires_note: false },
        { id: 'r-other', code: 'award_other', kind: 'award_justification', name: 'سبب آخر', requires_note: true },
        { id: 'c-budget', code: 'cancel_budget_withdrawn', kind: 'cancel', name: 'سحب الميزانية', requires_note: false },
      ],
    },
    etag: null,
  })
  integrations.listVendors.mockResolvedValue({ items: [], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false } })
  competitions.fetchSuggestions.mockResolvedValue([])
  useAuthStore().setSession(makeTokenPayload())
  // Covered fees, vendors and extensions are hidden in the first release: these tests run against the full product.
  useAppConfigStore().config = makeAppConfig()
})

// ---------------------------------------------------------------------------------------------

describe('W19 issuer live console', () => {
  const live = makeIssuerCompetition({ status: 'live', phase: 'final_window', permissions: { ...makeIssuerCompetition().permissions, can_extend: true, can_cancel: true } })

  it('renders the leading offer, the reserve indicator, the server-signed improvement and the ranking', async () => {
    setContext(live, makeLiveSnapshot(10))
    bidding.fetchOfferLog.mockResolvedValue({ entries: [makeOfferEntry(7), makeOfferEntry(8)], last_seq: 8, has_more: false })
    const wrapper = await mountSuspended(LiveConsole)
    await flush()
    const text = wrapper.text()
    expect(text).toContain('98,000.00')
    expect(text).toContain(t('live.console.reserve.not_met.tender'))
    expect(text).toContain('+60.8%')
    expect(text).toContain(t('live.metric.improvement.tender'))
    expect(text).toContain('شركة الريادة')
    expect(text).toContain('مؤسسة دلتا')
    // The feed asks for the latest offers only (after_seq = offers_count − 20, floored at 0).
    expect(bidding.fetchOfferLog).toHaveBeenCalledWith(COMPETITION_ID, 0, 200)
    expect(text).toContain('2026-11-09 14:59:58.412')
  })

  it('fills a gap in offer.accepted events from the log (S4)', async () => {
    setContext(live, makeLiveSnapshot(10))
    bidding.fetchOfferLog.mockResolvedValueOnce({ entries: [makeOfferEntry(1), makeOfferEntry(2)], last_seq: 2, has_more: false })
    await mountSuspended(LiveConsole)
    await flush()
    bidding.fetchOfferLog.mockResolvedValueOnce({ entries: [makeOfferEntry(3), makeOfferEntry(4)], last_seq: 4, has_more: false })
    emitEvent('offerAccepted', makeOfferEntry(5))
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenLastCalledWith(COMPETITION_ID, 2, 200)
  })

  it('announces an automatic extension with the new closing time', async () => {
    const state = setContext(live, makeLiveSnapshot(10))
    bidding.fetchOfferLog.mockResolvedValue({ entries: [], last_seq: 0, has_more: false })
    const wrapper = await mountSuspended(LiveConsole)
    await flush()
    const previous = state.live.value!
    state.live.value = makeLiveSnapshot(11, {
      extension_count: 1,
      effective_close_at: new Date(Date.parse(previous.effective_close_at!) + 3 * 60_000).toISOString(),
      last_change: { kind: 'extension', reason: 'auto' },
    })
    await flush()
    expect(wrapper.text()).toContain(t('live.console.extended.auto'))
    expect(wrapper.text()).toContain(t('competitions.detail.countdown.extensions', { count: 1 }, 1))
  })

  it('shows the sealed lock before the offers are opened (no amounts)', async () => {
    const sealed = makeIssuerCompetition({ status: 'live', format: 'sealed', phase: 'sealed' })
    setContext(sealed, makeLiveSnapshot(3, {
      phase: 'sealed',
      leader: null,
      reserve_met: null,
      metrics: { offers_count: 2, participants_joined: 2, participants_with_offers: 1, invitations_count: 3, improvement_vs_start_bps: null },
      ranking: [
        { participant_id: 'pa', alias_no: 3, organization: { id: 'oa', name: 'شركة الريادة', logo_url: null }, current_amount_minor: null, first_amount_minor: null, rank: null, is_leader: null, offers_count: 1, last_offer_at: '2026-11-09T11:58:00.000Z', submitted: true, bafo: { shortlisted: false, submitted: false } },
        { participant_id: 'pb', alias_no: 7, organization: { id: 'ob', name: 'مؤسسة دلتا', logo_url: null }, current_amount_minor: null, first_amount_minor: null, rank: null, is_leader: null, offers_count: 0, last_offer_at: null, submitted: false, bafo: { shortlisted: false, submitted: false } },
      ],
    }))
    bidding.fetchOfferLog.mockResolvedValue({ entries: [makeOfferEntry(1, { amount_minor: null, stage: 'sealed' })], last_seq: 1, has_more: false })
    const wrapper = await mountSuspended(LiveConsole)
    await flush()
    const text = wrapper.text()
    expect(text).toContain(t('live.console.sealed_locked'))
    expect(text).toContain(t('live.console.ranking.submitted_yes'))
    expect(text).toContain(t('live.console.ranking.submitted_no'))
    expect(text).toContain(t('offers.sealed_amount'))
    expect(text).not.toContain('98,000.00')
  })
})

// ---------------------------------------------------------------------------------------------

describe('W22 award workspace', () => {
  const closed = makeIssuerCompetition({
    status: 'closed',
    permissions: { ...makeIssuerCompetition().permissions, can_edit: false, can_award: true, can_close_without_award: true, can_start_bafo: true },
  })

  it('asks for a justification before awarding a participant that is not rank 1, then sends it', async () => {
    setContext(closed)
    bidding.fetchStandings.mockResolvedValue([makeStandingRow(1), makeStandingRow(2)])
    bidding.fetchAward.mockResolvedValue(null)
    bidding.issueAward.mockResolvedValue({})
    const wrapper = await mountSuspended(AwardWorkspace, { attachTo: document.body })
    await flush()

    const radio = wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'p2')!
    await radio.setValue(true)
    await flush()
    expect(wrapper.text()).toContain(t('award.workspace.why_not_leading'))

    await wrapper.find('form').trigger('submit')
    await flush()
    expect(wrapper.text()).toContain(t('award.workspace.reason_required'))
    expect(bidding.issueAward).not.toHaveBeenCalled()

    const select = wrapper.findAll('select').at(0)!
    const index = Array.from((select.element as HTMLSelectElement).options).findIndex(option => option.textContent?.includes('شروط تجارية أفضل'))
    await select.setValue(String(index - 1))
    await wrapper.find('form').trigger('submit')
    await flush()
    const confirm = Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('award.confirm.confirm'))!
    confirm.click()
    await flush()

    expect(bidding.issueAward).toHaveBeenCalledWith(COMPETITION_ID, expect.objectContaining({
      participant_id: 'p2',
      justification_reason_id: 'r-commercial',
      confirm_reserve_not_met: false,
    }))
    expect(ctx.refetch).toHaveBeenCalled()
    wrapper.unmount()
  })

  it('reveals the reserve confirmation when the server requires it', async () => {
    setContext(closed)
    bidding.fetchStandings.mockResolvedValue([makeStandingRow(1, { current_amount_minor: 20_000_000 })])
    bidding.fetchAward.mockResolvedValue(null)
    bidding.issueAward.mockRejectedValue(new ApiError({ status: 422, code: 'award_reserve_confirmation_required', message: 'confirm', errors: {} }))
    const wrapper = await mountSuspended(AwardWorkspace, { attachTo: document.body })
    await flush()
    await wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'p1')!.setValue(true)
    await wrapper.find('form').trigger('submit')
    await flush()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('award.confirm.confirm'))!.click()
    await flush()
    expect(wrapper.text()).toContain(t('award.workspace.confirm_reserve.tender'))
    expect(wrapper.text()).toContain(t('award.workspace.why_reserve'))
    wrapper.unmount()
  })
})

// ---------------------------------------------------------------------------------------------

describe('W15 review and publish', () => {
  const draft = makeIssuerCompetition()

  it('publishes and emits the new projection', async () => {
    setContext(draft)
    competitions.publishCompetition.mockResolvedValue(makeIssuerCompetition({ status: 'scheduled' }))
    const wrapper = await mountSuspended(StepReview, { props: { competition: draft, feesEnabled: false } })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('competitions.setup.review.publish'))!.trigger('click')
    await flush()
    expect(competitions.publishCompetition).toHaveBeenCalledWith(COMPETITION_ID)
    expect(wrapper.emitted('published')?.[0]?.[0]).toMatchObject({ status: 'scheduled' })
  })

  it('explains min_participants_not_met with the server counts and links to step 6', async () => {
    setContext(draft)
    competitions.publishCompetition.mockRejectedValue(new ApiError({ status: 422, code: 'min_participants_not_met', message: 'min', errors: {}, details: { required: 5, current: 3 } }))
    const wrapper = await mountSuspended(StepReview, { props: { competition: draft, feesEnabled: false } })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('competitions.setup.review.publish'))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('competitions.setup.publish_errors.min_participants', { count: 2 }, 2))
    expect(wrapper.find(`a[href$="/competitions/${COMPETITION_ID}/setup/participants"]`).exists()).toBe(true)
  })

  it('switches to Pay and publish on sponsorship_payment_required, then shows the server payment before redirecting', async () => {
    const sponsored = makeIssuerCompetition({ sponsorship: { mode: 'all', status: 'draft', funded_passes: 0, free_slots: 0 } })
    setContext(sponsored)
    billing.fetchSponsorshipQuote.mockResolvedValue(makeQuote({ passes_to_buy: 0, subtotal_minor: 0, vat_minor: 0, total_minor: 0 }))
    competitions.publishCompetition.mockRejectedValue(new ApiError({ status: 409, code: 'sponsorship_payment_required', message: 'pay', errors: {}, details: { quote: makeQuote() } }))
    billing.checkoutSponsorship.mockResolvedValue(makePayment())
    const wrapper = await mountSuspended(StepReview, { props: { competition: sponsored, feesEnabled: true } })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('competitions.setup.review.publish'))!.trigger('click')
    await flush()
    const pay = wrapper.findAll('button').find(button => button.text() === t('competitions.setup.review.pay_and_publish'))
    expect(pay).toBeTruthy()
    await pay!.trigger('click')
    await flush()
    const [id, body, key] = billing.checkoutSponsorship.mock.calls[0]!
    expect(id).toBe(COMPETITION_ID)
    expect(body).toMatchObject({ intent: 'publish', coupon_code: null })
    expect(body.return_url).toMatch(/\/ar\/dashboard\/billing\/checkout\/return$/)
    expect(key).toMatch(/^[0-9a-f-]{36}$/)
    expect(wrapper.text()).toContain('460.00')
    expect(wrapper.text()).toContain(t('sponsorship.fees.checkout.title'))
  })

  it('publishes directly when the passes are already funded', async () => {
    const sponsored = makeIssuerCompetition({ sponsorship: { mode: 'all', status: 'active', funded_passes: 2, free_slots: 2 } })
    setContext(sponsored)
    billing.fetchSponsorshipQuote.mockResolvedValue(makeQuote())
    billing.checkoutSponsorship.mockRejectedValue(new ApiError({ status: 409, code: 'sponsorship_already_funded', message: 'funded', errors: {} }))
    competitions.publishCompetition.mockResolvedValue(makeIssuerCompetition({ status: 'live' }))
    const wrapper = await mountSuspended(StepReview, { props: { competition: sponsored, feesEnabled: true } })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('competitions.setup.review.pay_and_publish'))!.trigger('click')
    await flush()
    expect(competitions.publishCompetition).toHaveBeenCalledWith(COMPETITION_ID)
    expect(wrapper.emitted('published')).toBeTruthy()
  })
})

// ---------------------------------------------------------------------------------------------

describe('W17 invite more', () => {
  const live = makeIssuerCompetition({ status: 'live', sponsorship: { mode: 'selected', status: 'active', funded_passes: 1, free_slots: 0 } })

  it('offers Pay and send, or Send without covering fees, when passes must be bought', async () => {
    setContext(live)
    competitions.createInvitations
      .mockRejectedValueOnce(new ApiError({ status: 409, code: 'sponsorship_payment_required', message: 'pay', errors: {}, details: { quote: makeQuote({ passes_to_buy: 1, subtotal_minor: 20_000, vat_minor: 3000, total_minor: 23_000 }) } }))
      .mockResolvedValueOnce([makeInvitation('new', { status: 'sent' })])
    const wrapper = await mountSuspended(InviteDrawer, {
      props: { competition: live, existing: [], sponsorshipMode: 'selected', open: true },
      attachTo: document.body,
    })
    await flush()
    const emailTab = Array.from(document.querySelectorAll('[role="tab"]')).find(tab => tab.textContent?.includes(t('invitations.issuer.picker.tabs.email'))) as HTMLElement
    emailTab.click()
    await flush()
    const input = document.querySelector<HTMLInputElement>('input[inputmode="email"]')!
    input.value = 'buyer@supplier.sa '
    input.dispatchEvent(new Event('input'))
    await flush()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.includes(t('invitations.issuer.picker.add_emails', { count: 1 }, 1)))!.click()
    await flush()
    // Cover this row's fees (selected mode).
    ;(document.querySelector('[role="switch"]') as HTMLButtonElement).click()
    await flush()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.includes(t('invitations.issuer.drawer.send', { count: 1 }, 1)))!.click()
    await flush()

    expect(competitions.createInvitations).toHaveBeenLastCalledWith(COMPETITION_ID, [{ email: 'buyer@supplier.sa', sponsored: true }])
    expect(document.body.textContent).toContain(t('invitations.issuer.drawer.pay_and_send'))
    expect(document.body.textContent).toContain('230.00')

    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.includes(t('invitations.issuer.drawer.send_without_fees')))!.click()
    await flush()
    expect(competitions.createInvitations).toHaveBeenLastCalledWith(COMPETITION_ID, [{ email: 'buyer@supplier.sa', sponsored: false }])
    expect(wrapper.emitted('created')).toBeTruthy()
    wrapper.unmount()
  })
})

// ---------------------------------------------------------------------------------------------

describe('extend dialog', () => {
  it('sends the new close and reason, and shows the earliest allowed time on extend_invalid', async () => {
    const live = makeIssuerCompetition({ status: 'live', schedule: { ...makeIssuerCompetition().schedule, effective_close_at: '2026-11-09T12:00:00.000Z' } })
    setContext(live)
    competitions.extendCompetition.mockRejectedValue(new ApiError({ status: 422, code: 'extend_invalid', message: 'bad', errors: {}, details: { min_new_close_at: '2026-11-09T12:10:00.000Z' } }))
    const wrapper = await mountSuspended(ExtendDialog, { props: { competition: live, open: true }, attachTo: document.body })
    await flush()
    const reason = document.querySelector('dialog[open] textarea') as HTMLTextAreaElement
    reason.value = 'طلب عدد من الموردين مهلة إضافية'
    reason.dispatchEvent(new Event('input'))
    await flush()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('competitions.issuer.extend.confirm'))!.click()
    await flush()
    expect(competitions.extendCompetition).toHaveBeenCalledWith(COMPETITION_ID, { new_close_at: '2026-11-09T12:05:00.000Z', reason: 'طلب عدد من الموردين مهلة إضافية' })
    expect(document.body.textContent).toContain('3:10')
    wrapper.unmount()
  })
})

// ---------------------------------------------------------------------------------------------

describe('BAFO round, revoke and report', () => {
  const closed = makeIssuerCompetition({ status: 'closed', rules: { ...makeIssuerCompetition().rules, bafo_round: { enabled: true, duration_minutes: 90 } } })

  it('starts a BAFO round with a manual shortlist and marks rows the server rejects', async () => {
    setContext(closed)
    bidding.startBafoRound
      .mockRejectedValueOnce(new ApiError({ status: 422, code: 'bafo_shortlist_invalid', message: 'bad', errors: {}, details: { invalid_participant_ids: ['p2'] } }))
      .mockResolvedValueOnce(makeIssuerCompetition({ status: 'bafo_round' }))
    const wrapper = await mountSuspended(BafoShortlistDialog, {
      props: { competition: closed, rows: [makeStandingRow(1), makeStandingRow(2)], open: true },
      attachTo: document.body,
    })
    await flush()
    const boxes = Array.from(document.querySelectorAll<HTMLInputElement>('input[type="checkbox"]'))
    boxes.forEach(box => box.click())
    await flush()
    const confirm = () => Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('bafo.shortlist.confirm', { count: 2 }, 2))!
    confirm().click()
    await flush()
    expect(bidding.startBafoRound).toHaveBeenCalledWith(COMPETITION_ID, { participant_ids: ['p1', 'p2'], duration_minutes: 90 })
    expect(document.body.textContent).toContain(t('bafo.shortlist.invalid_row'))

    boxes[1]!.click()
    await flush()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('bafo.shortlist.confirm', { count: 1 }, 1))!.click()
    await flush()
    expect(bidding.startBafoRound).toHaveBeenLastCalledWith(COMPETITION_ID, { participant_ids: ['p1'], duration_minutes: 90 })
    expect(wrapper.emitted('started')?.[0]?.[0]).toMatchObject({ status: 'bafo_round' })
    wrapper.unmount()
  })

  it('revokes an award only with a reason of at least 5 characters', async () => {
    const award = {
      id: 'award-1', status: 'issued' as const,
      participant: { id: 'p1', alias_no: 3, organization: { id: 'o1', name: 'شركة الريادة', cr_number: '1010000001', vat_number: '300000000000003' } },
      amount_minor: 9_800_000, currency: 'SAR' as const, price_basis: 'excl_vat' as const, is_leading_offer: true, rank_at_award: 1, reserve_met: true,
      justification: null, message_to_winner: null, internal_notes: null, offer: { id: 'o', seq: 57, accepted_at: '2026-11-09T11:58:00.000Z' },
      awarded_by: { id: 'u', name: 'سارة' }, awarded_at: '2026-11-10T09:00:00.000Z', revoked_at: null, revoke_reason: null,
      erp_sync: { status: 'pending' as const, message: null, synced_at: null, refs: [] }, ledger_head_hash: 'b3f1', created_at: '2026-11-10T09:00:00.000Z',
    }
    bidding.revokeAward.mockResolvedValue({ ...award, status: 'revoked' })
    const wrapper = await mountSuspended(AwardDetail, { props: { competitionId: COMPETITION_ID, direction: 'tender', award, canRevoke: true }, attachTo: document.body })
    await flush()
    expect(wrapper.text()).toContain('98,000.00')
    expect(wrapper.text()).toContain(t('award.detail.reserve_met.tender'))
    expect(wrapper.text()).toContain(t('award.detail.erp_status.pending'))
    await wrapper.findAll('button').find(button => button.text() === t('award.revoke.open'))!.trigger('click')
    await flush()
    const confirm = () => Array.from(document.querySelectorAll('dialog[open] button')).find(button => button.textContent?.trim() === t('award.revoke.confirm')) as HTMLButtonElement
    confirm().click()
    await flush()
    expect(bidding.revokeAward).not.toHaveBeenCalled()
    const reason = document.querySelector('dialog[open] textarea') as HTMLTextAreaElement
    reason.value = 'تعذّر التعاقد مع الفائز'
    reason.dispatchEvent(new Event('input'))
    await flush()
    confirm().click()
    await flush()
    expect(bidding.revokeAward).toHaveBeenCalledWith(COMPETITION_ID, 'تعذّر التعاقد مع الفائز')
    expect(wrapper.emitted('revoked')).toBeTruthy()
    wrapper.unmount()
  })

  it('polls a pending report, then downloads the private file', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const file = { id: 'f1', name: 'report.pdf', mime_type: 'application/pdf', extension: 'pdf', size_bytes: 1000, download_path: '/api/app/v1/files/f1/download', created_at: '2026-11-10T09:00:00.000Z' }
    bidding.fetchReport
      .mockResolvedValueOnce({ status: 'pending', locale: 'ar', generated_at: null, file: null })
      .mockResolvedValueOnce({ status: 'ready', locale: 'ar', generated_at: '2026-11-10T09:01:00.000Z', file })
    const wrapper = await mountSuspended(ReportButton, { props: { competitionId: COMPETITION_ID } })
    await wrapper.findAll('button').find(button => button.text() === t('award.report.download'))!.trigger('click')
    await flush()
    expect(bidding.fetchReport).toHaveBeenCalledWith(COMPETITION_ID, 'ar')
    expect(wrapper.text()).toContain(t('award.report.preparing'))
    await vi.advanceTimersByTimeAsync(3100)
    await flush()
    expect(bidding.fetchReport).toHaveBeenCalledTimes(2)
    expect(download).toHaveBeenCalledWith('/api/app/v1/files/f1/download', 'report.pdf')
    vi.useRealTimers()
  })

  it('hides the report before the first close', async () => {
    bidding.fetchReport.mockRejectedValue(new ApiError({ status: 409, code: 'report_not_available', message: 'no', errors: {} }))
    const wrapper = await mountSuspended(ReportButton, { props: { competitionId: COMPETITION_ID } })
    await wrapper.findAll('button').find(button => button.text() === t('award.report.download'))!.trigger('click')
    await flush()
    expect(wrapper.text()).not.toContain(t('award.report.title'))
  })
})
