<script setup lang="ts">
import { ArrowLeft, ArrowRight, Hourglass, Info, Layers } from '@lucide/vue'
import { fetchPlans, startTrial } from '~/services/billing'
import type { BillingInterval, Plan } from '~/types/api/billing'

/**
 * W31 Plans · `/dashboard/billing/plans` (every user; the CTA needs `billing.purchase`; SCREENS §2.4).
 * `GET /plans` with the interval toggle, a card per plan (server prices excl. VAT, list price
 * struck through, "Current plan"), and the custom plan with a live server quote. The CTA opens
 * checkout (W32) with `plan`, `interval`, `seats` and the optional `?return=` (invitee prompts, F8).
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const home = useHomeStore()
const toast = useToast()
const route = useRoute()
const locale = useAppLocale()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('billing.plans.title') })

const interval = ref<BillingInterval>(isBillingInterval(route.query.interval) ? route.query.interval : 'monthly')
const plans = ref<Plan[]>([])
const customSeats = ref<number | null>(parsePositiveInt(route.query.seats))
const loading = ref(true)
const loadError = ref<unknown>(null)

const canPurchase = computed(() => auth.can('billing.purchase'))
const returnTo = computed(() => safeDashboardReturn(route.query.return))
const currentPlanId = computed(() => {
  const subscription = auth.subscription
  return subscription && subscription.status === 'active' ? subscription.plan.id : null
})
const forward = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))

async function load(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    plans.value = await fetchPlans()
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const fixedPlans = computed(() => plans.value.filter(plan => !plan.is_custom))
const customPlan = computed(() => plans.value.find(plan => plan.is_custom && plan.custom) ?? null)

// The trial (ARCHITECTURE §13.3) is offered where plans are chosen; the server decides availability.
const trialAvailable = computed(() => auth.organization?.trial_available === true)
const startingTrial = ref(false)
const trialError = ref<string | null>(null)

async function onStartTrial(): Promise<void> {
  startingTrial.value = true
  trialError.value = null
  try {
    await startTrial()
    toast.success(t('billing.overview.trial.started'))
    await Promise.allSettled([auth.fetchMe(), home.load()])
  }
  catch (error) {
    trialError.value = message(error)
    if (error instanceof ApiError && error.code === 'trial_not_available') auth.fetchMe().catch(() => {})
  }
  finally {
    startingTrial.value = false
  }
}

function checkoutTarget(plan: Plan, seats: number | null = null) {
  const query: Record<string, string> = { plan: plan.id, interval: interval.value }
  if (plan.is_custom && seats !== null) query.seats = String(seats)
  if (returnTo.value) query.return = returnTo.value
  return { path: '/dashboard/billing/checkout', query }
}

function ctaLabel(plan: Plan): string {
  return plan.id === currentPlanId.value ? t('billing.plans.renew') : t('billing.plans.choose')
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('billing.plans.title')"
      :description="t('billing.plans.subtitle')"
    >
      <template #actions>
        <BillingIntervalToggle v-model="interval" />
      </template>
    </UiPageHeader>

    <UiAlert
      v-if="returnTo"
      tone="info"
    >
      {{ t('billing.plans.return_hint') }}
    </UiAlert>

    <UiCard
      v-if="trialAvailable && canPurchase"
      as="section"
      :aria-label="t('billing.overview.trial.title')"
    >
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
          <span
            class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-info-soft text-info-soft-fg"
            aria-hidden="true"
          >
            <Hourglass :size="20" />
          </span>
          <div>
            <h2 class="font-bold text-fg">
              {{ t('billing.overview.trial.title') }}
            </h2>
            <p class="mt-1 text-sm text-fg-muted">
              {{ t('billing.overview.trial.body') }}
            </p>
          </div>
        </div>
        <UiButton
          class="shrink-0"
          variant="secondary"
          :loading="startingTrial"
          @click="onStartTrial"
        >
          {{ t('billing.overview.trial.start') }}
        </UiButton>
      </div>
      <UiAlert
        v-if="trialError"
        class="mt-4"
        tone="danger"
        role="alert"
      >
        {{ trialError }}
      </UiAlert>
    </UiCard>

    <UiAlert
      v-if="!canPurchase"
      tone="info"
      :icon="Info"
    >
      {{ t('billing.common.purchase_permission') }}
    </UiAlert>

    <!-- L -->
    <div
      v-if="loading && plans.length === 0"
      class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
      :aria-label="t('common.loading')"
    >
      <UiCard
        v-for="n in 3"
        :key="n"
      >
        <UiSkeleton :lines="7" />
      </UiCard>
    </div>

    <!-- X -->
    <UiCard
      v-else-if="loadError && plans.length === 0"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
    </UiCard>

    <!-- E -->
    <UiCard v-else-if="plans.length === 0">
      <UiEmptyState
        :icon="Layers"
        :title="t('billing.plans.empty.title')"
        :description="t('billing.plans.empty.body')"
      />
    </UiCard>

    <template v-else>
      <ul
        class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        :aria-label="t('billing.plans.list_label')"
      >
        <li
          v-for="plan in fixedPlans"
          :key="plan.id"
        >
          <BillingPlanCard
            :plan="plan"
            :interval="interval"
            :current="plan.id === currentPlanId"
          >
            <template #action="{ available }">
              <UiButton
                v-if="canPurchase"
                block
                :variant="plan.is_featured ? 'primary' : 'secondary'"
                :disabled="!available"
                :to="checkoutTarget(plan)"
                :icon-end="forward"
                :aria-label="t('billing.plans.choose_named', { plan: plan.name })"
              >
                {{ ctaLabel(plan) }}
              </UiButton>
            </template>
          </BillingPlanCard>
        </li>
        <li
          v-if="customPlan && features.enabled('custom_plan_quote')"
          class="md:col-span-2 xl:col-span-1"
        >
          <BillingCustomPlanCard
            v-model:seats="customSeats"
            :plan="customPlan"
            :interval="interval"
            :current="customPlan.id === currentPlanId"
          >
            <template #action="{ available, seats }">
              <UiButton
                v-if="canPurchase"
                block
                variant="secondary"
                :disabled="!available"
                :to="checkoutTarget(customPlan, seats)"
                :icon-end="forward"
                :aria-label="t('billing.plans.choose_named', { plan: customPlan.name })"
              >
                {{ ctaLabel(customPlan) }}
              </UiButton>
            </template>
          </BillingCustomPlanCard>
        </li>
      </ul>

      <div class="flex flex-col gap-1 text-sm text-fg-muted">
        <p>{{ t('common.prices_exclude_vat') }}</p>
        <p>{{ t('billing.plans.footnote') }}</p>
      </div>
    </template>
  </div>
</template>
