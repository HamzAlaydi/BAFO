/**
 * Bidding: live snapshots, offers, BAFO round, award and report (API.md §1.6, §2.8, §2.9, §5).
 * Visibility is decided by the server (ARCHITECTURE §7.9): render only what a snapshot carries.
 */
import type { ApiFile, AppLocale, Currency, IsoDateTime, PriceBasis, Ulid } from './common'
import type { CloseReason } from './catalog'
import type { CompetitionPhase, CompetitionStatus, Coverage, Direction, ResultOutcome } from './competitions'
import type { ExternalRef } from './integrations'

export type OfferStage = 'initial' | 'live' | 'sealed' | 'bafo'
export type OfferChannel = 'web' | 'ios' | 'android' | 'api'
export type LastChangeKind = 'offer' | 'extension' | 'status' | 'bafo' | 'award' | 'void' | 'snapshot'
export type ExtensionReason = 'auto' | 'manual' | 'admin'

export interface LastChange {
  kind: LastChangeKind
  /** Set for extensions only. */
  reason: ExtensionReason | null
}

export interface OwnOffer {
  id: Ulid
  seq: number
  amount_minor: number
  stage: OfferStage
  accepted_at: IsoDateTime
}

export interface LadderEntry {
  alias_no: number
  amount_minor: number
  is_me: boolean
}

export interface ParticipantBafoState {
  shortlisted: boolean
  cutoff_at: IsoDateTime
  submitted: boolean
  reference_amount_minor: number | null
}

/** Every snapshot carries `v`; apply only when `v` > the last applied `v` (ARCHITECTURE §9.3). */
export interface VersionedSnapshot {
  v: number
  competition_id: Ulid
  server_time: IsoDateTime
}

/** `ParticipantLiveSnapshot` (API.md §2.8). */
export interface ParticipantLiveSnapshot extends VersionedSnapshot {
  direction: Direction
  status: CompetitionStatus
  phase: CompetitionPhase | null
  bidding_opens_at: IsoDateTime | null
  effective_close_at: IsoDateTime | null
  hard_stop_at: IsoDateTime | null
  extension_count: number
  /** The server would accept an offer from this participant now. */
  accepting_offers: boolean
  start_price_minor: number | null
  min_step: { minor: number | null, bps: number | null }
  amount_granularity_minor: 1 | 100
  my_offer: OwnOffer | null
  my_offers_count: number
  is_leading: boolean | null
  rank: number | null
  ranked_count: number | null
  leading_amount_minor: number | null
  ladder: LadderEntry[] | null
  /** Tender: the next offer must be ≤ it. Auction: ≥ it. */
  required_next_amount_minor: number | null
  bafo: ParticipantBafoState | null
  result: { outcome: ResultOutcome, winning_amount_minor: number | null } | null
  last_change: LastChange
}

export interface LiveLeader {
  participant_id: Ulid
  alias_no: number
  organization: { id: Ulid, name: string }
  amount_minor: number
  accepted_at: IsoDateTime
}

export interface RankingRow {
  participant_id: Ulid
  alias_no: number
  organization: { id: Ulid, name: string, logo_url: string | null }
  /** `null` while sealed and not yet unlocked. */
  current_amount_minor: number | null
  first_amount_minor: number | null
  rank: number | null
  is_leader: boolean | null
  offers_count: number
  last_offer_at: IsoDateTime | null
  submitted: boolean
  bafo: { shortlisted: boolean, submitted: boolean }
}

export interface IssuerLiveMetrics {
  offers_count: number
  participants_joined: number
  participants_with_offers: number
  invitations_count: number
  /** Server-signed: positive is better for the issuer. `null` without a start price or leader. */
  improvement_vs_start_bps: number | null
}

export interface IssuerLiveBafo {
  status: 'running' | 'ended'
  cutoff_at: IsoDateTime
  shortlist_count: number
  submitted_count: number
}

/** `IssuerLiveSnapshot` (API.md §2.8). */
export interface IssuerLiveSnapshot extends VersionedSnapshot {
  direction: Direction
  status: CompetitionStatus
  phase: CompetitionPhase | null
  bidding_opens_at: IsoDateTime | null
  effective_close_at: IsoDateTime | null
  hard_stop_at: IsoDateTime | null
  extension_count: number
  leader: LiveLeader | null
  /** Issuer only; `null` without a reserve. */
  reserve_met: boolean | null
  ranking: RankingRow[]
  metrics: IssuerLiveMetrics
  online_participants_count: number
  bafo: IssuerLiveBafo | null
  last_change: LastChange
}

