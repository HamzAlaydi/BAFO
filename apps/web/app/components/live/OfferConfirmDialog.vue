<script setup lang="ts">
import type { OfferMode } from '~/stores/liveRules'
import type { Direction } from '~/types/api/competitions'

/**
 * `OfferConfirmDialog` (SCREENS S5 "Confirm step (always)"): the amount large as an LTR island,
 * "excl. VAT", the direction reminder, and the stage note (sealed: revisable until closing; BAFO:
 * the only final offer). The caller owns the idempotency key (created when this dialog opens and
 * reused on retry) and shows the busy state; nothing changes on screen before the server answers.
 */
const props = defineProps<{
  amountMinor: number | null
  direction: Direction
  mode: OfferMode
  busy?: boolean
  /** The last attempt got no answer: the same key is retried. */
  unconfirmed?: boolean
  error?: string | null
  cooldownSeconds?: number
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()
const { t } = useI18n()

const note = computed(() => {
  switch (props.mode) {
    case 'sealed': return t('offers.confirm.sealed')
    case 'bafo': return t('offers.confirm.bafo')
    default: return t('offers.confirm.live')
  }
})

const confirmLabel = computed(() => {
  if ((props.cooldownSeconds ?? 0) > 0) return t('live.composer.cooldown', { seconds: props.cooldownSeconds })
  if (props.unconfirmed) return t('offers.confirm.retry')
  return props.mode === 'bafo' ? t('offers.confirm.submit_bafo') : t('offers.confirm.submit')
})
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="mode === 'bafo' ? t('offers.confirm.title_bafo') : t('offers.confirm.title')"
    size="sm"
    :dismissible="!busy"
  >
    <div
      class="flex flex-col gap-4"
      data-testid="offer-confirm"
    >
      <div class="flex flex-col items-center gap-1 rounded-lg bg-surface-muted px-4 py-5 text-center">
        <span class="text-sm text-fg-muted">{{ t('offers.confirm.amount_label') }}</span>
        <UiAmount
          :minor="amountMinor"
          size="xl"
          class="text-fg"
          data-testid="confirm-amount"
        />
        <span class="text-xs text-fg-muted">{{ t('offers.confirm.excl_vat') }}</span>
      </div>
      <CompetitionsDirectionChip
        :direction="direction"
        class="self-center"
      />
      <p class="text-sm text-fg">
        {{ note }}
      </p>
      <UiAlert
        v-if="unconfirmed"
        tone="warning"
      >
        {{ t('offers.error.unconfirmed') }}
      </UiAlert>
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
        {{ t('common.actions.cancel') }}
      </UiButton>
      <UiButton
        :loading="busy"
        :disabled="(cooldownSeconds ?? 0) > 0"
        data-testid="confirm-offer"
        @click="emit('confirm')"
      >
        {{ confirmLabel }}
      </UiButton>
    </template>
  </UiModal>
</template>
