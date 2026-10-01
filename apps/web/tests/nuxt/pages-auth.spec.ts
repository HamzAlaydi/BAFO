import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeTokenPayload } from '../fixtures/api'
import LoginPage from '~/pages/auth/login.vue'
import RegisterPage from '~/pages/auth/register.vue'
import VerifyPage from '~/pages/auth/verify.vue'
import InvitationLanding from '~/pages/invitations/index.vue'

const identity = vi.hoisted(() => ({
  login: vi.fn(),
  logout: vi.fn(),
  verifyOtp: vi.fn(),
  sendOtp: vi.fn(),
  register: vi.fn(),
  fetchMe: vi.fn(),
}))
const competitions = vi.hoisted(() => ({ lookupInvitation: vi.fn(), declineInvitationByToken: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/identity', () => identity)
vi.mock('~/services/competitions', () => competitions)
mockNuxtImport('navigateTo', () => navigate)

const t = (key: string, params: Record<string, unknown> = {}) => useNuxtApp().$i18n.t(key, params)

async function flush(): Promise<void> {
  for (let i = 0; i < 4; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  for (const fn of [...Object.values(identity), ...Object.values(competitions)]) fn.mockReset()
  navigate.mockReset()
  useAuthStore().clear()
  window.sessionStorage.clear()
  // OTP hand-offs and pending tokens live in useState: start every test from a clean slate.
  clearNuxtState()
})

afterEach(() => {
  window.sessionStorage.clear()
})

describe('W04 sign in', () => {
  async function submit(wrapper: Awaited<ReturnType<typeof mountSuspended>>, email = 'sara@issuer.sa', password = 'Bafo2026#') {
    await wrapper.get('input[type="email"]').setValue(email)
    await wrapper.get('input[autocomplete="current-password"]').setValue(password)
    await wrapper.get('form').trigger('submit')
    await flush()
  }

  it('validates on the client before calling the API', async () => {
    const wrapper = await mountSuspended(LoginPage)
    await wrapper.get('form').trigger('submit')
    expect(identity.login).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('validation.required'))
  })

  it('signs in and goes to the dashboard', async () => {
    identity.login.mockResolvedValue(makeTokenPayload())
    const wrapper = await mountSuspended(LoginPage)
    await submit(wrapper)
    expect(useAuthStore().hasSession).toBe(true)
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/^\/(ar|en)\/dashboard$/), { replace: true })
  })

  it('shows one generic message for wrong credentials', async () => {
    identity.login.mockRejectedValue(new ApiError({ status: 401, code: 'invalid_credentials', message: 'server', errors: {} }))
    const wrapper = await mountSuspended(LoginPage)
    await submit(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toContain(t('errors.invalid_credentials'))
    expect(navigate).not.toHaveBeenCalled()
  })

  it('sends an unverified e-mail to the OTP page', async () => {
    identity.login.mockRejectedValue(new ApiError({ status: 403, code: 'email_not_verified', message: '', errors: {}, details: { otp_expires_at: '2026-10-01T09:10:00.000Z' } }))
    const wrapper = await mountSuspended(LoginPage)
    await submit(wrapper)
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/^\/(ar|en)\/auth\/verify\?email=sara(%40|@)issuer\.sa$/))
  })

  it('shows the full-page account gate for inactive accounts', async () => {
    identity.login.mockRejectedValue(new ApiError({ status: 403, code: 'account_inactive', message: '', errors: {} }))
    const wrapper = await mountSuspended(LoginPage)
    await submit(wrapper)
    expect(wrapper.text()).toContain(t('common.account_gate.account_inactive.title'))
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('counts down on the button after a rate limit', async () => {
    identity.login.mockRejectedValue(new ApiError({ status: 429, code: 'too_many_requests', message: '', errors: {}, retryAfterSeconds: 30 }))
    const wrapper = await mountSuspended(LoginPage)
    await submit(wrapper)
    const button = wrapper.get('button[type="submit"]')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.text()).toContain(t('common.retry_in', { seconds: 30 }))
  })
})

