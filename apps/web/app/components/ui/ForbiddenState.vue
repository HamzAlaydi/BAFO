<script setup lang="ts">
import { ShieldX } from '@lucide/vue'

/** S8 Forbidden: a page the user lacks permission for renders this state instead of redirecting (CD8). */
defineProps<{ description?: string }>()
const { t } = useI18n()
</script>

<template>
  <UiCard padding="none">
    <UiEmptyState
      :icon="ShieldX"
      :title="t('common.states.forbidden.title')"
      :description="description ?? t('errors.forbidden')"
    >
      <UiButton
        to="/dashboard"
        variant="secondary"
      >
        {{ t('common.states.back_to_overview') }}
      </UiButton>
    </UiEmptyState>
  </UiCard>
</template>
