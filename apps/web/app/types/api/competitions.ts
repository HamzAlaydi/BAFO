/**
 * Competitions: projections by viewer role, lists, invitations, attachments, Q&A and home
 * (API.md §1.4, §1.5, §2.6, §2.7, §2.13 Home, §5 `competition.updated`).
 */
import type { IssuerBafoRound, IssuerLiveSnapshot, ParticipantBafoRound, ParticipantLiveSnapshot } from './bidding'
import type { Category, CloseReason, Region } from './catalog'
import type { ApiFile, Currency, IsoDateTime, OrganizationSummary, PageQuery, PlanRef, PriceBasis, Ulid } from './common'
import type { SubscriptionStatus } from './identity'

// ---------- Enums ----------

export type Direction = 'tender' | 'auction'
export type Format = 'live' | 'sealed'

export type CompetitionStatus
  = | 'draft'
    | 'scheduled'
    | 'live'
    | 'closed'
    | 'bafo_round'
    | 'awarded'
    | 'not_awarded'
    | 'cancelled'

/** Only set while `status = live` (ARCHITECTURE §6.1 derived phase). */
export type CompetitionPhase = 'initial' | 'open' | 'final_window' | 'sealed'

export type ViewerRole = 'issuer' | 'participant' | 'invitee'
export type CompetitionSource = 'web' | 'ios' | 'android' | 'api'
export type MustBeat = 'own' | 'best'
export type RankVisibility = 'full' | 'leading_flag' | 'none'
export type ResultPublication = 'none' | 'outcome_only' | 'outcome_and_amount'
export type AmountGranularity = 1 | 100

export type InvitationStatus = 'draft' | 'sent' | 'viewed' | 'joined' | 'declined' | 'revoked' | 'expired'
export type AccessState = 'join_required' | 'plan_required' | 'full' | 'read_only' | 'unavailable'
export type Coverage = 'sponsored' | 'own_plan' | 'none'
export type PassStatus = 'pending' | 'reserved' | 'joined' | 'released' | 'unused' | 'void'
export type AttachmentKind = 'document' | 'invitation_document' | 'external_link'
export type ResultOutcome = 'won' | 'not_selected' | 'not_awarded'

// ---------- Rules ----------

export interface AutoExtendRules {
  enabled: boolean
  window_seconds: number | null
  by_seconds: number | null
  max_extensions: number | null
}

export interface BafoRoundRules {
  enabled: boolean
  duration_minutes: number | null
}

/** `Rules` as the issuer sees them (the same keys are accepted as input). */
export interface Rules {
  start_price_minor: number | null
  /** Issuer only: omitted in every participant and invitee projection. */
  reserve_price_minor: number | null
  min_step_minor: number | null
  min_step_bps: number | null
  amount_granularity_minor: AmountGranularity
  /** `null` for sealed competitions. */
  must_beat: MustBeat | null
  rank_visibility: RankVisibility
  show_prices: boolean
  auto_extend: AutoExtendRules
  final_window_minutes: number | null
  bafo_round: BafoRoundRules
  min_participants: number
  result_publication: ResultPublication
}

/** Rules in participant and invitee projections: no reserve price. */
export type PublicRules = Omit<Rules, 'reserve_price_minor'>

/** `RulesInput`: any subset of the rules keys (absent keys take the preset or column defaults). */
export type RulesInput = Partial<Omit<Rules, 'auto_extend' | 'bafo_round'>> & {
  auto_extend?: Partial<AutoExtendRules>
  bafo_round?: Partial<BafoRoundRules>
}

// ---------- Nested values ----------

export interface CompetitionSchedule {
  bidding_opens_at: IsoDateTime | null
  scheduled_close_at: IsoDateTime | null
  effective_close_at: IsoDateTime | null
  hard_stop_at: IsoDateTime | null
  final_window_starts_at: IsoDateTime | null
  invitation_cutoff_at: IsoDateTime | null
  extension_count: number
  published_at: IsoDateTime | null
  opened_at: IsoDateTime | null
  closed_at: IsoDateTime | null
  offers_opened_at: IsoDateTime | null
  awarded_at: IsoDateTime | null
  not_awarded_at: IsoDateTime | null
  cancelled_at: IsoDateTime | null
}

