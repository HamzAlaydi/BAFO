<script setup lang="ts">
import { Check, Copy } from '@lucide/vue'

/** Copies `value` and announces «تم النسخ» / "Copied" politely. */
const props = withDefaults(defineProps<{
  value: string
  /** Accessible name, e.g. «نسخ معرّف العميل». */
  label?: string
  size?: 'sm' | 'md'
}>(), {
  size: 'sm',
})

const { t } = useI18n()
const { copy, copied, isSupported } = useClipboard({ copiedDuring: 2000, legacy: true })
</script>

<template>
  <span class="inline-flex items-center">
    <UiIconButton
      :icon="copied ? Check : Copy"
      :size="size"
      :label="label ?? t('common.actions.copy')"
      :disabled="!isSupported"
      @click="copy(props.value)"
    />
    <span
      class="sr-only"
      aria-live="polite"
    >{{ copied ? t('common.actions.copied') : '' }}</span>
  </span>
</template>
