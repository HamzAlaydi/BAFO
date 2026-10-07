/**
 * Draft schedule preview for the wizard (SCREENS W15 step 3, §6 G3; RELEASE_SCOPE.md §2.3), as pure
 * functions.
 *
 * Drafts have no derived times until publish (ARCHITECTURE §7.2 "Derived values at publish"), so
 * the wizard computes a **display-only** estimate with the same formulas and labels it
 * «تقديري، يُثبَّت عند النشر». The server recomputes everything at publish.
 *
 * The duration quick picks («ساعة، 3 ساعات، يوم، 3 أيام، أسبوع، مخصص») compute the close from the
 * opening time, or from the server time rounded up to the next 5 minutes when the draft opens at
 * publish («يُحتسب من لحظة النشر»).
 */
import type { Rules } from '~/types/api/competitions'
import type { EditorIssue } from './competition-editor-rules'

/** Settings defaults of ARCHITECTURE §15.3 (R16 and the invitation cutoff). */
export const EDITOR_SCHEDULE_BOUNDS = {
  minDurationMinutes: 10,
  maxDurationDays: 90,
  inviteCutoffMinutes: 60,
  /** R16: at publish, `opens ≥ now − 60 s`. */
  publishGraceMs: 60_000,
} as const

const MINUTE = 60_000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

// ---------- Duration quick picks (RELEASE_SCOPE.md §2.3) ----------

export type DurationQuickPick = 'hour' | 'hours_3' | 'day' | 'days_3' | 'week' | 'custom'

/** The chips in display order; `custom` has no duration and shows the date-time picker. */
export const DURATION_QUICK_PICKS: ReadonlyArray<{ key: DurationQuickPick, ms: number | null }> = [
  { key: 'hour', ms: HOUR },
  { key: 'hours_3', ms: 3 * HOUR },
  { key: 'day', ms: DAY },
  { key: 'days_3', ms: 3 * DAY },
  { key: 'week', ms: 7 * DAY },
  { key: 'custom', ms: null },
]

/** «فور النشر» drafts count from the server time rounded **up** to the next 5 minutes. */
export const QUICK_PICK_ROUNDING_MS = 5 * MINUTE

/** A pick is shown selected while `close − opens` matches its duration within this tolerance. */
export const QUICK_PICK_TOLERANCE_MS = 60_000

/** The instant a quick pick counts from: the opening time, or "now" rounded up to 5 minutes. */
export function quickPickOpensMs(opensIso: string | null, nowMs: number): number {
  const opens = parse(opensIso)
  if (opens !== null) return opens
  return Math.ceil(nowMs / QUICK_PICK_ROUNDING_MS) * QUICK_PICK_ROUNDING_MS
}

/** The close time (UTC ISO) a quick pick gives; `null` for `custom`. */
export function quickPickCloseAt(opensIso: string | null, pick: DurationQuickPick, nowMs: number): string | null {
  const duration = DURATION_QUICK_PICKS.find(item => item.key === pick)?.ms ?? null
  if (duration === null) return null
  return new Date(quickPickOpensMs(opensIso, nowMs) + duration).toISOString()
}

/** The pick whose duration matches `close − opens` within 60 s; `custom` otherwise (also without a close). */
export function activeQuickPick(opensIso: string | null, closeIso: string | null, nowMs: number): DurationQuickPick {
  const close = parse(closeIso)
  if (close === null) return 'custom'
  const duration = close - quickPickOpensMs(opensIso, nowMs)
  const match = DURATION_QUICK_PICKS.find(item => item.ms !== null && Math.abs(duration - item.ms) <= QUICK_PICK_TOLERANCE_MS)
  return match?.key ?? 'custom'
}

export interface EditorScheduleInput {
  /** UTC ISO, or null for "when published". */
  biddingOpensAt: string | null
  scheduledCloseAt: string | null
  rules: Pick<Rules, 'final_window_minutes' | 'auto_extend'>
  /** Server-clock now (`useServerTime().now()`). */
  nowMs: number
}

export interface EditorSchedulePreview {
  opensOnPublish: boolean
  /** The opening time, or "now" as an estimate when the draft opens at publish. */
  opensAtMs: number
  closeAtMs: number | null
  durationMs: number | null
  finalWindowStartsAtMs: number | null
  invitationCutoffAtMs: number | null
  /** "Latest possible close" with auto-extend: close + max × by. */
  hardStopAtMs: number | null
}