/** Schedule of the invitee teaser. */
export interface TeaserSchedule {
  bidding_opens_at: IsoDateTime | null
  scheduled_close_at: IsoDateTime | null
  effective_close_at: IsoDateTime | null
  invitation_cutoff_at: IsoDateTime | null
}

export interface CompetitionCounts {
  invitations: number
  joined: number
  declined: number
  participants_with_offers: number
  offers: number
  comments: number
  attachments: number
}

export interface CompetitionSponsorshipSummary {
  mode: 'all' | 'selected'
  status: 'draft' | 'active' | 'settled'
  funded_passes: number
  free_slots: number
}

export interface AwardSummary {
  id: Ulid
  status: 'issued' | 'revoked'
  participant: { id: Ulid, alias_no: number, organization: OrganizationSummary }
  amount_minor: number
  awarded_at: IsoDateTime
}

export interface CompetitionCancellation {
  reason: CloseReason
  note: string | null
  cancelled_at: IsoDateTime
}

export interface CompetitionNotAwarded {
  reason: CloseReason
  note: string | null
  not_awarded_at: IsoDateTime
}

export interface CreatedBy {
  type: 'user' | 'api_client' | 'admin' | 'system'
  id: Ulid
  name: string
}

/** Server decisions: render controls from these flags only (CONVENTIONS §7). */
export interface CompetitionPermissions {
  can_edit: boolean
  can_delete: boolean
  can_publish: boolean
  can_invite: boolean
  can_extend: boolean
  can_cancel: boolean
  can_start_bafo: boolean
  can_award: boolean
  can_revoke_award: boolean
  can_close_without_award: boolean
  can_manage_sponsorship: boolean
  can_comment: boolean
  can_join: boolean
  can_decline: boolean
  can_submit_offer: boolean
}

export interface ParticipationAccess {
  state: AccessState
  coverage: Coverage
  sponsor_name: string | null
  join_deadline: IsoDateTime | null
}

export interface Participation {
  participant_id: Ulid
  alias_no: number
  joined_at: IsoDateTime
  terms_accepted_at: IsoDateTime
}

export interface CompetitionResult {
  outcome: ResultOutcome | null
  winning_amount_minor: number | null
}

export interface InviteeInvitationRef {
  id: Ulid
  status: InvitationStatus
  join_deadline: IsoDateTime | null
  sent_at: IsoDateTime | null
}

// ---------- Competition projections ----------

interface CompetitionBase {
  id: Ulid
  reference_no: string | null
  title: string
  direction: Direction
  format: Format
  status: CompetitionStatus
  phase: CompetitionPhase | null
  currency: Currency
  price_basis: PriceBasis
  category: Category
  region: Region
  issuer: OrganizationSummary
  rules_summary: string[]
  server_time: IsoDateTime
}

