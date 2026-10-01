<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue'
import type { OutlierPrompt } from '~/composables/useOfferSubmit'

/**
 * `OutlierConfirmDialog` (SCREENS S5, `offer_outlier_confirm_required`): «هذا العرض أقل/أعلى من
 * عرضك الحالي بنسبة {pct}.» with `pct = change_bps / 100`, lower or higher from the two amounts
 * (never from the direction). Confirming re-sends with `confirm_outlier: true` and a **new** key.
 */
const props = defineProps<{
  prompt: OutlierPrompt | null
  /** True when the reference is the participant's current offer; false when it is the start price. */
  referenceIsOwnOffer: boolean
  busy?: boolean
  error?: string | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()
const { t } = useI18n()

const messageKey = computed(() => {
  const prompt = props.prompt
  if (!prompt) return null
  const side = prompt.amountMinor < prompt.referenceAmountMinor ? 'lower' : 'higher'
  return props.referenceIsOwnOffer ? `offers.confirm.outlier.${side}` : `offers.confirm.outlier.${side}_start`
})
const percent = computed(() => (props.prompt ? formatBpsPercent(props.prompt.changeBps) : ''))
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="t('offers.confirm.outlier.title')"
    size="sm"
    :dismissible="!busy"
  >
    <div
      v-if="prompt"
      class="flex flex-col gap-4"
      data-testid="outlier-confirm"
    >
      <p class="flex items-start gap-2 rounded-md bg-warning-soft p-3 text-sm font-semibold text-warning-soft-fg">
        <TriangleAlert
          :size="18"
          class="mt-0.5 shrink-0"
          aria-hidden="true"
        />
        <i18n-t
          v-if="messageKey"
          :keypath="messageKey"
          scope="global"
          tag="span"
        >
          <template #pct>
            <bdi class="tabular-nums">{{ percent }}</bdi>
          </template>
        </i18n-t>
      </p>
      <dl class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <dt class="text-fg-muted">
            {{ t('offers.confirm.outlier.amount') }}
          </dt>
          <dd class="font-bold text-fg">
            <UiAmount :minor="prompt.amountMinor" />
          </dd>
        </div>
        <div v-if="prompt.referenceAmountMinor > 0">
          <dt class="text-fg-muted">
            {{ t('offers.confirm.outlier.reference') }}
          </dt>
          <dd class="font-semibold text-fg">
            <UiAmount :minor="prompt.referenceAmountMinor" />
          </dd>
        </div>
      </dl>
      <p class="text-sm text-fg-muted">
        {{ t('offers.confirm.outlier.check') }}
      </p>
      <UiAlert
        v-if="error"
        tone="danger"
      >
        {{ error }}
      </UiAlert>
    </div>
    <template #footer>
      <UiButton
        variant="secondary"
        :disabled="busy"
        @click="open = false"
      >
        {{ t('offers.confirm.outlier.change_amount') }}
      </UiButton>
      <UiButton
        :loading="busy"
        data-testid="confirm-outlier"
        @click="emit('confirm')"
      >
        {{ t('offers.confirm.outlier.confirm') }}
      </UiButton>
    </template>
  </UiModal>
</template>
