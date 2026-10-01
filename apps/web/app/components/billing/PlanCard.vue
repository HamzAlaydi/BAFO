<script setup lang="ts">
import { Check, Users } from '@lucide/vue'
import type { BillingInterval, Plan } from '~/types/api/billing'

/**
 * A fixed plan (SCREENS W31): name, features, seats, the server price for the interval excl. VAT
 * (the list price struck through when higher), the VAT note, "Current plan" and the CTA slot.
 * The custom plan uses `BillingCustomPlanCard`.
 */
const props = defineProps<{
  plan: Plan
  interval: BillingInterval
  current?: boolean
}>()

const { t } = useI18n()
const money = useMoney()
const titleId = useId()

const price = computed(() => planIntervalPrice(props.plan, props.interval))
const periodLabel = computed(() => t(props.interval === 'monthly' ? 'billing.common.per_month' : 'billing.common.per_year'))
</script>

<template>
  <article
    class="relative flex h-full flex-col gap-5 rounded-lg border bg-surface p-5 shadow-xs sm:p-6"
    :class="plan.is_featured ? 'border-primary ring-1 ring-primary' : 'border-line'"
    :aria-labelledby="titleId"
  >
    <div
      v-if="current || plan.is_featured"
      class="flex flex-wrap items-center gap-2"
    >
      <UiBadge
        v-if="current"
        tone="primary"
        solid
        size="sm"
        :icon="Check"
      >
        {{ t('billing.plans.current') }}
      </UiBadge>
      <UiBadge
        v-if="plan.is_featured"
        tone="primary"
        size="sm"
      >
        {{ t('billing.plans.featured') }}
      </UiBadge>
    </div>

    <div class="flex flex-col gap-1">
      <h3
        :id="titleId"
        class="text-xl font-bold text-fg"
      >
        {{ plan.name }}
      </h3>
      <p
        v-if="plan.description"
        class="text-sm text-fg-muted"
      >
        {{ plan.description }}
      </p>
    </div>

    <div class="flex flex-col gap-1">
      <template v-if="price.price !== null">
        <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
          <bdi class="text-3xl font-bold whitespace-nowrap text-fg tabular-nums">{{ money.format(price.price) }}</bdi>
          <span class="text-sm text-fg-muted">{{ periodLabel }}</span>
        </p>
        <p
          v-if="price.listPrice !== null"
          class="text-sm text-fg-muted"
        >
          <span class="sr-only">{{ t('billing.plans.list_price', { amount: money.format(price.listPrice) }) }}</span>
          <del
            class="tabular-nums"
            aria-hidden="true"
          ><bdi>{{ money.format(price.listPrice) }}</bdi></del>
        </p>
        <p class="text-xs text-fg-muted">
          {{ t('billing.common.vat_note', { rate: t('billing.common.percent', { value: formatBps(plan.vat_rate_bp) }) }) }}
        </p>
      </template>
      <p
        v-else
        class="text-sm font-semibold text-fg-muted"
      >
        {{ t('billing.plans.unavailable_interval') }}
      </p>
    </div>

    <p
      v-if="plan.seats !== null"
      class="inline-flex items-center gap-2 text-sm font-semibold text-fg"
    >
      <Users
        :size="16"
        class="text-brand"
        aria-hidden="true"
      />
      {{ t('billing.common.seats', { count: plan.seats }, plan.seats) }}
    </p>

    <ul
      v-if="plan.features.length > 0"
      class="flex flex-1 flex-col gap-2 text-sm"
      :aria-label="t('billing.plans.features_label', { plan: plan.name })"
    >
      <li
        v-for="feature in plan.features"
        :key="feature"
        class="flex items-start gap-2"
      >
        <Check
          :size="16"
          class="mt-0.5 shrink-0 text-brand"
          aria-hidden="true"
        />
        <span class="text-fg">{{ feature }}</span>
      </li>
    </ul>
    <div
      v-else
      class="flex-1"
    />

    <div class="flex flex-col gap-2">
      <slot
        name="action"
        :available="price.price !== null"
      />
    </div>
  </article>
</template>
