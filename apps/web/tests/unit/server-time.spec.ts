import { describe, expect, it } from 'vitest'
import { CLOCK_SAMPLE_WINDOW, computeClockOffset, extractServerTime, median, pushSample } from '~/utils/server-time'

describe('computeClockOffset', () => {
  const server = '2026-09-29T12:00:10.000Z'
  const serverMs = Date.parse(server)

  it('uses the request midpoint to cancel network delay', () => {
    // request sent 400ms before the response, server stamped at the midpoint
    expect(computeClockOffset(server, serverMs - 5000 + 200, serverMs - 5000 - 200)).toBe(5000)
  })

  it('falls back to the receive time without a send time', () => {
    expect(computeClockOffset(server, serverMs + 1500)).toBe(-1500)
  })

  it('ignores a send time after the receive time', () => {
    expect(computeClockOffset(server, serverMs, serverMs + 10)).toBe(0)
  })

  it('returns null for invalid timestamps', () => {
    expect(computeClockOffset('soon', Date.now())).toBeNull()
  })
})

describe('extractServerTime', () => {
  it('finds server_time at the top level, in meta or in data', () => {
    expect(extractServerTime({ server_time: 'a' })).toBe('a')
    expect(extractServerTime({ data: {}, meta: { server_time: 'b' } })).toBe('b')
    expect(extractServerTime({ data: { id: '01J', server_time: 'c' } })).toBe('c')
  })

  it('returns null when absent', () => {
    expect(extractServerTime({ data: [] })).toBeNull()
    expect(extractServerTime(null)).toBeNull()
    expect(extractServerTime('text')).toBeNull()
  })
})

describe('median of clock samples (SCREENS S3 step 2)', () => {
  it('takes the middle value, or the mean of the two middle values', () => {
    expect(median([])).toBeNull()
    expect(median([40])).toBe(40)
    expect(median([10, 30])).toBe(20)
    expect(median([500, -20, 30])).toBe(30)
  })

  it('keeps only the last three samples', () => {
    let samples: number[] = []
    for (const sample of [1, 2, 3, 4, 5]) samples = pushSample(samples, sample)
    expect(samples).toEqual([3, 4, 5])
    expect(CLOCK_SAMPLE_WINDOW).toBe(3)
  })

  it('ignores a single outlier sample', () => {
    const samples = [1000, 1010, 60_000]
    expect(median(samples)).toBe(1010)
  })
})
