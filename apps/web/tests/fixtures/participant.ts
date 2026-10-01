/**
 * Participant-side fixtures shaped exactly like API.md §2.6–§2.8 (participant and invitee
 * projections, `ParticipantLiveSnapshot`, `CompetitionListItem` participant variant, `MyOffer`,
 * `Comment`). Override any field: `participantSnapshot({ is_leading: true })`.
 */
import type { MyOffer, ParticipantLiveSnapshot } from '../../app/types/api/bidding'
import type {
  Comment,
  InviteeCompetition,
  ParticipantCompetition,
  ParticipantCompetitionListItem,
} from '../../app/types/api/competitions'

export const COMPETITION_ID = '01j9comp000000000000000000'
export const INVITATION_ID = '01j9inv0000000000000000000'

const inHours = (hours: number) => new Date(Date.now() + hours * 3_600_000).toISOString()

export function participantSnapshot(overrides: Partial<ParticipantLiveSnapshot> = {}): ParticipantLiveSnapshot {
  return {
    v: 10,
    competition_id: COMPETITION_ID,
    direction: 'tender',
    status: 'live',
    phase: 'open',
    server_time: new Date().toISOString(),
    bidding_opens_at: inHours(-24),
    effective_close_at: inHours(2),
    hard_stop_at: null,
    extension_count: 0,
    accepting_offers: true,
    start_price_minor: 25_000_000,
    min_step: { minor: null, bps: 50 },
    amount_granularity_minor: 100,
    my_offer: { id: '01jboffer00000000000000001', seq: 57, amount_minor: 9_850_000, stage: 'live', accepted_at: '2026-11-09T11:59:58.412Z' },
    my_offers_count: 4,
    is_leading: false,
    rank: null,
    ranked_count: null,
    leading_amount_minor: null,
    ladder: null,
    required_next_amount_minor: 9_800_800,
    bafo: null,
    result: null,
    last_change: { kind: 'snapshot', reason: null },
    ...overrides,
  }
}

export function participantCompetition(overrides: Partial<ParticipantCompetition> = {}): ParticipantCompetition {
  return {
    id: COMPETITION_ID,
    reference_no: 'BAFO-T-2026-000123',
    title: 'توريد أجهزة حاسب محمول',
    description: 'وصف المنافسة',
    direction: 'tender',
    format: 'live',
    status: 'live',
    phase: 'open',
    currency: 'SAR',
    price_basis: 'excl_vat',
    category: { id: '01j9cat0000000000000000000', code: 'it_hardware', name: 'أجهزة تقنية', is_other: false, auction_allowed: true },
    category_other_text: null,
    region: { id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' },
    preset_code: 'standard_live_tender',
    rules: {
      start_price_minor: 25_000_000,
      min_step_minor: null,
      min_step_bps: 50,
      amount_granularity_minor: 100,
      must_beat: 'own',
      rank_visibility: 'leading_flag',
      show_prices: false,
      auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 },
      final_window_minutes: null,
      bafo_round: { enabled: false, duration_minutes: null },
      min_participants: 2,
      result_publication: 'outcome_only',
    },
    rules_summary: ['مناقصة: الأقل سعراً يفوز.', 'يُعتمد وقت استلام العرض على خادم بافو.'],
    schedule: {
      bidding_opens_at: inHours(-24),
      scheduled_close_at: inHours(2),
      effective_close_at: inHours(2),
      hard_stop_at: null,
      final_window_starts_at: null,
      invitation_cutoff_at: inHours(1),
      extension_count: 0,
      published_at: inHours(-48),
      opened_at: inHours(-24),
      closed_at: null,
      offers_opened_at: null,
      awarded_at: null,
      not_awarded_at: null,
      cancelled_at: null,
    },
    issuer: { id: '01j9iss0000000000000000000', name: 'شركة المصدر', logo_url: null, verified: true },
    bafo_round: null,
    cancellation: null,
    not_awarded: null,
    permissions: {
      can_edit: false, can_delete: false, can_publish: false, can_invite: false, can_extend: false, can_cancel: false,
      can_start_bafo: false, can_award: false, can_revoke_award: false, can_close_without_award: false,
      can_manage_sponsorship: false, can_comment: true, can_join: false, can_decline: false, can_submit_offer: true,
    },
    participation: { participant_id: '01j9par0000000000000000000', alias_no: 7, joined_at: '2026-11-02T08:00:00.000Z', terms_accepted_at: '2026-11-02T08:00:00.000Z' },
    access: { state: 'full', coverage: 'own_plan', sponsor_name: null, join_deadline: inHours(1) },
    result: { outcome: null, winning_amount_minor: null },
    live: participantSnapshot(),
    viewer_role: 'participant',
    server_time: new Date().toISOString(),
    created_at: '2026-10-30T08:00:00.000Z',
    updated_at: '2026-11-02T08:00:00.000Z',
    ...overrides,
  }
}

