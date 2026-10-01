/** Bidding endpoints (API.md §1.6): live snapshots, offers, BAFO round, award and report. */
import type { AppLocale, ApiResponse } from '~/types/api/common'
import type {
  Award,
  IssueAwardRequest,
  IssuerCompetition,
  LiveSnapshot,
  MyOffer,
  OfferLogEntry,
  OfferLogPage,
  ParticipantAwardView,
  ParticipantStandingRow,
  Report,
  StartBafoRoundRequest,
  SubmitOfferRequest,
  SubmitOfferResponse,
  SubmitOfferResult,
} from '~/types/api'
import { getData, getEnvelope, send, sendData, seg } from './http'

const base = (id: string) => `/competitions/${seg(id)}`

/** Issuer or participant snapshot, by viewer (`Cache-Control: no-store`). Invitees → 403 `not_a_participant`. */
export function fetchLive<T extends LiveSnapshot = LiveSnapshot>(id: string): Promise<T> {
  return getData<T>(`${base(id)}/live`)
}

/** Presence for the issuer's "online now" count; every 20 s while the live room is visible. */
export function sendHeartbeat(id: string): Promise<void> {
  return send('POST', `${base(id)}/live/heartbeat`)
}

/**
 * `POST …/offers` with the **required** `Idempotency-Key` (one per submit intent; reuse it on retry).
 * 201, or 200 with `Idempotent-Replayed: true` on a replay.
 */
export async function submitOffer(id: string, body: SubmitOfferRequest, idempotencyKey: string): Promise<SubmitOfferResult> {
  const response = await useApi().raw<ApiResponse<SubmitOfferResponse>>(`${base(id)}/offers`, {
    method: 'POST',
    body: { amount_minor: body.amount_minor, confirm_outlier: body.confirm_outlier ?? false },
    headers: { 'Idempotency-Key': idempotencyKey },
  })
  const data = response._data?.data as SubmitOfferResponse
  return { ...data, replayed: response.headers.get('idempotent-replayed') === 'true' }
}

/** Newest first, not paginated (≤ 500). */
export function fetchMyOffers(id: string): Promise<MyOffer[]> {
  return getData<MyOffer[]>(`${base(id)}/my-offers`)
}

/** Issuer standings, ordered by rank; participants without offers last. */
export function fetchStandings(id: string): Promise<ParticipantStandingRow[]> {
  return getData<ParticipantStandingRow[]>(`${base(id)}/offers`)
}

/** Issuer offer log, ascending `seq`. Page with `after_seq` while `has_more`. */
export async function fetchOfferLog(id: string, afterSeq = 0, limit = 200): Promise<OfferLogPage> {
  const response: ApiResponse<OfferLogEntry[]> = await getEnvelope<OfferLogEntry[]>(`${base(id)}/offers/log`, { after_seq: afterSeq, limit })
  const entries = response.data ?? []
  const lastSeq = typeof response.meta?.last_seq === 'number' ? response.meta.last_seq : (entries.at(-1)?.seq ?? afterSeq)
  return { entries, last_seq: lastSeq, has_more: response.meta?.has_more === true }
}

export function startBafoRound(id: string, body: StartBafoRoundRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', `${base(id)}/bafo-round`, body)
}

export function issueAward(id: string, body: IssueAwardRequest): Promise<Award> {
  return sendData<Award>('POST', `${base(id)}/award`, body)
}

/** Issuer: full `Award`; participant: the outcome view. `null` when there is no award. */
export function fetchAward<T extends Award | ParticipantAwardView = Award>(id: string): Promise<T | null> {
  return getData<T | null>(`${base(id)}/award`)
}

export function revokeAward(id: string, reason: string): Promise<Award> {
  return sendData<Award>('POST', `${base(id)}/award/revoke`, { reason })
}

/** 200 `ready` or 202 `pending` (poll every 3 s); 409 `report_not_available` before the first close. */
export function fetchReport(id: string, locale?: AppLocale): Promise<Report> {
  return getData<Report>(`${base(id)}/report`, { locale })
}
