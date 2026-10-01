import { ofetch, type FetchOptions, type ResponseType } from 'ofetch'
import type { Pinia } from 'pinia'
import type { AccountGate } from '~/stores/auth'
import type { ApiClient } from '~/types/runtime'

const ACCOUNT_GATE_CODES = new Set(['account_inactive', 'organization_suspended', 'email_not_verified'])

/**
 * First-party API client (`useApi()`), one configured ofetch instance per app/request.
 *
 * - Sends `Accept`, `Accept-Language` (current locale), `X-Platform: web`, `X-Request-Id` and the bearer token.
 * - Feeds every `server_time` into `useServerTime()` (ARCHITECTURE §9.6).
 * - Throws `ApiError` for every failure, including error bodies of blob downloads.
 * - Applies the session-wide page states of SCREENS S8: a rejected token ends the session,
 *   `account_inactive` / `organization_suspended` / `email_not_verified` raise the account gate,
 *   and `maintenance` switches the app to the maintenance state.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const config = useRuntimeConfig()
  const token = useAuthToken()
  const serverTime = useServerTime()
  const sentAt = new WeakMap<object, number>()
  const pinia = nuxtApp.$pinia as Pinia

  const client = ofetch.create({
    baseURL: config.public.apiBase,
    retry: 0,
    timeout: 30_000,
    onRequest({ options }) {
      sentAt.set(options, Date.now())
      const headers = new Headers(options.headers)
      headers.set('Accept', 'application/json')
      headers.set('Accept-Language', nuxtApp.$i18n.locale.value)
      // API.md §0.2: the platform drives version checks and the offer channel; the request id
      // is echoed back by the API for support and log correlation.
      headers.set('X-Platform', 'web')
      if (!headers.has('X-Request-Id')) headers.set('X-Request-Id', createRequestId())
      if (token.value) {
        headers.set('Authorization', `Bearer ${token.value}`)
      }
      options.headers = headers
    },
    onResponse({ options, response }) {
      const stamp = extractServerTime(response._data)
      if (stamp) serverTime.sync(stamp, sentAt.get(options), Date.now())
    },
  })

  const messages = () => ({
    network: nuxtApp.$i18n.t('errors.network'),
    unknown: nuxtApp.$i18n.t('errors.unknown'),
  })

  /** Error bodies of `responseType: 'blob'` requests arrive as Blobs; decode JSON ones so the code survives. */
  async function decodeBody(cause: unknown): Promise<unknown> {
    const data = (cause as { data?: unknown } | null)?.data
    if (typeof Blob === 'undefined' || !(data instanceof Blob)) return undefined
    try {
      return JSON.parse(await data.text()) as unknown
    }
    catch {
      return undefined
    }
  }

  function applySessionEffects(error: ApiError, hadToken: boolean): void {
    if (error.code === 'maintenance') {
      useAppConfigStore(pinia).enterMaintenance(error.message)
      return
    }
    if (!hadToken) return
    if (error.isUnauthenticated) {
      useAuthStore(pinia).endSession('expired')
    }
    else if (error.status === 403 && ACCOUNT_GATE_CODES.has(error.code)) {
      useAuthStore(pinia).raiseGate(error.code as AccountGate)
    }
  }

  async function run<T>(call: () => Promise<T>): Promise<T> {
    const hadToken = Boolean(token.value)
    try {
      return await call()
    }
    catch (cause) {
      const error = normalizeApiError(cause, messages(), await decodeBody(cause))
      applySessionEffects(error, hadToken)
      throw error
    }
  }

  const api = (<T>(request: string, options?: FetchOptions<'json'>) =>
    run(() => client<T>(request, options))) as ApiClient

  api.raw = <T, R extends ResponseType = 'json'>(request: string, options?: FetchOptions<R>) =>
    run(() => client.raw<T, R>(request, options))

  return { provide: { api: api as ApiClient } }
})
