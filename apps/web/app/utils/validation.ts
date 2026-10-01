import { normalizeDigits } from './digits'

/**
 * Client-side checks for early feedback only; the API remains the source of truth (SCREENS S7).
 * Patterns follow API.md §0.6 and §1.3.
 */

export function isEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim())
}

/** Splits pasted text on commas, semicolons, whitespace and new lines; trims, lower-cases, de-duplicates. */
export function splitEmailList(text: string): string[] {
  const seen = new Set<string>()
  const result: string[] = []
  for (const part of text.split(/[\s,;،]+/)) {
    const email = part.trim().replace(/^<|>$/g, '').toLowerCase()
    if (!email || seen.has(email)) continue
    seen.add(email)
    result.push(email)
  }
  return result
}

/** Commercial Registration (السجل التجاري): 10 digits. */
export function isSaudiCrNumber(value: string): boolean {
  return /^\d{10}$/.test(normalizeDigits(value).trim())
}

/** VAT registration number (الرقم الضريبي): 15 digits, starting and ending with 3. */
export function isSaudiVatNumber(value: string): boolean {
  return /^3\d{13}3$/.test(normalizeDigits(value).trim())
}

/** Saudi mobile in E.164, the API format: `+9665XXXXXXXX`. */
export const SAUDI_MOBILE_E164 = /^\+9665\d{8}$/

/**
 * Normalises what users type (05XXXXXXXX, 5XXXXXXXX, 9665XXXXXXXX, +9665XXXXXXXX, 00966…, with
 * spaces, dashes or Arabic-Indic digits) to `+9665XXXXXXXX`; null when it is not a Saudi mobile.
 */
export function toSaudiE164(value: string): string | null {
  const digits = normalizeDigits(value).replace(/[\s\-().]/g, '')
  const match = /^(?:\+966|00966|966|0)?(5\d{8})$/.exec(digits)
  return match ? `+966${match[1]}` : null
}

export function isSaudiMobile(value: string): boolean {
  return toSaudiE164(value) !== null
}

/** The 9 national digits of an E.164 Saudi mobile (for the fixed-prefix input), or ''. */
export function nationalMobileDigits(e164: string | null | undefined): string {
  const match = /^\+966(5\d{8})$/.exec(e164 ?? '')
  return match?.[1] ?? ''
}

/** Display format `+966 50 123 4567` (SCREENS S6). */
export function formatSaudiMobile(e164: string | null | undefined): string {
  const national = nationalMobileDigits(e164)
  if (!national) return e164 ?? ''
  return `+966 ${national.slice(0, 2)} ${national.slice(2, 5)} ${national.slice(5)}`
}

/** `https://` URL (organization website). */
export function isHttpsUrl(value: string): boolean {
  try {
    const url = new URL(value.trim())
    return url.protocol === 'https:' && Boolean(url.hostname)
  }
  catch {
    return false
  }
}

/** National address parts (API.md §1.3). */
export const NATIONAL_ADDRESS_PATTERNS = {
  building_number: /^\d{4}$/,
  postal_code: /^\d{5}$/,
  additional_number: /^\d{4}$/,
  short_address: /^[A-Z]{4}\d{4}$/,
} as const

export function isNationalAddressPart(part: keyof typeof NATIONAL_ADDRESS_PATTERNS, value: string): boolean {
  return NATIONAL_ADDRESS_PATTERNS[part].test(normalizeDigits(value).trim().toUpperCase())
}

export const PASSWORD_MIN_LENGTH = 8

export type PasswordRule = 'length' | 'lowercase' | 'uppercase' | 'digit' | 'symbol'

export const PASSWORD_RULES: readonly PasswordRule[] = ['length', 'lowercase', 'uppercase', 'digit', 'symbol']

/**
 * The API password rule (ARCHITECTURE §13.9): ≥ 8 characters with a lowercase letter, an uppercase
 * letter, a digit and a symbol. Returns each rule's state for the live checklist.
 */
export function passwordChecks(value: string): Record<PasswordRule, boolean> {
  return {
    length: value.length >= PASSWORD_MIN_LENGTH,
    lowercase: /\p{Ll}/u.test(value),
    uppercase: /\p{Lu}/u.test(value),
    digit: /\p{Nd}/u.test(value),
    symbol: /[^\p{L}\p{N}\s]/u.test(value),
  }
}

export function isStrongPassword(value: string): boolean {
  return Object.values(passwordChecks(value)).every(Boolean)
}

/** An organization lists at most this many activity categories (API.md §1.3). */
export const MAX_CATEGORIES = 20

/** Six digits after normalising Arabic-Indic digits. */
export function isOtpCode(value: string): boolean {
  return /^\d{6}$/.test(normalizeDigits(value).trim())
}
