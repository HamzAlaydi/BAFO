<script setup lang="ts">
import { ArrowLeft, ArrowRight, Lock, PackageSearch, Pencil } from '@lucide/vue'
import { checkoutReturnUrl, checkoutSubscription, fetchCustomQuote, fetchPlans } from '~/services/billing'
import type { BillingInterval, CouponValidation, CustomQuote, Payment, Plan, SubscriptionCheckoutRequest } from '~/types/api/billing'

/**
 * W32 Checkout · `/dashboard/billing/checkout?plan=&interval=&seats=&return=` (`billing.purchase`;
 * SCREENS §2.4, G5). Billing profile gate, order summary with a validated coupon, then
 * `POST /billing/checkout/subscription` with one `Idempotency-Key` per intent (reused on a network
 * or 5xx retry). The server amounts (lines, credit, discount, VAT, total) are shown before the user
 * is sent to the hosted payment page; a zero total goes straight to the return page (W33).
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const route = useRoute()
const router = useRouter()
const localePath = useLocalePath()
const locale = useAppLocale()
const date = useDate()
const money = useMoney()
const { message, bind } = useErrorMessage()
const cooldown = useCooldown()

useSeoMeta({ title: () => t('billing.checkout.title') })

const allowed = computed(() => auth.can('billing.purchase'))
const planId = computed(() => queryString(route.query.plan))
const returnTo = computed(() => safeDashboardReturn(route.query.return))
const interval = ref<BillingInterval>(isBillingInterval(route.query.interval) ? route.query.interval : 'monthly')
const seats = ref<number | null>(parsePositiveInt(route.query.seats))

const plans = ref<Plan[]>([])
const loadingPlans = ref(true)
const plansError = ref<unknown>(null)
const quote = ref<CustomQuote | null>(null)
const quoteError = ref<string | null>(null)
const seatsError = ref<string | null>(null)
const coupon = ref<CouponValidation | null>(null)
const couponError = ref<string | null>(null)
const formError = ref<string | null>(null)
const serverMissing = ref<string[] | null>(null)
const busy = ref(false)
const payment = ref<Payment | null>(null)
const paymentExpired = ref(false)
const redirecting = ref(false)
/** One key per purchase intent; reused only when the same request is retried after a network or 5xx error. */
let pendingIntent: { key: string, signature: string } | null = null
let quoteSeq = 0

const plan = computed(() => plans.value.find(item => item.id === planId.value) ?? null)
const isCustom = computed(() => plan.value?.is_custom === true)
const bounds = computed(() => plan.value?.custom ?? null)
const fixedPrice = computed(() => (plan.value && !isCustom.value ? planIntervalPrice(plan.value, interval.value) : null))
const forward = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))

const missingFields = computed(() => serverMissing.value ?? (auth.organization?.billing_profile_complete === false ? auth.organization.billing_profile_missing : []))
const profileBlocked = computed(() => missingFields.value.length > 0)

const seatsInRange = computed(() => !isCustom.value || (bounds.value !== null && seats.value !== null && seats.value >= bounds.value.min_seats && seats.value <= bounds.value.max_seats))
const orderReady = computed(() => {
  if (!plan.value) return false
  if (isCustom.value) return seatsInRange.value && quote.value !== null && quote.value.seats === seats.value && quote.value.interval === interval.value
  return fixedPrice.value?.price !== null
})

const couponContext = computed(() => ({
  purpose: 'subscription' as const,
  plan_id: planId.value ?? '',
  interval: interval.value,
  seats: isCustom.value ? seats.value : null,
}))

async function loadPlans(): Promise<void> {
  loadingPlans.value = true
  plansError.value = null
  try {
    plans.value = await fetchPlans()
    if (isCustom.value && seats.value === null && bounds.value) seats.value = bounds.value.min_seats
    if (isCustom.value) await loadQuote()
  }
  catch (error) {
    plansError.value = error
  }
  finally {
    loadingPlans.value = false
  }
}

async function loadQuote(): Promise<void> {
  const current = ++quoteSeq
  quoteError.value = null
  seatsError.value = null
  if (!isCustom.value || !seatsInRange.value || seats.value === null) {
    quote.value = null
    return
  }
  try {
    const result = await fetchCustomQuote(seats.value, interval.value)
    if (current === quoteSeq) quote.value = result
  }
  catch (error) {
    if (current !== quoteSeq) return
    quote.value = null
    if (error instanceof ApiError && (error.code === 'seats_out_of_range' || error.isValidation)) seatsError.value = message(error)
    else quoteError.value = message(error)
  }
}

