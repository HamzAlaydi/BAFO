import type { MaybeRefOrGetter } from 'vue'
import { fetchPayment, verifyPayment } from '~/services/billing'
import { TERMINAL_PAYMENT_STATUSES, type Payment } from '~/types/api/billing'

export type PaymentPollPhase = 'idle' | 'polling' | 'verifying' | 'done' | 'still_pending' | 'error'

export interface PaymentPollOptions {
  intervalMs?: number
  timeoutMs?: number
}

/**
 * After a return from checkout (CONVENTIONS §7, SCREENS W33): `GET /billing/payments/{id}` every 2 s
 * for up to 60 s, then `POST …/verify` once. Network hiccups keep polling; 403/404 stop with `error`.
 *
 * Phases: `polling` → `done` (terminal status) | `verifying` → `done` | `still_pending`.
 */
export function usePaymentPoll(paymentId: MaybeRefOrGetter<string | null | undefined>, options: PaymentPollOptions = {}) {
  const intervalMs = options.intervalMs ?? 2000
  const timeoutMs = options.timeoutMs ?? 60_000
  const payment = ref<Payment | null>(null)
  const phase = ref<PaymentPollPhase>('idle')
  const error = ref<ApiError | null>(null)
  let timer: ReturnType<typeof setTimeout> | null = null
  let startedAt = 0
  let generation = 0

  const isTerminal = (value: Payment | null) => value !== null && TERMINAL_PAYMENT_STATUSES.includes(value.status)

  function clearTimer(): void {
    if (timer) clearTimeout(timer)
    timer = null
  }

  async function verify(id: string, run: number): Promise<void> {
    phase.value = 'verifying'
    try {
      const result = await verifyPayment(id)
      if (run !== generation) return
      payment.value = result
      phase.value = isTerminal(result) ? 'done' : 'still_pending'
    }
    catch (cause) {
      if (run !== generation) return
      error.value = cause instanceof ApiError ? cause : null
      phase.value = 'still_pending'
    }
  }

  async function tick(id: string, run: number): Promise<void> {
    try {
      const result = await fetchPayment(id)
      if (run !== generation) return
      payment.value = result
      error.value = null
      if (isTerminal(result)) {
        phase.value = 'done'
        return
      }
    }
    catch (cause) {
      if (run !== generation) return
      const apiError = cause instanceof ApiError ? cause : null
      if (apiError && !apiError.isRetryable) {
        error.value = apiError
        phase.value = 'error'
        return
      }
    }
    if (Date.now() - startedAt >= timeoutMs) {
      await verify(id, run)
      return
    }
    timer = setTimeout(() => void tick(id, run), intervalMs)
  }

  function start(): void {
    const id = toValue(paymentId)
    stop()
    if (!id) return
    generation += 1
    startedAt = Date.now()
    error.value = null
    phase.value = 'polling'
    void tick(id, generation)
  }

  function stop(): void {
    generation += 1
    clearTimer()
    if (phase.value === 'polling' || phase.value === 'verifying') phase.value = 'idle'
  }

  if (getCurrentScope()) onScopeDispose(stop)

  return { payment, phase, error, start, stop }
}