/** Issuer projection (`viewer_role = issuer`). */
export interface IssuerCompetition extends CompetitionBase {
  viewer_role: 'issuer'
  description: string | null
  category_other_text: string | null
  preset_code: string | null
  rules: Rules
  schedule: CompetitionSchedule
  counts: CompetitionCounts
  /** `null` while sealed and not yet unlocked, or when there are no offers. */
  leading_amount_minor: number | null
  sponsorship: CompetitionSponsorshipSummary | null
  bafo_round: IssuerBafoRound | null
  award: AwardSummary | null
  cancellation: CompetitionCancellation | null
  not_awarded: CompetitionNotAwarded | null
  created_by: CreatedBy
  source: CompetitionSource
  permissions: CompetitionPermissions
  /** `null` in draft and scheduled. */
  live: IssuerLiveSnapshot | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

/** Participant projection (`viewer_role = participant`). */
export interface ParticipantCompetition extends CompetitionBase {
  viewer_role: 'participant'
  description: string | null
  category_other_text: string | null
  preset_code: string | null
  rules: PublicRules
  schedule: CompetitionSchedule
  bafo_round: ParticipantBafoRound | null
  cancellation: CompetitionCancellation | null
  not_awarded: CompetitionNotAwarded | null
  permissions: CompetitionPermissions
  participation: Participation
  access: ParticipationAccess
  result: CompetitionResult
  live: ParticipantLiveSnapshot | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

/**
 * `CompetitionTeaser`. The guest `InvitationLookup` omits `invitation_documents`, `access` and
 * `permissions`; the invitee projection below adds them.
 */
export interface CompetitionTeaser extends CompetitionBase {
  viewer_role?: 'invitee'
  rules: PublicRules
  schedule: TeaserSchedule
  invitation?: InviteeInvitationRef
}

/** Invitee projection (`viewer_role = invitee`). */
export interface InviteeCompetition extends CompetitionTeaser {
  viewer_role: 'invitee'
  invitation: InviteeInvitationRef
  access: ParticipationAccess
  invitation_documents: Attachment[]
  permissions: CompetitionPermissions
}

/** `GET /competitions/{id}`: switch on `viewer_role` (SCREENS CD1). */
export type Competition = IssuerCompetition | ParticipantCompetition | InviteeCompetition

// ---------- Lists ----------

export interface IssuerCompetitionListItem {
  id: Ulid
  reference_no: string | null
  title: string
  direction: Direction
  format: Format
  status: CompetitionStatus
  phase: CompetitionPhase | null
  category: Category
  region: Region
  schedule: { bidding_opens_at: IsoDateTime | null, effective_close_at: IsoDateTime | null }
  counts: { invitations: number, joined: number, participants_with_offers: number }
  leading_amount_minor: number | null
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface ParticipantCompetitionListItem {
  id: Ulid
  reference_no: string | null
  title: string
  direction: Direction
  format: Format
  status: CompetitionStatus
  phase: CompetitionPhase | null
  category: Category
  region: Region
  issuer: OrganizationSummary
  schedule: { bidding_opens_at: IsoDateTime | null, effective_close_at: IsoDateTime | null, invitation_cutoff_at: IsoDateTime | null }
  invitation: { id: Ulid, status: InvitationStatus, join_deadline: IsoDateTime | null }
  access: ParticipationAccess
  /** `null` when there is no offer or the organization has not joined. */
  my_offer_amount_minor: number | null
  /** Follows the visibility rules (ARCHITECTURE §7.9); otherwise `null`. */
  is_leading: boolean | null
  result: { outcome: ResultOutcome | null }
  updated_at: IsoDateTime
}

export type CompetitionStatusGroup = 'active' | 'draft' | 'ended' | 'all'
export type CompetitionSort = '-updated_at' | 'effective_close_at' | '-created_at'

export interface CompetitionListQuery extends PageQuery {
  status?: CompetitionStatus[]
  status_group?: CompetitionStatusGroup
  direction?: Direction
  q?: string
  sort?: CompetitionSort
}

// ---------- Writes ----------

export interface CreateCompetitionRequest {
  title: string
  description?: string | null
  category_id: Ulid
  category_other_text?: string | null
  region_id: Ulid
  direction: Direction
  format: Format
  preset_code?: string | null
  rules?: RulesInput
  bidding_opens_at?: IsoDateTime | null
  scheduled_close_at?: IsoDateTime | null
}

/** Fields accepted depend on the status (API.md §1.4 PATCH table). */
export type UpdateCompetitionRequest = Partial<CreateCompetitionRequest>

export interface ExtendCompetitionRequest {
  new_close_at: IsoDateTime
  reason: string
}

export interface CloseReasonRequest {
  close_reason_id: Ulid
  note?: string | null
}

// ---------- Suggestions ----------

export interface Suggestion {
  organization: OrganizationSummary
  region: Region | null
  categories: Category[]
  has_active_plan: boolean
  match: { category: boolean, region: boolean }
}

export interface SuggestionQuery {
  q?: string
  category_id?: Ulid
  region_id?: Ulid
  limit?: number
}

// ---------- Invitations ----------

/** Invitation, issuer view (API.md §2.7). */
export interface Invitation {
  id: Ulid
  email: string
  name: string | null
  status: InvitationStatus
  organization: OrganizationSummary | null
  vendor: { id: Ulid, name: string } | null
  sponsored_requested: boolean
  coverage: Coverage
  pass_status: PassStatus | null
  participant: { id: Ulid, alias_no: number, joined_at: IsoDateTime } | null
  sent_at: IsoDateTime | null
  viewed_at: IsoDateTime | null
  joined_at: IsoDateTime | null
  declined_at: IsoDateTime | null
  decline_reason: string | null
  revoked_at: IsoDateTime | null
  revoke_reason: string | null
  expired_at: IsoDateTime | null
  created_at: IsoDateTime
}

/** Invitation, invitee view. */
export interface InviteeInvitation {
  id: Ulid
  status: InvitationStatus
  join_deadline: IsoDateTime | null
  sent_at: IsoDateTime | null
  competition: CompetitionTeaser
}

export type InvitationCounts = Record<InvitationStatus, number>

export interface InvitationsResult {
  invitations: Invitation[]
  counts: InvitationCounts
}

export interface InvitationInput {
  email?: string
  organization_id?: Ulid
  vendor_id?: Ulid
  name?: string | null
  sponsored?: boolean
}

/** Machine codes in `details.item_codes` of a bulk-invitation 422. */
export type InvitationItemCode = 'invitation_duplicate' | 'cannot_invite_own_organization' | 'vendor_blocked' | 'vendor_not_found'

export interface UpdateInvitationRequest {
  name?: string | null
  sponsored?: boolean
}

/** `POST /invitations/lookup` (guest). */
export interface InvitationLookup {
  invitation: {
    id: Ulid
    status: InvitationStatus
    email_masked: string
    join_deadline: IsoDateTime | null
    sponsored: boolean
  }
  competition: CompetitionTeaser
  next_step: 'register' | 'login'
}

/** `POST /invitations/claim`: 200 when bound, 202 when an OTP was sent to the invited e-mail. */
export type InvitationClaimResult
  = | { kind: 'bound', invitation: InviteeInvitation }
    | { kind: 'otp_sent', otp_sent_to: string, otp_expires_at: IsoDateTime }

// ---------- Attachments ----------

export interface Attachment {
  id: Ulid
  kind: AttachmentKind
  title: string | null
  /** `null` for external links. */
  file: ApiFile | null
  /** Set for external links only. */
  url: string | null
  is_addendum: boolean
  sort_order: number
  created_at: IsoDateTime
}

export interface UploadAttachmentRequest {
  file: Blob
  kind: Exclude<AttachmentKind, 'external_link'>
  title?: string | null
}

export interface LinkAttachmentRequest {
  kind: 'external_link'
  url: string
  title: string
}

// ---------- Q&A ----------

export type CommentAuthor
  = | { kind: 'issuer', organization_name: string }
    | { kind: 'participant', alias_no: number, organization_name?: string }
    | { kind: 'me' }

export interface Comment {
  id: Ulid
  parent_id: Ulid | null
  body: string
  author: CommentAuthor
  created_at: IsoDateTime
  replies: Comment[]
}

// ---------- Home (API.md §2.13) ----------

export type HomeAlertCode
  = | 'subscription_expiring'
    | 'subscription_expired'
    | 'plan_required'
    | 'trial_available'
    | 'billing_profile_incomplete'

export interface HomeAlert {
  code: HomeAlertCode
  params: Record<string, string | number | boolean | null>
}

export type HomeActivityAction
  = | 'competition.created'
    | 'competition.published'
    | 'competition.cancelled'
    | 'competition.closed'
    | 'award.issued'
    | 'invitation.joined'
    | 'member.added'
    | 'subscription.activated'

export interface HomeActivity {
  id: Ulid
  action: HomeActivityAction | string
  occurred_at: IsoDateTime
  actor: { name: string } | null
  subject: { type: string, id: Ulid, title?: string | null } | null
}

export interface Home {
  issuer: {
    active_competitions: number
    draft_competitions: number
    live_now: number
    awaiting_award: number
    offers_received_30d: number
  }
  participant: {
    pending_invitations: number
    active_participations: number
    offers_submitted_30d: number
    awards_won: number
  }
  team: { members: number, seats_total: number }
  subscription: {
    plan: PlanRef
    status: SubscriptionStatus
    days_left: number
    total_days: number
    ends_at: IsoDateTime
  } | null
  alerts: HomeAlert[]
  activities: HomeActivity[]
}

// ---------- Realtime ----------

/** `competition.updated` on both competition channels. */
export interface CompetitionUpdatedEvent {
  competition_id: Ulid
  fields: Array<'title' | 'description' | 'schedule' | 'attachments' | 'invitations' | string>
  server_time: IsoDateTime
}