const debouncedQuote = useDebounceFn(() => void loadQuote(), 300)

onMounted(() => {
  if (!allowed.value) return
  void loadPlans()
  // The billing profile may have been completed a moment ago on the organization page.
  auth.fetchMe().catch(() => {})
})

/** Another plan, interval or seat count is another purchase: the coupon and any payment are dropped. */
function resetIntent(): void {
  coupon.value = null
  couponError.value = null
  formError.value = null
  payment.value = null
  paymentExpired.value = false
  pendingIntent = null
}

watch(interval, (value) => {
  resetIntent()
  void router.replace({ query: { ...route.query, interval: value } })
  if (isCustom.value) {
    quote.value = null
    void loadQuote()
  }
})

watch(seats, (value) => {
  if (!isCustom.value) return
  resetIntent()
  quote.value = null
  if (value !== null) void router.replace({ query: { ...route.query, seats: String(value) } })
  void debouncedQuote()
})

watch(coupon, () => {
  couponError.value = null
  pendingIntent = null
})

function requestBody(): SubscriptionCheckoutRequest | null {
  if (!plan.value) return null
  return {
    plan_id: plan.value.id,
    interval: interval.value,
    seats: isCustom.value ? seats.value : null,
    coupon_code: coupon.value?.code ?? null,
    return_url: checkoutReturnUrl(window.location.origin, locale.value),
  }
}

async function submit(): Promise<void> {
  const body = requestBody()
  if (!body || busy.value || cooldown.active.value || profileBlocked.value || !orderReady.value) return
  const signature = JSON.stringify([body.plan_id, body.interval, body.seats, body.coupon_code])
  if (!pendingIntent || pendingIntent.signature !== signature) pendingIntent = { key: uuidv4(), signature }
  busy.value = true
  formError.value = null
  couponError.value = null
  try {
    const result = await checkoutSubscription(body, pendingIntent.key)
    pendingIntent = null
    rememberCheckout(result.id, { returnTo: returnTo.value, planId: body.plan_id, interval: body.interval, seats: body.seats ?? null })
    if (result.status === 'succeeded' && !result.redirect_url) {
      await navigateTo(localePath({ path: '/dashboard/billing/checkout/return', query: { payment: result.id } }))
      return
    }
    if (!isHostedCheckoutUrl(result.redirect_url)) {
      formError.value = t('errors.server_error')
      return
    }
    payment.value = result
    paymentExpired.value = false
  }
  catch (error) {
    handleError(error)
  }
  finally {
    busy.value = false
  }
}

function handleError(error: unknown): void {
  if (!(error instanceof ApiError)) {
    formError.value = message(error)
    return
  }
  if (error.isRetryable) {
    // Same intent, same key on the next attempt (CONVENTIONS §4.2).
    formError.value = error.isNetwork ? t('billing.checkout.retry_network') : message(error)
    return
  }
  pendingIntent = null
  switch (error.code) {
    case 'billing_profile_incomplete':
      serverMissing.value = Object.keys(error.errors).length > 0 ? Object.keys(error.errors) : (auth.organization?.billing_profile_missing ?? [])
      auth.fetchMe().catch(() => {})
      return
    case 'subscription_renewal_too_early': {
      const from = error.detailString('renewable_from')
      formError.value = from ? t('billing.checkout.renewal_from', { date: date.formatDate(from) }) : message(error)
      return
    }
    case 'coupon_invalid':
    case 'coupon_expired':
    case 'coupon_not_applicable':
    case 'coupon_exhausted':
      couponError.value = message(error)
      return
    case 'seats_out_of_range':
      seatsError.value = message(error)
      return
    case 'too_many_requests':
      cooldown.start(error.retryAfterSeconds ?? 30)
      formError.value = message(error)
      return
    case 'return_url_not_allowed':
      // A configuration bug (BILLING_ALLOWED_RETURN_URLS): generic copy for the user, details for us.
      console.error('[checkout] return_url_not_allowed', error.toJSON())
      formError.value = message(error)
      return
    default: {
      if (error.isValidation) {
        const bound = bind(error, ['seats', 'coupon_code'])
        if (bound.fields.seats) seatsError.value = bound.fields.seats
        if (bound.fields.coupon_code) couponError.value = bound.fields.coupon_code
        formError.value = bound.unmatched[0] ?? (bound.fields.seats || bound.fields.coupon_code ? null : message(error))
        return
      }
      formError.value = message(error)
    }
  }
}

