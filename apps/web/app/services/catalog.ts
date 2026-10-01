/** Catalog lookups (API.md §1.2). Prefer `useLookupsStore()`, which caches with ETags per locale. */
import type { ApiResponse } from '~/types/api/common'
import type { CloseReason, CloseReasonKind, Lookups, LookupType } from '~/types/api/catalog'
import { getData, seg } from './http'

export type LookupsResult
  = | { status: 'fresh', lookups: Lookups, etag: string | null }
    | { status: 'not_modified' }

/**
 * `GET /lookups` with `If-None-Match`. A 304 means the cached copy for this locale is still current.
 * The API must expose `ETag` through CORS for the conditional request to be used.
 */
export async function fetchLookups(etag?: string | null): Promise<LookupsResult> {
  const response = await useApi().raw<ApiResponse<Lookups>>('/lookups', {
    headers: etag ? { 'If-None-Match': etag } : undefined,
  })
  if (response.status === 304 || !response._data) return { status: 'not_modified' }
  return { status: 'fresh', lookups: response._data.data, etag: response.headers.get('etag') }
}

/** `GET /lookups/{type}`. */
export function fetchLookup<T>(type: LookupType, query?: { kind?: CloseReasonKind }): Promise<T> {
  return getData<T>(`/lookups/${seg(type)}`, query)
}

/** `GET /lookups/close-reasons?kind=`. */
export function fetchCloseReasons(kind: CloseReasonKind): Promise<CloseReason[]> {
  return fetchLookup<CloseReason[]>('close-reasons', { kind })
}
