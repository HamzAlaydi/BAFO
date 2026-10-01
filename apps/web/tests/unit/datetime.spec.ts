import { describe, expect, it } from 'vitest'
import {
  formatDate,
  formatMachineTime,
  formatDateTime,
  formatTime,
  splitDuration,
  timeZoneOffsetMinutes,
  utcIsoToZonedInput,
  zonedInputToUtcIso,
} from '~/utils/datetime'

const INSTANT = '2026-09-29T12:05:00Z' // 15:05 in Riyadh

describe('datetime formatting', () => {
  it('uses Gregorian Arabic month names with Latin digits', () => {
    expect(formatDate(INSTANT, 'ar')).toBe('29 سبتمبر 2026')
    expect(formatDate(INSTANT, 'ar')).not.toMatch(/[٠-٩]/)
  })

  it('formats English dates as "29 Sep 2026"', () => {
    expect(formatDate(INSTANT, 'en')).toBe('29 Sep 2026')
  })

  it('shows 12-hour Riyadh time', () => {
    expect(formatTime(INSTANT, 'ar')).toBe('3:05 م')
    expect(formatTime(INSTANT, 'en')).toMatch(/^3:05\sPM$/)
    expect(formatDateTime(INSTANT, 'ar')).toBe('29 سبتمبر 2026، 3:05 م')
  })

  it('crosses the date line in Riyadh, not UTC', () => {
    expect(formatDate('2026-09-29T22:30:00Z', 'en')).toBe('30 Sep 2026')
  })

  it('throws on invalid input', () => {
    expect(() => formatDate('not-a-date', 'ar')).toThrow(RangeError)
  })
})

describe('datetime-local conversion', () => {
  it('knows Riyadh is UTC+3', () => {
    expect(timeZoneOffsetMinutes(new Date(INSTANT), 'Asia/Riyadh')).toBe(180)
    expect(timeZoneOffsetMinutes(new Date(INSTANT), 'UTC')).toBe(0)
  })

  it('round-trips between UTC ISO and Riyadh wall-clock input', () => {
    expect(utcIsoToZonedInput(INSTANT)).toBe('2026-09-29T15:05')
    expect(zonedInputToUtcIso('2026-09-29T15:05')).toBe('2026-09-29T12:05:00.000Z')
    expect(zonedInputToUtcIso('2026-09-30T01:30')).toBe('2026-09-29T22:30:00.000Z')
    expect(zonedInputToUtcIso(utcIsoToZonedInput('2027-01-01T00:00:00Z'))).toBe('2027-01-01T00:00:00.000Z')
  })

  it('handles zones with daylight saving time', () => {
    // 2026-07-01 10:00 in Berlin is CEST (UTC+2)
    expect(zonedInputToUtcIso('2026-07-01T10:00', 'Europe/Berlin')).toBe('2026-07-01T08:00:00.000Z')
    expect(utcIsoToZonedInput('2026-01-15T08:00:00Z', 'Europe/Berlin')).toBe('2026-01-15T09:00')
  })

  it('rejects malformed input values', () => {
    expect(zonedInputToUtcIso('')).toBeNull()
    expect(zonedInputToUtcIso('2026-09-29')).toBeNull()
  })
})

describe('splitDuration', () => {
  it('splits milliseconds into d/h/m/s', () => {
    expect(splitDuration(((2 * 24 + 3) * 3600 + 4 * 60 + 5) * 1000 + 999)).toEqual({
      totalMs: 183_845_999,
      days: 2,
      hours: 3,
      minutes: 4,
      seconds: 5,
    })
  })

  it('clamps negative durations to zero', () => {
    expect(splitDuration(-5000)).toEqual({ totalMs: 0, days: 0, hours: 0, minutes: 0, seconds: 0 })
  })
})

describe('formatMachineTime (CONVENTIONS §9.1)', () => {
  it('formats Riyadh time with milliseconds and Western digits', () => {
    expect(formatMachineTime('2026-11-09T11:59:58.412Z')).toBe('2026-11-09 14:59:58.412')
    expect(formatMachineTime('2026-11-09T21:00:00.007Z')).toBe('2026-11-10 00:00:00.007')
    expect(formatMachineTime('2026-11-09T11:59:58.412Z', { date: false })).toBe('14:59:58.412')
    expect(formatMachineTime('nope')).toBeNull()
    expect(formatMachineTime(null)).toBeNull()
  })
})
