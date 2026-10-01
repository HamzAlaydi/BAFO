import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useNuxtApp } from '#imports'

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } })
}

describe('useApi', () => {
  const fetchMock = vi.fn<typeof fetch>()

  beforeEach(() => {
    fetchMock.mockReset()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    useAuthToken().value = null
  })

  it('sends JSON, the locale, the platform, a request id and the bearer token to the configured API base', async () => {
    useAuthToken().value = 'test-token'
    fetchMock.mockResolvedValue(jsonResponse(200, { data: { ok: true } }))

    const result = await useApi()<{ data: { ok: boolean } }>('/ping')

    expect(result.data.ok).toBe(true)
    const [request, init] = fetchMock.mock.calls[0]!
    expect(String(request)).toBe(`${useRuntimeConfig().public.apiBase}/ping`)
    const headers = new Headers(init?.headers)
    expect(headers.get('accept')).toBe('application/json')
    expect(headers.get('accept-language')).toBe(useNuxtApp().$i18n.locale.value)
    expect(headers.get('authorization')).toBe('Bearer test-token')
    expect(headers.get('x-platform')).toBe('web')
    expect(headers.get('x-request-id')).toMatch(/^[A-Za-z0-9-]{8,64}$/)
  })

  it('omits the Authorization header when signed out', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: null }))
    await useApi()('/ping')
    const headers = new Headers(fetchMock.mock.calls[0]![1]?.headers)
    expect(headers.has('authorization')).toBe(false)
  })

  it('syncs the server clock from server_time', async () => {
    useServerTime().reset()
    const serverTime = new Date(Date.now() + 60_000).toISOString()
    fetchMock.mockResolvedValue(jsonResponse(200, { data: { id: '01J' }, meta: { server_time: serverTime } }))

    await useApi()('/competitions/01J')

    const clock = useServerTime()
    expect(clock.synced.value).toBe(true)
    expect(clock.offsetMs.value).toBeGreaterThan(55_000)
    expect(clock.offsetMs.value).toBeLessThan(65_000)
    expect(Math.abs(clock.now() - Date.parse(serverTime))).toBeLessThan(5_000)
  })

  it('throws a normalised ApiError with field errors', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'Invalid offer',
      code: 'offer_not_better',
      errors: { amount_minor: ['Must beat the leading offer'] },
    }))

    const error = await useApi()('/offers', { method: 'POST', body: {} }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).toJSON()).toEqual({
      status: 422,
      code: 'offer_not_better',
      message: 'Invalid offer',
      errors: { amount_minor: ['Must beat the leading offer'] },
      details: {},
    })
  })

  it('maps connection failures to network_error with a localised message', async () => {
    fetchMock.mockRejectedValue(new TypeError('fetch failed'))
    const error = await useApi()('/ping').catch((e: unknown) => e) as ApiError
    expect(error.code).toBe('network_error')
    expect(error.message).toBe(useNuxtApp().$i18n.t('errors.network'))
  })

  it('ends the session when the API rejects the token, and remembers why', async () => {
    useAuthToken().value = 'revoked'
    fetchMock.mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }))

    await expect(useApi()('/me')).rejects.toMatchObject({ code: 'unauthenticated', status: 401 })
    expect(useAuthStore().isAuthenticated).toBe(false)
    expect(useAuthStore().endedReason).toBe('expired')
  })

  it('does not end a session that never existed (a wrong password is not an expiry)', async () => {
    const endSession = vi.spyOn(useAuthStore(), 'endSession')
    fetchMock.mockResolvedValue(jsonResponse(401, { message: 'Wrong', code: 'invalid_credentials' }))
    await expect(useApi()('/auth/login', { method: 'POST', body: {} })).rejects.toMatchObject({ code: 'invalid_credentials' })
    expect(endSession).not.toHaveBeenCalled()
    endSession.mockRestore()
  })

  it('raises the account gate on account_inactive and organization_suspended (SCREENS S8)', async () => {
    useAuthToken().value = 'token'
    fetchMock.mockResolvedValue(jsonResponse(403, { message: 'Suspended', code: 'organization_suspended', errors: {} }))
    await useApi()('/home').catch(() => {})
    expect(useAuthStore().gate).toBe('organization_suspended')
    useAuthStore().clear()
  })

  it('enters maintenance on 503 maintenance, signed in or not', async () => {
    fetchMock.mockResolvedValue(jsonResponse(503, { message: 'Back at 10:00', code: 'maintenance', errors: {} }))
    await useApi()('/lookups').catch(() => {})
    const appConfig = useAppConfigStore()
    expect(appConfig.inMaintenance).toBe(true)
    expect(appConfig.maintenanceMessage).toBe('Back at 10:00')
    fetchMock.mockResolvedValue(jsonResponse(200, { data: { ...appConfigFixture(), maintenance: { enabled: false, message: '' } } }))
    await appConfig.checkMaintenance()
    expect(appConfig.inMaintenance).toBe(false)
  })

  it('exposes status and headers through raw()', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ data: { ok: true } }), {
      status: 202,
      headers: { 'content-type': 'application/json', 'ETag': '"abc"' },
    }))
    const response = await useApi().raw<{ data: { ok: boolean } }>('/invitations/claim', { method: 'POST', body: {} })
    expect(response.status).toBe(202)
    expect(response.headers.get('etag')).toBe('"abc"')
    expect(response._data?.data.ok).toBe(true)
  })

  it('decodes JSON error bodies of blob downloads so the code survives', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ message: 'Not ready', code: 'invoice_pdf_not_ready', errors: {} }), {
      status: 409,
      headers: { 'content-type': 'application/json' },
    }))
    const error = await useApi().raw('/billing/invoices/01j/pdf', { responseType: 'blob' }).catch((e: unknown) => e) as ApiError
    expect(error.code).toBe('invoice_pdf_not_ready')
    expect(error.status).toBe(409)
  })

  it('carries Retry-After on rate limits', async () => {
    fetchMock.mockResolvedValue(new Response(JSON.stringify({ message: 'Slow down', code: 'too_many_requests', errors: {} }), {
      status: 429,
      headers: { 'content-type': 'application/json', 'Retry-After': '12' },
    }))
    const error = await useApi()('/auth/login').catch((e: unknown) => e) as ApiError
    expect(error.retryAfterSeconds).toBe(12)
  })
})

function appConfigFixture() {
  return {
    min_version: { ios: '1.0.0', android: '1.0.0' },
    latest_version: { ios: '1.0.0', android: '1.0.0' },
    store_links: { ios: '', android: '' },
    maintenance: { enabled: false, message: '' },
    support: { email: 'help@bafo.test', phone: '', whatsapp: '' },
    realtime: { key: 'k', host: 'localhost', port: 8085, scheme: 'http' },
    legal: { terms: { version: '2026-10-01' } },
    features: { sponsorship: true },
    currency: 'SAR',
    vat_rate_bp: 1500,
    supported_locales: ['ar', 'en'],
    server_time: new Date().toISOString(),
  }
}
