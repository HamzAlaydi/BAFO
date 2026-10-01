import type { ApiErrorPayload } from '../types/api/common'

type ApiErrorInit = Omit<ApiErrorPayload, 'details'> & {
  status: number | null
  details?: Record<string, unknown>
  retryAfterSeconds?: number | null
}

/** Error thrown by `useApi()` for every failed request: `{ message, code, errors, details }` plus the HTTP status. */
export class ApiError extends Error implements ApiErrorPayload {
  readonly status: number | null
  readonly code: string
  readonly errors: Record<string, string[]>
  readonly details: Record<string, unknown>
  /** From `details.retry_after_seconds` or the `Retry-After` header (429, 503); `null` when absent. */
  readonly retryAfterSeconds: number | null

  constructor(payload: ApiErrorInit) {
    super(payload.message)
    this.name = 'ApiError'
    this.status = payload.status
    this.code = payload.code
    this.errors = payload.errors
    this.details = payload.details ?? {}
    this.retryAfterSeconds = payload.retryAfterSeconds ?? retryAfterFromDetails(this.details)
  }

  /** First message for a form field, e.g. `error.fieldError('email')`. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }

  /** Numeric `details` value (amounts, counts), or null when absent or not a number. */
  detailNumber(key: string): number | null {
    const value = this.details[key]
    return typeof value === 'number' && Number.isFinite(value) ? value : null
  }

  /** String `details` value (timestamps, codes), or null. */
  detailString(key: string): string | null {
    const value = this.details[key]
    return typeof value === 'string' && value !== '' ? value : null
  }

  get isValidation(): boolean {
    return this.status === 422 && this.code === 'validation_failed'
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  get isForbidden(): boolean {
    return this.status === 403
  }

  get isNotFound(): boolean {
    return this.status === 404
  }

  /** No response at all (offline, DNS, CORS, timeout). */
  get isNetwork(): boolean {
    return this.status === null && this.code === 'network_error'
  }

  /** 5xx or no response: the request may be retried. */
  get isRetryable(): boolean {
    return this.isNetwork || (this.status !== null && this.status >= 500 && this.code !== 'maintenance')
  }

  toJSON(): ApiErrorPayload & { status: number | null } {
    return { message: this.message, code: this.code, errors: this.errors, details: this.details, status: this.status }
  }
}

export interface ApiErrorMessages {
  /** Shown when the request never got a response (offline, CORS, DNS). */
  network: string
  /** Shown when the server answered without a usable message. */
  unknown: string
}

const CODE_BY_STATUS: Record<number, string> = {
  400: 'bad_request',
  401: 'unauthenticated',
  403: 'forbidden',
  404: 'not_found',
  405: 'method_not_allowed',
  406: 'not_acceptable',
  409: 'conflict',
  410: 'gone',
  413: 'payload_too_large',
  415: 'unsupported_media_type',
  419: 'session_expired',
  422: 'validation_failed',
  426: 'app_version_unsupported',
  429: 'too_many_requests',
  503: 'service_unavailable',
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function normalizeFieldErrors(value: unknown): Record<string, string[]> {
  if (!isRecord(value)) return {}
  const result: Record<string, string[]> = {}
  for (const [field, messages] of Object.entries(value)) {
    if (Array.isArray(messages)) {
      result[field] = messages.filter((m): m is string => typeof m === 'string')
    }
    else if (typeof messages === 'string') {
      result[field] = [messages]
    }
  }
  return result
}

function codeForStatus(status: number): string {
  return CODE_BY_STATUS[status] ?? (status >= 500 ? 'server_error' : 'http_error')
}

function retryAfterFromDetails(details: Record<string, unknown>): number | null {
  const value = details.retry_after_seconds
  return typeof value === 'number' && Number.isFinite(value) && value >= 0 ? Math.ceil(value) : null
}

/** `Retry-After` is either delta-seconds or an HTTP date. */
export function parseRetryAfter(value: string | null | undefined, now: number = Date.now()): number | null {
  if (!value) return null
  const trimmed = value.trim()
  if (/^\d+$/.test(trimmed)) return Number(trimmed)
  const date = Date.parse(trimmed)
  return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - now) / 1000))
}

function headerValue(response: Record<string, unknown> | undefined, name: string): string | null {
  const headers = response?.headers
  if (headers && typeof (headers as Headers).get === 'function') return (headers as Headers).get(name)
  return null
}

/**
 * Converts anything thrown by ofetch (FetchError with `status`/`data`), a network failure or an
 * unexpected value into an `ApiError`. The server's `{ message, code, errors, details }` wins when present.
 * `body` overrides `error.data` (used when the error body arrived as a Blob and was decoded first).
 */
export function normalizeApiError(error: unknown, messages: ApiErrorMessages, body?: unknown): ApiError {
  if (error instanceof ApiError) return error

  const source = isRecord(error) ? error : {}
  const response = isRecord(source.response) ? source.response : undefined
  const rawStatus = source.status ?? source.statusCode ?? response?.status
  const status = typeof rawStatus === 'number' && rawStatus > 0 ? rawStatus : null

  if (source.name === 'AbortError' || (isRecord(source.cause) && source.cause.name === 'AbortError')) {
    return new ApiError({ status: null, code: 'aborted', message: messages.unknown, errors: {} })
  }

  if (status === null) {
    return new ApiError({ status: null, code: 'network_error', message: messages.network, errors: {} })
  }

  const data = body !== undefined ? body : source.data
  const payload = isRecord(data) ? data : {}
  const message = typeof payload.message === 'string' && payload.message.trim() !== '' ? payload.message : messages.unknown
  const code = typeof payload.code === 'string' && payload.code !== '' ? payload.code : codeForStatus(status)
  const details = isRecord(payload.details) ? payload.details : {}
  const retryAfterSeconds = retryAfterFromDetails(details) ?? parseRetryAfter(headerValue(response, 'retry-after'))

  return new ApiError({ status, code, message, errors: normalizeFieldErrors(payload.errors), details, retryAfterSeconds })
}