function editOrder(): void {
  payment.value = null
  paymentExpired.value = false
  pendingIntent = null
}

function pay(): void {
  const url = payment.value?.redirect_url
  if (!isHostedCheckoutUrl(url) || paymentExpired.value) return
  redirecting.value = true
  window.location.assign(url)
}

const summaryItems = computed(() => {
  if (!plan.value) return []
  return [
    { key: 'plan', label: t('billing.checkout.fields.plan'), value: plan.value.name },
    { key: 'interval', label: t('billing.checkout.fields.interval'), value: t(`billing.intervals.${interval.value}`) },
    { key: 'seats', label: t('billing.checkout.fields.seats'), value: isCustom.value ? (seats.value ?? null) : plan.value.seats },
  ]
})

const plansLink = computed(() => ({ path: '/dashboard/billing/plans', query: { interval: interval.value, ...(returnTo.value ? { return: returnTo.value } : {}) } }))
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('billing.checkout.title')"
      :description="t('billing.checkout.subtitle')"
    >
      <template
        v-if="allowed && !payment"
        #actions
      >
        <UiButton
          variant="secondary"
          :to="plansLink"
        >
          {{ t('billing.checkout.change_plan') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState
      v-if="!allowed"
      :description="t('billing.common.purchase_permission')"
    />

    <template v-else>
      <!-- L -->
      <UiCard v-if="loadingPlans && plans.length === 0">
        <UiSkeleton :lines="6" />
      </UiCard>

      <!-- X -->
      <UiCard
        v-else-if="plansError && plans.length === 0"
        padding="none"
      >
        <UiErrorState
          :error="plansError"
          :retrying="loadingPlans"
          @retry="loadPlans"
        />
      </UiCard>

      <!-- Unknown or inactive plan -->
      <UiCard v-else-if="!plan">
        <UiEmptyState
          :icon="PackageSearch"
          :title="t('billing.checkout.plan_missing.title')"
          :description="t('billing.checkout.plan_missing.body')"
        >
          <UiButton :to="plansLink">
            {{ t('billing.overview.actions.plans') }}
          </UiButton>
        </UiEmptyState>
      </UiCard>

      <div
        v-else
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]"
      >
        <!-- Confirmation with the server amounts (after POST) -->
        <UiCard
          v-if="payment"
          as="section"
          :title="t('billing.checkout.confirm.title')"
          :description="t('billing.checkout.confirm.body')"
          class="lg:col-span-2"
        >
          <div class="flex flex-col gap-5">
            <BillingAmountsBreakdown
              :lines="payment.lines"
              :subtotal-minor="payment.subtotal_minor"
              :credit-minor="payment.credit_minor"
              :discount-minor="payment.discount_minor"
              :vat-rate-bp="payment.vat_rate_bp"
              :vat-minor="payment.vat_minor"
              :total-minor="payment.total_minor"
              :coupon-code="payment.coupon?.code ?? null"
            />
            <p
              v-if="payment.expires_at && !paymentExpired"
              class="flex flex-wrap items-center gap-2 text-sm text-fg-muted"
            >
              {{ t('billing.checkout.confirm.expires_in') }}
              <UiCountdown
                :ends-at="payment.expires_at"
                :label="t('billing.checkout.confirm.expires_in')"
                size="sm"
                @expire="paymentExpired = true"
              />
            </p>
            <UiAlert
              v-if="paymentExpired"
              tone="warning"
            >
              {{ t('billing.checkout.confirm.expired') }}
            </UiAlert>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
              <UiButton
                variant="ghost"
                :icon="Pencil"
                :disabled="redirecting"
                @click="editOrder"
              >
                {{ t('billing.checkout.confirm.edit') }}
              </UiButton>
              <UiButton
                size="lg"
                :icon="Lock"
                :loading="redirecting"
                :disabled="paymentExpired"
                @click="pay"
              >
                <i18n-t
                  keypath="billing.checkout.confirm.pay"
                  scope="global"
                >
                  <template #total>
                    <bdi class="tabular-nums">{{ money.format(payment.total_minor) }}</bdi>
                  </template>
                </i18n-t>
              </UiButton>
            </div>
            <p
              v-if="redirecting"
              class="text-sm text-fg-muted"
              role="status"
            >
              {{ t('billing.checkout.confirm.redirecting') }}
            </p>
          </div>
        </UiCard>

        <template v-else>
          <div class="flex flex-col gap-6">
            <BillingProfileGate
              v-if="profileBlocked"
              :missing="missingFields"
              :return-to="route.fullPath"
              :can-edit="auth.can('organization.update')"
            />

            <UiCard
              as="section"
              :title="t('billing.checkout.options_title')"
            >
              <div class="flex flex-col gap-5">
                <BillingIntervalToggle
                  v-model="interval"
                  :disabled="busy"
                />
                <BillingSeatsInput
                  v-if="isCustom && bounds"
                  v-model="seats"
                  :min="bounds.min_seats"
                  :max="bounds.max_seats"
                  :error="seatsError"
                  :disabled="busy"
                />
                <UiAlert
                  v-if="quoteError"
                  tone="danger"
                >
                  {{ quoteError }}
                </UiAlert>
                <BillingCouponField
                  v-if="features.enabled('coupons')"
                  v-model="coupon"
                  :context="couponContext"
                  :error="couponError"
                  :disabled="busy || !orderReady"
                />
              </div>
            </UiCard>
          </div>

          <UiCard
            as="section"
            :title="t('billing.checkout.summary_title')"
            class="lg:sticky lg:top-6"
          >
            <div class="flex flex-col gap-4">
              <UiKeyValueList :items="summaryItems" />
              <dl
                class="flex flex-col gap-2 border-t border-line pt-4 text-sm"
                aria-live="polite"
              >
                <template v-if="isCustom">
                  <div class="flex items-center justify-between gap-3">
                    <dt class="text-fg-muted">
                      {{ t('billing.checkout.fields.unit_price') }}
                    </dt>
                    <dd>
                      <UiSkeleton
                        v-if="!quote"
                        class="h-4 w-24"
                      />
                      <UiAmount
                        v-else
                        :minor="quote.unit_price_minor"
                      />
                    </dd>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                    <dt class="text-fg-muted">
                      {{ t('billing.amounts.subtotal') }}
                    </dt>
                    <dd>
                      <UiSkeleton
                        v-if="!quote"
                        class="h-4 w-24"
                      />
                      <UiAmount
                        v-else
                        :minor="quote.subtotal_minor"
                      />
                    </dd>
                  </div>
                </template>
                <div
                  v-else
                  class="flex items-center justify-between gap-3"
                >
                  <dt class="text-fg-muted">
                    {{ t('billing.checkout.fields.price') }}
                  </dt>
                  <dd>
                    <UiAmount :minor="fixedPrice?.price ?? null" />
                  </dd>
                </div>
                <div
                  v-if="coupon"
                  class="flex items-center justify-between gap-3"
                >
                  <dt class="text-fg-muted">
                    {{ t('billing.amounts.discount_code', { code: coupon.code }) }}
                  </dt>
                  <dd>
                    <UiAmount
                      :minor="coupon.discount_minor"
                      sign="minus"
                    />
                  </dd>
                </div>
              </dl>
              <p class="text-sm text-fg-muted">
                {{ t('billing.checkout.next_step_note') }}
              </p>
              <p class="text-xs text-fg-muted">
                {{ t('common.prices_exclude_vat') }}
              </p>

              <UiAlert
                v-if="formError"
                tone="danger"
                role="alert"
              >
                {{ formError }}
              </UiAlert>

              <UiButton
                block
                size="lg"
                :loading="busy"
                :disabled="profileBlocked || !orderReady || cooldown.active.value"
                :icon-end="forward"
                @click="submit"
              >
                {{ cooldown.active.value ? t('common.retry_in', { seconds: cooldown.seconds.value }) : t('billing.checkout.continue') }}
              </UiButton>
            </div>
          </UiCard>
        </template>
      </div>
    </template>
  </div>
</template>
