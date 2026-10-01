<script setup lang="ts">
import { SearchX } from '@lucide/vue'

/** S8 Not found (404). Never reveals whether a resource exists: the copy covers "no access" too. */
defineProps<{ title?: string, description?: string }>()
const { t } = useI18n()
</script>

<template>
  <UiCard padding="none">
    <UiEmptyState
      :icon="SearchX"
      :title="title ?? t('common.states.not_found.title')"
      :description="description ?? t('errors.not_found')"
    >
      <slot>
        <UiButton
          to="/dashboard"
          variant="secondary"
        >
          {{ t('common.states.back_to_overview') }}
        </UiButton>
      </slot>
    </UiEmptyState>
  </UiCard>
</template>
