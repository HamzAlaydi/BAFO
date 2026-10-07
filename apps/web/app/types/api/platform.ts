/** Platform resources (API.md §1.1, §2.13). */
import type { AppLocale, IsoDate, IsoDateTime, Ulid } from './common'

export type LegalDocumentCode = 'terms' | 'privacy' | 'refund' | 'competition_rules' | 'api_terms'

export const LEGAL_DOCUMENT_CODES: readonly LegalDocumentCode[] = ['terms', 'privacy', 'refund', 'competition_rules', 'api_terms']

/** `platform.release_scope` (RELEASE_SCOPE.md §1.1): shown for information only; clients branch on `flags`. */
export type ReleaseScope = 'core' | 'full'

/**
 * The 22 feature flags of RELEASE_SCOPE.md §1.3, in catalogue order. Clients read `features.flags.<name>`
 * and never the scope itself.
 */
export const FEATURE_FLAGS = [
  'team_management',
  'vendor_directory',
  'integrations_api',
  'csv_import_export',
  'sponsorship',
  'bafo_round',
  'sealed_format',
  'advanced_rules',
  'final_pricing_window',
  'deletion_approval',
  'deleted_competitions',
  'offer_report',
  'login_as',
  'google_signin',
  'dark_mode',
  'billing_invoices',
  'custom_plan_quote',
  'coupons',
  'qa_comments',
  'attachments',
  'extend_competition',
  'cancel_competition',
] as const

export type FeatureFlag = (typeof FEATURE_FLAGS)[number]

export type FeatureFlags = Record<FeatureFlag, boolean>

/** `AppConfig.features` (API.md §2.13, RELEASE_SCOPE.md §1.4). `sponsorship` always equals `flags.sponsorship`. */
export interface AppFeatures {
  release_scope: ReleaseScope
  sponsorship: boolean
  flags: FeatureFlags
}

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
  features: AppFeatures
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
