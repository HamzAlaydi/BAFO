import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import type { Payment } from '~/types/api/billing'
import type { SubmitOfferResult } from '~/types/api/bidding'

const billing = vi.hoisted(() => ({ fetchPayment: vi.fn(), verifyPayment: vi.fn() }))
const bidding = vi.hoisted(() => ({ submitOffer: vi.fn(), fetchLive: vi.fn(), sendHeartbeat: vi.fn() }))
vi.mock('~/services/billing', () => billing)
vi.mock('~/services/bidding', () => bidding)

/** Runs a composable inside a mounted component (setup context: i18n, lifecycle hooks). */
async function withSetup<T>(composable: () => T): Promise<{ result: T, unmount: () => void }> {
  let result!: T
  const wrapper = await mountSuspended(defineComponent({
    setup() {
      result = composable()
      return () => h('div')
    },
  }))
  return { result, unmount: () => wrapper.unmount() }
}

function payment(status: Payment['status']): Payment {
  return {
    id: '01jpay00000000000000000000',
    purpose: 'subscription',
    status,
    currency: 'SAR',
    lines: [],
    subtotal_minor: 150_000,
    credit_minor: 0,
    discount_minor: 0,
    vat_rate_bp: 1500,
    vat_minor: 22_500,
    total_minor: 172_500,
    coupon: null,
    redirect_url: null,
    failure_code: null,
    failure_message: null,
    paid_at: null,
    expires_at: null,
    created_at: '2026-10-01T09:00:00.000Z',
    invoice_id: null,
    context: { competition_id: null, intent: null, subscription_id: null },
  }
}

const offerResult = (v: number): SubmitOfferResult => ({
  offer: { id: '01joffer', seq: 57, amount_minor: 9_850_000, stage: 'live', accepted_at: '2026-10-01T12:59:58.412Z', voided: false },
  live: { v } as SubmitOfferResult['live'],
  replayed: false,
})

