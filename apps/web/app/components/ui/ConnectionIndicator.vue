<script setup lang="ts">
import { LoaderCircle, WifiOff } from '@lucide/vue'
import type { LiveConnectionState } from '~/utils/connection-state'

/**
 * Small live-connection indicator for published competitions (SCREENS S4): a green dot and «مباشر»
 * when connected, a spinner while connecting, and neutral or amber text otherwise. The full
 * explanation of degraded states is a `UiBanner` on the live page.
 */
const props = defineProps<{ state: LiveConnectionState }>()
const { t } = useI18n()

const tone = computed(() => {
  switch (props.state) {
    case 'connected':
    case 'grace':
      return 'text-success-soft-fg'
    case 'reconnecting':
    case 'polling':
    case 'offline':
      return 'text-warning-soft-fg'
    default:
      return 'text-fg-muted'
  }
})
</script>

<template>
  <span
    role="status"
    class="inline-flex items-center gap-1.5 text-sm font-semibold"
    :class="tone"
  >
    <LoaderCircle
      v-if="state === 'connecting'"
      :size="14"
      class="animate-spin"
      aria-hidden="true"
    />
    <WifiOff
      v-else-if="state === 'offline'"
      :size="14"
      aria-hidden="true"
    />
    <span
      v-else
      class="size-2 rounded-full bg-current"
      aria-hidden="true"
    />
    {{ t(`common.connection.${state}`) }}
  </span>
</template>
