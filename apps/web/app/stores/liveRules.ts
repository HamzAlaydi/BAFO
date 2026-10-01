/**
 * Participant-side live rules (SCREENS S2, S3, S5, CD10, CD13) as pure functions of what the server
 * sent. They never derive rank, leader, visibility, entitlement or a bound: they only choose which
 * server value to show, pre-check a typed amount against the bounds the latest snapshot carries,
 * and map documented error codes to a UI action. The server always decides.
 *
 * Auto-imported (Pinia `stores/` directory). Type imports only, so the unit project can load it.
 */
import type { ExtensionReason, LiveSnapshot, MyOffer, ParticipantLiveSnapshot } from '~/types/api/bidding'
import type { CompetitionStatus, Direction, ParticipantCompetitionListItem } from '~/types/api/competitions'

// ---------- Snapshots ----------

/** The context holds issuer or participant snapshots; only participant ones carry `accepting_offers`. */
export function isParticipantSnapshot(snapshot: LiveSnapshot | null | undefined): snapshot is ParticipantLiveSnapshot {
  return snapshot !== null && snapshot !== undefined && 'accepting_offers' in snapshot
}

/**
 * True when `amount` is on the allowed side of `bound` (API.md §2.8 bound rule): tender → the offer
 * must be ≤ the bound; auction → ≥ the bound. Equal amounts are allowed.
 */
export function withinOfferBound(direction: Direction, amountMinor: number, boundMinor: number): boolean {
  return direction === 'tender' ? amountMinor <= boundMinor : amountMinor >= boundMinor
}

/** Which offer rules apply now (ARCHITECTURE §7.3 stages), read from the snapshot. */
export type OfferMode = 'live' | 'initial' | 'sealed' | 'bafo'

export function offerModeOf(snapshot: Pick<ParticipantLiveSnapshot, 'status' | 'phase'>): OfferMode {
  if (snapshot.status === 'bafo_round') return 'bafo'
  if (snapshot.phase === 'sealed') return 'sealed'
  if (snapshot.phase === 'initial') return 'initial'
  return 'live'
}

// ---------- Offer bounds and pre-checks (CD13) ----------

export type OfferBoundKind = 'required_next' | 'bafo_reference' | 'start_price'

export interface OfferBound {
  kind: OfferBoundKind
  amountMinor: number
}

/**
 * The bounds the snapshot carries for the next offer, the stage bound first (it is the tightest
 * one whenever it exists), then the start price, which applies at every stage (§7.4 step 13).
 */
export function offerBoundsOf(snapshot: ParticipantLiveSnapshot): OfferBound[] {
  const bounds: OfferBound[] = []
  const mode = offerModeOf(snapshot)
  if (mode === 'bafo') {
    const reference = snapshot.bafo?.reference_amount_minor
    if (typeof reference === 'number') bounds.push({ kind: 'bafo_reference', amountMinor: reference })
  }
  else if (mode !== 'sealed' && snapshot.required_next_amount_minor !== null) {
    bounds.push({ kind: 'required_next', amountMinor: snapshot.required_next_amount_minor })
  }
  if (snapshot.start_price_minor !== null) bounds.push({ kind: 'start_price', amountMinor: snapshot.start_price_minor })
  return bounds
}

/** The hint shown above the composer: the tightest bound, if any ("min next valid amount"). */
export function primaryOfferBound(snapshot: ParticipantLiveSnapshot): OfferBound | null {
  return offerBoundsOf(snapshot)[0] ?? null
}

export type OfferPrecheck
  = | { ok: true }
    | { ok: false, reason: 'empty' | 'not_positive' }
    | { ok: false, reason: 'granularity', granularityMinor: number }
    | { ok: false, reason: OfferBoundKind, boundMinor: number }

/**
 * Inline pre-check before sending (CD13): a positive integer, a multiple of the granularity, and on
 * the right side of the bounds of the latest snapshot. Feedback only: a server rejection wins.
 */
export function precheckOffer(amountMinor: number | null, snapshot: ParticipantLiveSnapshot): OfferPrecheck {
  if (amountMinor === null) return { ok: false, reason: 'empty' }
  if (!Number.isSafeInteger(amountMinor) || amountMinor <= 0) return { ok: false, reason: 'not_positive' }
  const granularity = snapshot.amount_granularity_minor > 0 ? snapshot.amount_granularity_minor : 1
  if (amountMinor % granularity !== 0) return { ok: false, reason: 'granularity', granularityMinor: granularity }
  for (const bound of offerBoundsOf(snapshot)) {
    if (!withinOfferBound(snapshot.direction, amountMinor, bound.amountMinor)) {
      return { ok: false, reason: bound.kind, boundMinor: bound.amountMinor }
    }
  }
  return { ok: true }
}

// ---------- Standing (S2) ----------

