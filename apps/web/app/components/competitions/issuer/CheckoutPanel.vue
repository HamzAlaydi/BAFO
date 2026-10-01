<script setup lang="ts">
import { CreditCard } from '@lucide/vue'
import type { Payment } from '~/types/api/billing'

/**
 * Payment confirmation before the hosted checkout (SCREENS F3, W17 "Pay and send"): the server's
 * amounts only (lines, subtotal, credit, discount, VAT, total, hold expiry), then **Pay {total}**
 * navigates to `redirect_url`. The page never shows card or gateway details.
 */
const props = defineProps<{ payment: Payment }>()
const emit = defineEmits<{ cancel: [] }>()

const { t } = useI18n()
const money = useMoney()
const leaving = ref(false)

function pay(): void {
  if (!props.payment.redirect_url) return
  leaving.value = true
  window.location.assign(props.payment.redirect_url)
}
</script>

<template>
  <UiCard
    :title="t('sponsorship.fees.checkout.title')"
    :description="t('sponsorship.fees.checkout.description')"
  >
    <div class="flex flex-col gap-4">
      <ul class="flex flex-col gap-2 text-sm">
        <li
          v-for="(line, index) in payment.lines"
          :key="index"
          class="flex items-start justify-between gap-3"
        >
          <span class="min-w-0 text-fg">
            {{ line.description }}
            <span class="text-fg-muted">{{ '×' }} <bdi class="tabular-nums">{{ line.quantity }}</bdi></span>
          </span>
          <UiAmount :minor="line.net_minor" />
        </li>
      </ul>
      <dl class="flex flex-col gap-2 border-t border-line pt-3 text-sm">
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">
            {{ t('sponsorship.fees.checkout.subtotal') }}
          </dt>
          <dd><UiAmount :minor="payment.subtotal_minor" /></dd>
        </div>
        <div
          v-if="payment.credit_minor > 0"
          class="flex justify-between gap-3"
        >
          <dt class="text-fg-muted">
            {{ t('sponsorship.fees.checkout.credit') }}
          </dt>
          <dd>
            <UiAmount
              :minor="payment.credit_minor"
              sign="minus"
            />
          </dd>
        </div>
        <div
          v-if="payment.discount_minor > 0"
          class="flex justify-between gap-3"
        >
          <dt class="text-fg-muted">
            {{ t('sponsorship.fees.summary.discount') }}
          </dt>
          <dd>
            <UiAmount
              :minor="payment.discount_minor"
              sign="minus"
            />
          </dd>
        </div>
        <div class="flex justify-between gap-3">
          <dt class="text-fg-muted">
            {{ t('sponsorship.fees.summary.vat', { rate: formatBps(payment.vat_rate_bp) }) }}
          </dt>
          <dd><UiAmount :minor="payment.vat_minor" /></dd>
        </div>
        <div class="flex justify-between gap-3 border-t border-line pt-2">
          <dt class="font-bold text-fg">
            {{ t('sponsorship.fees.summary.total') }}
          </dt>
          <dd>
            <UiAmount
              :minor="payment.total_minor"
              size="lg"
            />
          </dd>
        </div>
      </dl>
      <p
        v-if="payment.expires_at"
        class="text-sm text-fg-muted"
      >
        {{ t('sponsorship.fees.checkout.expires') }}
        <UiDateTime
          :value="payment.expires_at"
          format="time"
        />
      </p>
    </div>
    <template #footer>
      <UiButton
        variant="secondary"
        :disabled="leaving"
        @click="emit('cancel')"
      >
        {{ t('common.actions.cancel') }}
      </UiButton>
      <UiButton
        :icon="CreditCard"
        :loading="leaving"
        :disabled="!payment.redirect_url"
        @click="pay"
      >
        {{ t('sponsorship.fees.checkout.pay', { amount: money.format(payment.total_minor) }) }}
      </UiButton>
    </template>
  </UiCard>
</template>
