import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { Ref } from 'vue'
import { makeTokenPayload } from '../fixtures/api'
import { COMPETITION_ID, makeIssuerCompetition, makeLiveSnapshot, makeOfferEntry } from '../fixtures/issuer'
import type { LiveSnapshot, OfferLogEntry } from '~/types/api/bidding'
import type { IssuerCompetition } from '~/types/api/competitions'
import LiveConsole from '~/components/competitions/issuer/LiveConsole.vue'

/**
 * The issuer console's "Latest offers" feed (W19, SCREENS S4 gap filling). Found by the live e2e
 * tender: the first offer's `offer.accepted` did not reach the console, and because the feed only
 * looked for gaps once it held an entry, that offer never appeared although the metrics counted it.
 */
const bidding = vi.hoisted(() => ({ fetchOfferLog: vi.fn() }))
vi.mock('~/services/bidding', () => bidding)

const ctx = vi.hoisted(() => ({
  competition: null as Ref<IssuerCompetition | null> | null,
  live: null as Ref<LiveSnapshot | null> | null,
  listeners: new Map<string, Array<(payload: unknown) => void>>(),
}))

mockNuxtImport('useCompetitionContext', () => () => ({
  id: computed(() => COMPETITION_ID),
  competition: ctx.competition!,
  viewerRole: computed(() => 'issuer'),
  loading: ref(false),
  error: ref(null),
  live: computed(() => ctx.live!.value),
  connection: computed(() => 'connected'),
  canSubmit: computed(() => true),
  refetch: vi.fn(),
  resync: vi.fn(),
  applyLive: () => true,
  on: (event: string, listener: (payload: unknown) => void) => {
    ctx.listeners.set(event, [...(ctx.listeners.get(event) ?? []), listener])
    return () => {}
  },
}))

function emitOffer(entry: OfferLogEntry): void {
  for (const listener of ctx.listeners.get('offerAccepted') ?? []) listener(entry)
}

async function flush(): Promise<void> {
  for (let i = 0; i < 8; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

const empty = { entries: [], last_seq: 0, has_more: false }

beforeEach(() => {
  bidding.fetchOfferLog.mockReset()
  ctx.listeners = new Map()
  ctx.competition = ref(makeIssuerCompetition({ status: 'live', phase: 'open' })) as Ref<IssuerCompetition | null>
  ctx.live = ref(makeLiveSnapshot(3, { metrics: { offers_count: 0, participants_joined: 2, participants_with_offers: 0, invitations_count: 2, improvement_vs_start_bps: null } })) as Ref<LiveSnapshot | null>
  useAuthStore().setSession(makeTokenPayload())
})

describe('W19 issuer console offer feed', () => {
  it('fetches missed offers when the first event it sees skips a seq, even with an empty feed', async () => {
    bidding.fetchOfferLog.mockResolvedValueOnce(empty)
    const wrapper = await mountSuspended(LiveConsole)
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenCalledWith(COMPETITION_ID, 0, 200)

    // Offer 1's event was missed; offer 2's arrives.
    bidding.fetchOfferLog.mockResolvedValueOnce({ entries: [makeOfferEntry(1), makeOfferEntry(2)], last_seq: 2, has_more: false })
    emitOffer(makeOfferEntry(2))
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenLastCalledWith(COMPETITION_ID, 0, 200)
    expect(bidding.fetchOfferLog).toHaveBeenCalledTimes(2)
    const text = wrapper.text()
    for (const seq of [1, 2]) expect(text).toContain(formatMachineTime(makeOfferEntry(seq).accepted_at) ?? '')
  })

  it('does not refetch when the events arrive in order', async () => {
    bidding.fetchOfferLog.mockResolvedValueOnce(empty)
    await mountSuspended(LiveConsole)
    await flush()
    emitOffer(makeOfferEntry(1))
    emitOffer(makeOfferEntry(2))
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenCalledTimes(1)
  })

  it('runs a gap fill requested while another fill is still loading', async () => {
    let release!: (value: unknown) => void
    bidding.fetchOfferLog.mockReturnValueOnce(new Promise((resolve) => {
      release = resolve
    }))
    await mountSuspended(LiveConsole)
    await flush()
    // The feed already holds offer 1 (from a realtime event) when offer 3 arrives: 2 was missed.
    emitOffer(makeOfferEntry(1))
    bidding.fetchOfferLog.mockResolvedValueOnce({ entries: [makeOfferEntry(2), makeOfferEntry(3)], last_seq: 3, has_more: false })
    emitOffer(makeOfferEntry(3))
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenCalledTimes(1)
    release(empty)
    await flush()
    expect(bidding.fetchOfferLog).toHaveBeenCalledTimes(2)
    expect(bidding.fetchOfferLog).toHaveBeenLastCalledWith(COMPETITION_ID, 1, 200)
  })
})
