/** Billing: plans, subscriptions, payments, invoices, vouchers, coupons and sponsorship (API.md §1.7, §2.10). */
import type { Currency, IsoDate, IsoDateTime, PlanRef, Ulid } from './common'
import type { Coverage, InvitationInput } from './competitions'
import type { SubscriptionSource, SubscriptionStatus } from './identity'

export type BillingInterval = 'monthly' | 'annual'

export interface CustomPlanInfo {
  min_seats: number
  max_seats: number
  seat_monthly_price_minor: number
  seat_annual_price_minor: number
}

/** `Plan`. The custom plan has `seats`, prices and list prices `null` and adds `custom`. */
export interface Plan {
  id: Ulid
  code: string
  name: string
  description: string | null
  features: string[]
  seats: number | null
  monthly_price_minor: number | null
  annual_price_minor: number | null
  monthly_list_price_minor: number | null
  annual_list_price_minor: number | null
  is_custom: boolean
  is_featured: boolean
  currency: Currency
  vat_rate_bp: number
  custom?: CustomPlanInfo
}

export interface CustomQuote {
  seats: number
  interval: BillingInterval
  unit_price_minor: number
  subtotal_minor: number
  vat_rate_bp: number
  vat_minor: number
  total_minor: number
  currency: Currency
}

export interface SubscriptionAmounts {
  subtotal_minor: number
  credit_minor: number
  discount_minor: number
  vat_minor: number
  total_minor: number
}

export interface Subscription {
  id: Ulid
  plan: PlanRef
  source: SubscriptionSource
  /** `null` for a trial or grant. */
  interval: BillingInterval | null
  seats: number
  status: SubscriptionStatus
  starts_at: IsoDateTime
  ends_at: IsoDateTime
  days_left: number
  total_days: number
  /** `null` for a trial or grant. */
  amounts: SubscriptionAmounts | null
  created_at: IsoDateTime
}

export interface SubscriptionOverview {
  current: Subscription | null
  upcoming: Subscription | null
  history: Subscription[]
  trial_available: boolean
  seats_used: number
  seats_total: number
}

export type PaymentPurpose = 'subscription' | 'sponsorship'
export type PaymentStatus = 'pending' | 'succeeded' | 'failed' | 'expired' | 'refunded'

export const TERMINAL_PAYMENT_STATUSES: readonly PaymentStatus[] = ['succeeded', 'failed', 'expired', 'refunded']

export interface PaymentLine {
  kind: 'plan' | 'custom_seats' | 'sponsored_pass'
  /** Localised. */
  description: string
  quantity: number
  unit_price_minor: number
  net_minor: number
}

export interface Payment {
  id: Ulid
  purpose: PaymentPurpose
  status: PaymentStatus
  currency: Currency
  lines: PaymentLine[]
  subtotal_minor: number
  credit_minor: number
  discount_minor: number
  vat_rate_bp: number
  vat_minor: number
  total_minor: number
  coupon: { code: string } | null
  /** Hosted checkout page; `null` with status `succeeded` when the total is 0. */
  redirect_url: string | null
  failure_code: string | null
  failure_message: string | null
  paid_at: IsoDateTime | null
  expires_at: IsoDateTime | null
  created_at: IsoDateTime
  invoice_id: Ulid | null
  context: { competition_id: Ulid | null, intent: 'publish' | 'invite' | null, subscription_id: Ulid | null }
}

export type EInvoiceStatus = 'pending' | 'cleared' | 'reported' | 'rejected' | 'failed'

export interface Invoice {
  id: Ulid
  number: string
  type: 'tax_invoice' | 'credit_note'
  issue_date: IsoDate
  currency: Currency
  subtotal_minor: number
  discount_minor: number
  vat_rate_bp: number
  vat_minor: number
  total_minor: number
  einvoice_status: EInvoiceStatus
  zatca_uuid: string | null
  lines: Array<{ description: string, quantity: number, unit_price_minor: number, net_minor: number }>
  pdf: { available: boolean, download_path: string }
  payment_id: Ulid | null
  issued_at: IsoDateTime
}

export interface Voucher {
  id: Ulid
  code: string
  amount_minor: number
  balance_minor: number
  valid_until: IsoDateTime | null
  reason: string | null
  source_competition_id: Ulid | null
  is_active: boolean
}

export interface CouponValidation {
  code: string
  kind: 'coupon' | 'voucher'
  discount_type: 'percent' | 'fixed'
  percent_bps: number | null
  amount_minor: number | null
  balance_minor: number | null
  applies_to: 'any' | 'subscription' | 'sponsorship'
  valid_until: IsoDateTime | null
  /** Computed for the given context. */
  discount_minor: number
}

export type ValidateCouponRequest
  = | { code: string, purpose: 'subscription', plan_id: Ulid, interval: BillingInterval, seats?: number | null }
    | { code: string, purpose: 'sponsorship', competition_id: Ulid }

export interface SubscriptionCheckoutRequest {
  plan_id: Ulid
  interval: BillingInterval
  seats?: number | null
  coupon_code?: string | null
  /** `{origin}/{locale}/dashboard/billing/checkout/return`, no query string (CONVENTIONS §4.3). */
  return_url: string
}

// ---------- Sponsorship (R4) ----------

export type SponsorshipMode = 'none' | 'all' | 'selected'

export interface SponsorshipCounts {
  pending: number
  reserved: number
  joined: number
  released: number
  unused: number
  void: number
  free_slots: number
}

export interface Sponsorship {
  mode: SponsorshipMode
  max_passes: number | null
  unit_price_minor: number
  vat_rate_bp: number
  currency: Currency
  status: 'draft' | 'active' | 'settled'
  funded_passes: number
  /** The organization flag and the global setting are both on. */
  enabled: boolean
  counts: SponsorshipCounts
  unused_count: number | null
  voucher: { code: string } | null
}

export interface SponsorshipQuoteLine {
  /** `null` in invite quotes (the row is new). */
  invitation_id: Ulid | null
  email: string
  organization_name: string | null
  coverage: Coverage
  reason: 'not_selected' | 'cap_reached' | 'own_plan' | null
}

export interface SponsorshipQuote {
  mode: SponsorshipMode
  max_passes: number | null
  unit_price_minor: number
  vat_rate_bp: number
  currency: Currency
  funded_passes: number
  free_slots: number
  lines: SponsorshipQuoteLine[]
  passes_to_reserve: number
  passes_to_buy: number
  subtotal_minor: number
  discount_minor: number
  vat_minor: number
  total_minor: number
}

export interface UpdateSponsorshipRequest {
  mode: SponsorshipMode
  max_passes?: number | null
}

export interface SponsorshipCheckoutRequest {
  intent: 'publish' | 'invite'
  invitations?: Array<Omit<InvitationInput, 'sponsored'>>
  coupon_code?: string | null
  return_url: string
}
