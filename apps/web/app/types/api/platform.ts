/** Platform resources (API.md §1.1, §2.13). */
import type { AppLocale, IsoDate, IsoDateTime, Ulid } from './common'

export type LegalDocumentCode = 'terms' | 'privacy' | 'refund' | 'competition_rules' | 'api_terms'

export const LEGAL_DOCUMENT_CODES: readonly LegalDocumentCode[] = ['terms', 'privacy', 'refund', 'competition_rules', 'api_terms']

export interface RealtimeConfig {
  key: string
  host: string
  port: number
  scheme: 'http' | 'https'
}

/** `GET /app-config` (API.md §2.13). */
export interface AppConfig {
  min_version: { ios: string, android: string }
  latest_version: { ios: string, android: string }
  store_links: { ios: string, android: string }
  /** `message` is localised by `Accept-Language`. */
  maintenance: { enabled: boolean, message: string }
  support: { email: string, phone: string, whatsapp: string }
  realtime: RealtimeConfig
  legal: Partial<Record<LegalDocumentCode, { version: string }>>
  features: { sponsorship: boolean }
  currency: 'SAR'
  vat_rate_bp: number
  supported_locales: AppLocale[]
  server_time: IsoDateTime
}

export interface ServerTime {
  server_time: IsoDateTime
}

export interface Health {
  status: 'ok' | 'degraded'
  checks: Record<'database' | 'redis' | 'queue', string>
}

export interface ContactRequest {
  name: string
  email: string
  phone?: string | null
  company?: string | null
  subject: string
  message: string
  /** Honeypot (API.md §0.8): a visually hidden field people never fill. */
  website_url: string
}

/** `GET /legal/{code}`: the latest published version in the request locale. */
export interface LegalDocument {
  code: LegalDocumentCode
  locale: AppLocale
  version: IsoDate | string
  title: string
  body_markdown: string
  published_at: IsoDateTime
}

export interface CreatedId {
  id: Ulid
}
