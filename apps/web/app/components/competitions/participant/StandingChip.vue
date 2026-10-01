<script setup lang="ts">
import { CircleAlert, CircleCheck } from '@lucide/vue'

/**
 * Compact standing chip for lists (SCREENS W23): shown only when the server projected `is_leading`
 * (never computed). Leading: primary-soft with a check; not leading: the amber outbid tone with an
 * alert icon, never red.
 */
defineProps<{
  leading: boolean
  size?: 'sm' | 'md'
}>()

const { t } = useI18n()
</script>

<template>
  <UiBadge
    :tone="leading ? 'primary' : 'warning'"
    :icon="leading ? CircleCheck : CircleAlert"
    :size="size ?? 'sm'"
  >
    {{ leading ? t('competitions.participating.card.leading') : t('competitions.participating.card.not_leading') }}
  </UiBadge>
</template>
