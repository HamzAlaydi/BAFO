<script setup lang="ts">
/** S8 Offline: a top banner while the browser has no network (mutations are disabled by the pages). */
const { t } = useI18n()
const online = useOnline()
const mounted = useMounted()
</script>

<template>
  <UiBanner
    v-if="mounted && !online"
    tone="warning"
    role="alert"
    offline
  >
    {{ t('errors.offline') }}
  </UiBanner>
</template>
