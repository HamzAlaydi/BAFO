import { describe, expect, it } from 'vitest'
import { relativeTime } from '~/utils/relative-time'

const NOW = Date.parse('2026-10-08T12:00:00.000Z')
const ago = (ms: number) => new Date(NOW - ms).toISOString()

describe('relativeTime (CONVENTIONS §9.1)', () => {
  it('buckets recent instants', () => {
    expect(relativeTime(ago(30_000), NOW)).toEqual({ unit: 'now' })
    expect(relativeTime(ago(5 * 60_000), NOW)).toEqual({ unit: 'minutes', value: 5 })
    expect(relativeTime(ago(3 * 3600_000), NOW)).toEqual({ unit: 'hours', value: 3 })
    expect(relativeTime(ago(2 * 86_400_000), NOW)).toEqual({ unit: 'days', value: 2 })
  })

  it('uses the date from 7 days on, and for invalid input', () => {
    expect(relativeTime(ago(7 * 86_400_000), NOW)).toEqual({ unit: 'date' })
    expect(relativeTime('nope', NOW)).toEqual({ unit: 'date' })
  })

  it('treats future instants (clock skew) as now', () => {
    expect(relativeTime(new Date(NOW + 90_000).toISOString(), NOW)).toEqual({ unit: 'now' })
  })
})
