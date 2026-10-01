import { describe, expect, it } from 'vitest'
import type { MyOffer, ParticipantLiveSnapshot } from '~/types/api/bidding'
import type { ParticipantCompetitionListItem } from '~/types/api/competitions'
import {
  detectExtension,
  detectTransition,
  isParticipantSnapshot,
  liveCountdownOf,
  needsAction,
  offerBoundsOf,
  offerChangeBps,
  offerErrorAction,
  offerModeOf,
  participatingCountdownOf,
  precheckOffer,
  primaryOfferBound,
  sortParticipating,
  standingOf,
  withinOfferBound,
  withOfferChanges,
} from '~/stores/liveRules'

function snapshot(overrides: Partial<ParticipantLiveSnapshot> = {}): ParticipantLiveSnapshot {
  return {
    v: 10,
    competition_id: '01j9comp000000000000000000',
    direction: 'tender',
    status: 'live',
    phase: 'open',
    server_time: '2026-11-09T11:59:58.120Z',
    bidding_opens_at: '2026-11-02T06:00:00.000Z',
    effective_close_at: '2026-11-09T12:03:00.000Z',
    hard_stop_at: null,
    extension_count: 0,
    accepting_offers: true,
    start_price_minor: 25_000_000,
    min_step: { minor: null, bps: 50 },
    amount_granularity_minor: 100,
    my_offer: { id: 'o1', seq: 57, amount_minor: 9_850_000, stage: 'live', accepted_at: '2026-11-09T11:59:58.412Z' },
    my_offers_count: 4,
    is_leading: false,
    rank: null,
    ranked_count: null,
    leading_amount_minor: null,
    ladder: null,
    required_next_amount_minor: 9_800_800,
    bafo: null,
    result: null,
    last_change: { kind: 'offer', reason: null },
    ...overrides,
  }
}

