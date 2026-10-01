import { describe, expect, it } from 'vitest'
import { CLOSING_SOON_SECONDS, competitionStatusVisual } from '~/utils/competition-status'
import type { CompetitionPhase, CompetitionStatus } from '~/types/api/competitions'

/** The S2 "Competition status visual" table (SCREENS §1), row by row. */
const NOW = Date.parse('2026-11-09T11:00:00.000Z')
const FAR = '2026-11-09T12:00:00.000Z'

describe('competitionStatusVisual', () => {
  const rows: Array<[CompetitionStatus, CompetitionPhase | null, string, string, string, boolean]> = [
    ['draft', null, 'draft', 'neutral', 'pencil', false],
    ['scheduled', null, 'scheduled', 'info', 'calendar_clock', false],
    ['live', 'initial', 'live', 'primary_soft', 'circle_dot', false],
    ['live', 'open', 'live', 'primary_soft', 'circle_dot', false],
    ['live', 'final_window', 'final_window', 'primary_soft', 'timer', true],
    ['live', 'sealed', 'live_sealed', 'primary_soft', 'lock', false],
    ['closed', null, 'closed', 'neutral', 'clipboard_check', false],
    ['bafo_round', null, 'bafo_round', 'inverse', 'brand_mark', false],
    ['awarded', null, 'awarded', 'primary', 'trophy', false],
    ['not_awarded', null, 'not_awarded', 'neutral', 'circle_slash', false],
    ['cancelled', null, 'cancelled', 'danger_soft', 'ban', false],
  ]

  it.each(rows)('%s / %s → %s', (status, phase, key, tone, icon, pulse) => {
    const visual = competitionStatusVisual(status, phase, FAR, 0, NOW)
    expect(visual).toEqual({ key, tone, icon, pulse, overlays: [] })
  })

  it('uses red only for cancelled', () => {
    const tones = rows.map(([status, phase]) => competitionStatusVisual(status, phase, FAR, 0, NOW).tone)
    expect(tones.filter(tone => tone === 'danger_soft')).toHaveLength(1)
  })

  it('adds "closing soon" under 10 minutes to the effective close, on the server clock', () => {
    const closeAt = new Date(NOW + (CLOSING_SOON_SECONDS - 1) * 1000).toISOString()
    expect(competitionStatusVisual('live', 'open', closeAt, 0, NOW).overlays).toEqual(['closing_soon'])
    const later = new Date(NOW + (CLOSING_SOON_SECONDS + 1) * 1000).toISOString()
    expect(competitionStatusVisual('live', 'open', later, 0, NOW).overlays).toEqual([])
  })

  it('adds "extended" after an extension, and both overlays together', () => {
    expect(competitionStatusVisual('live', 'final_window', FAR, 1, NOW).overlays).toEqual(['extended'])
    const soon = new Date(NOW + 60_000).toISOString()
    expect(competitionStatusVisual('live', 'final_window', soon, 2, NOW).overlays).toEqual(['closing_soon', 'extended'])
  })

  it('never shows overlays outside live', () => {
    const soon = new Date(NOW + 60_000).toISOString()
    expect(competitionStatusVisual('bafo_round', null, soon, 3, NOW).overlays).toEqual([])
    expect(competitionStatusVisual('closed', null, soon, 3, NOW).overlays).toEqual([])
  })

  it('tolerates a missing close time', () => {
    expect(competitionStatusVisual('live', 'open', null, null, NOW).overlays).toEqual([])
  })
})
