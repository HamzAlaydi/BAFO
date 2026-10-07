import { describe, expect, it } from 'vitest'
import { activeQuickPick, DURATION_QUICK_PICKS, quickPickCloseAt, quickPickOpensMs } from '~/stores/competition-editor-schedule'
import { relativeDuration } from '~/utils/relative-time'

const MIN = 60_000
const HOUR = 60 * MIN
const DAY = 24 * HOUR
const NOW = Date.parse('2026-11-01T09:02:30.000Z')
const iso = (ms: number) => new Date(ms).toISOString()

/** RELEASE_SCOPE.md §2.3: the duration quick picks of the schedule step. */
describe('duration quick picks', () => {
  it('lists the six chips with their durations', () => {
    expect(DURATION_QUICK_PICKS.map(pick => pick.key)).toEqual(['hour', 'hours_3', 'day', 'days_3', 'week', 'custom'])
    expect(DURATION_QUICK_PICKS.map(pick => pick.ms)).toEqual([HOUR, 3 * HOUR, DAY, 3 * DAY, 7 * DAY, null])
  })

  it('counts from the opening time, or from the server time rounded up to 5 minutes when opening at publish', () => {
    const opens = Date.parse('2026-11-02T06:00:00.000Z')
    expect(quickPickOpensMs(iso(opens), NOW)).toBe(opens)
    expect(quickPickOpensMs(null, NOW)).toBe(Date.parse('2026-11-01T09:05:00.000Z'))
    expect(quickPickOpensMs(null, Date.parse('2026-11-01T09:05:00.000Z'))).toBe(Date.parse('2026-11-01T09:05:00.000Z'))
  })

  it('computes the close for every pick and nothing for custom', () => {
    const opens = Date.parse('2026-11-02T06:00:00.000Z')
    expect(quickPickCloseAt(iso(opens), 'hour', NOW)).toBe(iso(opens + HOUR))
    expect(quickPickCloseAt(iso(opens), 'hours_3', NOW)).toBe(iso(opens + 3 * HOUR))
    expect(quickPickCloseAt(iso(opens), 'day', NOW)).toBe(iso(opens + DAY))
    expect(quickPickCloseAt(iso(opens), 'days_3', NOW)).toBe(iso(opens + 3 * DAY))
    expect(quickPickCloseAt(iso(opens), 'week', NOW)).toBe(iso(opens + 7 * DAY))
    expect(quickPickCloseAt(iso(opens), 'custom', NOW)).toBeNull()
    expect(quickPickCloseAt(null, 'day', NOW)).toBe('2026-11-02T09:05:00.000Z')
  })

  it('shows a pick selected while the close matches its duration within 60 s, custom otherwise', () => {
    const opens = Date.parse('2026-11-02T06:00:00.000Z')
    expect(activeQuickPick(iso(opens), iso(opens + 3 * DAY), NOW)).toBe('days_3')
    expect(activeQuickPick(iso(opens), iso(opens + 3 * DAY + 59_000), NOW)).toBe('days_3')
    expect(activeQuickPick(iso(opens), iso(opens + 3 * DAY + 61_000), NOW)).toBe('custom')
    expect(activeQuickPick(null, '2026-11-01T10:05:00.000Z', NOW)).toBe('hour')
    expect(activeQuickPick(null, null, NOW)).toBe('custom')
    expect(activeQuickPick(null, 'not a date', NOW)).toBe('custom')
  })
})

describe('relative close hint', () => {
  it('buckets a future duration for «يُغلق بعد …»', () => {
    expect(relativeDuration(-5)).toEqual({ unit: 'minutes', value: 0 })
    expect(relativeDuration(20_000)).toEqual({ unit: 'minutes', value: 1 })
    expect(relativeDuration(45 * MIN)).toEqual({ unit: 'minutes', value: 45 })
    expect(relativeDuration(3 * HOUR)).toEqual({ unit: 'hours', value: 3 })
    expect(relativeDuration(3 * DAY + 2 * HOUR)).toEqual({ unit: 'days', value: 3 })
    expect(relativeDuration(7 * DAY)).toEqual({ unit: 'days', value: 7 })
  })
})
