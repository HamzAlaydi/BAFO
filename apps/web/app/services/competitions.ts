/**
 * Competitions endpoints (API.md §1.4, §1.5): home, competitions, invitations (issuer and invitee
 * sides), attachments and Q&A.
 */
import type { ApiResponse, Page, PageQuery } from '~/types/api/common'
import type {
  Attachment,
  CloseReasonRequest,
  Comment,
  Competition,
  CompetitionListQuery,
  CreateCompetitionRequest,
  ExtendCompetitionRequest,
  Home,
  Invitation,
  InvitationClaimResult,
  InvitationCounts,
  InvitationInput,
  InvitationLookup,
  InvitationsResult,
  InvitationStatus,
  InviteeInvitation,
  IssuerCompetition,
  IssuerCompetitionListItem,
  LinkAttachmentRequest,
  ParticipantCompetitionListItem,
  Suggestion,
  SuggestionQuery,
  UpdateCompetitionRequest,
  UpdateInvitationRequest,
  UploadAttachmentRequest,
} from '~/types/api/competitions'
import { getData, getEnvelope, getPage, multipart, send, sendData, seg } from './http'

const base = (id: string) => `/competitions/${seg(id)}`

// ---------- Home ----------

/** `GET /home`: stats, alerts and recent activity (API.md §2.13). */
export function fetchHome(): Promise<Home> {
  return getData<Home>('/home')
}

// ---------- Competitions ----------

function listQuery(query: CompetitionListQuery) {
  return {
    status: query.status,
    status_group: query.status_group,
    direction: query.direction,
    q: query.q?.trim(),
    sort: query.sort,
    page: query.page,
    per_page: query.per_page ?? 20,
  }
}

/** `GET /competitions?role=issuer`: the caller organization's competitions. */
export function listIssuerCompetitions(query: CompetitionListQuery = {}): Promise<Page<IssuerCompetitionListItem>> {
  return getPage<IssuerCompetitionListItem>('/competitions', { role: 'issuer', ...listQuery(query) })
}

/** `GET /competitions?role=participant`: competitions the organization is invited to or takes part in. */
export function listParticipantCompetitions(query: CompetitionListQuery = {}): Promise<Page<ParticipantCompetitionListItem>> {
  return getPage<ParticipantCompetitionListItem>('/competitions', { role: 'participant', ...listQuery(query) })
}

/** 201 draft (issuer projection). `issuer_plan_required`, `auction_not_enabled`. */
export function createCompetition(body: CreateCompetitionRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', '/competitions', body)
}

/** Projected for the caller; switch on `viewer_role`. Anyone else → 404. */
export function fetchCompetition(id: string): Promise<Competition> {
  return getData<Competition>(base(id))
}

export function updateCompetition(id: string, body: UpdateCompetitionRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('PATCH', base(id), body)
}

/** Draft only (soft delete). */
export function deleteCompetition(id: string): Promise<void> {
  return send('DELETE', base(id))
}

export function publishCompetition(id: string): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', `${base(id)}/publish`, {})
}

export function extendCompetition(id: string, body: ExtendCompetitionRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', `${base(id)}/extend`, body)
}

export function cancelCompetition(id: string, body: CloseReasonRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', `${base(id)}/cancel`, body)
}

/** Close without award (`competitions.award`), status `closed` → `not_awarded`. */
export function closeCompetitionWithoutAward(id: string, body: CloseReasonRequest): Promise<IssuerCompetition> {
  return sendData<IssuerCompetition>('POST', `${base(id)}/close`, body)
}

export function fetchSuggestions(id: string, query: SuggestionQuery = {}): Promise<Suggestion[]> {
  return getData<Suggestion[]>(`${base(id)}/suggestions`, { ...query, q: query.q?.trim() })
}

// ---------- Invitations (issuer) ----------

