import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { makeMe, makeOrganization, makeTokenPayload, makeUser } from '../fixtures/api'

const identity = vi.hoisted(() => ({
  login: vi.fn(),
  logout: vi.fn(),
  fetchMe: vi.fn(),
  verifyOtp: vi.fn(),
  acceptTeamInvitation: vi.fn(),
  updateMe: vi.fn(),
}))
vi.mock('~/services/identity', () => identity)

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    for (const fn of Object.values(identity)) fn.mockReset()
    useAuthToken().value = null
  })

  it('signs in with a device name and keeps the token out of store state', async () => {
    identity.login.mockResolvedValue(makeTokenPayload({}, 'secret-token'))
    const auth = useAuthStore()

    await auth.login(' sara@issuer.sa ', 'Bafo2026#')

    const body = identity.login.mock.calls[0]![0]
    expect(body).toMatchObject({ email: 'sara@issuer.sa', password: 'Bafo2026#' })
    expect(body.device_name).toMatch(/^Web · .+ on .+$/)
    expect(auth.token).toBe('secret-token')
    expect(useAuthToken().value).toBe('secret-token')
    expect(auth.user?.email).toBe('sara@issuer.sa')
    expect(auth.hasSession).toBe(true)
    expect(JSON.stringify(auth.$state)).not.toContain('secret-token')
  })

  it('answers permission checks from Me.permissions only', async () => {
    identity.fetchMe.mockResolvedValue(makeMe({ permissions: ['competitions.create', 'participation.submit_offers'] }))
    useAuthToken().value = 't'
    const auth = useAuthStore()
    await auth.fetchMe()
    expect(auth.can('competitions.create')).toBe(true)
    expect(auth.can('team.manage')).toBe(false)
    expect(auth.isOwner).toBe(true)
  })

  it('derives the account gate from Me (GET /me is exempt from the API gate)', () => {
    expect(gateFromMe(makeMe())).toBeNull()
    expect(gateFromMe(makeMe({ organization: makeOrganization({ status: 'suspended' }) }))).toBe('organization_suspended')
    expect(gateFromMe(makeMe({ user: makeUser({ status: 'pending_verification', email_verified_at: null }) }))).toBe('email_not_verified')
    expect(gateFromMe(makeMe({ membership: { id: 'm', role: 'member', can_award: false, can_purchase: false, status: 'inactive' } }))).toBe('account_inactive')
  })

  it('raises and clears an API gate', async () => {
    const auth = useAuthStore()
    auth.setSession(makeTokenPayload())
    auth.raiseGate('account_inactive')
    expect(auth.gate).toBe('account_inactive')
    identity.fetchMe.mockResolvedValue(makeMe())
    await auth.fetchMe()
    expect(auth.gate).toBeNull()
  })

  it('remembers why a session ended', () => {
    const auth = useAuthStore()
    auth.setSession(makeTokenPayload())
    auth.endSession('expired')
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.me).toBeNull()
    expect(auth.endedReason).toBe('expired')
  })

  it('always clears the local session on logout, even if revoking fails', async () => {
    const auth = useAuthStore()
    auth.setSession(makeTokenPayload())
    identity.logout.mockRejectedValue(new ApiError({ status: 401, code: 'unauthenticated', message: 'x', errors: {} }))

    await auth.logout()

    expect(identity.logout).toHaveBeenCalledOnce()
    expect(auth.isAuthenticated).toBe(false)
    expect(auth.user).toBeNull()
    expect(auth.endedReason).toBe('signed_out')
    expect(useAuthToken().value).toBeNull()
  })

  it('skips the revoke call when there is no token', async () => {
    await useAuthStore().logout()
    expect(identity.logout).not.toHaveBeenCalled()
  })

  it('verifies the e-mail with the OTP and signs in', async () => {
    identity.verifyOtp.mockResolvedValue(makeTokenPayload())
    const auth = useAuthStore()
    await auth.verifyEmail('sara@issuer.sa', '123456')
    expect(identity.verifyOtp.mock.calls[0]![0]).toMatchObject({ email: 'sara@issuer.sa', code: '123456', purpose: 'email_verification' })
    expect(auth.hasSession).toBe(true)
  })

  it('accepts a team invitation and signs in', async () => {
    identity.acceptTeamInvitation.mockResolvedValue(makeTokenPayload())
    const auth = useAuthStore()
    await auth.acceptTeamInvitation('tok', 'Bafo2026#', 'Bafo2026#')
    expect(identity.acceptTeamInvitation.mock.calls[0]![0]).toMatchObject({ token: 'tok', accept_terms: true })
    expect(auth.isAuthenticated).toBe(true)
  })

  it('syncs the stored language only when it differs, and never throws', async () => {
    const auth = useAuthStore()
    auth.setSession(makeTokenPayload())
    await auth.syncLocale('ar')
    expect(identity.updateMe).not.toHaveBeenCalled()

    identity.updateMe.mockResolvedValue(makeMe({ user: makeUser({ locale: 'en' }) }))
    await auth.syncLocale('en')
    expect(identity.updateMe).toHaveBeenCalledWith({ locale: 'en' })
    expect(auth.user?.locale).toBe('en')

    identity.updateMe.mockRejectedValue(new Error('offline'))
    await expect(auth.syncLocale('ar')).resolves.toBeUndefined()
  })
})
