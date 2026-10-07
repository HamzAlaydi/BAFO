/** Relative times are used below 7 days; older items show the date (CONVENTIONS §9.1). */
export const RELATIVE_LIMIT_MS = 7 * 24 * 3600_000

export type RelativeTime
  = | { unit: 'now' }
    | { unit: 'minutes' | 'hours' | 'days', value: number }
    | { unit: 'date' }

/**
 * Buckets an instant relative to `nowMs` (server time). The caller renders
 * `common.relative.<unit>` with the Arabic 6-form plural («منذ 5 دقائق» / "5 minutes ago").
 * Future instants (clock skew) count as "just now".
 */
export function relativeTime(iso: string, nowMs: number): RelativeTime {
  const at = Date.parse(iso)
  if (Number.isNaN(at)) return { unit: 'date' }
  const diff = nowMs - at
  if (diff < 60_000) return { unit: 'now' }
  if (diff >= RELATIVE_LIMIT_MS) return { unit: 'date' }
  if (diff < 3600_000) return { unit: 'minutes', value: Math.floor(diff / 60_000) }
  if (diff < 24 * 3600_000) return { unit: 'hours', value: Math.floor(diff / 3600_000) }
  return { unit: 'days', value: Math.floor(diff / (24 * 3600_000)) }
}

export type RelativeDuration = { unit: 'minutes' | 'hours' | 'days', value: number }

/**
 * Buckets a positive duration for "in … " phrases («بعد 3 أيام», "in 3 days"), used for the relative
 * close hint of the schedule step. The caller renders `common.relative.in_<unit>` with the 6-form
 * plural. Durations below a minute count as one minute; negative ones as zero minutes.
 */
export function relativeDuration(ms: number): RelativeDuration {
  if (ms <= 0) return { unit: 'minutes', value: 0 }
  if (ms < 3600_000) return { unit: 'minutes', value: Math.max(1, Math.round(ms / 60_000)) }
  if (ms < 24 * 3600_000) return { unit: 'hours', value: Math.round(ms / 3600_000) }
  return { unit: 'days', value: Math.round(ms / (24 * 3600_000)) }
}