describe('offer bounds (API.md §2.8, CD13)', () => {
  it('uses the direction of the bound: tender ≤, auction ≥ (equal allowed)', () => {
    expect(withinOfferBound('tender', 100, 100)).toBe(true)
    expect(withinOfferBound('tender', 101, 100)).toBe(false)
    expect(withinOfferBound('auction', 100, 100)).toBe(true)
    expect(withinOfferBound('auction', 99, 100)).toBe(false)
  })

  it('reads the stage from the snapshot', () => {
    expect(offerModeOf({ status: 'live', phase: 'open' })).toBe('live')
    expect(offerModeOf({ status: 'live', phase: 'final_window' })).toBe('live')
    expect(offerModeOf({ status: 'live', phase: 'initial' })).toBe('initial')
    expect(offerModeOf({ status: 'live', phase: 'sealed' })).toBe('sealed')
    expect(offerModeOf({ status: 'bafo_round', phase: null })).toBe('bafo')
  })

  it('puts the server’s required next amount first, then the start price', () => {
    expect(offerBoundsOf(snapshot())).toEqual([
      { kind: 'required_next', amountMinor: 9_800_800 },
      { kind: 'start_price', amountMinor: 25_000_000 },
    ])
    expect(primaryOfferBound(snapshot({ my_offer: null, required_next_amount_minor: null }))).toEqual({ kind: 'start_price', amountMinor: 25_000_000 })
  })

  it('ignores the required next amount while sealed and uses the BAFO reference in a BAFO round', () => {
    expect(offerBoundsOf(snapshot({ phase: 'sealed', required_next_amount_minor: 1 })).map(bound => bound.kind)).toEqual(['start_price'])
    const bafo = snapshot({ status: 'bafo_round', phase: null, required_next_amount_minor: null, bafo: { shortlisted: true, cutoff_at: '2026-11-10T12:00:00.000Z', submitted: false, reference_amount_minor: 9_850_000 } })
    expect(offerBoundsOf(bafo)).toEqual([
      { kind: 'bafo_reference', amountMinor: 9_850_000 },
      { kind: 'start_price', amountMinor: 25_000_000 },
    ])
  })

  it('pre-checks empty, non-positive and granularity before the bounds', () => {
    expect(precheckOffer(null, snapshot())).toEqual({ ok: false, reason: 'empty' })
    expect(precheckOffer(0, snapshot())).toEqual({ ok: false, reason: 'not_positive' })
    expect(precheckOffer(9_700_050, snapshot())).toEqual({ ok: false, reason: 'granularity', granularityMinor: 100 })
    expect(precheckOffer(9_700_050, snapshot({ amount_granularity_minor: 1 }))).toEqual({ ok: true })
  })

  it('rejects a tender offer above the required next amount, and accepts it at the bound', () => {
    expect(precheckOffer(9_800_900, snapshot())).toEqual({ ok: false, reason: 'required_next', boundMinor: 9_800_800 })
    expect(precheckOffer(9_800_800, snapshot())).toEqual({ ok: true })
  })

  it('rejects an auction offer below the opening price on the first offer', () => {
    const auction = snapshot({ direction: 'auction', my_offer: null, required_next_amount_minor: null, start_price_minor: 1_000_000 })
    expect(precheckOffer(900_000, auction)).toEqual({ ok: false, reason: 'start_price', boundMinor: 1_000_000 })
    expect(precheckOffer(1_000_000, auction)).toEqual({ ok: true })
  })

  it('lets a sealed revision move either way within the start price', () => {
    const sealed = snapshot({ phase: 'sealed', required_next_amount_minor: null })
    expect(precheckOffer(12_000_000, sealed)).toEqual({ ok: true })
    expect(precheckOffer(26_000_000, sealed)).toEqual({ ok: false, reason: 'start_price', boundMinor: 25_000_000 })
  })

  it('never lets a BAFO offer be worse than the reference', () => {
    const bafo = snapshot({ status: 'bafo_round', phase: null, required_next_amount_minor: null, bafo: { shortlisted: true, cutoff_at: '2026-11-10T12:00:00.000Z', submitted: false, reference_amount_minor: 9_850_000 } })
    expect(precheckOffer(9_900_000, bafo)).toEqual({ ok: false, reason: 'bafo_reference', boundMinor: 9_850_000 })
    expect(precheckOffer(9_850_000, bafo)).toEqual({ ok: true })
  })
})

describe('standing (SCREENS S2, ARCHITECTURE §7.9)', () => {
  it('shows only what the projection carries', () => {
    expect(standingOf(snapshot({ is_leading: true, rank: 1, ranked_count: 5 }))).toEqual({ kind: 'leading', rank: 1, rankedCount: 5 })
    expect(standingOf(snapshot({ is_leading: false }))).toEqual({ kind: 'not_leading', rank: null, rankedCount: null })
    expect(standingOf(snapshot({ is_leading: null, rank: 3, ranked_count: 4 }))).toEqual({ kind: 'ranked', rank: 3, rankedCount: 4 })
    expect(standingOf(snapshot({ is_leading: null }))).toEqual({ kind: 'hidden' })
  })

  it('has dedicated states for no offer, the initial phase, sealed and BAFO', () => {
    expect(standingOf(snapshot({ my_offer: null, is_leading: null }))).toEqual({ kind: 'no_offer' })
    expect(standingOf(snapshot({ phase: 'initial', is_leading: null }))).toEqual({ kind: 'own_only' })
    expect(standingOf(snapshot({ phase: 'sealed', is_leading: null }))).toEqual({ kind: 'sealed' })
    expect(standingOf(snapshot({ status: 'bafo_round', phase: null }))).toEqual({ kind: 'bafo' })
  })

  it('tells participant snapshots from issuer ones', () => {
    expect(isParticipantSnapshot(snapshot())).toBe(true)
    expect(isParticipantSnapshot(null)).toBe(false)
    expect(isParticipantSnapshot({ v: 1, competition_id: 'x', server_time: 'y', ranking: [] } as never)).toBe(false)
  })
})

