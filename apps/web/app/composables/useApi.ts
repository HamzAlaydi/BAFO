import type { ApiClient } from '~/types/runtime'

/**
 * First-party API client (plugin `api.ts`): base URL from `runtimeConfig.public.apiBase`, bearer token
 * from the `bafo_token` cookie, `Accept-Language` from the current locale, `X-Platform: web` and a
 * fresh `X-Request-Id`. Failed requests throw `ApiError` (`{ message, code, errors, details, status }`).
 * Responses carrying `server_time` sync `useServerTime()`.
 *
 *   const { data } = await useApi()<ApiResponse<Me>>('/me')
 *   const response = await useApi().raw<ApiResponse<Lookups>>('/lookups')   // status + headers
 *
 * Prefer the per-module functions in `app/services/<module>.ts`.
 */
export function useApi(): ApiClient {
  return useNuxtApp().$api as ApiClient
}
