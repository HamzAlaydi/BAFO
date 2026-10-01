/**
 * Issuer-side fixtures shaped exactly like API.md §2.6–§2.10 (competitions, live snapshots, standings,
 * sponsorship). Override any field: `makeIssuerCompetition({ status: 'live' })`.
 */
import type { Preset } from '../../app/types/api/catalog'
import type { Invitation, IssuerCompetition, IssuerCompetitionListItem } from '../../app/types/api/competitions'
import type { IssuerLiveSnapshot, OfferLogEntry, ParticipantStandingRow } from '../../app/types/api/bidding'
import type { Payment, SponsorshipQuote } from '../../app/types/api/billing'

export const COMPETITION_ID = '01j9comp000000000000000000'
export const CATEGORY = { id: '01j9cat0000000000000000000', code: 'it_hardware', name: 'أجهزة تقنية', is_other: false, auction_allowed: true }
export const REGION = { id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' }

export const PRESETS: Preset[] = [
  {
    id: 'p1',
    code: 'standard_live_tender',
    name: 'مناقصة مباشرة قياسية',
    description: 'قواعد متوازنة',
    direction: 'tender',
    format: 'live',
    rules: {
      must_beat: 'own',
      min_step_bps: 50,
      rank_visibility: 'leading_flag',
      show_prices: false,
      auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 },
      final_window_minutes: 60,
      bafo_round: { enabled: false, duration_minutes: null },
    },
  },
  {
    id: 'p2',
    code: 'sealed_rfq',
    name: 'طلب عروض مغلق',
    description: 'عروض مغلقة',
    direction: 'tender',
    format: 'sealed',
    rules: { rank_visibility: 'none', show_prices: false, bafo_round: { enabled: true, duration_minutes: 60 } },
  },
]

export function makeIssuerCompetition(overrides: Partial<IssuerCompetition> = {}): IssuerCompetition {
  return {
    id: COMPETITION_ID,
    reference_no: null,
    title: 'توريد أجهزة حاسب محمول',
    description: 'مواصفات الأجهزة',
    direction: 'tender',
    format: 'live',
    status: 'draft',
    phase: null,
    currency: 'SAR',
    price_basis: 'excl_vat',
    category: CATEGORY,
    category_other_text: null,
    region: REGION,
    issuer: { id: '01j9org0000000000000000000', name: 'شركة المصدر', logo_url: null, verified: true },
    preset_code: 'standard_live_tender',
    rules: {
      start_price_minor: 25_000_000,
      reserve_price_minor: 21_000_000,
      min_step_minor: null,
      min_step_bps: 50,
      amount_granularity_minor: 100,
      must_beat: 'own',
      rank_visibility: 'leading_flag',
      show_prices: false,
      auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 },
      final_window_minutes: 60,
      bafo_round: { enabled: false, duration_minutes: null },
      min_participants: 2,
      result_publication: 'outcome_only',
    },
    rules_summary: ['مناقصة: الأقل سعراً يفوز.'],
    schedule: {
      bidding_opens_at: null,
      scheduled_close_at: '2026-11-09T12:00:00.000Z',
      effective_close_at: null,
      hard_stop_at: null,
      final_window_starts_at: null,
      invitation_cutoff_at: null,
      extension_count: 0,
      published_at: null,
      opened_at: null,
      closed_at: null,
      offers_opened_at: null,
      awarded_at: null,
      not_awarded_at: null,
      cancelled_at: null,
    },
    counts: { invitations: 3, joined: 0, declined: 0, participants_with_offers: 0, offers: 0, comments: 0, attachments: 1 },
    leading_amount_minor: null,
    sponsorship: null,
    bafo_round: null,
    award: null,
    cancellation: null,
    not_awarded: null,
    created_by: { type: 'user', id: '01j9usr0000000000000000000', name: 'سارة' },
    source: 'web',
    permissions: {
      can_edit: true,
      can_delete: true,
      can_publish: true,
      can_invite: true,
      can_extend: false,
      can_cancel: false,
      can_start_bafo: false,
      can_award: false,
      can_revoke_award: false,
      can_close_without_award: false,
      can_manage_sponsorship: true,
      can_comment: false,
      can_join: false,
      can_decline: false,
      can_submit_offer: false,
    },
    viewer_role: 'issuer',
    live: null,
    server_time: '2026-11-01T09:00:00.000Z',
    created_at: '2026-10-30T09:00:00.000Z',
    updated_at: '2026-10-31T09:00:00.000Z',
    ...overrides,
  }
}

export function makeListItem(overrides: Partial<IssuerCompetitionListItem> = {}): IssuerCompetitionListItem {
  return {
    id: COMPETITION_ID,
    reference_no: 'BAFO-T-2026-000123',
    title: 'توريد أجهزة حاسب محمول',
    direction: 'tender',
    format: 'live',
    status: 'scheduled',
    phase: null,
    category: CATEGORY,
    region: REGION,
    schedule: { bidding_opens_at: '2026-11-02T06:00:00.000Z', effective_close_at: '2026-11-09T12:00:00.000Z' },
    counts: { invitations: 5, joined: 4, participants_with_offers: 3 },
    leading_amount_minor: 9_800_000,
    created_at: '2026-10-30T09:00:00.000Z',
    updated_at: '2026-10-31T09:00:00.000Z',
    ...overrides,
  }
}

