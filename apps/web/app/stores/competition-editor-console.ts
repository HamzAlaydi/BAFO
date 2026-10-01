/**
 * Issuer console helpers (SCREENS W19 console, W20 offers log, W22 award), as pure functions:
 * the `seq`-ordered offer log with gap detection (S4), the machine time format of the log
 * (CONVENTIONS §9.1), signed server percentages, and award form hints.
 *
 * Nothing here ranks, projects or decides entitlement: those values come from the server.
 */
import type { IssuerLiveSnapshot, LiveSnapshot, OfferLogEntry, ParticipantStandingRow } from '~/types/api/bidding'
import type { Direction } from '~/types/api/competitions'

/** The live feed keeps the latest 20 accepted offers (SCREENS W19). */
export const OFFER_FEED_SIZE = 20

/** Merges entries by `id` (realtime duplicates and refetches overlap) and orders them by `seq`. */
export function mergeOfferEntries(existing: readonly OfferLogEntry[], incoming: readonly OfferLogEntry[]): OfferLogEntry[] {
  const byId = new Map<string, OfferLogEntry>()
  for (const entry of existing) byId.set(entry.id, entry)
  for (const entry of incoming) byId.set(entry.id, entry)
  return [...byId.values()].sort((a, b) => a.seq - b.seq)
}

/** The highest `seq` held, or 0. */
export function lastOfferSeq(entries: readonly OfferLogEntry[]): number {
  return entries.reduce((max, entry) => Math.max(max, entry.seq), 0)
}

/**
 * S4: an `offer.accepted` whose `seq` skips numbers means events were missed; the caller then
 * fetches `offers/log?after_seq={lastSeq}` and merges.
 */
export function offerSeqGap(lastSeq: number, entry: Pick<OfferLogEntry, 'seq'>): boolean {
  return entry.seq > lastSeq + 1
}

export function isIssuerSnapshot(snapshot: LiveSnapshot | null | undefined): snapshot is IssuerLiveSnapshot {
  return Boolean(snapshot) && Array.isArray((snapshot as IssuerLiveSnapshot).ranking)
}

/** Standing rows the issuer may award or shortlist: participants with a current offer (§7.11, §7.12). */
export function rowsWithOffers(rows: readonly ParticipantStandingRow[]): ParticipantStandingRow[] {
  return rows.filter(row => row.current_amount_minor !== null && row.offers_count > 0)
}

export interface AwardHints {
  /** The selection is not rank 1: a justification reason is required (§7.12). */
  notLeading: boolean
  /** A reserve is set and this amount does not meet it: confirmation and a justification are required. */
  reserveNotMet: boolean
}

/**
 * Form hints for the award workspace (SCREENS W22). They only decide which fields to show; the
 * server answers `award_justification_required` / `award_reserve_confirmation_required` when a
 * field is still missing, and that answer wins. The reserve comparison uses the contract formula
 * `d × (amount − reserve) < 0` (ARCHITECTURE §7.12) on the issuer's own reserve.
 */
export function awardHints(
  row: Pick<ParticipantStandingRow, 'rank' | 'is_leader' | 'current_amount_minor'> | null,
  reservePriceMinor: number | null,
  direction: Direction,
): AwardHints {
  if (!row) return { notLeading: false, reserveNotMet: false }
  const notLeading = row.rank !== 1
  const amount = row.current_amount_minor
  const d = direction === 'tender' ? -1 : 1
  const reserveNotMet = reservePriceMinor !== null && amount !== null && d * (amount - reservePriceMinor) < 0
  return { notLeading, reserveNotMet }
}

/** Minutes between two instants, rounded (the extension banner: "extended by N minutes"). */
export function minutesBetween(fromIso: string | null | undefined, toIso: string | null | undefined): number | null {
  if (!fromIso || !toIso) return null
  const from = Date.parse(fromIso)
  const to = Date.parse(toIso)
  if (Number.isNaN(from) || Number.isNaN(to)) return null
  return Math.round((to - from) / 60_000)
}
