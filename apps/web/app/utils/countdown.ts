import { splitDuration } from './datetime'

/** The last 5 minutes use the warning tone (CONVENTIONS §9.1): never red, never flashing. */
export const COUNTDOWN_WARNING_MS = 5 * 60_000

/** Screen-reader announcements (SCREENS S3 step 10): 10 and 5 minutes polite, 1 minute assertive. */
export const COUNTDOWN_ANNOUNCE_MINUTES = [10, 5, 1] as const

export interface CountdownDisplay {
  /** `days` → "N days HH:MM" (≥ 24 h); `clock` → `HH:MM:SS`. */
  mode: 'days' | 'clock'
  days: number
  /** `HH:MM` in `days` mode, `HH:MM:SS` in `clock` mode (tabular, Western digits). */
  clock: string
  /** Whole seconds remaining (the repaint key). */
  totalSeconds: number
}

const pad = (value: number) => String(value).padStart(2, '0')

/**
 * CONVENTIONS §9.1 countdown format. The caller renders `days` with the pluralised
 * `common.countdown.days` message followed by `clock` («3 أيام 04:10» / "3 days 04:10").
 */
export function countdownDisplay(remainingMs: number): CountdownDisplay {
  // Whole seconds, rounded up: "00:00:00" appears exactly when the time is up.
  const totalSeconds = Math.ceil(Math.max(0, remainingMs) / 1000)
  const { days, hours, minutes, seconds } = splitDuration(totalSeconds * 1000)
  if (days > 0) {
    return { mode: 'days', days, clock: `${pad(hours)}:${pad(minutes)}`, totalSeconds }
  }
  return { mode: 'clock', days: 0, clock: `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`, totalSeconds }
}

/**
 * The announcement threshold (minutes) crossed between two remaining times, if any.
 * `previousMs` is the last rendered value; returns null when no threshold lies in (next, previous].
 */
export function crossedThreshold(previousMs: number, nextMs: number): number | null {
  for (const minutes of COUNTDOWN_ANNOUNCE_MINUTES) {
    const threshold = minutes * 60_000
    if (previousMs > threshold && nextMs <= threshold) return minutes
  }
  return null
}
