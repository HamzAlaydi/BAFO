<script setup lang="ts">
import { PlugZap } from '@lucide/vue'

/**
 * Shown instead of the API clients and webhooks pages when `organization.features.api_enabled` is
 * false (SCREENS W36–W40): those pages never call the API, which would answer `api_access_disabled`.
 */
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })
const { t } = useI18n()
</script>

<template>
  <UiAlert
    v-if="compact"
    tone="info"
    :icon="PlugZap"
  >
    {{ t('errors.api_access_disabled') }}
  </UiAlert>
  <UiCard v-else>
    <UiEmptyState
      :icon="PlugZap"
      :title="t('integrations.disabled.title')"
      :description="t('errors.api_access_disabled')"
    >
      <div class="flex flex-col items-center gap-3">
        <AppSupportContacts />
        <UiButton
          variant="secondary"
          to="/dashboard/integrations"
        >
          {{ t('integrations.back') }}
        </UiButton>
      </div>
    </UiEmptyState>
  </UiCard>
</template>
