import type { AppLocale } from '../types/api'

/** Deadlines are shown in Riyadh time (D5). Saudi Arabia has no DST. */
export const RIYADH_TIME_ZONE = 'Asia/Riyadh'

/** Gregorian calendar and Latin digits, even for Arabic ("29 سبتمبر 2026"). */
const INTL_LOCALE: Record<AppLocale, string> = {
  ar: 'ar-SA-u-ca-gregory-nu-latn',
  en: 'en-US-u-ca-gregory-nu-latn',
}

type DateInput = string | number | Date

function toDate(value: DateInput): Date {
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) {
    throw new RangeError(`Invalid date: ${String(value)}`)
  }
  return date
}

const formatterCache = new Map<string, Intl.DateTimeFormat>()

function formatter(locale: AppLocale, options: Intl.DateTimeFormatOptions): Intl.DateTimeFormat {
  const key = `${locale}|${JSON.stringify(options)}`
  let cached = formatterCache.get(key)
  if (!cached) {
    cached = new Intl.DateTimeFormat(INTL_LOCALE[locale], options)
    formatterCache.set(key, cached)
  }
  return cached
}

function part(parts: Intl.DateTimeFormatPart[], type: Intl.DateTimeFormatPartTypes): string {
  return parts.find(p => p.type === type)?.value ?? ''
}

/** "29 سبتمبر 2026" / "29 Sep 2026" */
export function formatDate(value: DateInput, locale: AppLocale, timeZone = RIYADH_TIME_ZONE): string {
  const date = toDate(value)
  if (locale === 'ar') {
    return formatter('ar', { day: 'numeric', month: 'long', year: 'numeric', timeZone }).format(date)
  }
  // Built from parts: engines disagree on "Sep" vs "Sept" and on day/month order for en.
  const parts = formatter('en', { day: 'numeric', month: 'short', year: 'numeric', timeZone }).formatToParts(date)
  return `${part(parts, 'day')} ${part(parts, 'month')} ${part(parts, 'year')}`
}

/** "3:05 م" / "3:05 PM" (12-hour clock) */
export function formatTime(value: DateInput, locale: AppLocale, timeZone = RIYADH_TIME_ZONE): string {
  return formatter(locale, { hour: 'numeric', minute: '2-digit', hour12: true, timeZone }).format(toDate(value))
}

/** "29 سبتمبر 2026، 3:05 م" / "29 Sep 2026, 3:05 PM" */
export function formatDateTime(value: DateInput, locale: AppLocale, timeZone = RIYADH_TIME_ZONE): string {
  const separator = locale === 'ar' ? '، ' : ', '
  return `${formatDate(value, locale, timeZone)}${separator}${formatTime(value, locale, timeZone)}`
}

/** Offset of `timeZone` from UTC at `instant`, in minutes (Riyadh → 180). */
export function timeZoneOffsetMinutes(instant: Date, timeZone: string): number {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hourCycle: 'h23',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).formatToParts(instant)
  const n = (type: Intl.DateTimeFormatPartTypes) => Number(part(parts, type))
  const asUtc = Date.UTC(n('year'), n('month') - 1, n('day'), n('hour'), n('minute'), n('second'))
  return Math.round((asUtc - Math.floor(instant.getTime() / 1000) * 1000) / 60_000)
}

const pad = (value: number, size = 2) => String(value).padStart(size, '0')

/** UTC ISO → value for `<input type="datetime-local">` in `timeZone` ("2026-09-29T15:05"). */
export function utcIsoToZonedInput(iso: string, timeZone = RIYADH_TIME_ZONE): string {
  const date = toDate(iso)
  const shifted = new Date(date.getTime() + timeZoneOffsetMinutes(date, timeZone) * 60_000)
  return `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}-${pad(shifted.getUTCDate())}T${pad(shifted.getUTCHours())}:${pad(shifted.getUTCMinutes())}`
}

/** `<input type="datetime-local">` value in `timeZone` → UTC ISO ("2026-09-29T12:05:00.000Z"). */
export function zonedInputToUtcIso(value: string, timeZone = RIYADH_TIME_ZONE): string | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value)
  if (!match) return null
  const [, y, mo, d, h, mi, s] = match
  const wallClockAsUtc = Date.UTC(Number(y), Number(mo) - 1, Number(d), Number(h), Number(mi), Number(s ?? 0))
  const offset = timeZoneOffsetMinutes(new Date(wallClockAsUtc), timeZone)
  return new Date(wallClockAsUtc - offset * 60_000).toISOString()
}

export interface DurationParts {
  totalMs: number
  days: number
  hours: number
  minutes: number
  seconds: number
}

/** Splits a non-negative duration into d/h/m/s. Negative input clamps to zero. */
export function splitDuration(ms: number): DurationParts {
  const totalMs = Math.max(0, ms)
  const totalSeconds = Math.floor(totalMs / 1000)
  return {
    totalMs,
    days: Math.floor(totalSeconds / 86_400),
    hours: Math.floor((totalSeconds % 86_400) / 3600),
    minutes: Math.floor((totalSeconds % 3600) / 60),
    seconds: totalSeconds % 60,
  }
}

/**
 * Machine-readable Riyadh time for offer logs and receipts (CONVENTIONS §9.1, SCREENS S6):
 * `2026-11-09 14:59:58.412`, or `14:59:58.412` with `date: false`. Western digits; the caller adds the
 * «(KSA)» label (`offers.machine_time`). Null for a missing or invalid value.
 */
export function formatMachineTime(value: DateInput | null | undefined, options: { date?: boolean } = {}): string | null {
  if (value === null || value === undefined || value === '') return null
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return null
  const shifted = new Date(date.getTime() + timeZoneOffsetMinutes(date, RIYADH_TIME_ZONE) * 60_000)
  const time = `${pad(shifted.getUTCHours())}:${pad(shifted.getUTCMinutes())}:${pad(shifted.getUTCSeconds())}.${pad(shifted.getUTCMilliseconds(), 3)}`
  if (options.date === false) return time
  return `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}-${pad(shifted.getUTCDate())} ${time}`
}