export type Standing
  = | { kind: 'sealed' }
    | { kind: 'bafo' }
    | { kind: 'no_offer' }
    | { kind: 'own_only' }
    | { kind: 'leading', rank: number | null, rankedCount: number | null }
    | { kind: 'not_leading', rank: number | null, rankedCount: number | null }
    | { kind: 'ranked', rank: number, rankedCount: number | null }
    | { kind: 'hidden' }

/**
 * What the standing banner shows, straight from the projected fields (ARCHITECTURE §7.9): the
 * client never computes a rank or a leader. `own_only` is the initial phase, where only the
 * participant's own offer is visible; `hidden` is a competition that does not show standings.
 */
export function standingOf(snapshot: ParticipantLiveSnapshot): Standing {
  const mode = offerModeOf(snapshot)
  if (snapshot.status === 'live' && mode === 'sealed') return { kind: 'sealed' }
  if (mode === 'bafo') return { kind: 'bafo' }
  if (!snapshot.my_offer) return { kind: 'no_offer' }
  if (snapshot.status === 'live' && mode === 'initial') return { kind: 'own_only' }
  if (snapshot.is_leading === true) return { kind: 'leading', rank: snapshot.rank, rankedCount: snapshot.ranked_count }
  if (snapshot.is_leading === false) return { kind: 'not_leading', rank: snapshot.rank, rankedCount: snapshot.ranked_count }
  if (snapshot.rank !== null) return { kind: 'ranked', rank: snapshot.rank, rankedCount: snapshot.ranked_count }
  return { kind: 'hidden' }
}

// ---------- Countdown targets (S3) ----------

export type LiveCountdownKind = 'opens' | 'closes' | 'bafo_closes'

export interface LiveCountdown {
  kind: LiveCountdownKind
  /** UTC ISO deadline, counted down on the server clock. */
  target: string
}

/** S3 targets: scheduled → opens; live → effective close; BAFO round → the round cutoff. */
export function liveCountdownOf(snapshot: Pick<ParticipantLiveSnapshot, 'status' | 'bidding_opens_at' | 'effective_close_at' | 'bafo'>): LiveCountdown | null {
  switch (snapshot.status) {
    case 'scheduled':
      return snapshot.bidding_opens_at ? { kind: 'opens', target: snapshot.bidding_opens_at } : null
    case 'live':
      return snapshot.effective_close_at ? { kind: 'closes', target: snapshot.effective_close_at } : null
    case 'bafo_round':
      return snapshot.bafo?.cutoff_at ? { kind: 'bafo_closes', target: snapshot.bafo.cutoff_at } : null
    default:
      return null
  }
}

/** Statuses in which the live room still runs a clock and accepts heartbeats. */
export function isRunningStatus(status: CompetitionStatus | null | undefined): boolean {
  return status === 'scheduled' || status === 'live' || status === 'bafo_round'
}

export interface ExtensionNotice {
  /** Whole minutes added to the close (at least 1). */
  minutes: number
  reason: ExtensionReason | null
  closeAt: string
}

/** An extension between two applied snapshots (anti-sniping or manual), for the announcement. */
export function detectExtension(
  previous: Pick<ParticipantLiveSnapshot, 'extension_count' | 'effective_close_at'> | null,
  next: Pick<ParticipantLiveSnapshot, 'extension_count' | 'effective_close_at' | 'last_change'>,
): ExtensionNotice | null {
  if (!previous || next.extension_count <= previous.extension_count || !next.effective_close_at) return null
  const after = Date.parse(next.effective_close_at)
  if (Number.isNaN(after)) return null
  const before = previous.effective_close_at ? Date.parse(previous.effective_close_at) : Number.NaN
  const minutes = Number.isNaN(before) ? 1 : Math.max(1, Math.round((after - before) / 60_000))
  const reason = next.last_change.kind === 'extension' ? next.last_change.reason : null
  return { minutes, reason, closeAt: next.effective_close_at }
}

export type LiveTransition = 'opened' | 'final_window' | 'bafo_started' | 'closed' | 'awarded' | 'not_awarded' | 'cancelled'

/** A lifecycle change between two applied snapshots, for the room's announcements (S9). */
export function detectTransition(
  previous: Pick<ParticipantLiveSnapshot, 'status' | 'phase'> | null,
  next: Pick<ParticipantLiveSnapshot, 'status' | 'phase'>,
): LiveTransition | null {
  if (!previous) return null
  if (previous.status !== next.status) {
    switch (next.status) {
      case 'live': return previous.status === 'scheduled' ? 'opened' : null
      case 'bafo_round': return 'bafo_started'
      case 'closed': return 'closed'
      case 'awarded': return 'awarded'
      case 'not_awarded': return 'not_awarded'
      case 'cancelled': return 'cancelled'
      default: return null
    }
  }
  if (previous.phase === 'initial' && next.phase === 'final_window') return 'final_window'
  return null
}

// ---------- Offer errors (S5 responses table) ----------

