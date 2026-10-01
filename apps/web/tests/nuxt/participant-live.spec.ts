import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { LiveOfferComposer, LiveParticipantRoom } from '#components'
import { makeTokenPayload } from '../fixtures/api'
import { COMPETITION_ID, participantCompetition, participantSnapshot } from '../fixtures/participant'
import type { ParticipantLiveSnapshot } from '~/types/api/bidding'
import type { ParticipantCompetition } from '~/types/api/competitions'

const competitions = vi.hoisted(() => ({ fetchCompetition: vi.fn() }))
const bidding = vi.hoisted(() => ({
  fetchLive: vi.fn(),
  submitOffer: vi.fn(),
  fetchMyOffers: vi.fn(),
  sendHeartbeat: vi.fn(),
  fetchAward: vi.fn(),
}))
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/bidding', () => bidding)

/** A fake Echo: records channel listeners so the test can push `live.updated` snapshots. */
const echo = vi.hoisted(() => {
  const channels = new Map<string, { listeners: Map<string, (payload: unknown) => void>, subscribed: Array<() => void> }>()
  const api = {
    channels,
    private(name: string) {
      const channel = { listeners: new Map<string, (payload: unknown) => void>(), subscribed: [] as Array<() => void> }
      channels.set(name, channel)
      const handle = {
        listen(event: string, callback: (payload: unknown) => void) {
          channel.listeners.set(event, callback)
          return handle
        },
        subscribed(callback: () => void) {
          channel.subscribed.push(callback)
          return handle
        },
      }
      return handle
    },
    leave() {},
  }
  return api
})
const realtime = vi.hoisted(() => ({ available: { value: true }, state: { value: 'connected' } }))
mockNuxtImport('useEcho', () => () => echo)
mockNuxtImport('useRealtimeStatus', () => () => ({ available: toRef(realtime.available, 'value'), state: toRef(realtime.state, 'value') }))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

/** Dialogs stay in the DOM (native `<dialog>`); what matters is whether they are open. */
function dialogOpen(wrapper: { find: (selector: string) => { exists: () => boolean, element: Element } }, testId: string): boolean {
  const found = wrapper.find(`[data-testid="${testId}"]`)
  return found.exists() && (found.element.closest('dialog') as HTMLDialogElement | null)?.open === true
}

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

/** Mounts the live room under a real `provideCompetition()` (mocked services, fake Echo). */
async function mountRoom(competition: ParticipantCompetition) {
  competitions.fetchCompetition.mockResolvedValue(competition)
  bidding.fetchLive.mockResolvedValue(competition.live)
  const Harness = defineComponent({
    setup() {
      provideCompetition(COMPETITION_ID)
      return () => h(LiveParticipantRoom)
    },
  })
  const wrapper = await mountSuspended(Harness, { attachTo: document.body })
  await flush()
  const channel = [...echo.channels.values()][0]
  channel?.subscribed.forEach(callback => callback())
  await flush()
  const push = async (snapshot: ParticipantLiveSnapshot) => {
    channel?.listeners.get('.live.updated')?.(snapshot)
    await flush()
  }
  return { wrapper, push }
}