describe('countdowns and announcements (SCREENS S3, S9)', () => {
  it('targets opens, effective close or the BAFO cutoff', () => {
    expect(liveCountdownOf(snapshot({ status: 'scheduled' }))).toEqual({ kind: 'opens', target: '2026-11-02T06:00:00.000Z' })
    expect(liveCountdownOf(snapshot())).toEqual({ kind: 'closes', target: '2026-11-09T12:03:00.000Z' })
    expect(liveCountdownOf(snapshot({ status: 'bafo_round', bafo: { shortlisted: false, cutoff_at: '2026-11-10T12:00:00.000Z', submitted: false, reference_amount_minor: null } })))
      .toEqual({ kind: 'bafo_closes', target: '2026-11-10T12:00:00.000Z' })
    expect(liveCountdownOf(snapshot({ status: 'closed' }))).toBeNull()
  })

  it('detects an anti-sniping extension with the minutes added', () => {
    const before = snapshot()
    const after = snapshot({ v: 11, extension_count: 1, effective_close_at: '2026-11-09T12:06:00.000Z', last_change: { kind: 'extension', reason: 'auto' } })
    expect(detectExtension(before, after)).toEqual({ minutes: 3, reason: 'auto', closeAt: '2026-11-09T12:06:00.000Z' })
    expect(detectExtension(after, snapshot({ v: 12, extension_count: 1 }))).toBeNull()
    expect(detectExtension(null, after)).toBeNull()
  })

  it('detects lifecycle transitions', () => {
    expect(detectTransition({ status: 'scheduled', phase: null }, { status: 'live', phase: 'open' })).toBe('opened')
    expect(detectTransition({ status: 'live', phase: 'initial' }, { status: 'live', phase: 'final_window' })).toBe('final_window')
    expect(detectTransition({ status: 'live', phase: 'open' }, { status: 'closed', phase: null })).toBe('closed')
    expect(detectTransition({ status: 'closed', phase: null }, { status: 'bafo_round', phase: null })).toBe('bafo_started')
    expect(detectTransition({ status: 'live', phase: 'open' }, { status: 'live', phase: 'open' })).toBeNull()
  })
})

describe('offer errors (SCREENS S5 responses)', () => {
  const error = (code: string, details: Record<string, unknown> = {}, retryAfterSeconds: number | null = null) => ({ code, details, retryAfterSeconds })

  it('maps amount problems to inline errors with the server amounts', () => {
    expect(offerErrorAction(error('offer_step_not_met', { required_amount_minor: 9_750_000 }))).toEqual({ type: 'step_not_met', requiredMinor: 9_750_000 })
    expect(offerErrorAction(error('offer_start_price', { start_price_minor: 25_000_000 }))).toEqual({ type: 'start_price', startPriceMinor: 25_000_000 })
    expect(offerErrorAction(error('offer_granularity', { granularity_minor: 100 }))).toEqual({ type: 'granularity', granularityMinor: 100 })
    expect(offerErrorAction(error('offer_amount_too_large', { max_amount_minor: 10 }))).toEqual({ type: 'amount_too_large', maxMinor: 10 })
    expect(offerErrorAction(error('offer_bafo_worse_than_reference', { reference_amount_minor: 5 }))).toEqual({ type: 'bafo_worse', referenceMinor: 5 })
    expect(offerErrorAction(error('offer_amount_invalid'))).toEqual({ type: 'amount_invalid' })
  })

  it('maps state problems to banners and refetches', () => {
    expect(offerErrorAction(error('offer_not_accepting', { status: 'live', opens_at: '2026-11-02T06:00:00.000Z' }))).toEqual({ type: 'not_open', opensAt: '2026-11-02T06:00:00.000Z' })
    expect(offerErrorAction(error('offer_closed', { closed_at: 'x' }))).toEqual({ type: 'closed' })
    expect(offerErrorAction(error('offer_not_shortlisted'))).toEqual({ type: 'bafo_state' })
    expect(offerErrorAction(error('offer_bafo_already_submitted'))).toEqual({ type: 'bafo_state' })
    expect(offerErrorAction(error('not_a_participant'))).toEqual({ type: 'not_participant' })
  })

  it('maps the rate limit to a cooldown and a reused key to a new confirmation', () => {
    expect(offerErrorAction(error('too_many_requests', { retry_after_seconds: 2 }))).toEqual({ type: 'cooldown', seconds: 2 })
    expect(offerErrorAction(error('too_many_requests', {}, 5))).toEqual({ type: 'cooldown', seconds: 5 })
    expect(offerErrorAction(error('idempotency_key_reused'))).toEqual({ type: 'reconfirm' })
    expect(offerErrorAction(error('forbidden'))).toEqual({ type: 'generic' })
  })
})

