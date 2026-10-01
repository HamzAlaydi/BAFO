import type { CookieOptions } from '#app'

/** Name of the cookie that carries the Sanctum personal access token. */
export const TOKEN_COOKIE = 'bafo_token'

/** 30 days; the API can revoke the token earlier. Never put the token in a URL. */
export const TOKEN_COOKIE_OPTIONS: CookieOptions<string | null> & { readonly?: false } = {
  sameSite: 'lax',
  secure: !import.meta.dev,
  path: '/',
  maxAge: 60 * 60 * 24 * 30,
  default: () => null,
}
