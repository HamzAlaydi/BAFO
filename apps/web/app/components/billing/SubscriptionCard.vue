<script setup lang="ts">
import { CalendarClock, CircleCheck, Clock, Gift, Hourglass } from '@lucide/vue'
import type { Component } from 'vue'
import type { Subscription } from '~/types/api/billing'
import type { KeyValueItem } from '~/types/ui'

/**
 * `SubscriptionCard` (SCREENS W30): plan, source (paid, trial, grant), interval, seats, status,
 * period, the days-left meter and the amounts snapshot. `variant="upcoming"` is the renewal that
 * starts when the current subscription ends (no meter).
 */
const props = withDefaults(defineProps<{
  subscription: Subscription
  variant?: 'current' | 'upcoming'
}>(), {
  variant: 'current',
})

const { t } = useI18n()
const date = useDate()

const SOURCE_ICONS: Record<Subscription['source'], Component> = { paid: CircleCheck, trial: Hourglass, grant: Gift }
const STATUS_ICONS: Partial<Record<Subscription['status'], Component>> = { pending_payment: Clock, active: CircleCheck }

const items = computed<KeyValueItem[]>(() => {
  const s = props.subscription
  const rows: KeyValueItem[] = [
    { key: 'interval', label: t('billing.overview.fields.interval'), value: s.interval ? t(`billing.intervals.${s.interval}`) : t('billing.overview.no_interval') },
    { key: 'seats', label: t('billing.overview.fields.seats'), value: t('billing.common.seats', { count: s.seats }, s.seats) },
    { key: 'period', label: t('billing.overview.fields.period'), value: t('billing.overview.period_value', { start: date.formatDate(s.starts_at), end: date.formatDate(s.ends_at) }) },
  ]
  if (s.amounts) rows.push({ key: 'total', label: t('billing.overview.fields.total_paid'), value: null })
  return rows
})

const daysText = computed(() => t('billing.common.days_left', { count: props.subscription.days_left }, props.subscription.days_left))
</script>

<template>
  <UiCard
    as="section"
    :aria-label="variant === 'current' ? t('billing.overview.current_title') : t('billing.overview.upcoming_title')"
  >
    <div class="flex flex-col gap-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
          <p class="text-sm font-semibold text-fg-muted">
            {{ variant === 'current' ? t('billing.overview.current_title') : t('billing.overview.upcoming_title') }}
          </p>
          <h2 class="text-2xl font-bold text-fg">
            {{ subscription.plan.name }}
          </h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <UiBadge
            tone="neutral"
            size="sm"
            :icon="SOURCE_ICONS[subscription.source]"
          >
            {{ t(`billing.sources.${subscription.source}`) }}
          </UiBadge>
          <UiBadge
            :tone="SUBSCRIPTION_STATUS_TONES[subscription.status]"
            size="sm"
            :icon="STATUS_ICONS[subscription.status]"
            :dot="!STATUS_ICONS[subscription.status]"
          >
            {{ t(`billing.statuses.${subscription.status}`) }}
          </UiBadge>
        </div>
      </div>

      <div
        v-if="variant === 'current' && subscription.status === 'active'"
        class="flex flex-col gap-2"
      >
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
          <span class="inline-flex items-center gap-2 font-semibold text-fg">
            <CalendarClock
              :size="16"
              class="text-brand"
              aria-hidden="true"
            />
            {{ t('billing.overview.days_meter_label') }}
          </span>
          <span class="text-fg-muted tabular-nums">{{ daysText }}</span>
        </div>
        <UiMeter
          :value="subscription.days_left"
          :max="subscription.total_days"
          :label="t('billing.overview.days_meter_label')"
          :value-text="daysText"
          warn-when="low"
        />
        <p class="text-sm text-fg-muted">
          {{ t('billing.overview.ends_on', { date: date.formatDate(subscription.ends_at) }) }}
        </p>
      </div>
      <p
        v-else-if="variant === 'upcoming'"
        class="text-sm text-fg-muted"
      >
        {{ t('billing.overview.upcoming_starts', { date: date.formatDate(subscription.starts_at) }) }}
      </p>

      <UiKeyValueList :items="items">
        <template #value-total>
          <UiAmount
            v-if="subscription.amounts"
            :minor="subscription.amounts.total_minor"
          />
          <span
            v-if="subscription.amounts"
            class="ms-2 text-xs text-fg-muted"
          >{{ t('billing.common.incl_vat') }}</span>
        </template>
      </UiKeyValueList>

      <div
        v-if="$slots.actions"
        class="flex flex-wrap gap-2"
      >
        <slot name="actions" />
      </div>
    </div>
  </UiCard>
</template>
