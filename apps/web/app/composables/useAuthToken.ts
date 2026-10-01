import type { CookieRef } from '#app'

/** The `bafo_token` cookie (readable during SSR), shared by the store, the API client and realtime. */
export function useAuthToken(): CookieRef<string | null> {
  return useSharedCookie<string | null>(TOKEN_COOKIE, TOKEN_COOKIE_OPTIONS)
}