export type OfferErrorAction
  = | { type: 'step_not_met', requiredMinor: number | null }
    | { type: 'start_price', startPriceMinor: number | null }
    | { type: 'granularity', granularityMinor: number | null }
    | { type: 'amount_invalid' }
    | { type: 'amount_too_large', maxMinor: number | null }
    | { type: 'bafo_worse', referenceMinor: number | null }
    | { type: 'not_open', opensAt: string | null }
    | { type: 'closed' }
    | { type: 'bafo_state' }
    | { type: 'not_participant' }
    | { type: 'cooldown', seconds: number }
    | { type: 'reconfirm' }
    | { type: 'generic' }

interface OfferErrorLike {
  code: string
  details: Record<string, unknown>
  retryAfterSeconds?: number | null
}

function detailNumber(details: Record<string, unknown>, key: string): number | null {
  const value = details[key]
  return typeof value === 'number' && Number.isFinite(value) ? value : null
}

function detailString(details: Record<string, unknown>, key: string): string | null {
  const value = details[key]
  return typeof value === 'string' && value !== '' ? value : null
}

/** Maps a rejected offer to what the composer does (inline error, banner, refetch, cooldown). */
export function offerErrorAction(error: OfferErrorLike): OfferErrorAction {
  const details = error.details ?? {}
  switch (error.code) {
    case 'offer_step_not_met': return { type: 'step_not_met', requiredMinor: detailNumber(details, 'required_amount_minor') }
    case 'offer_start_price': return { type: 'start_price', startPriceMinor: detailNumber(details, 'start_price_minor') }
    case 'offer_granularity': return { type: 'granularity', granularityMinor: detailNumber(details, 'granularity_minor') }
    case 'offer_amount_invalid': return { type: 'amount_invalid' }
    case 'offer_amount_too_large': return { type: 'amount_too_large', maxMinor: detailNumber(details, 'max_amount_minor') }
    case 'offer_bafo_worse_than_reference': return { type: 'bafo_worse', referenceMinor: detailNumber(details, 'reference_amount_minor') }
    case 'offer_not_accepting': return { type: 'not_open', opensAt: detailString(details, 'opens_at') }
    case 'offer_closed': return { type: 'closed' }
    case 'offer_not_shortlisted':
    case 'offer_bafo_already_submitted': return { type: 'bafo_state' }
    case 'not_a_participant': return { type: 'not_participant' }
    case 'too_many_requests': return { type: 'cooldown', seconds: Math.max(1, error.retryAfterSeconds ?? detailNumber(details, 'retry_after_seconds') ?? 2) }
    case 'idempotency_key_reused': return { type: 'reconfirm' }
    default: return { type: 'generic' }
  }
}

// ---------- Formatting helpers ----------

/** Signed change between two own offers in basis points (history only; never a ranking). */
export function offerChangeBps(previousMinor: number, currentMinor: number): number | null {
  if (!Number.isFinite(previousMinor) || previousMinor <= 0) return null
  return Math.round(((currentMinor - previousMinor) * 10_000) / previousMinor)
}

export interface MyOfferRow extends MyOffer {
  /** Change against the previous non-voided own offer; null for the first one and for voided rows. */
  changeBps: number | null
}

/** Adds the change against the previous own offer to a newest-first history (W21). */
export function withOfferChanges(offers: readonly MyOffer[]): MyOfferRow[] {
  const sorted = [...offers].sort((a, b) => b.seq - a.seq)
  return sorted.map((offer, index) => {
    if (offer.voided) return { ...offer, changeBps: null }
    const previous = sorted.slice(index + 1).find(candidate => !candidate.voided)
    return { ...offer, changeBps: previous ? offerChangeBps(previous.amount_minor, offer.amount_minor) : null }
  })
}

// ---------- Participating list (W23) ----------

/** Cards that wait for the organisation's decision (join or get a plan). */
export function needsAction(item: Pick<ParticipantCompetitionListItem, 'access'>): boolean {
  return item.access.state === 'join_required' || item.access.state === 'plan_required'
}

/** Cards needing action first within the loaded page (SCREENS §6 G1); the server order otherwise. */
export function sortParticipating<T extends Pick<ParticipantCompetitionListItem, 'access'>>(items: readonly T[]): T[] {
  return [...items.filter(needsAction), ...items.filter(item => !needsAction(item))]
}

export type ParticipatingCountdownKind = 'join' | 'opens' | 'closes'

/** The card's countdown: the join deadline while the invitation waits; the close while live. */
export function participatingCountdownOf(item: Pick<ParticipantCompetitionListItem, 'access' | 'invitation' | 'status' | 'schedule'>): { kind: ParticipatingCountdownKind, target: string } | null {
  if (needsAction(item)) {
    const deadline = item.access.join_deadline ?? item.invitation.join_deadline
    return deadline ? { kind: 'join', target: deadline } : null
  }
  if (item.access.state !== 'full') return null
  if (item.status === 'live' && item.schedule.effective_close_at) return { kind: 'closes', target: item.schedule.effective_close_at }
  if (item.status === 'scheduled' && item.schedule.bidding_opens_at) return { kind: 'opens', target: item.schedule.bidding_opens_at }
  return null
}
