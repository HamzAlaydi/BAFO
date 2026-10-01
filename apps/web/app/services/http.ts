/**
 * Helpers shared by `app/services/<module>.ts` (CONVENTIONS §4.1): every call goes through `useApi()`,
 * single resources are unwrapped from `{data}`, page-paginated lists become `Page<T>`.
 */
import type { FetchOptions } from 'ofetch'
import type { ApiMeta, ApiResponse, Page, PagePagination } from '~/types/api/common'

type QueryValue = string | number | boolean | null | undefined | ReadonlyArray<string | number>

/**
 * Drops empty values, sends booleans as `1`/`0` and arrays as comma lists (the API's `status=a,b`).
 */
export function cleanQuery(query: Record<string, QueryValue> | undefined): Record<string, string | number> | undefined {
  if (!query) return undefined
  const result: Record<string, string | number> = {}
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') continue
    if (Array.isArray(value)) {
      if (value.length > 0) result[key] = value.join(',')
    }
    else if (typeof value === 'boolean') {
      result[key] = value ? 1 : 0
    }
    else {
      result[key] = value as string | number
    }
  }
  return Object.keys(result).length > 0 ? result : undefined
}

/** `GET` a single resource and unwrap `data`. */
export async function getData<T>(url: string, query?: Record<string, QueryValue>): Promise<T> {
  const response = await useApi()<ApiResponse<T>>(url, { query: cleanQuery(query) })
  return response.data
}

/** `GET` the whole envelope, for lists that carry extra `meta` keys. */
export function getEnvelope<T>(url: string, query?: Record<string, QueryValue>): Promise<ApiResponse<T>> {
  return useApi()<ApiResponse<T>>(url, { query: cleanQuery(query) })
}

type Method = 'POST' | 'PUT' | 'PATCH' | 'DELETE'

/** A write that returns a resource in `data`. */
export async function sendData<T>(
  method: Method,
  url: string,
  body?: FetchOptions<'json'>['body'],
  headers?: Record<string, string>,
): Promise<T> {
  const response = await useApi()<ApiResponse<T>>(url, { method, body, headers })
  return response.data
}

/** A write answered with 204 (or whose body the caller does not need). */
export async function send(method: Method, url: string, body?: FetchOptions<'json'>['body']): Promise<void> {
  await useApi()(url, { method, body })
}

/** Page pagination from `meta`, with a safe single-page default when the server sent none. */
export function pageFrom<T>(response: ApiResponse<T[]>): Page<T> {
  const items = response.data ?? []
  const meta: ApiMeta = response.meta ?? {}
  const pagination = meta.pagination?.type === 'page'
    ? meta.pagination
    : { type: 'page', current_page: 1, per_page: items.length, has_more: false, total: items.length, last_page: 1 } satisfies PagePagination
  return { items, pagination }
}

/** `GET` a page-paginated list. */
export async function getPage<T>(url: string, query?: Record<string, QueryValue>): Promise<Page<T>> {
  return pageFrom(await getEnvelope<T[]>(url, query))
}

/** Multipart body: `file` plus plain fields (null/undefined skipped). */
export function multipart(fields: Record<string, Blob | string | number | boolean | null | undefined>): FormData {
  const form = new FormData()
  for (const [key, value] of Object.entries(fields)) {
    if (value === null || value === undefined) continue
    if (value instanceof Blob) form.append(key, value)
    else form.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  return form
}

/** Encodes a route parameter (ids are ULIDs, but never trust a caller-supplied string in a path). */
export function seg(value: string): string {
  return encodeURIComponent(value)
}
