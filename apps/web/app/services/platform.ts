/** Platform endpoints (API.md §1.1). */
import type { AppConfig, ContactRequest, CreatedId, Health, LegalDocument, LegalDocumentCode, ServerTime } from '~/types/api/platform'
import { getData, seg, sendData } from './http'

/** `GET /app-config` (guest; never blocked by maintenance or version checks). */
export function fetchAppConfig(): Promise<AppConfig> {
  return getData<AppConfig>('/app-config')
}

/** `GET /time`: the API client records the sample for `useServerTime()`. */
export function fetchServerTime(): Promise<ServerTime> {
  return getData<ServerTime>('/time')
}

export function fetchHealth(): Promise<Health> {
  return getData<Health>('/health')
}

/** `POST /contact` (guest, `guest-forms` limiter, honeypot). */
export function submitContact(body: ContactRequest): Promise<CreatedId> {
  return sendData<CreatedId>('POST', '/contact', body)
}

/** `GET /legal/{code}`: latest published version in the request locale; 404 when none. */
export function fetchLegalDocument(code: LegalDocumentCode): Promise<LegalDocument> {
  return getData<LegalDocument>(`/legal/${seg(code)}`)
}
