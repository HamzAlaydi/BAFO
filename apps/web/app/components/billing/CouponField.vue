<script setup lang="ts">
import { TicketPercent, X } from '@lucide/vue'
import { validateCoupon } from '~/services/billing'
import type { BillingInterval, CouponValidation, ValidateCouponRequest } from '~/types/api/billing'

type CouponContext
  = | { purpose: 'subscription', plan_id: string, interval: BillingInterval, seats?: number | null }
    | { purpose: 'sponsorship', competition_id: string }

/**
 * Coupon or voucher code for every payment (SCREENS W32 checkout, W15 step 7 fees, W17 "Pay and
 * send"): `POST /billing/coupons/validate` for the purchase context; the applied code shows the server
 * discount. One code per payment. The parent clears the model when the context changes (another plan,
 * interval or seat count).
 *
 * `v-model` is the validation; `v-model:code` is the applied code alone, for callers that keep only
 * the code (the sponsorship quote carries the discount). A code applied earlier without its
 * validation is shown without the amount.
 */
const props = defineProps<{
  context: CouponContext
  /** An error for the applied code from the checkout call (e.g. `coupon_exhausted`). */
  error?: string | null
  disabled?: boolean
}>()

const applied = defineModel<CouponValidation | null>({ default: null })
const appliedCode = defineModel<string | null>('code', { default: null })
const { t } = useI18n()
const { message } = useErrorMessage()
const code = ref('')
const busy = ref(false)
const localError = ref<string | null>(null)

const shownError = computed(() => localError.value ?? props.error ?? null)

async function apply(): Promise<void> {
  const value = normalizeDigits(code.value).trim()
  localError.value = null
  if (!value) {
    localError.value = t('billing.coupon.required')
    return
  }
  busy.value = true
  try {
    const result = await validateCoupon({ ...props.context, code: value } as ValidateCouponRequest)
    applied.value = result
    appliedCode.value = result.code
    code.value = ''
  }
  catch (error) {
    localError.value = message(error)
  }
  finally {
    busy.value = false
  }
}

function remove(): void {
  applied.value = null
  appliedCode.value = null
  localError.value = null
}

const shownCode = computed(() => applied.value?.code ?? appliedCode.value)
</script>

<template>
  <div class="flex flex-col gap-2">
    <div
      v-if="shownCode"
      class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-primary-soft px-3 py-2 text-primary-soft-fg"
      role="status"
    >
      <span class="inline-flex min-w-0 items-center gap-2 text-sm font-semibold">
        <TicketPercent
          :size="18"
          aria-hidden="true"
        />
        <span>
          <i18n-t
            :keypath="applied ? 'billing.coupon.applied' : 'billing.coupon.applied_code'"
            scope="global"
          >
            <template #code>
              <bdi class="font-mono">{{ shownCode }}</bdi>
            </template>
            <template
              v-if="applied"
              #amount
            >
              <UiAmount :minor="applied.discount_minor" />
            </template>
          </i18n-t>
        </span>
      </span>
      <UiButton
        variant="ghost"
        size="sm"
        :icon="X"
        :disabled="disabled"
        @click="remove"
      >
        {{ t('billing.coupon.remove') }}
      </UiButton>
    </div>
    <p
      v-if="shownCode && error"
      class="text-sm font-medium text-danger"
      role="alert"
    >
      {{ error }}
    </p>

    <form
      v-if="!shownCode"
      class="flex flex-col gap-2 sm:flex-row sm:items-start"
      novalidate
      @submit.prevent="apply"
    >
      <div class="min-w-0 flex-1">
        <UiInput
          v-model="code"
          :label="t('billing.coupon.label')"
          :hint="t('billing.coupon.hint')"
          :error="shownError"
          dir="ltr"
          autocomplete="off"
          :maxlength="64"
          :disabled="disabled || busy"
        />
      </div>
      <UiButton
        type="submit"
        variant="secondary"
        class="sm:mt-[1.875rem]"
        :loading="busy"
        :disabled="disabled"
      >
        {{ t('billing.coupon.apply') }}
      </UiButton>
    </form>
  </div>
</template>
