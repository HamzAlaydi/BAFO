import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeAppConfig, makeMe, makeOrganization, makeTokenPayload } from '../fixtures/api'
import {
  makeCouponValidation,
  makeCustomPlan,
  makeCustomQuote,
  makeInvoice,
  makeOverview,
  makePayment,
  makePlan,
  makeSubscription,
  makeVoucher,
  pageOf,
} from '../fixtures/billing'
import type { Me } from '~/types/api/identity'
import BillingPage from '~/pages/dashboard/billing/index.vue'
import PlansPage from '~/pages/dashboard/billing/plans.vue'
import CheckoutPage from '~/pages/dashboard/billing/checkout/index.vue'
import ReturnPage from '~/pages/dashboard/billing/checkout/return.vue'
import InvoicesPage from '~/pages/dashboard/billing/invoices/index.vue'
import InvoicePage from '~/pages/dashboard/billing/invoices/[id].vue'

const billing = vi.hoisted(() => ({
  fetchPlans: vi.fn(),
  fetchCustomQuote: vi.fn(),
  fetchSubscription: vi.fn(),
  startTrial: vi.fn(),
  validateCoupon: vi.fn(),
  checkoutSubscription: vi.fn(),
  fetchPayment: vi.fn(),
  verifyPayment: vi.fn(),
  listInvoices: vi.fn(),
  fetchInvoice: vi.fn(),
  listVouchers: vi.fn(),
  invoicePdfPath: (id: string) => `/billing/invoices/${id}/pdf`,
  checkoutReturnUrl: (origin: string, locale: string) => `${origin}/${locale}/dashboard/billing/checkout/return`,
}))
const identity = vi.hoisted(() => ({ fetchMe: vi.fn(), logout: vi.fn() }))
const competitions = vi.hoisted(() => ({ fetchHome: vi.fn(), fetchCompetition: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
const download = vi.hoisted(() => vi.fn())
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/identity', () => identity)
vi.mock('~/services/competitions', () => competitions)
mockNuxtImport('navigateTo', () => navigate)
mockNuxtImport('useFileDownload', () => () => ({ download, downloading: ref(false) }))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)
const money = (minor: number) => formatMoney(minor, 'ar')

/** vue-i18n renders a missing key as the key itself: none may reach the page. */
function expectNoRawKeys(text: string): void {
  expect(text).not.toMatch(/\b(landing|billing|integrations|vendors|common|errors|validation)\.[a-z_]+\.[a-z_]+/)
}

async function flush(times = 6): Promise<void> {
  for (let i = 0; i < times; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

const completeOrg = () => makeOrganization({ billing_profile_complete: true, billing_profile_missing: [] })

/** Signs in with `Me` overrides; `GET /me` (refetched by some pages) answers the same `Me`. */
function signIn(overrides: Partial<Me> = {}): void {
  useAuthStore().setSession(makeTokenPayload(overrides))
  identity.fetchMe.mockResolvedValue(makeMe(overrides))
}

beforeEach(() => {
  for (const group of [billing, identity, competitions]) {
    for (const fn of Object.values(group)) if (vi.isMockFunction(fn)) fn.mockReset()
  }
  navigate.mockReset()
  download.mockReset()
  competitions.fetchHome.mockResolvedValue({ issuer: null, participant: null, team: null, subscription: null, alerts: [], activities: [] })
  signIn({ organization: completeOrg() })
  // The custom plan quote, coupons and invoice pages are flag-gated: these tests run against the full product.
  useAppConfigStore().config = makeAppConfig()
  sessionStorage.clear()
})

afterEach(() => {
  vi.useRealTimers()
})

// ---------- W31 Plans ----------

describe('W31 plans', () => {
  it('shows server prices with the struck list price, the current plan and checkout links', async () => {
    signIn({ organization: completeOrg(), subscription: { plan: { id: '01j9plan000000000000000pro', code: 'pro', name: 'باقة برو' }, source: 'paid', status: 'active', ends_at: '2026-11-01T00:00:00.000Z', days_left: 21, total_days: 31 } })
    billing.fetchPlans.mockResolvedValue([makePlan(), makePlan({ id: 'plus', code: 'plus', name: 'باقة بلس', is_featured: false, monthly_list_price_minor: null, monthly_price_minor: 90000 })])
    const wrapper = await mountSuspended(PlansPage)
    await flush()
    expect(wrapper.text()).toContain(money(150000))
    expect(wrapper.find('del').text()).toContain(money(300000))
    expect(wrapper.text()).toContain(t('billing.plans.current'))
    expect(wrapper.text()).toContain(t('billing.plans.renew'))
    expectNoRawKeys(wrapper.text())
    const links = wrapper.findAll('a').map(link => link.attributes('href') ?? '')
    expect(links.some(href => href.includes('/dashboard/billing/checkout') && href.includes('plan=01j9plan000000000000000pro') && href.includes('interval=monthly'))).toBe(true)

    await wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'annual')!.setValue(true)
    await flush()
    expect(wrapper.text()).toContain(money(1500000))
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').includes('interval=annual'))).toBe(true)
  })

  it('replaces the call to action without the purchase permission (S10)', async () => {
    signIn({ organization: completeOrg(), permissions: ['billing.view'] })
    billing.fetchPlans.mockResolvedValue([makePlan()])
    const wrapper = await mountSuspended(PlansPage)
    await flush()
    expect(wrapper.text()).toContain(t('billing.common.purchase_permission'))
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').includes('/checkout'))).toBe(false)
  })

  it('quotes the custom plan from the server, debounced, and flags seats out of range', async () => {
    billing.fetchPlans.mockResolvedValue([makeCustomPlan()])
    billing.fetchCustomQuote.mockImplementation((seats: number) => Promise.resolve(makeCustomQuote({ seats })))
    const wrapper = await mountSuspended(PlansPage)
    await flush()
    expect(billing.fetchCustomQuote).toHaveBeenCalledWith(4, 'monthly')
    expect(wrapper.text()).toContain(money(57500 * 4))

    const input = wrapper.get('input[inputmode="numeric"]')
    await input.setValue('6')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flush()
    expect(billing.fetchCustomQuote).toHaveBeenLastCalledWith(6, 'monthly')
    expect(wrapper.text()).toContain(money(57500 * 6))
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').includes('seats=6'))).toBe(true)

    const calls = billing.fetchCustomQuote.mock.calls.length
    await input.setValue('99')
    await new Promise(resolve => setTimeout(resolve, 350))
    await flush()
    expect(wrapper.text()).toContain(t('billing.plans.custom.seats_range', { min: 4, max: 50 }))
    expect(billing.fetchCustomQuote.mock.calls.length).toBe(calls)
  })

  it('offers the free trial where plans are chosen, when the server says it is available', async () => {
    signIn({ organization: makeOrganization({ billing_profile_complete: true, billing_profile_missing: [], trial_available: true }) })
    billing.fetchPlans.mockResolvedValue([makePlan()])
    billing.startTrial.mockResolvedValue(makeSubscription({ source: 'trial', interval: null, amounts: null }))
    const wrapper = await mountSuspended(PlansPage)
    await flush()
    identity.fetchMe.mockClear()
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.overview.trial.start')))!.trigger('click')
    await flush()
    expect(billing.startTrial).toHaveBeenCalledTimes(1)
    expect(identity.fetchMe).toHaveBeenCalled()
  })

  it('shows the error state when plans cannot load, with retry', async () => {
    billing.fetchPlans.mockRejectedValueOnce(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    const wrapper = await mountSuspended(PlansPage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.server_error'))
    billing.fetchPlans.mockResolvedValue([makePlan()])
    await wrapper.findAll('button').find(button => button.text().includes(t('common.actions.retry')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(makePlan().name)
  })
})

// ---------- W32 Checkout ----------

const CHECKOUT = '/ar/dashboard/billing/checkout?plan=01j9plan000000000000000pro&interval=monthly'

describe('W32 checkout', () => {
  it('is forbidden without billing.purchase', async () => {
    signIn({ organization: completeOrg(), permissions: ['billing.view'] })
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    expect(wrapper.text()).toContain(t('billing.common.purchase_permission'))
    expect(billing.fetchPlans).not.toHaveBeenCalled()
  })

  it('blocks payment behind the billing profile gate with a way back', async () => {
    signIn()
    billing.fetchPlans.mockResolvedValue([makePlan()])
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    expect(wrapper.text()).toContain(t('organization.billing_profile.title'))
    const gateLink = wrapper.findAll('a').find(link => link.text().includes(t('billing.checkout.profile_gate.complete')))!
    expect(decodeURIComponent(gateLink.attributes('href') ?? '')).toContain('/dashboard/organization?return=/ar/dashboard/billing/checkout')
    const continueButton = wrapper.findAll('button').find(button => button.text().includes(t('billing.checkout.continue')))!
    expect(continueButton.attributes('disabled')).toBeDefined()
  })

  it('applies a coupon, creates the payment with one idempotency key and shows the server amounts before paying', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan()])
    billing.validateCoupon.mockResolvedValue(makeCouponValidation())
    const payment = makePayment({ discount_minor: 15000, vat_minor: 20250, total_minor: 155250, coupon: { code: 'LAUNCH10' } })
    billing.checkoutSubscription.mockResolvedValue(payment)
    const assign = vi.spyOn(window.location, 'assign').mockImplementation(() => {})
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()

    await wrapper.get('input[dir="ltr"]').setValue('launch10')
    await wrapper.get('form').trigger('submit')
    await flush()
    expect(billing.validateCoupon).toHaveBeenCalledWith({ purpose: 'subscription', plan_id: '01j9plan000000000000000pro', interval: 'monthly', seats: null, code: 'launch10' })
    expect(wrapper.text()).toContain(money(15000))

    await wrapper.findAll('button').find(button => button.text().includes(t('billing.checkout.continue')))!.trigger('click')
    await flush()
    const [body, key] = billing.checkoutSubscription.mock.calls[0]!
    expect(body).toMatchObject({ plan_id: '01j9plan000000000000000pro', interval: 'monthly', seats: null, coupon_code: 'LAUNCH10' })
    expect(body.return_url).toMatch(/\/ar\/dashboard\/billing\/checkout\/return$/)
    expect(key).toMatch(/^[0-9a-f-]{36}$/)
    expect(wrapper.text()).toContain(t('billing.checkout.confirm.title'))
    expect(wrapper.text()).toContain(money(155250))
    expectNoRawKeys(wrapper.text())

    await wrapper.findAll('button').find(button => button.text().includes(money(155250)))!.trigger('click')
    expect(assign).toHaveBeenCalledWith(payment.redirect_url)
    expect(JSON.parse(sessionStorage.getItem('bafo.checkout_memory') ?? '{}')[payment.id]).toMatchObject({ planId: '01j9plan000000000000000pro', interval: 'monthly' })
    assign.mockRestore()
  })

  it('reuses the idempotency key after a network error, and uses a new one for a new intent', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan()])
    billing.checkoutSubscription.mockRejectedValue(new ApiError({ status: null, code: 'network_error', message: '', errors: {} }))
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    const clickContinue = async () => {
      await wrapper.findAll('button').find(button => button.text().includes(t('billing.checkout.continue')))!.trigger('click')
      await flush()
    }
    await clickContinue()
    expect(wrapper.text()).toContain(t('billing.checkout.retry_network'))
    await clickContinue()
    const keys = billing.checkoutSubscription.mock.calls.map(call => call[1])
    expect(keys[0]).toBe(keys[1])

    await wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'annual')!.setValue(true)
    await flush()
    await clickContinue()
    const last = billing.checkoutSubscription.mock.calls.at(-1)!
    expect(last[0].interval).toBe('annual')
    expect(last[1]).not.toBe(keys[0])
  })

  it('goes straight to the return page for a zero total', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan()])
    billing.checkoutSubscription.mockResolvedValue(makePayment({ status: 'succeeded', redirect_url: null, total_minor: 0 }))
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.checkout.continue')))!.trigger('click')
    await flush()
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/\/dashboard\/billing\/checkout\/return\?payment=01j9pay0000000000000000001$/))
  })

  it('explains an early renewal with the server date', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan()])
    billing.checkoutSubscription.mockRejectedValue(new ApiError({ status: 409, code: 'subscription_renewal_too_early', message: '', errors: {}, details: { renewable_from: '2026-10-15T00:00:00.000Z' } }))
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.checkout.continue')))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('billing.checkout.renewal_from', { date: formatDate('2026-10-15T00:00:00.000Z', 'ar') }))
  })

  it('shows the unknown plan state', async () => {
    billing.fetchPlans.mockResolvedValue([makePlan({ id: 'other' })])
    const wrapper = await mountSuspended(CheckoutPage, { route: CHECKOUT })
    await flush()
    expect(wrapper.text()).toContain(t('billing.checkout.plan_missing.title'))
  })
})