function parse(value: string | null): number | null {
  if (!value) return null
  const ms = Date.parse(value)
  return Number.isNaN(ms) ? null : ms
}

/** ARCHITECTURE §7.2 derived values, estimated for a draft. */
export function editorSchedulePreview(input: EditorScheduleInput): EditorSchedulePreview {
  const opensAt = parse(input.biddingOpensAt)
  const opensAtMs = opensAt ?? input.nowMs
  const closeAtMs = parse(input.scheduledCloseAt)
  if (closeAtMs === null) {
    return { opensOnPublish: opensAt === null, opensAtMs, closeAtMs: null, durationMs: null, finalWindowStartsAtMs: null, invitationCutoffAtMs: null, hardStopAtMs: null }
  }
  const finalWindow = input.rules.final_window_minutes
  const finalWindowStartsAtMs = finalWindow !== null ? closeAtMs - finalWindow * MINUTE : null
  const cutoff = finalWindowStartsAtMs ?? closeAtMs - EDITOR_SCHEDULE_BOUNDS.inviteCutoffMinutes * MINUTE
  const autoExtend = input.rules.auto_extend
  const hardStopAtMs = autoExtend.enabled && autoExtend.by_seconds !== null && autoExtend.max_extensions !== null
    ? closeAtMs + autoExtend.max_extensions * autoExtend.by_seconds * 1000
    : null
  return {
    opensOnPublish: opensAt === null,
    opensAtMs,
    closeAtMs,
    durationMs: closeAtMs - opensAtMs,
    finalWindowStartsAtMs,
    invitationCutoffAtMs: Math.max(cutoff, opensAtMs),
    hardStopAtMs,
  }
}

/**
 * R16 and the publish part of R11 (feedback only). Keys live under `competitions.setup.schedule.issues`.
 *
 * Every message states the value and the bound (RELEASE_SCOPE.md §2.3): «المدة 4 دقائق فقط؛ الحد
 * الأدنى 10 دقائق.», «المدة 95 يوماً؛ الحد الأقصى 90 يوماً.» (`count` carries the plural choice).
 * A close time that has already passed blocks saving: it can never become a valid schedule.
 */
export function editorScheduleIssues(input: EditorScheduleInput): EditorIssue[] {
  const issues: EditorIssue[] = []
  const opensAt = parse(input.biddingOpensAt)
  const closeAt = parse(input.scheduledCloseAt)
  const base = 'competitions.setup.schedule.issues'

  if (closeAt === null) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.close_required`, when: 'publish' })
  }
  if (opensAt !== null && opensAt < input.nowMs - EDITOR_SCHEDULE_BOUNDS.publishGraceMs) {
    issues.push({ field: 'bidding_opens_at', key: `${base}.opens_in_past`, when: 'publish' })
  }
  if (closeAt === null) return issues

  if (closeAt <= input.nowMs) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.close_in_past`, when: 'save' })
    return issues
  }
  if (opensAt !== null && closeAt <= opensAt) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.close_after_open`, when: 'save' })
    return issues
  }
  const duration = closeAt - (opensAt ?? input.nowMs)
  if (duration < EDITOR_SCHEDULE_BOUNDS.minDurationMinutes * MINUTE) {
    const minutes = Math.max(0, Math.floor(duration / MINUTE))
    issues.push({ field: 'scheduled_close_at', key: `${base}.min_duration`, when: 'publish', params: { minutes, min: EDITOR_SCHEDULE_BOUNDS.minDurationMinutes }, count: minutes })
  }
  else if (duration > EDITOR_SCHEDULE_BOUNDS.maxDurationDays * DAY) {
    const days = Math.ceil(duration / DAY)
    issues.push({ field: 'scheduled_close_at', key: `${base}.max_duration`, when: 'publish', params: { days, max: EDITOR_SCHEDULE_BOUNDS.maxDurationDays }, count: days })
  }
  const finalWindow = input.rules.final_window_minutes
  if (finalWindow !== null && finalWindow * MINUTE >= duration) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.final_window_too_long`, when: 'publish', params: { minutes: finalWindow } })
  }
  return issues
}

/** Whole minutes of a duration (for the "Duration" line), floored at 0. */
export function editorDurationMinutes(ms: number | null): number | null {
  return ms === null ? null : Math.max(0, Math.floor(ms / MINUTE))
}
