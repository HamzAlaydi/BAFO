<script setup lang="ts">
import { SlidersHorizontal } from '@lucide/vue'
import { fetchCustomQuote } from '~/services/billing'
import type { BillingInterval, CustomQuote, Plan } from '~/types/api/billing'

/**
 * The custom plan (SCREENS W31): seats within the server bounds and a live server quote
 * (`GET /plans/custom-quote`, debounced 300 ms; `seats_out_of_range`). The CTA slot receives the
 * seats once the quote for them has arrived.
 */
const props = withDefaults(defineProps<{
  plan: Plan
  interval: BillingInterval
  current?: boolean
  debounceMs?: number
}>(), {
  debounceMs: 300,
})

const seats = defineModel<number | null>('seats', { default: null })
const { t } = useI18n()
const money = useMoney()
const { message } = useErrorMessage()
const titleId = useId()

const bounds = computed(() => props.plan.custom ?? { min_seats: 1, max_seats: 1, seat_monthly_price_minor: 0, seat_annual_price_minor: 0 })
if (seats.value === null) seats.value = bounds.value.min_seats

const quote = ref<CustomQuote | null>(null)
const quoting = ref(false)
const quoteError = ref<string | null>(null)
const seatsError = ref<string | null>(null)
let seq = 0

const inRange = computed(() => seats.value !== null && seats.value >= bounds.value.min_seats && seats.value <= bounds.value.max_seats)
const perSeat = computed(() => (props.interval === 'monthly' ? bounds.value.seat_monthly_price_minor : bounds.value.seat_annual_price_minor))
const quoteReady = computed(() => quote.value !== null && quote.value.seats === seats.value && quote.value.interval === props.interval)

async function loadQuote(): Promise<void> {
  const current = ++seq
  seatsError.value = null
  quoteError.value = null
  if (!inRange.value || seats.value === null) {
    quote.value = null
    quoting.value = false
    return
  }
  quoting.value = true
  try {
    const result = await fetchCustomQuote(seats.value, props.interval)
    if (current !== seq) return
    quote.value = result
  }
  catch (error) {
    if (current !== seq) return
    quote.value = null
    if (error instanceof ApiError && (error.code === 'seats_out_of_range' || error.isValidation)) seatsError.value = message(error)
    else quoteError.value = message(error)
  }
  finally {
    if (current === seq) quoting.value = false
  }
}

const debouncedQuote = useDebounceFn(() => void loadQuote(), () => props.debounceMs)

watch([seats, () => props.interval], () => {
  quote.value = null
  quoting.value = inRange.value
  void debouncedQuote()
})

onMounted(() => void loadQuote())

const periodLabel = computed(() => t(props.interval === 'monthly' ? 'billing.common.per_month' : 'billing.common.per_year'))
const vatLabel = computed(() => t('billing.amounts.vat', { rate: t('billing.common.percent', { value: formatBps(quote.value?.vat_rate_bp ?? props.plan.vat_rate_bp) }) }))
</script>

<template>
  <article
    class="flex h-full flex-col gap-5 rounded-lg border border-line bg-surface p-5 shadow-xs sm:p-6"
    :aria-labelledby="titleId"
  >
    <div class="flex items-start gap-3">
      <span
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
        aria-hidden="true"
      >
        <SlidersHorizontal :size="20" />
      </span>
      <div class="flex min-w-0 flex-col gap-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3
            :id="titleId"
            class="text-xl font-bold text-fg"
          >
            {{ plan.name }}
          </h3>
          <UiBadge
            v-if="current"
            tone="primary"
            solid
            size="sm"
          >
            {{ t('billing.plans.current') }}
          </UiBadge>
        </div>
        <p class="text-sm text-fg-muted">
          {{ plan.description || t('billing.plans.custom.description') }}
        </p>
      </div>
    </div>

    <p class="text-sm text-fg">
      <i18n-t
        keypath="billing.plans.custom.per_seat"
        scope="global"
      >
        <template #amount>
          <bdi class="font-bold tabular-nums">{{ money.format(perSeat) }}</bdi>
        </template>
        <template #period>
          {{ periodLabel }}
        </template>
      </i18n-t>
    </p>

    <BillingSeatsInput
      v-model="seats"
      :min="bounds.min_seats"
      :max="bounds.max_seats"
      :error="seatsError"
    />

    <div
      class="rounded-md bg-surface-muted p-4"
      aria-live="polite"
      :aria-busy="quoting || undefined"
    >
      <UiAlert
        v-if="quoteError"
        tone="danger"
      >
        {{ quoteError }}
        <UiButton
          class="mt-2"
          size="sm"
          variant="secondary"
          @click="loadQuote"
        >
          {{ t('common.actions.retry') }}
        </UiButton>
      </UiAlert>
      <div
        v-else-if="quoting || !quoteReady"
        class="flex flex-col gap-2"
      >
        <span class="sr-only">{{ t('billing.plans.custom.quote_loading') }}</span>
        <UiSkeleton class="h-4 w-2/3" />
        <UiSkeleton class="h-4 w-1/2" />
        <UiSkeleton class="h-6 w-3/4" />
      </div>
      <dl
        v-else-if="quote"
        class="flex flex-col gap-2 text-sm"
      >
        <div class="flex items-center justify-between gap-3">
          <dt class="text-fg-muted">
            {{ t('billing.amounts.subtotal') }}
          </dt>
          <dd><UiAmount :minor="quote.subtotal_minor" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3">
          <dt class="text-fg-muted">
            {{ vatLabel }}
          </dt>
          <dd><UiAmount :minor="quote.vat_minor" /></dd>
        </div>
        <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
          <dt class="font-semibold text-fg">
            {{ t('billing.amounts.total') }}
          </dt>
          <dd>
            <UiAmount
              :minor="quote.total_minor"
              size="lg"
            />
          </dd>
        </div>
      </dl>
    </div>

    <div class="mt-auto flex flex-col gap-2">
      <slot
        name="action"
        :available="quoteReady && inRange"
        :seats="seats"
      />
    </div>
  </article>
</template>
