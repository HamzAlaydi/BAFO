<script setup lang="ts">
import { fetchSubscription } from '~/services/billing'
import { fetchCompetition } from '~/services/competitions'
import type { Subscription } from '~/types/api/billing'
import type { CompetitionStatus } from '~/types/api/competitions'

/**
 * W33 Payment return · `/dashboard/billing/checkout/return?payment={id}` (the payer or
 * `billing.view`; SCREENS §2.4). `usePaymentPoll`: `GET /billing/payments/{id}` every 2 s for up to
 * 60 s, then `POST …/verify` once. The panel follows the status and the purpose (subscription, or
 * sponsorship with `intent` publish or invite). It shows BAFO's amounts only, never a card or
 * gateway detail. Nothing is assumed before the server says so.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const home = useHomeStore()
const route = useRoute()
const date = useDate()

useSeoMeta({ title: () => t('billing.payment_return.title'), robots: 'noindex, nofollow' })

const paymentId = computed(() => queryString(route.query.payment))
const poll = usePaymentPoll(paymentId)
const memory = computed(() => (paymentId.value && import.meta.client ? recallCheckout(paymentId.value) : null))

const outcome = computed(() => paymentOutcome(poll.payment.value, poll.phase.value))
const payment = computed(() => poll.payment.value)
const intent = computed(() => payment.value?.context.intent ?? null)
const competitionId = computed(() => payment.value?.context.competition_id ?? null)

/**
 * After success: the subscription this payment bought (`context.subscription_id`, read from the
 * overview when the viewer has `billing.view`; a renewal starts later, so `GET /me` alone would show
 * the old one), or the competition's new status.
 */
const paidSubscription = ref<Subscription | null>(null)
const competitionStatus = ref<CompetitionStatus | null>(null)
const followUpDone = ref(false)

onMounted(() => {
  if (paymentId.value) poll.start()
})

watch(outcome, async (value) => {
  if (value !== 'succeeded' || followUpDone.value || !payment.value) return
  followUpDone.value = true
  if (payment.value.purpose === 'subscription') {
    const subscriptionId = payment.value.context.subscription_id
    await Promise.allSettled([auth.fetchMe(), home.load()])
    if (subscriptionId && auth.can('billing.view')) {
      try {
        const overview = await fetchSubscription()
        paidSubscription.value = [overview.current, overview.upcoming, ...overview.history].find(item => item?.id === subscriptionId) ?? null
      }
      catch {
        paidSubscription.value = null
      }
    }
  }
  else if (competitionId.value) {
    try {
      competitionStatus.value = (await fetchCompetition(competitionId.value)).status
    }
    catch {
      competitionStatus.value = null
    }
    void auth.fetchMe().catch(() => {})
  }
})

const pollErrorState = computed(() => {
  if (poll.phase.value !== 'error') return null
  const error = poll.error.value
  if (error?.isForbidden) return 'forbidden'
  if (error?.isNotFound) return 'not_found'
  return 'error'
})

// ---------- Destinations ----------

const competitionLink = (suffix = '') => (competitionId.value ? `/dashboard/competitions/${competitionId.value}${suffix}` : null)

const billingHome = computed(() => (auth.can('billing.view') ? '/dashboard/billing' : '/dashboard'))

const retryTarget = computed(() => {
  if (payment.value?.purpose === 'sponsorship') {
    return intent.value === 'invite' ? competitionLink('/participants') : competitionLink('/setup/review')
  }
  const remembered = memory.value
  if (!remembered) return '/dashboard/billing/plans'
  const query: Record<string, string> = { plan: remembered.planId, interval: remembered.interval }
  if (remembered.seats !== null) query.seats = String(remembered.seats)
  if (remembered.returnTo) query.return = remembered.returnTo
  return { path: '/dashboard/billing/checkout', query }
})

const successTarget = computed<{ to: string, label: string }>(() => {
  if (payment.value?.purpose === 'sponsorship') {
    if (intent.value === 'invite') {
      const to = competitionLink('/participants')
      return to ? { to, label: t('billing.payment_return.sponsorship.participants') } : { to: '/dashboard', label: t('billing.payment_return.to_dashboard') }
    }
    if (competitionStatus.value === 'draft') {
      const to = competitionLink('/setup/review')
      return to ? { to, label: t('billing.payment_return.sponsorship.review') } : { to: '/dashboard', label: t('billing.payment_return.to_dashboard') }
    }
    const to = competitionLink()
    return to ? { to, label: t('billing.payment_return.sponsorship.open_competition') } : { to: '/dashboard', label: t('billing.payment_return.to_dashboard') }
  }
  if (memory.value?.returnTo) return { to: stripLocalePrefix(memory.value.returnTo), label: t('billing.payment_return.continue') }
  return { to: billingHome.value, label: auth.can('billing.view') ? t('billing.payment_return.to_billing') : t('billing.payment_return.to_dashboard') }
})

const pendingTarget = computed(() => {
  if (payment.value?.purpose === 'sponsorship') return competitionLink() ?? '/dashboard'
  return billingHome.value
})

// ---------- Copy ----------