export function makeLiveSnapshot(v: number, overrides: Partial<IssuerLiveSnapshot> = {}): IssuerLiveSnapshot {
  return {
    v,
    competition_id: COMPETITION_ID,
    direction: 'tender',
    status: 'live',
    phase: 'final_window',
    server_time: '2026-11-09T11:59:58.120Z',
    bidding_opens_at: '2026-11-02T06:00:00.000Z',
    effective_close_at: new Date(Date.now() + 3_600_000).toISOString(),
    hard_stop_at: null,
    extension_count: 0,
    leader: { participant_id: 'pa', alias_no: 3, organization: { id: 'oa', name: 'شركة الريادة' }, amount_minor: 9_800_000, accepted_at: '2026-11-09T11:58:00.000Z' },
    reserve_met: false,
    ranking: [
      { participant_id: 'pa', alias_no: 3, organization: { id: 'oa', name: 'شركة الريادة', logo_url: null }, current_amount_minor: 9_800_000, first_amount_minor: 11_000_000, rank: 1, is_leader: true, offers_count: 6, last_offer_at: '2026-11-09T11:58:00.000Z', submitted: true, bafo: { shortlisted: false, submitted: false } },
      { participant_id: 'pb', alias_no: 7, organization: { id: 'ob', name: 'مؤسسة دلتا', logo_url: null }, current_amount_minor: 9_900_000, first_amount_minor: 10_500_000, rank: 2, is_leader: false, offers_count: 2, last_offer_at: '2026-11-09T11:50:00.000Z', submitted: true, bafo: { shortlisted: false, submitted: false } },
    ],
    metrics: { offers_count: 8, participants_joined: 2, participants_with_offers: 2, invitations_count: 3, improvement_vs_start_bps: 6080 },
    online_participants_count: 2,
    bafo: null,
    last_change: { kind: 'offer', reason: null },
    ...overrides,
  }
}

export function makeOfferEntry(seq: number, overrides: Partial<OfferLogEntry> = {}): OfferLogEntry {
  return {
    id: `offer-${seq}`,
    seq,
    participant: { id: 'pa', alias_no: 3, organization: { id: 'oa', name: 'شركة الريادة' } },
    amount_minor: 10_000_000 - seq * 1000,
    stage: 'live',
    accepted_at: '2026-11-09T11:59:58.412Z',
    channel: 'web',
    voided: false,
    ...overrides,
  }
}

export function makeStandingRow(rank: number, overrides: Partial<ParticipantStandingRow> = {}): ParticipantStandingRow {
  return {
    participant: {
      id: `p${rank}`,
      alias_no: rank + 2,
      joined_at: '2026-11-02T08:00:00.000Z',
      organization: { id: `o${rank}`, name: `منشأة ${rank}`, logo_url: null, email: `o${rank}@x.sa`, phone: '+966501234567', cr_number: '1010000001' },
      coverage: 'own_plan',
    },
    current_amount_minor: 9_000_000 + rank * 100_000,
    first_amount_minor: 10_000_000,
    offers_count: 2,
    last_offer_at: '2026-11-09T11:50:00.000Z',
    rank,
    is_leader: rank === 1,
    change_ratio_bps: 900,
    submitted: true,
    bafo: { shortlisted: false, submitted: false, reference_amount_minor: null },
    ...overrides,
  }
}

export function makeInvitation(id: string, overrides: Partial<Invitation> = {}): Invitation {
  return {
    id,
    email: `${id}@supplier.sa`,
    name: null,
    status: 'draft',
    organization: null,
    vendor: null,
    sponsored_requested: false,
    coverage: 'none',
    pass_status: null,
    participant: null,
    sent_at: null,
    viewed_at: null,
    joined_at: null,
    declined_at: null,
    decline_reason: null,
    revoked_at: null,
    revoke_reason: null,
    expired_at: null,
    created_at: '2026-10-31T09:00:00.000Z',
    ...overrides,
  }
}

export function makeQuote(overrides: Partial<SponsorshipQuote> = {}): SponsorshipQuote {
  return {
    mode: 'all',
    max_passes: null,
    unit_price_minor: 20_000,
    vat_rate_bp: 1500,
    currency: 'SAR',
    funded_passes: 0,
    free_slots: 0,
    lines: [],
    passes_to_reserve: 0,
    passes_to_buy: 2,
    subtotal_minor: 40_000,
    discount_minor: 0,
    vat_minor: 6000,
    total_minor: 46_000,
    ...overrides,
  }
}

export function makePayment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: '01j9pay0000000000000000000',
    purpose: 'sponsorship',
    status: 'pending',
    currency: 'SAR',
    lines: [{ kind: 'sponsored_pass', description: 'تصريح مشاركة مغطّاة', quantity: 2, unit_price_minor: 20_000, net_minor: 40_000 }],
    subtotal_minor: 40_000,
    credit_minor: 0,
    discount_minor: 0,
    vat_rate_bp: 1500,
    vat_minor: 6000,
    total_minor: 46_000,
    coupon: null,
    redirect_url: 'http://localhost:8000/pay/fake/01j9pay0000000000000000000',
    failure_code: null,
    failure_message: null,
    paid_at: null,
    expires_at: '2026-11-01T09:30:00.000Z',
    created_at: '2026-11-01T09:00:00.000Z',
    invoice_id: null,
    context: { competition_id: COMPETITION_ID, intent: 'publish', subscription_id: null },
    ...overrides,
  }
}
