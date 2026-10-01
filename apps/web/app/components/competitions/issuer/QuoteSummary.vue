<script setup lang="ts">
import type { SponsorshipQuote } from '~/types/api/billing'

/**
 * Order summary of a covered-fees quote (SCREENS W15 step 7, W17 invite): passes to buy × unit price,
 * discount, VAT and total, all server amounts (the client never computes money). Passes that reuse
 * already funded slots are noted separately.
 */
defineProps<{ quote: SponsorshipQuote }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-3">
    <dl class="flex flex-col gap-2 text-sm">
      <div class="flex items-center justify-between gap-3">
        <dt class="text-fg-muted">
          {{ t('sponsorship.fees.summary.passes', { count: quote.passes_to_buy }, quote.passes_to_buy) }}
          <span class="text-xs">{{ '×' }} <UiAmount
            :minor="quote.unit_price_minor"
            size="sm"
          /></span>
        </dt>
        <dd><UiAmount :minor="quote.subtotal_minor" /></dd>
      </div>
      <div
        v-if="quote.discount_minor > 0"
        class="flex items-center justify-between gap-3"
      >
        <dt class="text-fg-muted">
          {{ t('sponsorship.fees.summary.discount') }}
        </dt>
        <dd>
          <UiAmount
            :minor="quote.discount_minor"
            sign="minus"
          />
        </dd>
      </div>
      <div class="flex items-center justify-between gap-3">
        <dt class="text-fg-muted">
          {{ t('sponsorship.fees.summary.vat', { rate: formatBps(quote.vat_rate_bp) }) }}
        </dt>
        <dd><UiAmount :minor="quote.vat_minor" /></dd>
      </div>
      <div class="flex items-center justify-between gap-3 border-t border-line pt-2">
        <dt class="font-bold text-fg">
          {{ t('sponsorship.fees.summary.total') }}
        </dt>
        <dd>
          <UiAmount
            :minor="quote.total_minor"
            size="lg"
          />
        </dd>
      </div>
    </dl>
    <p
      v-if="quote.passes_to_reserve > 0"
      class="text-sm text-fg-muted"
    >
      {{ t('sponsorship.fees.summary.reserve_note', { count: quote.passes_to_reserve }, quote.passes_to_reserve) }}
    </p>
    <p class="text-xs text-fg-muted">
      {{ t('common.prices_exclude_vat') }}
    </p>
  </div>
</template>