describe('formatting', () => {
  it('computes and formats the change between own offers', () => {
    expect(offerChangeBps(10_000, 9_950)).toBe(-50)
    expect(offerChangeBps(10_000, 16_080)).toBe(6080)
    expect(offerChangeBps(0, 1)).toBeNull()
  })

  it('adds the change against the previous non-voided own offer, newest first', () => {
    const offers: MyOffer[] = [
      { id: 'a', seq: 1, amount_minor: 10_000, stage: 'live', accepted_at: '2026-11-09T10:00:00.000Z', voided: false },
      { id: 'c', seq: 5, amount_minor: 9_000, stage: 'live', accepted_at: '2026-11-09T10:02:00.000Z', voided: false },
      { id: 'b', seq: 3, amount_minor: 9_500, stage: 'live', accepted_at: '2026-11-09T10:01:00.000Z', voided: true },
    ]
    expect(withOfferChanges(offers).map(row => [row.id, row.changeBps])).toEqual([['c', -1000], ['b', null], ['a', null]])
  })
})

describe('participating list (W23)', () => {
  function item(id: string, state: ParticipantCompetitionListItem['access']['state'], overrides: Partial<ParticipantCompetitionListItem> = {}): ParticipantCompetitionListItem {
    return {
      id,
      reference_no: null,
      title: id,
      direction: 'tender',
      format: 'live',
      status: 'live',
      phase: 'open',
      category: { id: 'c', code: 'c', name: 'c', is_other: false, auction_allowed: true },
      region: { id: 'r', code: 'RIY', name: 'r' },
      issuer: { id: 'i', name: 'i', logo_url: null, verified: false },
      schedule: { bidding_opens_at: '2026-11-02T06:00:00.000Z', effective_close_at: '2026-11-09T12:00:00.000Z', invitation_cutoff_at: '2026-11-09T11:00:00.000Z' },
      invitation: { id: `inv-${id}`, status: 'sent', join_deadline: '2026-11-09T11:00:00.000Z' },
      access: { state, coverage: 'none', sponsor_name: null, join_deadline: '2026-11-09T11:00:00.000Z' },
      my_offer_amount_minor: null,
      is_leading: null,
      result: { outcome: null },
      updated_at: '2026-11-01T00:00:00.000Z',
      ...overrides,
    } as ParticipantCompetitionListItem
  }

  it('puts cards needing action first and keeps the server order otherwise', () => {
    const items = [item('a', 'full'), item('b', 'plan_required'), item('c', 'unavailable'), item('d', 'join_required')]
    expect(sortParticipating(items).map(entry => entry.id)).toEqual(['b', 'd', 'a', 'c'])
    expect(needsAction(item('x', 'read_only'))).toBe(false)
  })

  it('counts down to the join deadline while pending and to the close while live', () => {
    expect(participatingCountdownOf(item('a', 'join_required'))).toEqual({ kind: 'join', target: '2026-11-09T11:00:00.000Z' })
    expect(participatingCountdownOf(item('b', 'full'))).toEqual({ kind: 'closes', target: '2026-11-09T12:00:00.000Z' })
    expect(participatingCountdownOf(item('c', 'full', { status: 'scheduled' }))).toEqual({ kind: 'opens', target: '2026-11-02T06:00:00.000Z' })
    expect(participatingCountdownOf(item('d', 'read_only', { status: 'awarded' }))).toBeNull()
  })
})