beforeEach(() => {
  for (const fn of [...Object.values(billing), ...Object.values(bidding)]) fn.mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('useCountdown (SCREENS S3)', () => {
  it('counts down on the server clock and formats per CONVENTIONS §9.1', async () => {
    const clock = useServerTime()
    clock.reset()
    clock.sync(new Date(Date.now() + 3_600_000).toISOString())
    const target = new Date(Date.now() + 90 * 60_000).toISOString()
    const { result, unmount } = await withSetup(() => useCountdown(target, { keepSynced: false }))
    expect(result.display.value?.mode).toBe('clock')
    expect(result.display.value?.clock).toMatch(/^00:(29|30):\d\d$/)
    expect(result.warning.value).toBe(false)
    unmount()
    clock.reset()
  })

  it('reports thresholds while on screen and expiry once', async () => {
    vi.useFakeTimers({ now: Date.parse('2026-10-01T09:00:00.000Z') })
    useServerTime().reset()
    const onThreshold = vi.fn()
    const onExpire = vi.fn()
    const target = new Date(Date.now() + 61_000).toISOString()
    const { result, unmount } = await withSetup(() => useCountdown(target, { keepSynced: false, onThreshold, onExpire }))
    expect(result.warning.value).toBe(true)
    await vi.advanceTimersByTimeAsync(2_000)
    expect(onThreshold).toHaveBeenCalledWith(1)
    await vi.advanceTimersByTimeAsync(60_000)
    expect(result.expired.value).toBe(true)
    expect(onExpire).toHaveBeenCalledOnce()
    unmount()
  })
})

describe('useLiveReducer', () => {
  it('applies snapshots in v order and buffers during a resync', () => {
    const live = useLiveReducer<{ v: number }>()
    expect(live.apply({ v: 3 })).toBe(true)
    expect(live.apply({ v: 2 })).toBe(false)
    live.beginResync()
    expect(live.resyncing.value).toBe(true)
    live.apply({ v: 7 })
    live.completeResync({ v: 5 })
    expect(live.lastAppliedV.value).toBe(7)
    live.reset()
    expect(live.snapshot.value).toBeNull()
  })
})

describe('useDirectionCopy', () => {
  it('picks the .tender or .auction variant by direction', async () => {
    const direction = ref<'tender' | 'auction'>('tender')
    const { result } = await withSetup(() => useDirectionCopy(direction))
    const { t } = useNuxtApp().$i18n
    expect(result.td('competitions.direction.rule')).toBe(t('competitions.direction.rule.tender'))
    direction.value = 'auction'
    expect(result.td('competitions.direction.rule')).toBe(t('competitions.direction.rule.auction'))
    expect(result.key('live.status.not_leading')).toBe('live.status.not_leading.auction')
  })
})

describe('usePaymentPoll (CONVENTIONS §7)', () => {
  it('stops at a terminal status', async () => {
    vi.useFakeTimers()
    billing.fetchPayment.mockResolvedValueOnce(payment('pending')).mockResolvedValueOnce(payment('succeeded'))
    const poll = usePaymentPoll('01jpay00000000000000000000')
    poll.start()
    await vi.advanceTimersByTimeAsync(0)
    expect(poll.phase.value).toBe('polling')
    await vi.advanceTimersByTimeAsync(2000)
    expect(poll.phase.value).toBe('done')
    expect(poll.payment.value?.status).toBe('succeeded')
    expect(billing.fetchPayment).toHaveBeenCalledTimes(2)
    expect(billing.verifyPayment).not.toHaveBeenCalled()
  })

  it('verifies once after 60 s of pending, then reports still pending', async () => {
    vi.useFakeTimers()
    billing.fetchPayment.mockResolvedValue(payment('pending'))
    billing.verifyPayment.mockResolvedValue(payment('pending'))
    const poll = usePaymentPoll('01jpay00000000000000000000')
    poll.start()
    await vi.advanceTimersByTimeAsync(62_000)
    expect(billing.verifyPayment).toHaveBeenCalledOnce()
    expect(poll.phase.value).toBe('still_pending')
    const calls = billing.fetchPayment.mock.calls.length
    await vi.advanceTimersByTimeAsync(10_000)
    expect(billing.fetchPayment.mock.calls.length).toBe(calls)
  })

  it('keeps polling through network errors and stops on a 404', async () => {
    vi.useFakeTimers()
    billing.fetchPayment
      .mockRejectedValueOnce(new ApiError({ status: null, code: 'network_error', message: '', errors: {} }))
      .mockRejectedValueOnce(new ApiError({ status: 404, code: 'not_found', message: '', errors: {} }))
    const poll = usePaymentPoll('01jpay00000000000000000000')
    poll.start()
    await vi.advanceTimersByTimeAsync(2000)
    expect(poll.phase.value).toBe('error')
    expect(poll.error.value?.code).toBe('not_found')
  })
})

describe('useJobPoll', () => {
  it('polls until the job completes, starting from the 202 response', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn()
      .mockResolvedValueOnce({ status: 'processing' })
      .mockResolvedValueOnce({ status: 'completed' })
    const job = useJobPoll(fetcher, { initial: { status: 'queued' } })
    job.start()
    expect(job.polling.value).toBe(true)
    await vi.advanceTimersByTimeAsync(2000)
    expect(job.data.value?.status).toBe('processing')
    await vi.advanceTimersByTimeAsync(2000)
    expect(job.data.value?.status).toBe('completed')
    expect(job.polling.value).toBe(false)
    await vi.advanceTimersByTimeAsync(10_000)
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('does not poll an already terminal job, and supports custom terminal rules', async () => {
    vi.useFakeTimers()
    const fetcher = vi.fn().mockResolvedValue({ status: 'ready' })
    const job = useJobPoll(fetcher, { intervalMs: 3000 })
    job.start({ status: 'ready' })
    await vi.advanceTimersByTimeAsync(6000)
    expect(fetcher).not.toHaveBeenCalled()
  })
})

describe('usePendingToken (SCREENS CD7)', () => {
  afterEach(() => {
    window.sessionStorage.clear()
  })

  it('captures the fragment token, strips it from the address bar and keeps it in sessionStorage', () => {
    window.history.replaceState(null, '', '/ar/invitations?utm=x#t=secret-token&action=decline')
    const pending = usePendingToken('invitation')
    expect(pending.captureFromLocation()).toEqual({ token: 'secret-token', action: 'decline' })
    expect(window.location.hash).toBe('')
    expect(window.location.search).toBe('?utm=x')
    expect(window.location.href).not.toContain('secret-token')
    expect(window.sessionStorage.getItem('bafo.pending_invitation')).toBe('secret-token')
    expect(pending.read()).toBe('secret-token')
    pending.clear()
    expect(pending.read()).toBeNull()
    expect(window.sessionStorage.getItem('bafo.pending_invitation')).toBeNull()
  })

  it('returns null without a fragment token', () => {
    window.history.replaceState(null, '', '/ar/auth/accept-invite')
    expect(usePendingToken('team_invitation').captureFromLocation()).toBeNull()
  })
})

describe('useOfferSubmit (SCREENS S5)', () => {
  it('reuses the Idempotency-Key when retrying a network failure once after 1 s', async () => {
    vi.useFakeTimers()
    bidding.submitOffer
      .mockRejectedValueOnce(new ApiError({ status: null, code: 'network_error', message: '', errors: {} }))
      .mockResolvedValueOnce(offerResult(12))
    const offer = useOfferSubmit('01jcomp')
    const intent = offer.prepare(9_850_000)
    const pending = offer.submit()
    await vi.advanceTimersByTimeAsync(1000)
    const outcome = await pending
    expect(outcome.kind).toBe('accepted')
    expect(bidding.submitOffer).toHaveBeenCalledTimes(2)
    expect(bidding.submitOffer.mock.calls[0]![2]).toBe(intent.key)
    expect(bidding.submitOffer.mock.calls[1]![2]).toBe(intent.key)
    expect(bidding.submitOffer.mock.calls[0]![1]).toEqual({ amount_minor: 9_850_000, confirm_outlier: false })
  })

  it('keeps the same key after two failures so the user can retry the same intent', async () => {
    vi.useFakeTimers()
    const failure = new ApiError({ status: 502, code: 'server_error', message: '', errors: {} })
    bidding.submitOffer.mockRejectedValueOnce(failure).mockRejectedValueOnce(failure).mockResolvedValueOnce(offerResult(3))
    const offer = useOfferSubmit('01jcomp')
    const intent = offer.prepare(100)
    const first = offer.submit()
    await vi.advanceTimersByTimeAsync(1000)
    expect((await first).kind).toBe('unconfirmed')
    expect(offer.unconfirmed.value).toBe(true)
    expect(offer.intent.value?.key).toBe(intent.key)
    const second = await offer.submit()
    expect(second.kind).toBe('accepted')
    expect(bidding.submitOffer.mock.calls[2]![2]).toBe(intent.key)
  })

  it('asks to confirm outliers and re-sends with confirm_outlier and a new key', async () => {
    bidding.submitOffer
      .mockRejectedValueOnce(new ApiError({ status: 422, code: 'offer_outlier_confirm_required', message: '', errors: {}, details: { change_bps: 6080, reference_amount_minor: 25_000_000 } }))
      .mockResolvedValueOnce(offerResult(4))
    const offer = useOfferSubmit('01jcomp')
    const first = offer.prepare(9_800_000)
    const outcome = await offer.submit()
    expect(outcome).toEqual({ kind: 'outlier', prompt: { changeBps: 6080, referenceAmountMinor: 25_000_000, amountMinor: 9_800_000 } })
    const confirmed = await offer.confirmOutlier()
    expect(confirmed.kind).toBe('accepted')
    const [, body, key] = bidding.submitOffer.mock.calls[1]!
    expect(body).toEqual({ amount_minor: 9_800_000, confirm_outlier: true })
    expect(key).not.toBe(first.key)
  })

  it('returns business rejections without retrying and applies the 429 cooldown', async () => {
    bidding.submitOffer.mockRejectedValueOnce(new ApiError({ status: 422, code: 'offer_step_not_met', message: '', errors: {}, details: { required_amount_minor: 9_750_000 } }))
    const offer = useOfferSubmit('01jcomp')
    offer.prepare(9_900_000)
    const outcome = await offer.submit()
    expect(outcome.kind).toBe('rejected')
    expect(bidding.submitOffer).toHaveBeenCalledOnce()

    vi.useFakeTimers()
    bidding.submitOffer.mockRejectedValueOnce(new ApiError({ status: 429, code: 'too_many_requests', message: '', errors: {}, details: { retry_after_seconds: 2 } }))
    offer.prepare(9_700_000)
    await offer.submit()
    expect(offer.cooldownSeconds.value).toBe(2)
    expect((await offer.submit()).kind).toBe('unconfirmed')
    await vi.advanceTimersByTimeAsync(2000)
    expect(offer.cooldownSeconds.value).toBe(0)
  })
})

describe('useCooldown', () => {
  it('counts whole seconds down to zero', async () => {
    vi.useFakeTimers()
    const cooldown = useCooldown()
    cooldown.start(2.2)
    expect(cooldown.seconds.value).toBe(3)
    expect(cooldown.active.value).toBe(true)
    await vi.advanceTimersByTimeAsync(3000)
    expect(cooldown.active.value).toBe(false)
  })
})

describe('useErrorMessage', () => {
  it('maps known codes to errors.<code> and binds 422 field paths', async () => {
    const { result } = await withSetup(() => useErrorMessage())
    const { t } = useNuxtApp().$i18n
    expect(result.message(new ApiError({ status: 401, code: 'invalid_credentials', message: 'server', errors: {} }))).toBe(t('errors.invalid_credentials'))
    expect(result.message(new ApiError({ status: 409, code: 'not_in_the_list', message: 'server copy', errors: {} }))).toBe('server copy')
    const bound = result.bind(new ApiError({ status: 422, code: 'validation_failed', message: '', errors: { email: ['taken'], website_url: ['bot'] } }), ['email'])
    expect(bound).toEqual({ fields: { email: 'taken' }, unmatched: ['bot'] })
    expect(result.bind(new Error('x'), ['email'])).toEqual({ fields: {}, unmatched: [] })
  })
})