beforeEach(() => {
  for (const group of [competitions, bidding]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  bidding.fetchMyOffers.mockResolvedValue([])
  bidding.sendHeartbeat.mockResolvedValue(undefined)
  bidding.fetchAward.mockResolvedValue(null)
  echo.channels.clear()
  realtime.available.value = true
  realtime.state.value = 'connected'
  useAuthStore().setSession(makeTokenPayload())
  useToast().clear()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('W19 participant live room: rendering from the projection', () => {
  it('subscribes to the participant channel, sends a heartbeat and shows the tender standing', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    const orgId = useAuthStore().organization!.id
    expect([...echo.channels.keys()]).toEqual([`competition.${COMPETITION_ID}.participant.${orgId}`])
    expect(bidding.sendHeartbeat).toHaveBeenCalledWith(COMPETITION_ID)
    const banner = wrapper.get('[data-testid="standing-banner"]')
    expect(banner.attributes('data-tone')).toBe('outbid')
    expect(banner.text()).toContain(t('live.status.not_leading.tender'))
    expect(wrapper.get('[data-testid="bound-hint"]').text()).toContain('98,008.00')
    wrapper.unmount()
  })

  it('never renders competitors’ prices unless the snapshot carries them', async () => {
    const hidden = await mountRoom(participantCompetition())
    expect(hidden.wrapper.find('[data-testid="leading-amount"]').exists()).toBe(false)
    expect(hidden.wrapper.find('[data-testid="ladder"]').exists()).toBe(false)
    hidden.wrapper.unmount()

    echo.channels.clear()
    const snapshot = participantSnapshot({
      is_leading: true,
      rank: 1,
      ranked_count: 3,
      leading_amount_minor: 9_850_000,
      ladder: [
        { alias_no: 7, amount_minor: 9_850_000, is_me: true },
        { alias_no: 3, amount_minor: 9_900_000, is_me: false },
      ],
    })
    const { wrapper } = await mountRoom(participantCompetition({ live: snapshot }))
    expect(wrapper.get('[data-testid="standing-banner"]').attributes('data-tone')).toBe('leading')
    expect(wrapper.get('[data-testid="standing-banner"]').text()).toContain(t('live.status.rank', { rank: 1, count: 3 }))
    expect(wrapper.get('[data-testid="leading-amount"]').text()).toContain('98,500.00')
    const ladder = wrapper.get('[data-testid="ladder"]').text()
    expect(ladder).toContain(t('live.market.you'))
    expect(ladder).toContain(t('live.market.alias', { alias: 3 }))
    wrapper.unmount()
  })

  it('shows the sealed panel with the receipt and the sealed composer', async () => {
    const snapshot = participantSnapshot({ phase: 'sealed', is_leading: null, required_next_amount_minor: null })
    const { wrapper } = await mountRoom(participantCompetition({ format: 'sealed', phase: 'sealed', live: snapshot }))
    expect(wrapper.get('[data-testid="sealed-panel"]').text()).toContain(t('live.sealed.title'))
    expect(wrapper.get('[data-testid="sealed-receipt"]').text()).toContain('#57')
    expect(wrapper.get('[data-testid="offer-composer"]').text()).toContain(t('live.composer.title_sealed'))
    expect(wrapper.find('[data-testid="standing-banner"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows the BAFO banner and a one-shot composer to the shortlisted participant only', async () => {
    const bafo = { shortlisted: true, cutoff_at: new Date(Date.now() + 3_600_000).toISOString(), submitted: false, reference_amount_minor: 9_850_000 }
    const snapshot = participantSnapshot({ status: 'bafo_round', phase: null, required_next_amount_minor: null, bafo })
    const shortlisted = await mountRoom(participantCompetition({ status: 'bafo_round', phase: null, live: snapshot }))
    expect(shortlisted.wrapper.get('[data-testid="bafo-banner"]').text()).toContain(t('bafo.rule.tender'))
    expect(shortlisted.wrapper.get('[data-testid="offer-composer"]').text()).toContain(t('live.composer.submit_bafo'))
    expect(shortlisted.wrapper.get('[data-testid="bound-hint"]').text()).toContain('98,500.00')
    shortlisted.wrapper.unmount()

    echo.channels.clear()
    const other = participantSnapshot({ status: 'bafo_round', phase: null, accepting_offers: false, required_next_amount_minor: null, bafo: { ...bafo, shortlisted: false, reference_amount_minor: null } })
    const { wrapper } = await mountRoom(participantCompetition({ status: 'bafo_round', phase: null, live: other }))
    expect(wrapper.get('[data-testid="bafo-banner"]').text()).toContain(t('bafo.not_shortlisted'))
    expect(wrapper.find('[data-testid="offer-composer"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows the result with the outcome and the final position after the award', async () => {
    bidding.fetchAward.mockResolvedValue({ outcome: 'won', winning_amount_minor: 9_850_000, message_to_winner: 'نتطلع إلى التعاون' })
    const snapshot = participantSnapshot({ status: 'awarded', phase: null, accepting_offers: false, is_leading: true, result: { outcome: 'won', winning_amount_minor: 9_850_000 } })
    const { wrapper } = await mountRoom(participantCompetition({ status: 'awarded', phase: null, live: snapshot, result: { outcome: 'won', winning_amount_minor: 9_850_000 } }))
    await flush()
    const panel = wrapper.get('[data-testid="result-panel"]')
    expect(panel.attributes('data-outcome')).toBe('won')
    expect(panel.text()).toContain(t('award.outcome.won'))
    expect(panel.text()).toContain(t('award.result.final_leading'))
    expect(panel.text()).toContain('نتطلع إلى التعاون')
    expect(wrapper.find('[data-testid="offer-composer"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows «جارٍ الإغلاق…» when the clock reaches zero before the server closes', async () => {
    const snapshot = participantSnapshot({ effective_close_at: new Date(Date.now() - 1000).toISOString() })
    const { wrapper } = await mountRoom(participantCompetition({ live: snapshot }))
    await new Promise(resolve => setTimeout(resolve, 300))
    await flush()
    expect(wrapper.find('[data-testid="offer-composer"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="composer-unavailable"]').text()).toContain(t('live.countdown.closing'))
    expect(wrapper.text()).not.toContain(t('live.announce.closed'))
    wrapper.unmount()
  })

  it('announces an anti-sniping extension politely and the close assertively', async () => {
    const { wrapper, push } = await mountRoom(participantCompetition())
    const before = participantSnapshot()
    const later = new Date(Date.parse(before.effective_close_at!) + 3 * 60_000).toISOString()
    await push(participantSnapshot({ v: 11, extension_count: 1, effective_close_at: later, last_change: { kind: 'extension', reason: 'auto' } }))
    const extended = t('live.extended', { minutes: t('live.minutes', { count: 3 }, 3) })
    expect(wrapper.get('[data-testid="room-announcer-polite"]').text()).toBe(extended)
    expect(wrapper.get('[data-testid="extension-notice"]').text()).toContain(extended)
    expect(wrapper.get('[data-testid="extension-count"]').text()).toBe(t('live.countdown.extensions', { count: 1 }, 1))

    competitions.fetchCompetition.mockResolvedValue(participantCompetition({ status: 'closed', phase: null }))
    await push(participantSnapshot({ v: 12, status: 'closed', phase: null, accepting_offers: false, extension_count: 1, effective_close_at: later, last_change: { kind: 'status', reason: null } }))
    expect(wrapper.get('[data-testid="room-announcer-assertive"]').text()).toBe(t('live.announce.closed'))
    wrapper.unmount()
  })

  it('drops snapshots older than the last applied version', async () => {
    const { wrapper, push } = await mountRoom(participantCompetition())
    await push(participantSnapshot({ v: 20, is_leading: true }))
    await push(participantSnapshot({ v: 15, is_leading: false }))
    expect(wrapper.get('[data-testid="standing-banner"]').attributes('data-tone')).toBe('leading')
    wrapper.unmount()
  })

  it('tells invitees to join first', async () => {
    competitions.fetchCompetition.mockResolvedValue({ ...participantCompetition(), viewer_role: 'invitee' })
    const Harness = defineComponent({
      setup() {
        provideCompetition(COMPETITION_ID)
        return () => h(LiveParticipantRoom)
      },
    })
    const wrapper = await mountSuspended(Harness)
    await flush()
    expect(wrapper.text()).toContain(t('live.room.invitee_title'))
    wrapper.unmount()
  })
})

describe('W19 offer submission (SCREENS S5)', () => {
  async function typeAmount(wrapper: Awaited<ReturnType<typeof mountRoom>>['wrapper'], value: string) {
    await wrapper.get('input[data-testid="offer-amount"]').setValue(value)
    await wrapper.get('[data-testid="offer-composer"] form').trigger('submit')
    await flush()
  }

  it('pre-checks the bound inline and sends nothing', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    await typeAmount(wrapper, '98,009')
    expect(dialogOpen(wrapper, 'offer-confirm')).toBe(false)
    expect(wrapper.get('[data-testid="offer-composer"]').text()).toContain(t('offers.error.step_not_met.tender', { amount: '⁨98,008.00 ر.س⁩' }))
    expect(bidding.submitOffer).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('confirms, sends one idempotency key, and changes nothing before the server answers', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    await wrapper.get('[data-testid="use-required"]').trigger('click')
    await flush()
    expect((wrapper.get('input[data-testid="offer-amount"]').element as HTMLInputElement).value).toBe('98,008.00')
    await wrapper.get('[data-testid="offer-composer"] form').trigger('submit')
    await flush()
    expect(dialogOpen(wrapper, 'offer-confirm')).toBe(true)
    expect(wrapper.get('[data-testid="confirm-amount"]').text()).toContain('98,008.00')

    let resolve!: (value: unknown) => void
    bidding.submitOffer.mockReturnValue(new Promise((done) => {
      resolve = done
    }))
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    expect(bidding.submitOffer).toHaveBeenCalledTimes(1)
    const [id, body, key] = bidding.submitOffer.mock.calls[0]!
    expect(id).toBe(COMPETITION_ID)
    expect(body).toEqual({ amount_minor: 9_800_800, confirm_outlier: false })
    expect(key).toMatch(UUID)
    // No optimistic UI: still not leading, still the previous offer.
    expect(wrapper.get('[data-testid="standing-banner"]').attributes('data-tone')).toBe('outbid')
    expect(wrapper.get('[data-testid="my-offer-card"]').text()).toContain('98,500.00')

    const accepted = { id: '01jboffer00000000000000002', seq: 58, amount_minor: 9_800_800, stage: 'live' as const, accepted_at: '2026-11-09T12:00:01.250Z', voided: false }
    resolve({ offer: accepted, live: participantSnapshot({ v: 11, is_leading: true, my_offer: accepted, my_offers_count: 5 }), replayed: false })
    await flush()
    expect(wrapper.get('[data-testid="standing-banner"]').attributes('data-tone')).toBe('leading')
    expect(wrapper.get('[data-testid="my-offer-card"]').text()).toContain('98,008.00')
    expect(useToast().toasts.value.at(-1)?.message).toBe(t('offers.submitted', { time: t('offers.machine_time', { time: '15:00:01.250' }) }))
    expect(dialogOpen(wrapper, 'offer-confirm')).toBe(false)
    wrapper.unmount()
  })

  it('shows the server’s step error inline with "Use this amount"; the next intent gets a new key', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    bidding.submitOffer.mockRejectedValueOnce(new ApiError({ status: 422, code: 'offer_step_not_met', message: '', errors: {}, details: { required_amount_minor: 9_700_000 } }))
    await typeAmount(wrapper, '98,000')
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    const composer = wrapper.get('[data-testid="offer-composer"]')
    expect(composer.text()).toContain(t('offers.error.step_not_met.tender', { amount: '⁨97,000.00 ر.س⁩' }))
    const firstKey = bidding.submitOffer.mock.calls[0]![2]

    // A real click moves focus to the button first; the field then shows the amount formatted.
    await wrapper.get('input[data-testid="offer-amount"]').trigger('blur')
    await composer.findAll('button').find(button => button.text() === t('offers.use_required'))!.trigger('click')
    await flush()
    expect((wrapper.get('input[data-testid="offer-amount"]').element as HTMLInputElement).value).toBe('97,000.00')
    bidding.submitOffer.mockResolvedValueOnce({ offer: { id: 'x', seq: 59, amount_minor: 9_700_000, stage: 'live', accepted_at: '2026-11-09T12:00:01.250Z', voided: false }, live: participantSnapshot({ v: 12 }), replayed: false })
    await wrapper.get('[data-testid="offer-composer"] form').trigger('submit')
    await flush()
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    expect(bidding.submitOffer.mock.calls[1]![2]).not.toBe(firstKey)
    wrapper.unmount()
  })

  it('asks to confirm an outlier and re-sends with confirm_outlier and a new key', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    bidding.submitOffer
      .mockRejectedValueOnce(new ApiError({ status: 422, code: 'offer_outlier_confirm_required', message: '', errors: {}, details: { change_bps: 5000, reference_amount_minor: 9_850_000 } }))
      .mockResolvedValueOnce({ offer: { id: 'y', seq: 60, amount_minor: 4_900_000, stage: 'live', accepted_at: '2026-11-09T12:00:01.250Z', voided: false }, live: participantSnapshot({ v: 13 }), replayed: false })
    await typeAmount(wrapper, '49,000')
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    expect(wrapper.get('[data-testid="outlier-confirm"]').text()).toContain('50%')
    expect(wrapper.get('[data-testid="outlier-confirm"]').text()).toContain(t('offers.confirm.outlier.lower', { pct: '50%' }))
    await wrapper.get('[data-testid="confirm-outlier"]').trigger('click')
    await flush()
    const [first, second] = bidding.submitOffer.mock.calls
    expect(second![1]).toEqual({ amount_minor: 4_900_000, confirm_outlier: true })
    expect(second![2]).toMatch(UUID)
    expect(second![2]).not.toBe(first![2])
    wrapper.unmount()
  })

  it('keeps the intent and shows the cooldown on a rate limit', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    bidding.submitOffer.mockRejectedValueOnce(new ApiError({ status: 429, code: 'too_many_requests', message: '', errors: {}, details: { retry_after_seconds: 2 } }))
    await typeAmount(wrapper, '98,000')
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    expect(wrapper.get('[data-testid="confirm-offer"]').text()).toContain(t('live.composer.cooldown', { seconds: 2 }))
    expect(wrapper.get('[data-testid="confirm-offer"]').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('refetches when the server says the competition closed', async () => {
    const { wrapper } = await mountRoom(participantCompetition())
    bidding.submitOffer.mockRejectedValueOnce(new ApiError({ status: 409, code: 'offer_closed', message: '', errors: {}, details: { closed_at: '2026-11-09T12:00:00.000Z' } }))
    const callsBefore = competitions.fetchCompetition.mock.calls.length
    await typeAmount(wrapper, '98,000')
    await wrapper.get('[data-testid="confirm-offer"]').trigger('click')
    await flush()
    await new Promise(resolve => setTimeout(resolve, 350))
    expect(competitions.fetchCompetition.mock.calls.length).toBeGreaterThan(callsBefore)
    expect(wrapper.get('[data-testid="offer-composer"]').text()).toContain(t('errors.offer_closed'))
    wrapper.unmount()
  })

  it('disables submitting while reconnecting or offline, with the reason', async () => {
    const reconnecting = await mountSuspended(LiveOfferComposer, {
      props: { competitionId: COMPETITION_ID, snapshot: participantSnapshot(), canSubmit: false, connection: 'reconnecting' },
    })
    expect(reconnecting.get('[data-testid="composer-disabled"]').text()).toBe(t('live.composer.disabled.reconnecting'))
    expect(reconnecting.get('[data-testid="submit-offer"]').attributes('disabled')).toBeDefined()
    const offline = await mountSuspended(LiveOfferComposer, {
      props: { competitionId: COMPETITION_ID, snapshot: participantSnapshot(), canSubmit: false, connection: 'offline' },
    })
    expect(offline.get('[data-testid="composer-disabled"]').text()).toBe(t('live.composer.disabled.offline'))
  })

  it('explains a missing offer permission', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['competitions.create'] }))
    const wrapper = await mountSuspended(LiveOfferComposer, {
      props: { competitionId: COMPETITION_ID, snapshot: participantSnapshot(), canSubmit: true, connection: 'connected' },
    })
    expect(wrapper.get('[data-testid="composer-disabled"]').text()).toBe(t('live.composer.no_permission'))
  })
})
