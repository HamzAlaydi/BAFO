import type { ApiError } from './api-error'

type Translate = (key: string, params?: Record<string, unknown>) => string
type Exists = (key: string) => boolean

/**
 * CONVENTIONS §4.1 error display: `t('errors.<code>')` when the key exists, otherwise the server
 * `message` (already localised by `Accept-Language`). Network failures use `errors.network`.
 */
export function errorMessage(error: unknown, t: Translate, te: Exists): string {
  if (!isApiErrorLike(error)) return t('errors.unknown')
  if (error.code === 'network_error') return t('errors.network')
  const key = `errors.${error.code}`
  if (error.code && te(key)) return t(key)
  return error.message || t('errors.unknown')
}

interface ApiErrorLike {
  code: string
  message: string
  errors: Record<string, string[]>
}

function isApiErrorLike(value: unknown): value is ApiErrorLike {
  return typeof value === 'object' && value !== null && typeof (value as ApiErrorLike).code === 'string'
    && typeof (value as ApiErrorLike).errors === 'object'
}

export interface BoundFieldErrors {
  /** First message per known field path (server wording). */
  fields: Record<string, string>
  /** Messages whose path matches no field on the form: show them in a form-level alert. */
  unmatched: string[]
}

/**
 * Binds `422 validation_failed` field errors to form fields by path (SCREENS S7).
 * `fields` lists the paths the form renders; an alias map covers renamed paths
 * (e.g. `{ 'organization.national_address.street': 'street' }`).
 */
export function bindFieldErrors(
  error: Pick<ApiError, 'errors'>,
  fields: readonly string[],
  aliases: Record<string, string> = {},
): BoundFieldErrors {
  const known = new Set(fields)
  const result: BoundFieldErrors = { fields: {}, unmatched: [] }
  for (const [path, messages] of Object.entries(error.errors)) {
    const message = messages[0]
    if (!message) continue
    const target = aliases[path] ?? path
    if (known.has(target)) {
      result.fields[target] ??= message
    }
    else {
      result.unmatched.push(message)
    }
  }
  return result
}
