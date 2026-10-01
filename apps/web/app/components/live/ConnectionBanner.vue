<script setup lang="ts">
import type { LiveConnectionState } from '~/utils/connection-state'

/**
 * `ConnectionBanner` (SCREENS S4): explains the degraded live states. Reconnecting pauses
 * submitting; polling refreshes every 3 s in the last 5 minutes, otherwise every 10 s. Offline is
 * covered by the dashboard's global offline banner. Amber tone, never red.
 */
const props = defineProps<{
  state: LiveConnectionState
  /** Current poll interval in seconds (3 or 10). */
  pollSeconds: number
}>()

const { t } = useI18n()
const visible = computed(() => props.state === 'reconnecting' || props.state === 'polling')
</script>

<template>
  <UiBanner
    v-if="visible"
    tone="warning"
    role="alert"
    class="rounded-lg border"
    data-testid="connection-banner"
  >
    <template v-if="state === 'reconnecting'">
      {{ t('live.connection.reconnecting') }}
    </template>
    <template v-else>
      {{ t('live.connection.polling', { count: pollSeconds }, pollSeconds) }}
    </template>
  </UiBanner>
</template>
