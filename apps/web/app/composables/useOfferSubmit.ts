import type { MaybeRefOrGetter } from 'vue'
import { submitOffer } from '~/services/bidding'
import type { SubmitOfferResult } from '~/types/api/bidding'

export interface OfferIntent {
  /** `Idempotency-Key`: one per user intent, reused on every retry of that intent. */
  key: string
  amountMinor: number
  confirmOutlier: boolean
}

export interface OutlierPrompt {
  changeBps: number
  referenceAmountMinor: number
  amountMinor: number
}

export type OfferSubmitOutcome
  = | { kind: 'accepted', result: SubmitOfferResult }
    | { kind: 'outlier', prompt: OutlierPrompt }
    | { kind: 'rejected', error: ApiError }
    | { kind: 'unconfirmed' }

const RETRY_DELAY_MS = 1000

/**
 * Offer submission (SCREENS S5, CONVENTIONS §4.2). No optimistic UI: the caller shows a busy state
 * and applies `result.live` through the live reducer (v-guarded).
 *
 * - `prepare(amount)` opens a new intent with a new key (when the confirm step opens).
 * - `submit()` sends it; a network error or 5xx is retried **once** after 1 s with the **same** key,
 *   then the outcome is `unconfirmed` ("We could not confirm your offer") and `submit()` may be
 *   called again with the same key.
 * - `offer_outlier_confirm_required` → `outlier`; `confirmOutlier()` re-sends with
 *   `confirm_outlier: true` and a **new** key.
 * - `idempotency_key_reused` should not happen: a fresh key is prepared and the error returned so the
 *   user confirms again.
 * - `too_many_requests` sets `cooldownSeconds` (from `details.retry_after_seconds` or `Retry-After`).
 */
export function useOfferSubmit(competitionId: MaybeRefOrGetter<string>) {
  const intent = shallowRef<OfferIntent | null>(null)
  const submitting = ref(false)
  const lastError = ref<ApiError | null>(null)
  const unconfirmed = ref(false)
  /** Seconds left before submitting is allowed again after a 429 (shown on the button). */
  const cooldownSeconds = ref(0)
  let cooldownTimer: ReturnType<typeof setInterval> | null = null

  function startCooldown(seconds: number): void {
    if (cooldownTimer) clearInterval(cooldownTimer)
    cooldownSeconds.value = Math.max(1, Math.ceil(seconds))
    cooldownTimer = setInterval(() => {
      cooldownSeconds.value = Math.max(0, cooldownSeconds.value - 1)
      if (cooldownSeconds.value === 0 && cooldownTimer) {
        clearInterval(cooldownTimer)
        cooldownTimer = null
      }
    }, 1000)
  }

  if (getCurrentScope()) {
    onScopeDispose(() => {
      if (cooldownTimer) clearInterval(cooldownTimer)
    })
  }

  function prepare(amountMinor: number, confirmOutlier = false): OfferIntent {
    intent.value = { key: uuidv4(), amountMinor, confirmOutlier }
    lastError.value = null
    unconfirmed.value = false
    return intent.value
  }

  function cancel(): void {
    intent.value = null
    lastError.value = null
    unconfirmed.value = false
  }

  async function send(current: OfferIntent): Promise<SubmitOfferResult> {
    return submitOffer(toValue(competitionId), { amount_minor: current.amountMinor, confirm_outlier: current.confirmOutlier }, current.key)
  }

  async function submit(): Promise<OfferSubmitOutcome> {
    const current = intent.value
    if (!current || submitting.value || cooldownSeconds.value > 0) return { kind: 'unconfirmed' }
    submitting.value = true
    lastError.value = null
    unconfirmed.value = false
    try {
      let result: SubmitOfferResult
      try {
        result = await send(current)
      }
      catch (first) {
        if (!(first instanceof ApiError) || !first.isRetryable) throw first
        await new Promise(resolve => setTimeout(resolve, RETRY_DELAY_MS))
        result = await send(current)
      }
      intent.value = null
      return { kind: 'accepted', result }
    }
    catch (cause) {
      const error = cause instanceof ApiError
        ? cause
        : new ApiError({ status: null, code: 'network_error', message: '', errors: {} })
      if (error.isRetryable) {
        // Same key kept: the next submit() is a retry of the same intent.
        unconfirmed.value = true
        return { kind: 'unconfirmed' }
      }
      lastError.value = error
      if (error.code === 'offer_outlier_confirm_required') {
        return {
          kind: 'outlier',
          prompt: {
            changeBps: error.detailNumber('change_bps') ?? 0,
            referenceAmountMinor: error.detailNumber('reference_amount_minor') ?? 0,
            amountMinor: current.amountMinor,
          },
        }
      }
      if (error.code === 'too_many_requests') {
        startCooldown(error.retryAfterSeconds ?? 2)
      }
      if (error.code === 'idempotency_key_reused') {
        prepare(current.amountMinor, current.confirmOutlier)
        lastError.value = error
      }
      return { kind: 'rejected', error }
    }
    finally {
      submitting.value = false
    }
  }

  /** The user confirmed the outlier dialog: new key, `confirm_outlier: true`. */
  function confirmOutlier(): Promise<OfferSubmitOutcome> {
    const amount = intent.value?.amountMinor
    if (amount === undefined) return Promise.resolve({ kind: 'unconfirmed' })
    prepare(amount, true)
    return submit()
  }

  return {
    intent: readonly(intent),
    submitting: readonly(submitting),
    lastError: readonly(lastError),
    unconfirmed: readonly(unconfirmed),
    cooldownSeconds: readonly(cooldownSeconds),
    prepare,
    cancel,
    submit,
    confirmOutlier,
  }
}
