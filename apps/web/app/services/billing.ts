/**
 * Billing endpoints (API.md §1.7), web only (mobile never purchases, SCREENS CD6).
 * Checkout `return_url` must be `{origin}/{locale}/dashboard/billing/checkout/return` with no query
 * (CONVENTIONS §4.3); build it with `checkoutReturnUrl()`.
 */
import type { AppLocale, Page, PageQuery } from '~/types/api/common'
import type {
  BillingInterval,
  CouponValidation,
  CustomQuote,
  Invoice,
  Payment,
  Plan,
  SponsorshipCheckoutRequest,
  Sponsorship,
  SponsorshipQuote,
  Subscription,
  SubscriptionCheckoutRequest,
  SubscriptionOverview,
  UpdateSponsorshipRequest,
  ValidateCouponRequest,
  Voucher,
} from '~/types/api/billing'
import { getData, getPage, sendData, seg } from './http'

/** The payment return page for this origin and locale (no query string; the gateway appends `?payment=`). */
export function checkoutReturnUrl(origin: string, locale: AppLocale): string {
  return `${origin.replace(/\/$/, '')}/${locale}/dashboard/billing/checkout/return`
}

// ---------- Plans and subscription ----------

export function fetchPlans(): Promise<Plan[]> {
  return getData<Plan[]>('/plans')
}

/** `seats_out_of_range` (422) outside the custom bounds. */
export function fetchCustomQuote(seats: number, interval: BillingInterval): Promise<CustomQuote> {
  return getData<CustomQuote>('/plans/custom-quote', { seats, interval })
}

export function fetchSubscription(): Promise<SubscriptionOverview> {
  return getData<SubscriptionOverview>('/billing/subscription')
}

/** `trial_not_available` (409). Call `fetchMe` afterwards: `can_issue` changes. */
export function startTrial(): Promise<Subscription> {
  return sendData<Subscription>('POST', '/billing/trial')
}

export function validateCoupon(body: ValidateCouponRequest): Promise<CouponValidation> {
  return sendData<CouponValidation>('POST', '/billing/coupons/validate', body)
}

/** 201 `Payment` with `redirect_url` (null and `succeeded` when the total is 0). */
export function checkoutSubscription(body: SubscriptionCheckoutRequest, idempotencyKey?: string): Promise<Payment> {
  return sendData<Payment>('POST', '/billing/checkout/subscription', body, idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : undefined)
}

export function fetchPayment(id: string): Promise<Payment> {
  return getData<Payment>(`/billing/payments/${seg(id)}`)
}

/** Asks the gateway now; idempotent. */
export function verifyPayment(id: string): Promise<Payment> {
  return sendData<Payment>('POST', `/billing/payments/${seg(id)}/verify`)
}

// ---------- Invoices and vouchers ----------

export function listInvoices(query: PageQuery = {}): Promise<Page<Invoice>> {
  return getPage<Invoice>('/billing/invoices', { page: query.page, per_page: query.per_page ?? 20 })
}

export function fetchInvoice(id: string): Promise<Invoice> {
  return getData<Invoice>(`/billing/invoices/${seg(id)}`)
}

/** API path of the invoice PDF for `useFileDownload()`; 409 `invoice_pdf_not_ready` until cleared. */
export function invoicePdfPath(id: string): string {
  return `/billing/invoices/${seg(id)}/pdf`
}

export function listVouchers(): Promise<Voucher[]> {
  return getData<Voucher[]>('/billing/vouchers')
}

// ---------- Sponsorship (R4) ----------

const base = (competitionId: string) => `/competitions/${seg(competitionId)}/sponsorship`

/** `{mode: "none", …}` when there is no row. */
export function fetchSponsorship(competitionId: string): Promise<Sponsorship> {
  return getData<Sponsorship>(base(competitionId))
}

export function updateSponsorship(competitionId: string, body: UpdateSponsorshipRequest): Promise<Sponsorship> {
  return sendData<Sponsorship>('PUT', base(competitionId), body)
}

export function fetchSponsorshipQuote(competitionId: string, couponCode?: string | null): Promise<SponsorshipQuote> {
  return getData<SponsorshipQuote>(`${base(competitionId)}/quote`, { coupon_code: couponCode ?? undefined })
}

/** `sponsorship_already_funded` (409) → publish or invite directly. */
export function checkoutSponsorship(competitionId: string, body: SponsorshipCheckoutRequest, idempotencyKey?: string): Promise<Payment> {
  return sendData<Payment>('POST', `${base(competitionId)}/checkout`, body, idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : undefined)
}
