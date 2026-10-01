<script setup lang="ts">
import { CloudOff, TriangleAlert } from '@lucide/vue'

/**
 * S8 Error: a failed load with Retry. Pass the caught error; network failures and server errors get
 * their own copy (`errors.network`, `errors.server_error`), other codes their mapped message.
 */
const props = defineProps<{
  error?: unknown
  retrying?: boolean
  compact?: boolean
}>()

const emit = defineEmits<{ retry: [] }>()
const { t } = useI18n()
const { message } = useErrorMessage()

const isNetwork = computed(() => props.error instanceof ApiError && props.error.isNetwork)
const description = computed(() => {
  if (!props.error) return t('errors.server_error')
  if (props.error instanceof ApiError && props.error.status !== null && props.error.status >= 500) return t('errors.server_error')
  return message(props.error)
})
</script>

<template>
  <UiEmptyState
    :icon="isNetwork ? CloudOff : TriangleAlert"
    :title="t('common.states.error.title')"
    :description="description"
    :compact="compact"
    role="alert"
  >
    <UiButton
      variant="secondary"
      :loading="retrying"
      @click="emit('retry')"
    >
      {{ t('common.actions.retry') }}
    </UiButton>
  </UiEmptyState>
</template>
