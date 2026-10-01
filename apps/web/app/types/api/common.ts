/**
 * Shapes shared by every module of the first-party API (`/api/app/v1`).
 * Envelope, pagination, errors and data types follow docs/build/API.md §0.4–§0.7 and §2.5.
 * Field names stay snake_case, exactly as the API sends them (CONVENTIONS §4.1).
 */

export type AppLocale = 'ar' | 'en'

/** RFC 3339 UTC timestamp with milliseconds, e.g. `2026-10-01T12:59:58.412Z`. */
export type IsoDateTime = string

/** `YYYY-MM-DD`. */
export type IsoDate = string

/** 26-char lowercase ULID (`public_id`). */
export type Ulid = string

export type Currency = 'SAR'

/** Prices always exclude VAT. */
export type PriceBasis = 'excl_vat'

/** `meta.pagination` of page-based lists (app v1: `?page=&per_page=`). */
export interface PagePagination {
  type: 'page'
  current_page: number
  per_page: number
  has_more: boolean
  total?: number
  last_page?: number
}

/** `meta.pagination` of cursor-based lists (public v1). */
export interface CursorPagination {
  type: 'cursor'
  per_page: number
  next_cursor: string | null
  prev_cursor: string | null
  has_more: boolean
}

export type PaginationMeta = PagePagination | CursorPagination

/** App v1 always sends `meta.server_time`; list endpoints add their own keys (`seats`, `counts`, …). */
export interface ApiMeta {
  pagination?: PaginationMeta
  server_time?: IsoDateTime
  [key: string]: unknown
}

export interface ApiResponse<T> {
  data: T
  meta?: ApiMeta
}

/** A page of a page-paginated list, as returned by the services. */
export interface Page<T> {
  items: T[]
  pagination: PagePagination
}

export interface PageQuery {
  page?: number
  per_page?: number
}

/**
 * Normalised error thrown by `useApi()` for every failed request. `details` carries the
 * machine-readable extras some codes document (e.g. `required_amount_minor`); `{}` when absent.
 */
export interface ApiErrorPayload {
  message: string
  code: string
  errors: Record<string, string[]>
  details: Record<string, unknown>
}

export interface Money {
  amount_minor: number
  currency: Currency
}

/**
 * `File` resource (API.md §2.5). Named `ApiFile` so it does not shadow the DOM `File` used by uploads.
 * Download with `useFileDownload().download(file.download_path, file.name)`.
 */
export interface ApiFile {
  id: Ulid
  name: string
  mime_type: string
  extension: string
  size_bytes: number
  /** Absolute path on the API origin, e.g. `/api/app/v1/files/01j…/download`. */
  download_path: string
  created_at: IsoDateTime
}

/** `OrganizationSummary`, embedded in other resources. */
export interface OrganizationSummary {
  id: Ulid
  name: string
  logo_url: string | null
  verified: boolean
}

/** Plan reference embedded in subscriptions, `Me` and `Home`. */
export interface PlanRef {
  id: Ulid
  code: string
  name: string
}