const copy = computed<{ title: string, description: string | null }>(() => {
  const p = payment.value
  switch (outcome.value) {
    case 'checking':
      return { title: t('billing.payment_return.checking.title'), description: t('billing.payment_return.checking.body') }
    case 'still_pending':
      return { title: t('billing.payment_return.still_pending.title'), description: t('billing.payment_return.still_pending.body') }
    case 'failed':
      return { title: t('billing.payment_return.failed.title'), description: p?.failure_message || t('billing.payment_return.failed.body') }
    case 'expired':
      return { title: t('billing.payment_return.expired.title'), description: t('billing.payment_return.expired.body') }
    case 'refunded':
      return { title: t('billing.payment_return.refunded.title'), description: t('billing.payment_return.refunded.body') }
    default:
      break
  }
  if (p?.purpose === 'sponsorship') {
    if (intent.value === 'invite') return { title: t('billing.payment_return.sponsorship.invited_title'), description: t('billing.payment_return.sponsorship.invited_body') }
    if (competitionStatus.value === 'draft') return { title: t('billing.payment_return.sponsorship.draft_title'), description: t('billing.payment_return.sponsorship.draft_body') }
    return { title: t('billing.payment_return.sponsorship.published_title'), description: t('billing.payment_return.sponsorship.published_body') }
  }
  const subscription = paidSubscription.value
  if (subscription?.status === 'active') {
    return {
      title: t('billing.payment_return.subscription.title'),
      description: t('billing.payment_return.subscription.body', { plan: subscription.plan.name, date: date.formatDate(subscription.ends_at) }),
    }
  }
  if (subscription) {
    return {
      title: t('billing.payment_return.subscription.renewed_title'),
      description: t('billing.payment_return.subscription.renewed_body', { plan: subscription.plan.name, start: date.formatDate(subscription.starts_at), end: date.formatDate(subscription.ends_at) }),
    }
  }
  return { title: t('billing.payment_return.subscription.generic_title'), description: t('billing.payment_return.subscription.body_generic') }
})

const showAmounts = computed(() => payment.value !== null && outcome.value !== 'checking')
</script>

<template>
  <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <UiPageHeader :title="t('billing.payment_return.title')" />

    <UiCard v-if="!paymentId">
      <UiNotFoundState
        :title="t('billing.payment_return.missing.title')"
        :description="t('billing.payment_return.missing.body')"
      />
    </UiCard>

    <UiForbiddenState v-else-if="pollErrorState === 'forbidden'" />

    <UiCard v-else-if="pollErrorState === 'not_found'">
      <UiNotFoundState
        :title="t('billing.payment_return.missing.title')"
        :description="t('billing.payment_return.missing.body')"
      />
    </UiCard>

    <UiCard
      v-else-if="pollErrorState === 'error'"
      padding="none"
    >
      <UiErrorState
        :error="poll.error.value"
        @retry="poll.start()"
      />
    </UiCard>

    <template v-else>
      <UiCard>
        <BillingPaymentStatusPanel
          :outcome="outcome"
          :title="copy.title"
          :description="copy.description"
        >
          <template
            v-if="outcome !== 'checking'"
            #actions
          >
            <template v-if="outcome === 'succeeded'">
              <UiButton :to="successTarget.to">
                {{ successTarget.label }}
              </UiButton>
            </template>
            <template v-else-if="outcome === 'still_pending'">
              <UiButton
                variant="secondary"
                @click="poll.start()"
              >
                {{ t('billing.payment_return.check_again') }}
              </UiButton>
              <UiButton :to="pendingTarget">
                {{ t('billing.payment_return.done') }}
              </UiButton>
            </template>
            <template v-else-if="outcome === 'failed' || outcome === 'expired'">
              <UiButton
                v-if="retryTarget"
                :to="retryTarget"
              >
                {{ payment?.purpose === 'sponsorship' && intent === 'invite' ? t('billing.payment_return.back') : t('billing.payment_return.try_again') }}
              </UiButton>
              <UiButton
                variant="secondary"
                :to="billingHome"
              >
                {{ auth.can('billing.view') ? t('billing.payment_return.to_billing') : t('billing.payment_return.to_dashboard') }}
              </UiButton>
            </template>
            <template v-else>
              <UiButton :to="billingHome">
                {{ auth.can('billing.view') ? t('billing.payment_return.to_billing') : t('billing.payment_return.to_dashboard') }}
              </UiButton>
            </template>
          </template>
        </BillingPaymentStatusPanel>
      </UiCard>

      <UiCard
        v-if="showAmounts && payment"
        as="section"
        :title="t('billing.payment_return.receipt_title')"
      >
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
        <template
          v-if="payment.paid_at || (payment.invoice_id && auth.can('billing.view'))"
          #footer
        >
          <p
            v-if="payment.paid_at"
            class="me-auto text-sm text-fg-muted"
          >
            {{ t('billing.payment_return.paid_at') }}
            <UiDateTime
              :value="payment.paid_at"
              format="deadline"
            />
          </p>
          <UiButton
            v-if="payment.invoice_id && auth.can('billing.view') && features.enabled('billing_invoices')"
            variant="secondary"
            size="sm"
            :to="`/dashboard/billing/invoices/${payment.invoice_id}`"
          >
            {{ t('billing.payment_return.invoice') }}
          </UiButton>
        </template>
      </UiCard>
    </template>
  </div>
</template>
