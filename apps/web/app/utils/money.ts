import type { AppLocale } from '../types/api'
import { normalizeDigits } from './digits'

/** SAR has 100 halalas. Amounts travel as integer halalas (`amount_minor`). */
export const MINOR_PER_MAJOR = 100

/** VAT in basis points (15%). Prices exclude VAT. */
export const VAT_RATE_BPS = 1500

const CURRENCY_LABEL: Record<AppLocale, string> = {
  ar: 'ر.س',
  en: 'SAR',
}

const amountFormatter = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
  useGrouping: true,
})

const plainFormatter = new Intl.NumberFormat('en-US', {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
  useGrouping: false,
})

function assertMinor(minor: number): void {
  if (!Number.isSafeInteger(minor)) {
    throw new TypeError(`Money amounts must be integer halalas, got ${minor}`)
  }
}

/** 1250050 → "12,500.50" (Latin digits, always 2 decimals). */
export function formatAmount(minor: number, options: { grouping?: boolean } = {}): string {
  assertMinor(minor)
  const formatter = options.grouping === false ? plainFormatter : amountFormatter
  return formatter.format(minor / MINOR_PER_MAJOR)
}

/** 1250050 → "12,500.50 ر.س" (ar) or "SAR 12,500.50" (en). */
export function formatMoney(minor: number, locale: AppLocale): string {
  const amount = formatAmount(minor)
  return locale === 'ar' ? `${amount} ${CURRENCY_LABEL.ar}` : `${CURRENCY_LABEL.en} ${amount}`
}

export function currencyLabel(locale: AppLocale): string {
  return CURRENCY_LABEL[locale]
}

/**
 * Parses a user-typed SAR amount into integer halalas without floating-point maths.
 * Accepts grouping commas and Arabic-Indic digits. Returns null for empty or invalid
 * input, negative amounts and more than two decimals.
 *
 *   "12,500.5" → 1250050, "١٢٥٠٠٫٥" → 1250050, "" → null, "1.234" → null
 */
export function parseAmountToMinor(input: string): number | null {
  const cleaned = normalizeDigits(input).replace(/[\s,]/g, '')
  if (cleaned === '') return null
  const match = /^(\d+)(?:\.(\d{0,2}))?$/.exec(cleaned)
  if (!match) return null
  const major = match[1] ?? '0'
  const fraction = (match[2] ?? '').padEnd(2, '0')
  const minor = Number(major) * MINOR_PER_MAJOR + Number(fraction)
  return Number.isSafeInteger(minor) ? minor : null
}

/** VAT due on a VAT-exclusive amount, rounded half-up to the halala. */
export function vatOf(minor: number): number {
  assertMinor(minor)
  return Math.round((minor * VAT_RATE_BPS) / 10_000)
}

export function withVat(minor: number): number {
  return minor + vatOf(minor)
}

/**
 * Basis points as a percentage number (SCREENS S6): `bps / 100`, up to 2 decimals, trailing zeros
 * trimmed. 1500 → "15", 50 → "0.5", 6080 → "60.8". Callers add the sign and the % (or a localised
 * template).
 */
export function formatBps(bps: number): string {
  return (Math.round(Math.abs(bps)) / 100).toFixed(2).replace(/\.?0+$/, '')
}

/**
 * Basis points as a percentage: "0.5%", or with `signed` "+60.8%" / "−10.9%" / "0%" for server-signed
 * ratios (positive = better for the issuer; SCREENS S2). Unsigned values show the magnitude only, for
 * copy that states the direction in words or with an arrow.
 */
export function formatBpsPercent(bps: number, options: { signed?: boolean } = {}): string {
  const text = `${formatBps(bps)}%`
  if (!options.signed || bps === 0) return text
  return `${bps > 0 ? '+' : '−'}${text}`
}
