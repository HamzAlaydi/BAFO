/**
 * Billing fixtures shaped exactly like API.md §2.10 (for mocked services in tests).
 */
import type { CouponValidation, CustomQuote, Invoice, Payment, Plan, Subscription, SubscriptionOverview, Voucher } from '../../app/types/api/billing'

export function makePlan(overrides: Partial<Plan> = {}): Plan {
  return {
    id: '01j9plan000000000000000pro',
    code: 'pro',
    name: 'باقة برو',
    description: 'للفرق المتوسطة',
    features: ['منافسات غير محدودة', 'مزايدات'],
    seats: 3,
    monthly_price_minor: 150000,
    annual_price_minor: 1500000,
    monthly_list_price_minor: 300000,
    annual_list_price_minor: 1500000,
    is_custom: false,
    is_featured: true,
    currency: 'SAR',
    vat_rate_bp: 1500,
    ...overrides,
  }
}

export function makeCustomPlan(overrides: Partial<Plan> = {}): Plan {
  return makePlan({
    id: '01j9plan00000000000000custo',
    code: 'custom',
    name: 'باقة مخصصة',
    description: null,
    features: [],
    seats: null,
    monthly_price_minor: null,
    annual_price_minor: null,
    monthly_list_price_minor: null,
    annual_list_price_minor: null,
    is_custom: true,
    is_featured: false,
    custom: { min_seats: 4, max_seats: 50, seat_monthly_price_minor: 50000, seat_annual_price_minor: 500000 },
    ...overrides,
  })
}

export function makeCustomQuote(overrides: Partial<CustomQuote> = {}): CustomQuote {
  const seats = overrides.seats ?? 6
  return {
    seats,
    interval: 'monthly',
    unit_price_minor: 50000,
    subtotal_minor: 50000 * seats,
    vat_rate_bp: 1500,
    vat_minor: 7500 * seats,
    total_minor: 57500 * seats,
    currency: 'SAR',
    ...overrides,
  }
}

export function makeSubscription(overrides: Partial<Subscription> = {}): Subscription {
  return {
    id: '01j9sub0000000000000000001',
    plan: { id: '01j9plan000000000000000pro', code: 'pro', name: 'باقة برو' },
    source: 'paid',
    interval: 'monthly',
    seats: 3,
    status: 'active',
    starts_at: '2026-10-01T00:00:00.000Z',
    ends_at: '2026-11-01T00:00:00.000Z',
    days_left: 21,
    total_days: 31,
    amounts: { subtotal_minor: 150000, credit_minor: 0, discount_minor: 0, vat_minor: 22500, total_minor: 172500 },
    created_at: '2026-10-01T00:00:00.000Z',
    ...overrides,
  }
}

export function makeOverview(overrides: Partial<SubscriptionOverview> = {}): SubscriptionOverview {
  return {
    current: makeSubscription(),
    upcoming: null,
    history: [makeSubscription()],
    trial_available: false,
    seats_used: 2,
    seats_total: 3,
    ...overrides,
  }
}

export function makePayment(overrides: Partial<Payment> = {}): Payment {
  return {
    id: '01j9pay0000000000000000001',
    purpose: 'subscription',
    status: 'pending',
    currency: 'SAR',
    lines: [{ kind: 'plan', description: 'باقة برو — شهري', quantity: 1, unit_price_minor: 150000, net_minor: 150000 }],
    subtotal_minor: 150000,
    credit_minor: 0,
    discount_minor: 0,
    vat_rate_bp: 1500,
    vat_minor: 22500,
    total_minor: 172500,
    coupon: null,
    redirect_url: 'http://localhost:8000/pay/fake/01j9pay0000000000000000001',
    failure_code: null,
    failure_message: null,
    paid_at: null,
    expires_at: '2099-10-01T09:30:00.000Z',
    created_at: '2026-10-01T09:00:00.000Z',
    invoice_id: null,
    context: { competition_id: null, intent: null, subscription_id: '01j9sub0000000000000000002' },
    ...overrides,
  }
}

export function makeInvoice(overrides: Partial<Invoice> = {}): Invoice {
  return {
    id: '01j9inv0000000000000000001',
    number: 'BAFO-INV-2026-000042',
    type: 'tax_invoice',
    issue_date: '2026-10-01',
    currency: 'SAR',
    subtotal_minor: 140000,
    discount_minor: 0,
    vat_rate_bp: 1500,
    vat_minor: 21000,
    total_minor: 161000,
    einvoice_status: 'cleared',
    zatca_uuid: '7b1c0000-0000-4000-8000-000000000000',
    lines: [{ description: 'تصريح مشاركة مغطّاة — BAFO-T-2026-000123', quantity: 7, unit_price_minor: 20000, net_minor: 140000 }],
    pdf: { available: true, download_path: '/api/app/v1/billing/invoices/01j9inv0000000000000000001/pdf' },
    payment_id: '01j9pay0000000000000000001',
    issued_at: '2026-10-01T09:05:00.000Z',
    ...overrides,
  }
}

export function makeVoucher(overrides: Partial<Voucher> = {}): Voucher {
  return {
    id: '01j9vou0000000000000000001',
    code: 'V-7KQ2M9XA1B',
    amount_minor: 40000,
    balance_minor: 30000,
    valid_until: '2027-10-01T00:00:00.000Z',
    reason: 'Unused passes BAFO-T-2026-000123',
    source_competition_id: '01j9comp000000000000000000',
    is_active: true,
    ...overrides,
  }
}

export function makeCouponValidation(overrides: Partial<CouponValidation> = {}): CouponValidation {
  return {
    code: 'LAUNCH10',
    kind: 'coupon',
    discount_type: 'percent',
    percent_bps: 1000,
    amount_minor: null,
    balance_minor: null,
    applies_to: 'any',
    valid_until: '2026-12-31T00:00:00.000Z',
    discount_minor: 15000,
    ...overrides,
  }
}

export const pageOf = <T>(items: T[], overrides: Record<string, unknown> = {}) => ({
  items,
  pagination: { type: 'page' as const, current_page: 1, per_page: 20, has_more: false, total: items.length, last_page: 1, ...overrides },
})
