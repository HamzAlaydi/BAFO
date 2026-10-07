<script setup lang="ts">
import { ArrowDownToLine, Send } from '@lucide/vue'
import type { ParticipantLiveSnapshot, SubmitOfferResult } from '~/types/api/bidding'
import type { OfferSubmitOutcome, OutlierPrompt } from '~/composables/useOfferSubmit'
import type { OfferErrorAction } from '~/stores/liveRules'
import type { LiveConnectionState } from '~/utils/connection-state'

/**
 * `OfferComposer` (SCREENS S5, W19): amount → confirm → `POST …/offers`.
 *
 * - Hints come from the latest snapshot only: the required next amount (tender ≤, auction ≥), the
 *   start price for a first offer, the BAFO reference, the granularity, and "prices exclude VAT".
 *   "Use {amount}" fills the required next amount.
 * - Inline pre-checks (CD13) mirror those bounds for feedback; the server decides and its rejection
 *   always wins (the documented codes map to inline errors, banners, a refetch or a cooldown).
 * - One `Idempotency-Key` per intent, created when the confirm dialog opens, reused on retry; the
 *   outlier confirmation re-sends with a new key (`useOfferSubmit`).
 * - No optimistic UI: the caller applies the response's `live` snapshot through the v-guarded
 *   reducer after the server answers.
 */
const props = defineProps<{
  competitionId: string
  snapshot: ParticipantLiveSnapshot
  /** The connection gate of the competition context (S4). */
  canSubmit: boolean
  connection: LiveConnectionState
  /** The countdown reached zero: «جارٍ الإغلاق…» until the server says otherwise. */
  closing?: boolean
}>()

const emit = defineEmits<{
  accepted: [result: SubmitOfferResult]
  /** The state changed under the composer (closed, not open yet, BAFO, role): refetch. */
  stale: []
}>()

const { t } = useI18n()
const { td } = useDirectionCopy(() => props.snapshot.direction)
const money = useMoney()
const date = useDate()
const auth = useAuthStore()
const { message } = useErrorMessage()
const submitter = useOfferSubmit(() => props.competitionId)
const inputId = `offer-amount-${useId()}`

const amount = ref<number | null>(null)
const invalidText = ref(false)
const touched = ref(false)
const serverError = ref<OfferErrorAction | null>(null)
const banner = ref<string | null>(null)
const confirmOpen = ref(false)
const outlierOpen = ref(false)
const outlierPrompt = ref<OutlierPrompt | null>(null)
const outlierReferenceIsOwn = ref(true)
const dialogError = ref<string | null>(null)

/** Amounts inside composed plain strings are isolated for bidi (S1). */
const isolated = (minor: number) => `⁨${money.format(minor)}⁩`

const mode = computed(() => offerModeOf(props.snapshot))
const bound = computed(() => primaryOfferBound(props.snapshot))
const precheck = computed(() => precheckOffer(amount.value, props.snapshot))
const hasOffer = computed(() => props.snapshot.my_offer !== null)
const requiredNext = computed(() => (mode.value === 'live' || mode.value === 'initial' ? props.snapshot.required_next_amount_minor : null))

const title = computed(() => {
  if (mode.value === 'bafo') return t('live.composer.title_bafo')
  if (mode.value === 'sealed') return t('live.composer.title_sealed')
  return hasOffer.value ? t('live.composer.title_improve') : t('live.composer.title')
})

const submitLabel = computed(() => {
  if (submitter.cooldownSeconds.value > 0) return t('live.composer.cooldown', { seconds: submitter.cooldownSeconds.value })
  if (mode.value === 'bafo') return t('live.composer.submit_bafo')
  if (mode.value === 'sealed') return hasOffer.value ? t('live.composer.revise_sealed') : t('live.composer.submit_sealed')
  return hasOffer.value ? t('live.composer.submit_improve') : t('live.composer.submit')
})

const boundHintKey = computed(() => {
  switch (bound.value?.kind) {
    case 'required_next': return 'offers.hint.required_next'
    case 'bafo_reference': return 'offers.hint.bafo_reference'
    // The start price is the hint for a first offer, and the only bound of a sealed revision.
    case 'start_price': return hasOffer.value && mode.value !== 'sealed' ? null : 'offers.hint.start_price'
    default: return null
  }
})

const granularityHint = computed(() => (props.snapshot.amount_granularity_minor === 100
  ? t('live.composer.granularity_whole')
  : t('live.composer.granularity_halalas')))

const fieldHint = computed(() => `${granularityHint.value} · ${t('common.prices_exclude_vat')} · ${t('live.composer.amount_example')}`)

