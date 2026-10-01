/**
 * Draft schedule preview for the wizard (SCREENS W15 step 4, §6 G3), as pure functions.
 *
 * Drafts have no derived times until publish (ARCHITECTURE §7.2 "Derived values at publish"), so
 * the wizard computes a **display-only** estimate with the same formulas and labels it
 * «تقديري، يُثبَّت عند النشر». The server recomputes everything at publish.
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
const DAY = 24 * 60 * MINUTE

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

  if (opensAt !== null && closeAt <= opensAt) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.close_after_open`, when: 'save' })
    return issues
  }
  const duration = closeAt - (opensAt ?? input.nowMs)
  if (duration < EDITOR_SCHEDULE_BOUNDS.minDurationMinutes * MINUTE) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.min_duration`, when: 'publish', params: { minutes: EDITOR_SCHEDULE_BOUNDS.minDurationMinutes } })
  }
  else if (duration > EDITOR_SCHEDULE_BOUNDS.maxDurationDays * DAY) {
    issues.push({ field: 'scheduled_close_at', key: `${base}.max_duration`, when: 'publish', params: { days: EDITOR_SCHEDULE_BOUNDS.maxDurationDays } })
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
