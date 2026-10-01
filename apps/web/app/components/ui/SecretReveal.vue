<script setup lang="ts">
import { KeyRound } from '@lucide/vue'

/**
 * A secret shown once (API client secret, API key, webhook signing secret): monospace LTR value with a
 * copy button, a warning that it will not be shown again, and a required acknowledgement before the
 * caller lets the user leave (`v-model:acknowledged`). Never store or log the value.
 */
defineProps<{
  label: string
  value: string
}>()

const acknowledged = defineModel<boolean>('acknowledged', { default: false })
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-3">
    <UiAlert
      tone="warning"
      :icon="KeyRound"
    >
      {{ t('common.secret.once') }}
    </UiAlert>
    <div class="flex flex-col gap-1.5">
      <span class="text-sm font-semibold text-fg">{{ label }}</span>
      <div class="flex items-center gap-2 rounded-md border border-line bg-surface-muted px-3 py-2">
        <code
          dir="ltr"
          class="min-w-0 flex-1 font-mono text-sm break-all text-fg select-all"
        >{{ value }}</code>
        <UiCopyButton
          :value="value"
          :label="t('common.secret.copy', { label })"
        />
      </div>
    </div>
    <UiCheckbox
      v-model="acknowledged"
      :label="t('common.secret.acknowledge')"
      required
    />
  </div>
</template>