/** Not paginated (≤ 200), plus `meta.counts`. */
export async function listInvitations(id: string, status?: InvitationStatus[]): Promise<InvitationsResult> {
  const response: ApiResponse<Invitation[]> = await getEnvelope<Invitation[]>(`${base(id)}/invitations`, { status })
  return { invitations: response.data ?? [], counts: (response.meta?.counts ?? {}) as InvitationCounts }
}

/** All-or-nothing: per-row errors in `errors."invitations.{i}.*"` and `details.item_codes`. */
export function createInvitations(id: string, invitations: InvitationInput[]): Promise<Invitation[]> {
  return sendData<Invitation[]>('POST', `${base(id)}/invitations`, { invitations })
}

export function updateInvitation(id: string, invitationId: string, body: UpdateInvitationRequest): Promise<Invitation> {
  return sendData<Invitation>('PATCH', `${base(id)}/invitations/${seg(invitationId)}`, body)
}

/** Draft → deleted (returns `null`); sent or viewed → `revoked` (returns the invitation). */
export async function removeInvitation(id: string, invitationId: string): Promise<Invitation | null> {
  const response = await useApi()<ApiResponse<Invitation> | undefined>(`${base(id)}/invitations/${seg(invitationId)}`, { method: 'DELETE' })
  return response?.data ?? null
}

/** New token, mail re-sent; at most 3 per invitation per day (429). */
export function resendInvitation(id: string, invitationId: string): Promise<void> {
  return send('POST', `${base(id)}/invitations/${seg(invitationId)}/resend`)
}

// ---------- Invitations (invitee) ----------

/** Guest lookup from the e-mail link fragment (`website_url` honeypot always empty). */
export function lookupInvitation(token: string): Promise<InvitationLookup> {
  return sendData<InvitationLookup>('POST', '/invitations/lookup', { token, website_url: '' })
}

export function declineInvitationByToken(token: string, reason?: string | null): Promise<{ status: 'declined' }> {
  return sendData<{ status: 'declined' }>('POST', '/invitations/decline', { token, reason: reason || null })
}

/** 200 → bound; 202 → an OTP was sent to the invited e-mail (send it back as `code`). */
export async function claimInvitation(token: string, code?: string | null): Promise<InvitationClaimResult> {
  const response = await useApi().raw<ApiResponse<InviteeInvitation | { otp_sent_to: string, otp_expires_at: string }>>(
    '/invitations/claim',
    { method: 'POST', body: { token, code: code || null } },
  )
  const data = response._data?.data
  if (response.status === 202 && data && 'otp_sent_to' in data) {
    return { kind: 'otp_sent', otp_sent_to: data.otp_sent_to, otp_expires_at: data.otp_expires_at }
  }
  return { kind: 'bound', invitation: data as InviteeInvitation }
}

/** Returns the participant projection. `plan_required` carries `details.access`. */
export function joinInvitation(invitationId: string): Promise<Competition> {
  return sendData<Competition>('POST', `/invitations/${seg(invitationId)}/join`, { accept_terms: true })
}

export function declineInvitation(invitationId: string, reason?: string | null): Promise<InviteeInvitation> {
  return sendData<InviteeInvitation>('POST', `/invitations/${seg(invitationId)}/decline`, { reason: reason || null })
}

// ---------- Attachments ----------

/** Visible to the viewer (invitees get `invitation_document` only). */
export function listAttachments(id: string): Promise<Attachment[]> {
  return getData<Attachment[]>(`${base(id)}/attachments`)
}

/** Multipart upload (≤ 100 MB; pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip). */
export function uploadAttachment(id: string, body: UploadAttachmentRequest): Promise<Attachment> {
  return sendData<Attachment>('POST', `${base(id)}/attachments`, multipart({ file: body.file, kind: body.kind, title: body.title }))
}

export function createLinkAttachment(id: string, body: LinkAttachmentRequest): Promise<Attachment> {
  return sendData<Attachment>('POST', `${base(id)}/attachments`, body)
}

