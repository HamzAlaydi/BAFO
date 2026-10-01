/**
 * Billing display helpers (SCREENS W30–W35). Pure functions only: every amount comes from the
 * server (plans, quotes, payments, invoices); nothing here computes a price, a credit or VAT.
 */
import type { BillingInterval, EInvoiceStatus, Payment, PaymentStatus, Plan } from '../types/api/billing'
import type { SubscriptionStatus } from '../types/api/identity'
import type { Tone } from '../types/ui'
import { normalizeDigits } from './digits'

export const BILLING_INTERVALS: readonly BillingInterval[] = ['monthly', 'annual']

export function isBillingInterval(value: unknown): value is BillingInterval {
  return value === 'monthly' || value === 'annual'
}

export interface PlanIntervalPrice {
  /** Price excl. VAT for the interval; `null` for the custom plan or an interval the plan lacks. */
  price: number | null
  /** The list price, only when the server sent one higher than the price (shown struck through). */
  listPrice: number | null
}

/** The server prices of a fixed plan for one interval. */
export function planIntervalPrice(plan: Plan, interval: BillingInterval): PlanIntervalPrice {
  const price = interval === 'monthly' ? plan.monthly_price_minor : plan.annual_price_minor
  const list = interval === 'monthly' ? plan.monthly_list_price_minor : plan.annual_list_price_minor
  return {
    price,
    listPrice: price !== null && list !== null && list > price ? list : null,
  }
}

/** A positive integer from a query value or an input ("12", "١٢"); null otherwise. */
export function parsePositiveInt(value: unknown): number | null {
  const raw = Array.isArray(value) ? value[0] : value
  if (typeof raw === 'number') return Number.isSafeInteger(raw) && raw > 0 ? raw : null
  if (typeof raw !== 'string') return null
  const cleaned = normalizeDigits(raw).trim()
  if (!/^\d{1,6}$/.test(cleaned)) return null
  const parsed = Number(cleaned)
  return parsed > 0 ? parsed : null
}

/** The first string of a query value. */
export function queryString(value: unknown): string | null {
  const raw = Array.isArray(value) ? value[0] : value
  return typeof raw === 'string' && raw !== '' ? raw : null
}

/**
 * A `?return=` target for billing flows (SCREENS W31, F8): a same-origin dashboard path, with or
 * without the locale prefix. Anything else (absolute URLs, `//host`, other areas) is dropped.
 */
export function safeDashboardReturn(value: unknown): string | null {
  const raw = queryString(value)
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || raw.startsWith('/\\')) return null
  return /^\/((ar|en)\/)?dashboard(\/|$|\?|#)/.test(raw) ? raw : null
}

/** Drops a leading `/ar` or `/en` so localised links (`NuxtLinkLocale`) add the current one. */
export function stripLocalePrefix(path: string): string {
  return path.replace(/^\/(ar|en)(?=\/|$|\?|#)/, '') || '/'
}

// ---------- Checkout memory (per-viewer convenience, sessionStorage) ----------

/**
 * What the return page needs to know about a checkout it did not start: where to go back to and
 * how to try again. The gateway return URL carries only `?payment=` (CONVENTIONS §4.3), so this is
 * kept in `sessionStorage`, keyed by payment id. It holds no secret.
 */
export interface CheckoutMemory {
  returnTo: string | null
  planId: string
  interval: BillingInterval
  seats: number | null
}

const CHECKOUT_MEMORY_KEY = 'bafo.checkout_memory'
const CHECKOUT_MEMORY_MAX = 10

function readAllCheckoutMemory(): Record<string, CheckoutMemory> {
  try {
    const raw = globalThis.sessionStorage?.getItem(CHECKOUT_MEMORY_KEY)
    const parsed: unknown = raw ? JSON.parse(raw) : {}
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed as Record<string, CheckoutMemory> : {}
  }
  catch {
    return {}
  }
}

export function rememberCheckout(paymentId: string, memory: CheckoutMemory): void {
  try {
    const all = readAllCheckoutMemory()
    const entries = Object.entries({ ...all, [paymentId]: memory }).slice(-CHECKOUT_MEMORY_MAX)
    globalThis.sessionStorage?.setItem(CHECKOUT_MEMORY_KEY, JSON.stringify(Object.fromEntries(entries)))
  }
  catch {
    // Storage can be unavailable (private mode, blocked site data): the page falls back to W30.
  }
}

export function recallCheckout(paymentId: string): CheckoutMemory | null {
  const memory = readAllCheckoutMemory()[paymentId]
  if (!memory || typeof memory.planId !== 'string' || !isBillingInterval(memory.interval)) return null
  return {
    returnTo: safeDashboardReturn(memory.returnTo),
    planId: memory.planId,
    interval: memory.interval,
    seats: parsePositiveInt(memory.seats),
  }
}

// ---------- Payment return (W33) ----------

export type PaymentOutcome = 'checking' | 'succeeded' | 'failed' | 'expired' | 'refunded' | 'still_pending'

/**
 * The state the return page shows, from the latest `Payment` and the poll phase (SCREENS W33):
 * a terminal status wins; `pending` is "checking" while polling and "still pending" after `verify`.
 */
export function paymentOutcome(payment: Pick<Payment, 'status'> | null, phase: string): PaymentOutcome {
  const status: PaymentStatus | null = payment?.status ?? null
  if (status && status !== 'pending') return status
  return phase === 'still_pending' ? 'still_pending' : 'checking'
}

/** Only an http(s) hosted-checkout URL is followed; anything else is treated as a server error. */
export function isHostedCheckoutUrl(value: string | null | undefined): value is string {
  if (!value) return false
  try {
    const url = new URL(value)
    return url.protocol === 'https:' || url.protocol === 'http:'
  }
  catch {
    return false
  }
}

// ---------- Chip tones (never red outside errors and cancellations, SCREENS S2) ----------

export const SUBSCRIPTION_STATUS_TONES: Record<SubscriptionStatus, Tone> = {
  pending_payment: 'warning',
  active: 'primary',
  superseded: 'neutral',
  expired: 'neutral',
  cancelled: 'danger',
}

export const EINVOICE_STATUS_TONES: Record<EInvoiceStatus, Tone> = {
  cleared: 'primary',
  reported: 'primary',
  pending: 'info',
  rejected: 'warning',
  failed: 'warning',
}