function granularityMessage(granularityMinor: number | null): string {
  if (granularityMinor === 100) return t('offers.error.granularity_whole')
  if (granularityMinor === null) return t('errors.offer_granularity')
  return t('offers.error.granularity', { amount: isolated(granularityMinor) })
}

const serverMessage = computed<string | null>(() => {
  const action = serverError.value
  if (!action) return null
  switch (action.type) {
    case 'step_not_met':
      return action.requiredMinor === null ? t('errors.offer_step_not_met') : td('offers.error.step_not_met', { amount: isolated(action.requiredMinor) })
    case 'start_price':
      return action.startPriceMinor === null ? t('errors.offer_start_price') : td('offers.error.start_price', { amount: isolated(action.startPriceMinor) })
    case 'granularity':
      return granularityMessage(action.granularityMinor)
    case 'amount_invalid':
      return t('offers.error.not_positive')
    case 'amount_too_large':
      return action.maxMinor === null ? t('errors.offer_amount_too_large') : t('offers.error.too_large', { amount: isolated(action.maxMinor) })
    case 'bafo_worse':
      return action.referenceMinor === null ? t('errors.offer_bafo_worse_than_reference') : td('offers.error.bafo_reference', { amount: isolated(action.referenceMinor) })
    default:
      return null
  }
})

const precheckMessage = computed<string | null>(() => {
  if (!touched.value || invalidText.value) return null
  const result = precheck.value
  if (result.ok) return null
  switch (result.reason) {
    case 'empty': return t('offers.error.empty')
    case 'not_positive': return t('offers.error.not_positive')
    case 'granularity': return granularityMessage(result.granularityMinor)
    case 'required_next': return td('offers.error.step_not_met', { amount: isolated(result.boundMinor) })
    case 'bafo_reference': return td('offers.error.bafo_reference', { amount: isolated(result.boundMinor) })
    case 'start_price': return td('offers.error.start_price', { amount: isolated(result.boundMinor) })
    default: return null
  }
})

/** The server's rejection wins over the client pre-check (CD13). */
const inlineError = computed(() => serverMessage.value ?? precheckMessage.value)

/** "Use this amount" after `offer_step_not_met`, from `details.required_amount_minor`. */
const suggestedAmount = computed(() => (serverError.value?.type === 'step_not_met' ? serverError.value.requiredMinor : null))

const disabledReason = computed<string | null>(() => {
  if (!auth.can('participation.submit_offers')) return t('live.composer.no_permission')
  if (props.closing) return t('live.countdown.closing')
  if (!props.canSubmit) {
    if (props.connection === 'offline') return t('live.composer.disabled.offline')
    if (props.connection === 'connecting') return t('live.composer.disabled.connecting')
    return t('live.composer.disabled.reconnecting')
  }
  return null
})

watch(amount, () => {
  serverError.value = null
})

function onRawInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  invalidText.value = value.trim() !== '' && parseAmountToMinor(value) === null
}

function useAmount(minor: number | null): void {
  if (minor === null) return
  amount.value = minor
  invalidText.value = false
  touched.value = true
  banner.value = null
}

function focusInput(): void {
  nextTick(() => document.getElementById(inputId)?.focus())
}

function openConfirm(): void {
  touched.value = true
  banner.value = null
  if (disabledReason.value || submitter.cooldownSeconds.value > 0) return
  if (invalidText.value || !precheck.value.ok || amount.value === null) {
    serverError.value = null
    focusInput()
    return
  }
  serverError.value = null
  dialogError.value = null
  submitter.prepare(amount.value)
  confirmOpen.value = true
}

function closeAll(): void {
  confirmOpen.value = false
  outlierOpen.value = false
}

function handleRejection(error: ApiError): void {
  const action = offerErrorAction(error)
  switch (action.type) {
    case 'reconfirm':
      // `useOfferSubmit` prepared a fresh key: the user confirms the same amount again.
      dialogError.value = t('offers.error.reconfirm')
      if (outlierOpen.value) {
        outlierOpen.value = false
        confirmOpen.value = true
      }
      return
    case 'cooldown':
      dialogError.value = message(error)
      return
    case 'generic':
      dialogError.value = message(error)
      return
    case 'not_open':
      closeAll()
      banner.value = action.opensAt ? t('live.composer.unavailable.opens_at', { time: date.formatDeadline(action.opensAt) }) : message(error)
      emit('stale')
      return
    case 'closed':
    case 'bafo_state':
    case 'not_participant':
      closeAll()
      banner.value = message(error)
      emit('stale')
      return
    default:
      // Amount problems are shown on the field; the next attempt is a new intent (new key).
      closeAll()
      submitter.cancel()
      serverError.value = action
      focusInput()
  }
}

