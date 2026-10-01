<script setup lang="ts">
import { Lock, Radio } from '@lucide/vue'
import type { Format } from '~/types/api/competitions'

/** Format chip (SCREENS S2): Live «مباشرة»; Sealed: lock + «بظرف مغلق». Neutral tone, icon and text. */
withDefaults(defineProps<{
  format: Format
  size?: 'sm' | 'md'
}>(), {
  size: 'md',
})

const { t } = useI18n()
</script>

<template>
  <UiBadge
    tone="neutral"
    :size="size"
    :icon="format === 'sealed' ? Lock : Radio"
  >
    {{ t(`competitions.format.${format}`) }}
  </UiBadge>
</template>