describe('W06 verify e-mail', () => {
  it('verifies the 6-digit code and signs in', async () => {
    identity.verifyOtp.mockResolvedValue(makeTokenPayload())
    const wrapper = await mountSuspended(VerifyPage, { route: '/ar/auth/verify?email=sara@issuer.sa' })
    expect(wrapper.text()).toContain('sara@issuer.sa')
    await wrapper.get('input[autocomplete="one-time-code"]').setValue('123456')
    await flush()
    expect(identity.verifyOtp).toHaveBeenCalledWith(expect.objectContaining({ email: 'sara@issuer.sa', code: '123456', purpose: 'email_verification' }))
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/\/dashboard$/), { replace: true })
  })

  it('explains a wrong code and highlights resend after too many attempts', async () => {
    identity.verifyOtp.mockRejectedValue(new ApiError({ status: 429, code: 'otp_too_many_attempts', message: '', errors: {} }))
    const wrapper = await mountSuspended(VerifyPage, { route: '/ar/auth/verify?email=sara@issuer.sa' })
    await wrapper.get('input[autocomplete="one-time-code"]').setValue('654321')
    await flush()
    expect(wrapper.text()).toContain(t('errors.otp_too_many_attempts'))
    expect(wrapper.find('.bg-warning-soft').exists()).toBe(true)
  })

  it('waits for the resend cooldown the server reports', async () => {
    identity.sendOtp.mockRejectedValue(new ApiError({ status: 429, code: 'otp_resend_cooldown', message: '', errors: {}, details: { retry_after_seconds: 45 } }))
    const wrapper = await mountSuspended(VerifyPage, { route: '/ar/auth/verify?email=sara@issuer.sa' })
    const resend = wrapper.findAll('button').find(button => button.text().includes(t('auth.verify.resend')))!
    await resend.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('auth.verify.resend_in', { seconds: 45 }))
  })
})

describe('W05 register', () => {
  it('validates every section before sending and never sends an incomplete form', async () => {
    const wrapper = await mountSuspended(RegisterPage)
    await wrapper.get('form').trigger('submit')
    await flush()
    expect(identity.register).not.toHaveBeenCalled()
    expect(wrapper.findAll('[aria-invalid="true"]').length).toBeGreaterThan(3)
    expect(wrapper.text()).toContain(t('validation.accept_terms'))
  })
})

describe('W03 invitation landing', () => {
  const lookup = {
    invitation: { id: '01jinv', status: 'viewed', email_masked: 's***@acme.sa', join_deadline: new Date(Date.now() + 86_400_000).toISOString(), sponsored: true },
    competition: {
      id: '01jcomp',
      reference_no: 'BAFO-T-2026-000123',
      title: 'توريد أجهزة حاسب محمول',
      direction: 'tender',
      format: 'live',
      status: 'scheduled',
      phase: null,
      currency: 'SAR',
      price_basis: 'excl_vat',
      category: { id: 'c', code: 'it', name: 'أجهزة تقنية', is_other: false, auction_allowed: true },
      region: { id: 'r', code: 'RIY', name: 'الرياض' },
      issuer: { id: 'o', name: 'شركة المصدر', logo_url: null, verified: true },
      rules: {},
      rules_summary: ['مناقصة: الأقل سعراً يفوز.'],
      schedule: { bidding_opens_at: null, scheduled_close_at: null, effective_close_at: null, invitation_cutoff_at: null },
      server_time: new Date().toISOString(),
    },
    next_step: 'register',
  }

  it('reads the token from the fragment, strips it and shows the teaser', async () => {
    window.history.replaceState(null, '', '/ar/invitations#t=invite-token')
    competitions.lookupInvitation.mockResolvedValue(lookup)
    const wrapper = await mountSuspended(InvitationLanding)
    await flush()
    expect(competitions.lookupInvitation).toHaveBeenCalledWith('invite-token')
    expect(window.location.hash).toBe('')
    expect(window.sessionStorage.getItem('bafo.pending_invitation')).toBe('invite-token')
    expect(wrapper.text()).toContain('توريد أجهزة حاسب محمول')
    expect(wrapper.text()).toContain(t('sponsorship.badge.fees_covered'))
    expect(wrapper.text()).toContain(t('invitations.landing.register_cta'))
  })

  it('explains an invalid link and forgets the token', async () => {
    window.history.replaceState(null, '', '/ar/invitations#t=bad')
    competitions.lookupInvitation.mockRejectedValue(new ApiError({ status: 404, code: 'invitation_invalid', message: '', errors: {} }))
    const wrapper = await mountSuspended(InvitationLanding)
    await flush()
    expect(wrapper.text()).toContain(t('errors.invitation_invalid'))
    expect(window.sessionStorage.getItem('bafo.pending_invitation')).toBeNull()
  })

  it('opens the decline dialog directly with action=decline and declines by token', async () => {
    window.history.replaceState(null, '', '/ar/invitations#t=invite-token&action=decline')
    competitions.lookupInvitation.mockResolvedValue(lookup)
    competitions.declineInvitationByToken.mockResolvedValue({ status: 'declined' })
    const wrapper = await mountSuspended(InvitationLanding, { attachTo: document.body })
    await flush()
    const confirm = wrapper.findAll('button').find(button => button.text().includes(t('invitations.decline.confirm')))!
    await confirm.trigger('click')
    await flush()
    expect(competitions.declineInvitationByToken).toHaveBeenCalledWith('invite-token', null)
    expect(wrapper.text()).toContain(t('invitations.decline.done_title'))
    wrapper.unmount()
  })
})