function handle(outcome: OfferSubmitOutcome): void {
  switch (outcome.kind) {
    case 'accepted':
      closeAll()
      amount.value = null
      touched.value = false
      serverError.value = null
      emit('accepted', outcome.result)
      return
    case 'outlier':
      confirmOpen.value = false
      outlierPrompt.value = outcome.prompt
      outlierReferenceIsOwn.value = props.snapshot.my_offer !== null
      dialogError.value = null
      outlierOpen.value = true
      return
    case 'unconfirmed':
      // Retry goes through the confirm dialog with the same key (and the same `confirm_outlier`).
      if (submitter.unconfirmed.value) {
        outlierOpen.value = false
        confirmOpen.value = true
      }
      return
    case 'rejected':
      handleRejection(outcome.error)
  }
}

async function confirm(): Promise<void> {
  dialogError.value = null
  handle(await submitter.submit())
}

async function confirmOutlier(): Promise<void> {
  dialogError.value = null
  handle(await submitter.confirmOutlier())
}

// Closing a dialog without confirming abandons the intent (the next one gets a new key).
watch([confirmOpen, outlierOpen], ([confirming, outlier]) => {
  if (!confirming && !outlier && !submitter.submitting.value) {
    submitter.cancel()
    dialogError.value = null
  }
})
</script>

<template>
  <UiCard
    :title="title"
    data-testid="offer-composer"
  >
    <form
      class="flex flex-col gap-4"
      novalidate
      @submit.prevent="openConfirm"
    >
      <UiAlert
        v-if="banner"
        tone="info"
        dismissible
        @dismiss="banner = null"
      >
        {{ banner }}
      </UiAlert>

      <div
        v-if="boundHintKey && bound"
        class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-surface-muted p-3"
      >
        <p
          class="text-sm font-semibold text-fg"
          data-testid="bound-hint"
        >
          <i18n-t
            :keypath="`${boundHintKey}.${snapshot.direction}`"
            scope="global"
          >
            <template #amount>
              <UiAmount :minor="bound.amountMinor" />
            </template>
          </i18n-t>
        </p>
        <UiButton
          v-if="requiredNext !== null"
          variant="secondary"
          size="sm"
          :icon="ArrowDownToLine"
          data-testid="use-required"
          @click="useAmount(requiredNext)"
        >
          <i18n-t
            keypath="live.composer.use_amount"
            scope="global"
          >
            <template #amount>
              <UiAmount :minor="requiredNext" />
            </template>
          </i18n-t>
        </UiButton>
      </div>

      <UiMoneyInput
        :id="inputId"
        v-model="amount"
        :label="t('live.composer.amount_label')"
        :hint="fieldHint"
        :error="inlineError"
        required
        :disabled="!auth.can('participation.submit_offers')"
        data-testid="offer-amount"
        @input="onRawInput"
        @blur="touched = true"
      />

      <div
        v-if="suggestedAmount !== null"
        class="-mt-2"
      >
        <UiButton
          variant="link"
          size="sm"
          @click="useAmount(suggestedAmount)"
        >
          {{ t('offers.use_required') }}
        </UiButton>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p
          v-if="disabledReason"
          class="text-sm text-fg-muted"
          role="status"
          data-testid="composer-disabled"
        >
          {{ disabledReason }}
        </p>
        <UiButton
          type="submit"
          size="lg"
          :icon="Send"
          :flip-icons="true"
          :disabled="Boolean(disabledReason) || submitter.cooldownSeconds.value > 0"
          :loading="submitter.submitting.value && !confirmOpen && !outlierOpen"
          class="sm:ms-auto"
          data-testid="submit-offer"
        >
          {{ submitLabel }}
        </UiButton>
      </div>
    </form>

    <LiveOfferConfirmDialog
      v-model:open="confirmOpen"
      :amount-minor="submitter.intent.value?.amountMinor ?? amount"
      :direction="snapshot.direction"
      :mode="mode"
      :busy="submitter.submitting.value"
      :unconfirmed="submitter.unconfirmed.value"
      :error="dialogError"
      :cooldown-seconds="submitter.cooldownSeconds.value"
      @confirm="confirm"
    />
    <LiveOutlierConfirmDialog
      v-model:open="outlierOpen"
      :prompt="outlierPrompt"
      :reference-is-own-offer="outlierReferenceIsOwn"
      :busy="submitter.submitting.value"
      :error="dialogError"
      @confirm="confirmOutlier"
    />
  </UiCard>
</template>
