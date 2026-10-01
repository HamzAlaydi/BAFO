/** Integrations dashboard: vendors, API clients and keys, webhooks, import and export (API.md §1.9, §2.12). */
import type { Category, Region } from './catalog'
import type { ApiFile, IsoDate, IsoDateTime, OrganizationSummary, PageQuery, Ulid } from './common'

/** `id` is the ERP key. */
export interface ExternalRef {
  system: string
  type: string
  id: string
  number: string | null
  url: string | null
}

// ---------- Vendors ----------

export type VendorStatus = 'active' | 'blocked' | 'archived'
export type VendorSource = 'web' | 'api' | 'import'

export interface Vendor {
  id: Ulid
  name: string
  name_en: string | null
  cr_number: string | null
  vat_number: string | null
  email: string
  contact_name: string | null
  phone: string | null
  region: Region | null
  city: string | null
  categories: Category[]
  status: VendorStatus
  linked_organization: OrganizationSummary | null
  source: VendorSource
  notes: string | null
  external_refs: ExternalRef[]
  created_at: IsoDateTime
  updated_at: IsoDateTime
}

export interface VendorListQuery extends PageQuery {
  q?: string
  status?: VendorStatus
  category_id?: Ulid
  region_id?: Ulid
}

export interface VendorInput {
  name: string
  name_en?: string | null
  email: string
  contact_name?: string | null
  phone?: string | null
  cr_number?: string | null
  vat_number?: string | null
  region_id?: Ulid | null
  city?: string | null
  category_ids?: Ulid[]
  status?: Exclude<VendorStatus, 'archived'>
  notes?: string | null
  external_refs?: Array<{ system: string, type: string, id: string, number?: string | null, url?: string | null }>
}

// ---------- API clients and keys ----------

/** ARCHITECTURE §14.4. */
export type ApiScope
  = | 'organization:read'
    | 'lookups:read'
    | 'vendors:read'
    | 'vendors:write'
    | 'competitions:read'
    | 'competitions:write'
    | 'competitions:publish'
    | 'competitions:manage'
    | 'invitations:read'
    | 'invitations:write'
    | 'offers:read'
    | 'awards:read'
    | 'awards:sync'
    | 'webhooks:manage'

export const API_SCOPES: readonly ApiScope[] = [
  'organization:read',
  'lookups:read',
  'vendors:read',
  'vendors:write',
  'competitions:read',
  'competitions:write',
  'competitions:publish',
  'competitions:manage',
  'invitations:read',
  'invitations:write',
  'offers:read',
  'awards:read',
  'awards:sync',
  'webhooks:manage',
]

/** Scopes that are opt-in with a warning when creating a client. */
export const OPT_IN_API_SCOPES: readonly ApiScope[] = ['competitions:publish', 'competitions:manage', 'webhooks:manage']

export interface ApiKey {
  id: Ulid
  prefix: string
  masked: string
  expires_at: IsoDateTime | null
  revoked_at: IsoDateTime | null
  last_used_at: IsoDateTime | null
  created_at: IsoDateTime
}

/** The create response adds the key once. */
export interface ApiKeyWithSecret extends ApiKey {
  key: string
}

export interface ApiClient {
  id: Ulid
  /** Equals `id`. */
  client_id: Ulid
  name: string
  description: string | null
  scopes: ApiScope[]
  status: 'active' | 'suspended' | 'revoked'
  keys: ApiKey[]
  last_used_at: IsoDateTime | null
  created_by: { id: Ulid, name: string } | null
  created_at: IsoDateTime
}

/** Create and rotate responses add the client secret once. */
export interface ApiClientWithSecret extends ApiClient {
  client_secret: string
}

export interface ApiClientInput {
  name: string
  description?: string | null
  scopes: ApiScope[]
}

// ---------- Webhooks ----------

export interface WebhookEventType {
  type: string
  /** Localised. */
  description: string
}

export interface WebhookEndpoint {
  id: Ulid
  url: string
  description: string | null
  event_types: string[]
  status: 'active' | 'disabled'
  disabled_reason: string | null
  failing_since: IsoDateTime | null
  last_success_at: IsoDateTime | null
  last_failure_at: IsoDateTime | null
  created_at: IsoDateTime
}

/** Create and rotate responses add the signing secret once. */
export interface WebhookEndpointWithSecret extends WebhookEndpoint {
  secret: string
}

export interface WebhookEndpointInput {
  url: string
  /** Catalogue types, or `['*']`. */
  event_types: string[]
  description?: string | null
}

export type WebhookEndpointUpdate = Partial<WebhookEndpointInput> & { status?: 'active' | 'disabled' }

export type DeliveryStatus = 'pending' | 'succeeded' | 'failed'

export interface WebhookDelivery {
  id: Ulid
  event: { id: Ulid, type: string, occurred_at: IsoDateTime }
  status: DeliveryStatus
  attempts: number
  next_attempt_at: IsoDateTime | null
  last_attempt_at: IsoDateTime | null
  last_http_status: number | null
  last_error: string | null
  last_response_excerpt: string | null
  last_duration_ms: number | null
  succeeded_at: IsoDateTime | null
  failed_at: IsoDateTime | null
}

export interface DeliveryListQuery extends PageQuery {
  status?: DeliveryStatus
}

// ---------- Import and export ----------

export type JobStatus = 'queued' | 'processing' | 'completed' | 'failed'

export const TERMINAL_JOB_STATUSES: readonly JobStatus[] = ['completed', 'failed']

/** Row codes of ARCHITECTURE §14.7. */
export type ImportRowCode = 'required' | 'invalid_format' | 'unknown_region' | 'unknown_category' | 'duplicate_in_file'

export interface ImportJob {
  id: Ulid
  type: 'vendors'
  mode: 'validate' | 'commit'
  status: JobStatus
  total_rows: number | null
  valid_rows: number | null
  created_rows: number | null
  updated_rows: number | null
  error_rows: number | null
  errors_preview: Array<{ row: number, column: string, code: ImportRowCode | string, message: string }>
  errors_file: ApiFile | null
  source_file: ApiFile | null
  failure_message: string | null
  finished_at: IsoDateTime | null
  created_at: IsoDateTime
}

export type ExportType = 'results' | 'offer_log' | 'awards' | 'vendors'
export type ExportFormat = 'csv' | 'xlsx'

export interface ExportJob {
  id: Ulid
  type: ExportType
  format: ExportFormat
  filters: Record<string, unknown>
  status: JobStatus
  row_count: number | null
  file: ApiFile | null
  failure_message: string | null
  finished_at: IsoDateTime | null
  created_at: IsoDateTime
}

export interface CreateExportRequest {
  type: ExportType
  format: ExportFormat
  /** Required for `results` and `offer_log`. */
  competition_id?: Ulid
  /** Awards only. */
  from?: IsoDate
  to?: IsoDate
}
