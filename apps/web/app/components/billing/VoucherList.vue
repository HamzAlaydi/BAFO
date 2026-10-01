<script setup lang="ts">
import { Ticket } from '@lucide/vue'
import type { Voucher } from '~/types/api/billing'

/**
 * `VoucherList` (SCREENS W30): the organization's vouchers, active first (server order): code with a
 * copy button, remaining balance of the original amount, validity, reason and source competition.
 */
defineProps<{ vouchers: Voucher[] }>()
const { t } = useI18n()
</script>

<template>
  <ul
    class="flex flex-col divide-y divide-line"
    :aria-label="t('billing.vouchers.title')"
  >
    <li
      v-for="voucher in vouchers"
      :key="voucher.id"
      class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
    >
      <div class="flex min-w-0 items-start gap-3">
        <span
          class="inline-flex size-10 shrink-0 items-center justify-center rounded-md"
          :class="voucher.is_active ? 'bg-info-soft text-info-soft-fg' : 'bg-neutral-soft text-neutral-soft-fg'"
          aria-hidden="true"
        >
          <Ticket :size="20" />
        </span>
        <div class="flex min-w-0 flex-col gap-1">
          <div class="flex flex-wrap items-center gap-2">
            <bdi class="font-mono text-sm font-bold text-fg">{{ voucher.code }}</bdi>
            <UiCopyButton
              :value="voucher.code"
              :label="t('billing.vouchers.copy_code', { code: voucher.code })"
            />
            <UiBadge
              :tone="voucher.is_active ? 'info' : 'neutral'"
              size="sm"
              dot
            >
              {{ voucher.is_active ? t('billing.vouchers.active') : t('billing.vouchers.inactive') }}
            </UiBadge>
          </div>
          <p
            v-if="voucher.reason"
            class="text-sm text-fg-muted"
          >
            <BillingIsolatedText :text="voucher.reason" />
          </p>
          <p
            v-if="voucher.valid_until"
            class="text-sm text-fg-muted"
          >
            {{ t('billing.vouchers.valid_until') }}
            <UiDateTime
              :value="voucher.valid_until"
              format="date"
            />
          </p>
          <UiButton
            v-if="voucher.source_competition_id"
            variant="link"
            size="sm"
            class="self-start text-sm"
            :to="`/dashboard/competitions/${voucher.source_competition_id}`"
          >
            {{ t('billing.vouchers.source_competition') }}
          </UiButton>
        </div>
      </div>
      <div class="flex shrink-0 flex-col gap-0.5 sm:items-end">
        <span class="text-xs text-fg-muted">{{ t('billing.vouchers.balance') }}</span>
        <UiAmount
          :minor="voucher.balance_minor"
          size="lg"
        />
        <span class="text-xs text-fg-muted">
          <i18n-t
            keypath="billing.vouchers.of_amount"
            scope="global"
          >
            <template #amount>
              <UiAmount
                :minor="voucher.amount_minor"
                size="sm"
              />
            </template>
          </i18n-t>
        </span>
      </div>
    </li>
  </ul>
</template>
