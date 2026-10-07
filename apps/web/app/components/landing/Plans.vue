<script setup lang="ts">
import type { BillingInterval, Plan } from '~/types/api/billing'
import type { ChoiceOption } from '~/types/ui'

/**
 * Pricing teaser (RELEASE_SCOPE §6.1 row 6): up to three fixed plans from `GET /plans` with the
 * monthly / annual toggle, prices excl. VAT and the register CTA. The parent hides the whole
 * section when the list is empty or failed to load.
 */
defineProps<{
  plans: Plan[]
  hasCustomPlan: boolean
}>()

const { t } = useI18n()
const interval = ref<BillingInterval>('monthly')

const intervals = computed<ChoiceOption<BillingInterval>[]>(() => [
  { value: 'monthly', label: t('billing.intervals.monthly') },
  { value: 'annual', label: t('billing.intervals.annual') },
])
</script>

<template>
  <section
    id="plans"
    class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-24"
    aria-labelledby="plans-title"
  >
    <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
      <LandingSectionHeading
        id="plans-title"
        :title="t('landing.plans.title')"
        :subtitle="t('landing.plans.subtitle')"
      />
      <UiSegmented
        v-model="interval"
        :options="intervals"
        :label="t('landing.plans.interval_label')"
        name="landing-plan-interval"
        class="shrink-0"
      />
    </div>
    <ul
      class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3"
      :aria-label="t('billing.plans.list_label')"
    >
      <li
        v-for="plan in plans"
        :key="plan.id"
      >
        <BillingPlanCard
          :plan="plan"
          :interval="interval"
        >
          <template #action>
            <UiButton
              block
              :variant="plan.is_featured ? 'primary' : 'secondary'"
              to="/auth/register"
            >
              {{ t('landing.plans.cta') }}
            </UiButton>
          </template>
        </BillingPlanCard>
      </li>
    </ul>
    <div class="mt-6 flex flex-col gap-1 text-sm text-fg-muted">
      <p v-if="hasCustomPlan">
        {{ t('landing.plans.custom') }}
      </p>
      <p>{{ t('landing.plans.trial') }}</p>
      <p>{{ t('common.prices_exclude_vat') }}</p>
    </div>
  </section>
</template>