export function inviteeCompetition(overrides: Partial<InviteeCompetition> = {}): InviteeCompetition {
  const base = participantCompetition()
  return {
    id: base.id,
    reference_no: base.reference_no,
    title: base.title,
    direction: base.direction,
    format: base.format,
    status: 'scheduled',
    phase: null,
    currency: 'SAR',
    price_basis: 'excl_vat',
    category: base.category,
    region: base.region,
    issuer: base.issuer,
    rules: base.rules,
    rules_summary: base.rules_summary,
    schedule: { bidding_opens_at: inHours(24), scheduled_close_at: inHours(48), effective_close_at: inHours(48), invitation_cutoff_at: inHours(40) },
    invitation: { id: INVITATION_ID, status: 'viewed', join_deadline: inHours(40), sent_at: '2026-11-01T08:00:00.000Z' },
    access: { state: 'join_required', coverage: 'sponsored', sponsor_name: 'شركة المصدر', join_deadline: inHours(40) },
    invitation_documents: [],
    permissions: { ...base.permissions, can_comment: false, can_submit_offer: false, can_join: true, can_decline: true },
    viewer_role: 'invitee',
    server_time: new Date().toISOString(),
    ...overrides,
  }
}

export function participantListItem(id: string, overrides: Partial<ParticipantCompetitionListItem> = {}): ParticipantCompetitionListItem {
  return {
    id,
    reference_no: `BAFO-T-2026-${id.slice(-6)}`,
    title: `منافسة ${id}`,
    direction: 'tender',
    format: 'live',
    status: 'live',
    phase: 'open',
    category: { id: '01j9cat0000000000000000000', code: 'it_hardware', name: 'أجهزة تقنية', is_other: false, auction_allowed: true },
    region: { id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' },
    issuer: { id: '01j9iss0000000000000000000', name: 'شركة المصدر', logo_url: null, verified: true },
    schedule: { bidding_opens_at: inHours(-24), effective_close_at: inHours(2), invitation_cutoff_at: inHours(1) },
    invitation: { id: `inv-${id}`, status: 'joined', join_deadline: inHours(1) },
    access: { state: 'full', coverage: 'own_plan', sponsor_name: null, join_deadline: inHours(1) },
    my_offer_amount_minor: 9_850_000,
    is_leading: true,
    result: { outcome: null },
    updated_at: '2026-11-02T08:00:00.000Z',
    ...overrides,
  }
}

export function myOffer(seq: number, amountMinor: number, overrides: Partial<MyOffer> = {}): MyOffer {
  return {
    id: `01jboffer${String(seq).padStart(17, '0')}`,
    seq,
    amount_minor: amountMinor,
    stage: 'live',
    accepted_at: '2026-11-09T11:59:58.412Z',
    voided: false,
    ...overrides,
  }
}

export function comment(id: string, overrides: Partial<Comment> = {}): Comment {
  return {
    id,
    parent_id: null,
    body: `سؤال ${id}`,
    author: { kind: 'participant', alias_no: 3 },
    created_at: new Date().toISOString(),
    replies: [],
    ...overrides,
  }
}