export type LiveSnapshot = IssuerLiveSnapshot | ParticipantLiveSnapshot

// ---------- Offers ----------

export interface SubmitOfferRequest {
  amount_minor: number
  confirm_outlier?: boolean
}

export interface AcceptedOffer extends OwnOffer {
  voided: boolean
}

export interface SubmitOfferResponse {
  offer: AcceptedOffer
  live: ParticipantLiveSnapshot
}

export interface SubmitOfferResult extends SubmitOfferResponse {
  /** `Idempotent-Replayed: true`: the server answered from the stored response. */
  replayed: boolean
}

/** `GET …/my-offers`, newest first. */
export interface MyOffer {
  id: Ulid
  seq: number
  amount_minor: number
  stage: OfferStage
  accepted_at: IsoDateTime
  voided: boolean
}

/** Issuer offer log entry (`GET …/offers/log` and realtime `offer.accepted`). */
export interface OfferLogEntry {
  id: Ulid
  seq: number
  participant: { id: Ulid, alias_no: number, organization: { id: Ulid, name: string } }
  /** `null` while sealed and not yet unlocked. */
  amount_minor: number | null
  stage: OfferStage
  accepted_at: IsoDateTime
  channel: OfferChannel
  voided: boolean
}

export interface OfferLogPage {
  entries: OfferLogEntry[]
  last_seq: number
  has_more: boolean
}

/** Issuer `GET …/offers`, ordered by rank. */
export interface ParticipantStandingRow {
  participant: {
    id: Ulid
    alias_no: number
    joined_at: IsoDateTime
    organization: { id: Ulid, name: string, logo_url: string | null, email: string | null, phone: string | null, cr_number: string | null }
    coverage: Coverage
  }
  current_amount_minor: number | null
  first_amount_minor: number | null
  offers_count: number
  last_offer_at: IsoDateTime | null
  rank: number | null
  is_leader: boolean | null
  /** Server-signed improvement ratio. */
  change_ratio_bps: number | null
  submitted: boolean
  bafo: { shortlisted: boolean, submitted: boolean, reference_amount_minor: number | null }
}

// ---------- BAFO round ----------

/** Issuer view (`Competition.bafo_round`). */
export interface IssuerBafoRound {
  id: Ulid
  status: 'running' | 'ended'
  starts_at: IsoDateTime
  cutoff_at: IsoDateTime
  ended_at: IsoDateTime | null
  shortlist_count: number
  submitted_count: number
}

/** Participant view (`Competition.bafo_round`). */
export interface ParticipantBafoRound {
  status: 'running' | 'ended'
  cutoff_at: IsoDateTime
  shortlisted: boolean
  submitted: boolean
}

export interface StartBafoRoundRequest {
  participant_ids: Ulid[]
  duration_minutes?: number | null
}

// ---------- Award ----------

export type ErpSyncStatus = 'not_required' | 'pending' | 'synced' | 'failed'

/** Issuer view (API.md §2.9). */
export interface Award {
  id: Ulid
  status: 'issued' | 'revoked'
  participant: {
    id: Ulid
    alias_no: number
    organization: { id: Ulid, name: string, cr_number: string | null, vat_number: string | null }
  }
  amount_minor: number
  currency: Currency
  price_basis: PriceBasis
  is_leading_offer: boolean
  rank_at_award: number | null
  reserve_met: boolean | null
  justification: { reason: CloseReason, text: string | null } | null
  message_to_winner: string | null
  internal_notes: string | null
  offer: { id: Ulid, seq: number, accepted_at: IsoDateTime }
  awarded_by: { id: Ulid, name: string } | null
  awarded_at: IsoDateTime
  revoked_at: IsoDateTime | null
  revoke_reason: string | null
  erp_sync: { status: ErpSyncStatus, message: string | null, synced_at: IsoDateTime | null, refs: ExternalRef[] }
  ledger_head_hash: string | null
  created_at: IsoDateTime
}

/** Participant view of `GET …/award`. `message_to_winner` is present for the winner only. */
export interface ParticipantAwardView {
  outcome: ResultOutcome | null
  winning_amount_minor: number | null
  message_to_winner?: string | null
}

export interface IssueAwardRequest {
  participant_id: Ulid
  justification_reason_id?: Ulid | null
  justification_text?: string | null
  confirm_reserve_not_met?: boolean
  message_to_winner?: string | null
  internal_notes?: string | null
}

// ---------- Report ----------

export interface Report {
  status: 'pending' | 'ready' | 'failed'
  locale: AppLocale
  generated_at: IsoDateTime | null
  file: ApiFile | null
}
