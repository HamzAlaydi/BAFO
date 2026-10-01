import type { CompetitionPhase, CompetitionStatus } from '../types/api/competitions'

/** Status chip keys under `competitions.status.*` (SCREENS S2). */
export type CompetitionStatusKey
  = | 'draft'
    | 'scheduled'
    | 'live'
    | 'final_window'
    | 'live_sealed'
    | 'closed'
    | 'bafo_round'
    | 'awarded'
    | 'not_awarded'
    | 'cancelled'

/** Visual tones of the S2 table; mapped to badge tones by `CompetitionStatusChip`. */
export type StatusTone = 'neutral' | 'info' | 'primary_soft' | 'primary' | 'inverse' | 'danger_soft'

/** Icon names (lucide equivalents, plus the brand mark); mapped to components by the chip. */
export type StatusIcon
  = | 'pencil'
    | 'calendar_clock'
    | 'circle_dot'
    | 'timer'
    | 'lock'
    | 'clipboard_check'
    | 'brand_mark'
    | 'trophy'
    | 'circle_slash'
    | 'ban'

/** Overlay pills shown next to the chip while `live` (`competitions.status.closing_soon|extended`). */
export type StatusOverlay = 'closing_soon' | 'extended'

export interface CompetitionStatusVisual {
  key: CompetitionStatusKey
  tone: StatusTone
  icon: StatusIcon
  /** Pulsing dot (static under reduced motion): the final pricing window. */
  pulse: boolean
  overlays: StatusOverlay[]
}

/** Client constant: the first value of setting `bidding.closing_soon_minutes` (SCREENS S2). */
export const CLOSING_SOON_SECONDS = 600

const BY_STATUS: Record<Exclude<CompetitionStatus, 'live'>, Omit<CompetitionStatusVisual, 'overlays'>> = {
  draft: { key: 'draft', tone: 'neutral', icon: 'pencil', pulse: false },
  scheduled: { key: 'scheduled', tone: 'info', icon: 'calendar_clock', pulse: false },
  closed: { key: 'closed', tone: 'neutral', icon: 'clipboard_check', pulse: false },
  bafo_round: { key: 'bafo_round', tone: 'inverse', icon: 'brand_mark', pulse: false },
  awarded: { key: 'awarded', tone: 'primary', icon: 'trophy', pulse: false },
  not_awarded: { key: 'not_awarded', tone: 'neutral', icon: 'circle_slash', pulse: false },
  cancelled: { key: 'cancelled', tone: 'danger_soft', icon: 'ban', pulse: false },
}

function liveVisual(phase: CompetitionPhase | null): Omit<CompetitionStatusVisual, 'overlays'> {
  if (phase === 'final_window') return { key: 'final_window', tone: 'primary_soft', icon: 'timer', pulse: true }
  if (phase === 'sealed') return { key: 'live_sealed', tone: 'primary_soft', icon: 'lock', pulse: false }
  return { key: 'live', tone: 'primary_soft', icon: 'circle_dot', pulse: false }
}

/**
 * Maps the server state to the status chip (SCREENS S2, "Competition status visual"). Pure:
 * `serverNow` is `useServerTime().now()`, never the device clock.
 *
 * Overlays only apply while `live`: "Closing soon" when fewer than 10 minutes remain before
 * `effective_close_at`, "Extended" when `extension_count > 0`.
 */
export function competitionStatusVisual(
  status: CompetitionStatus,
  phase: CompetitionPhase | null,
  effectiveCloseAt: string | null | undefined,
  extensionCount: number | null | undefined,
  serverNow: number,
): CompetitionStatusVisual {
  if (status !== 'live') {
    const visual = BY_STATUS[status] ?? BY_STATUS.draft
    return { ...visual, overlays: [] }
  }
  const overlays: StatusOverlay[] = []
  const closeAt = effectiveCloseAt ? Date.parse(effectiveCloseAt) : Number.NaN
  if (!Number.isNaN(closeAt) && closeAt - serverNow < CLOSING_SOON_SECONDS * 1000) overlays.push('closing_soon')
  if ((extensionCount ?? 0) > 0) overlays.push('extended')
  return { ...liveVisual(phase), overlays }
}