// ---------- W33 Payment return ----------

describe('W33 payment return', () => {
  it('confirms the subscription this payment activated and offers the way back', async () => {
    billing.fetchPayment.mockResolvedValue(makePayment({ status: 'succeeded', paid_at: '2026-10-01T09:01:00.000Z', invoice_id: '01j9inv0000000000000000001' }))
    billing.fetchSubscription.mockResolvedValue(makeOverview({ current: makeSubscription({ id: '01j9sub0000000000000000002', ends_at: '2026-11-01T00:00:00.000Z' }) }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await flush(10)
    expect(billing.fetchPayment).toHaveBeenCalledWith('01j9pay0000000000000000001')
    expect(identity.fetchMe).toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('billing.payment_return.subscription.body', { plan: 'باقة برو', date: formatDate('2026-11-01T00:00:00.000Z', 'ar') }))
    expect(wrapper.text()).toContain(money(172500))
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').endsWith('/dashboard/billing'))).toBe(true)
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').includes('/dashboard/billing/invoices/01j9inv0000000000000000001'))).toBe(true)
  })

  it('describes a renewal that starts later (not the current subscription)', async () => {
    billing.fetchPayment.mockResolvedValue(makePayment({ status: 'succeeded' }))
    const upcoming = makeSubscription({ id: '01j9sub0000000000000000002', status: 'pending_payment', starts_at: '2026-11-01T00:00:00.000Z', ends_at: '2026-12-01T00:00:00.000Z' })
    billing.fetchSubscription.mockResolvedValue(makeOverview({ upcoming }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await flush(10)
    expect(wrapper.text()).toContain(t('billing.payment_return.subscription.renewed_body', { plan: 'باقة برو', start: formatDate('2026-11-01T00:00:00.000Z', 'ar'), end: formatDate('2026-12-01T00:00:00.000Z', 'ar') }))
  })

  it('uses neutral copy for a payer without billing.view', async () => {
    signIn({ organization: completeOrg(), permissions: ['billing.purchase'] })
    billing.fetchPayment.mockResolvedValue(makePayment({ status: 'succeeded' }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await flush(10)
    expect(billing.fetchSubscription).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('billing.payment_return.subscription.body_generic'))
    expect(wrapper.findAll('a').some(link => (link.attributes('href') ?? '').endsWith('/ar/dashboard'))).toBe(true)
  })

  it('shows the gateway failure and retries with the remembered checkout', async () => {
    sessionStorage.setItem('bafo.checkout_memory', JSON.stringify({ '01j9pay0000000000000000001': { returnTo: null, planId: 'pro-id', interval: 'annual', seats: null } }))
    billing.fetchPayment.mockResolvedValue(makePayment({ status: 'failed', failure_message: 'رُفضت البطاقة' }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await flush()
    expect(wrapper.text()).toContain(t('billing.payment_return.failed.title'))
    expect(wrapper.text()).toContain('رُفضت البطاقة')
    const retry = wrapper.findAll('a').find(link => link.text().includes(t('billing.payment_return.try_again')))!
    expect(retry.attributes('href')).toContain('/dashboard/billing/checkout?plan=pro-id&interval=annual')
  })

  it('verifies once after 60 s and then says it is still checking', async () => {
    vi.useFakeTimers()
    billing.fetchPayment.mockResolvedValue(makePayment({ status: 'pending' }))
    billing.verifyPayment.mockResolvedValue(makePayment({ status: 'pending' }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await vi.advanceTimersByTimeAsync(0)
    expect(wrapper.text()).toContain(t('billing.payment_return.checking.title'))
    await vi.advanceTimersByTimeAsync(62_000)
    expect(billing.verifyPayment).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain(t('billing.payment_return.still_pending.body'))
  })

  it('reports a sponsorship publish that stayed a draft (paid, not published)', async () => {
    billing.fetchPayment.mockResolvedValue(makePayment({ purpose: 'sponsorship', status: 'succeeded', context: { competition_id: '01j9comp000000000000000000', intent: 'publish', subscription_id: null } }))
    competitions.fetchCompetition.mockResolvedValue({ id: '01j9comp000000000000000000', status: 'draft' })
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=01j9pay0000000000000000001' })
    await flush(10)
    expect(competitions.fetchCompetition).toHaveBeenCalledWith('01j9comp000000000000000000')
    expect(wrapper.text()).toContain(t('billing.payment_return.sponsorship.draft_body'))
  })

  it('does not reveal payments the viewer cannot access (404)', async () => {
    billing.fetchPayment.mockRejectedValue(new ApiError({ status: 404, code: 'not_found', message: '', errors: {} }))
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return?payment=nope' })
    await flush()
    expect(wrapper.text()).toContain(t('billing.payment_return.missing.title'))
  })

  it('handles a missing payment parameter', async () => {
    const wrapper = await mountSuspended(ReturnPage, { route: '/ar/dashboard/billing/checkout/return' })
    await flush()
    expect(wrapper.text()).toContain(t('billing.payment_return.missing.title'))
    expect(billing.fetchPayment).not.toHaveBeenCalled()
  })
})

// ---------- W30 Overview ----------

describe('W30 subscription and billing', () => {
  it('is forbidden without billing.view', async () => {
    signIn({ organization: completeOrg(), permissions: ['competitions.create'] })
    const wrapper = await mountSuspended(BillingPage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.forbidden'))
    expect(billing.fetchSubscription).not.toHaveBeenCalled()
  })

  it('shows the current subscription, seats, vouchers and history', async () => {
    billing.fetchSubscription.mockResolvedValue(makeOverview())
    billing.listVouchers.mockResolvedValue([makeVoucher()])
    const wrapper = await mountSuspended(BillingPage)
    await flush()
    expect(wrapper.text()).toContain('باقة برو')
    expect(wrapper.text()).toContain(t('billing.common.days_left', { count: 21 }, 21))
    expect(wrapper.text()).toContain('V-7KQ2M9XA1B')
    expect(wrapper.text()).toContain(money(30000))
    expect(wrapper.text()).toContain(t('team.seats.used', { used: 2, total: 3 }))
    expect(wrapper.text()).toContain(t('billing.overview.history.title'))
    expectNoRawKeys(wrapper.text())
  })

  it('hides vouchers and the covered-participation card in release scope core (§1.3 sponsorship)', async () => {
    useAppConfigStore().config = makeAppConfig({}, 'core')
    billing.fetchSubscription.mockResolvedValue(makeOverview())
    billing.listVouchers.mockResolvedValue([makeVoucher()])
    const wrapper = await mountSuspended(BillingPage)
    await flush()
    expect(wrapper.text()).toContain('باقة برو')
    expect(wrapper.text()).toContain(t('billing.subtitle_core'))
    expect(wrapper.text()).not.toContain(t('billing.vouchers.title'))
    expect(wrapper.text()).not.toContain(t('billing.overview.sponsorship.title'))
    expect(wrapper.find('a[href$="/billing/invoices"]').exists()).toBe(false)
    expect(billing.listVouchers).not.toHaveBeenCalled()
    expectNoRawKeys(wrapper.text())
  })

  it('starts the trial only when the server has created it (no optimistic state)', async () => {
    billing.fetchSubscription.mockResolvedValue(makeOverview({ current: null, history: [], trial_available: true }))
    billing.listVouchers.mockResolvedValue([])
    let resolveTrial!: (value: unknown) => void
    billing.startTrial.mockReturnValue(new Promise((resolve) => {
      resolveTrial = resolve
    }))
    const wrapper = await mountSuspended(BillingPage)
    await flush()
    expect(wrapper.text()).toContain(t('billing.overview.none.title'))
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.overview.trial.start')))!.trigger('click')
    await flush()
    expect(billing.startTrial).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain(t('billing.overview.none.title'))
    billing.fetchSubscription.mockResolvedValue(makeOverview({ current: makeSubscription({ source: 'trial', interval: null, amounts: null }), trial_available: false }))
    resolveTrial(makeSubscription({ source: 'trial' }))
    await flush(10)
    expect(identity.fetchMe).toHaveBeenCalled()
    expect(wrapper.text()).toContain(t('billing.sources.trial'))
    expect(wrapper.text()).not.toContain(t('billing.overview.none.title'))
  })
})

// ---------- W34 / W35 Invoices ----------

describe('W34 invoices', () => {
  it('lists invoices and downloads the PDF as a blob', async () => {
    billing.listInvoices.mockResolvedValue(pageOf([makeInvoice()]))
    download.mockResolvedValue(undefined)
    const wrapper = await mountSuspended(InvoicesPage)
    await flush()
    expect(wrapper.text()).toContain('BAFO-INV-2026-000042')
    expect(wrapper.text()).toContain(t('billing.invoices.einvoice.cleared'))
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.invoices.download_pdf')))!.trigger('click')
    await flush()
    expect(download).toHaveBeenCalledWith('/billing/invoices/01j9inv0000000000000000001/pdf', 'BAFO-INV-2026-000042.pdf')
  })

  it('says the invoice is being issued on invoice_pdf_not_ready', async () => {
    billing.listInvoices.mockResolvedValue(pageOf([makeInvoice()]))
    download.mockRejectedValue(new ApiError({ status: 409, code: 'invoice_pdf_not_ready', message: '', errors: {} }))
    const wrapper = await mountSuspended(InvoicesPage)
    await flush()
    await wrapper.findAll('button').find(button => button.text().includes(t('billing.invoices.download_pdf')))!.trigger('click')
    await flush()
    expect(useToast().toasts.value.some(toast => toast.message === t('billing.invoices.not_ready'))).toBe(true)
  })

  it('shows the empty state', async () => {
    billing.listInvoices.mockResolvedValue(pageOf([]))
    const wrapper = await mountSuspended(InvoicesPage)
    await flush()
    expect(wrapper.text()).toContain(t('billing.invoices.empty.title'))
  })
})

describe('W35 invoice detail', () => {
  it('shows lines and totals', async () => {
    billing.fetchInvoice.mockResolvedValue(makeInvoice())
    const wrapper = await mountSuspended(InvoicePage, { route: '/ar/dashboard/billing/invoices/01j9inv0000000000000000001' })
    await flush()
    expect(wrapper.text()).toContain('تصريح مشاركة مغطّاة — BAFO-T-2026-000123')
    expect(wrapper.text()).toContain(money(161000))
    expect(wrapper.text()).toContain('7b1c0000-0000-4000-8000-000000000000')
    expectNoRawKeys(wrapper.text())
  })

  it('shows not found for 404', async () => {
    billing.fetchInvoice.mockRejectedValue(new ApiError({ status: 404, code: 'not_found', message: '', errors: {} }))
    const wrapper = await mountSuspended(InvoicePage, { route: '/ar/dashboard/billing/invoices/nope' })
    await flush()
    expect(wrapper.text()).toContain(t('errors.not_found'))
  })
})
