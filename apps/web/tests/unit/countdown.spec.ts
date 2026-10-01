import { describe, expect, it } from 'vitest'
import { countdownDisplay, crossedThreshold } from '~/utils/countdown'

describe('countdownDisplay (CONVENTIONS §9.1)', () => {
  it('shows HH:MM:SS under 24 hours, rounding up to whole seconds', () => {
    expect(countdownDisplay((4 * 3600 + 10 * 60 + 59) * 1000)).toMatchObject({ mode: 'clock', clock: '04:10:59' })
    expect(countdownDisplay(58_400)).toMatchObject({ clock: '00:00:59', totalSeconds: 59 })
    expect(countdownDisplay(1)).toMatchObject({ clock: '00:00:01' })
    expect(countdownDisplay(0)).toMatchObject({ clock: '00:00:00', totalSeconds: 0 })
    expect(countdownDisplay(-5000)).toMatchObject({ clock: '00:00:00' })
  })

  it('shows days and HH:MM from 24 hours', () => {
    expect(countdownDisplay((3 * 86_400 + 4 * 3600 + 10 * 60) * 1000)).toEqual({ mode: 'days', days: 3, clock: '04:10', totalSeconds: 3 * 86_400 + 4 * 3600 + 600 })
    expect(countdownDisplay(86_400_000)).toMatchObject({ mode: 'days', days: 1, clock: '00:00' })
    expect(countdownDisplay(86_399_000)).toMatchObject({ mode: 'clock', clock: '23:59:59' })
  })
})

describe('crossedThreshold (SCREENS S3 step 10)', () => {
  it('reports 10, 5 and 1 minute when crossed', () => {
    expect(crossedThreshold(600_001, 600_000)).toBe(10)
    expect(crossedThreshold(300_500, 299_900)).toBe(5)
    expect(crossedThreshold(61_000, 59_000)).toBe(1)
  })

  it('reports nothing between thresholds or when time goes up (extension)', () => {
    expect(crossedThreshold(500_000, 499_000)).toBeNull()
    expect(crossedThreshold(599_000, 900_000)).toBeNull()
  })
})
