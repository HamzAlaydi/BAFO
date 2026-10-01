import type { CookieOptions, CookieRef } from '#app'

const cookieRefs = new WeakMap<object, Map<string, CookieRef<unknown>>>()

/**
 * `useCookie()` that returns the same ref for the same cookie name within one app instance
 * (one per request during SSR). Separate `useCookie()` refs for one cookie do not update each
 * other synchronously, so state such as the session token or theme must share a single ref.
 */
export function useSharedCookie<T>(name: string, options: CookieOptions<T> & { readonly?: false }): CookieRef<T> {
  const nuxtApp = useNuxtApp()
  let refs = cookieRefs.get(nuxtApp)
  if (!refs) {
    refs = new Map()
    cookieRefs.set(nuxtApp, refs)
  }
  let cookie = refs.get(name) as CookieRef<T> | undefined
  if (!cookie) {
    cookie = useCookie<T>(name, options)
    refs.set(name, cookie as CookieRef<unknown>)
  }
  return cookie
}