export function updateAttachment(id: string, attachmentId: string, body: { title?: string | null, sort_order?: number }): Promise<Attachment> {
  return sendData<Attachment>('PATCH', `${base(id)}/attachments/${seg(attachmentId)}`, body)
}

/** Draft or scheduled only. */
export function deleteAttachment(id: string, attachmentId: string): Promise<void> {
  return send('DELETE', `${base(id)}/attachments/${seg(attachmentId)}`)
}

// ---------- Q&A ----------

/** Top-level newest first, replies oldest first; paginated by top-level comment. */
export function listComments(id: string, query: PageQuery = {}): Promise<Page<Comment>> {
  return getPage<Comment>(`${base(id)}/comments`, { page: query.page, per_page: query.per_page })
}

/** `comments_closed` outside `scheduled`/`live`. */
export function postComment(id: string, body: string, parentId?: string | null): Promise<Comment> {
  return sendData<Comment>('POST', `${base(id)}/comments`, { body, parent_id: parentId ?? null })
}

// ---------- Attachment upload with progress (issuer wizard, SCREENS W15 step 5) ----------

export interface UploadProgress {
  loaded: number
  /** `null` when the browser cannot compute the total. */
  total: number | null
}

/**
 * Multipart attachment upload with per-file progress. `fetch` cannot report upload progress, so
 * this one call uses `XMLHttpRequest` with the same headers as `useApi()` (bearer, `Accept-Language`,
 * `X-Platform: web`, `X-Request-Id`), feeds `meta.server_time` to the server clock, ends the session
 * on a 401 and rejects with the normalised `ApiError` (`file_type_not_allowed`, `file_too_large`, …).
 */
export function uploadAttachmentWithProgress(
  id: string,
  body: UploadAttachmentRequest,
  onProgress: (progress: UploadProgress) => void,
  signal?: AbortSignal,
): Promise<Attachment> {
  const nuxtApp = useNuxtApp()
  const token = useAuthToken()
  const serverTime = useServerTime()
  const apiBase = String(useRuntimeConfig().public.apiBase).replace(/\/$/, '')
  const messages = { network: nuxtApp.$i18n.t('errors.network'), unknown: nuxtApp.$i18n.t('errors.unknown') }
  const hadToken = Boolean(token.value)

  return new Promise<Attachment>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    const sentAt = Date.now()
    xhr.open('POST', `${apiBase}${base(id)}/attachments`)
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.setRequestHeader('Accept-Language', nuxtApp.$i18n.locale.value)
    xhr.setRequestHeader('X-Platform', 'web')
    xhr.setRequestHeader('X-Request-Id', createRequestId())
    if (token.value) xhr.setRequestHeader('Authorization', `Bearer ${token.value}`)

    xhr.upload.onprogress = (event) => {
      onProgress({ loaded: event.loaded, total: event.lengthComputable ? event.total : null })
    }
    xhr.onload = () => {
      let parsed: unknown
      try {
        parsed = xhr.responseText ? JSON.parse(xhr.responseText) as unknown : null
      }
      catch {
        parsed = null
      }
      const stamp = (parsed as { meta?: { server_time?: unknown } } | null)?.meta?.server_time
      if (typeof stamp === 'string') serverTime.sync(stamp, sentAt, Date.now())
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve((parsed as ApiResponse<Attachment>).data)
        return
      }
      const error = normalizeApiError({
        status: xhr.status,
        data: parsed,
        response: { status: xhr.status, headers: { get: (name: string) => xhr.getResponseHeader(name) } },
      }, messages)
      if (error.isUnauthenticated && hadToken) useAuthStore().endSession('expired')
      reject(error)
    }
    xhr.onerror = () => reject(normalizeApiError(new TypeError('network'), messages))
    xhr.onabort = () => reject(new ApiError({ status: null, code: 'aborted', message: messages.unknown, errors: {} }))
    signal?.addEventListener('abort', () => xhr.abort(), { once: true })
    xhr.send(multipart({ file: body.file, kind: body.kind, title: body.title }))
  })
}
