<script setup lang="ts">
/**
 * W41 Import vendors · `/dashboard/integrations/import` (`integrations.manage`; `api_enabled` not
 * needed; SCREENS §2.4). `ImportWizard`: template → upload and validate → review → import the valid
 * rows. Leaving the page with a file in progress asks for confirmation.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: 'csv_import_export' })

const { t } = useI18n()
const auth = useAuthStore()

useSeoMeta({ title: () => t('integrations.import.title') })

const allowed = computed(() => auth.can('integrations.manage'))
const dirty = ref(false)
useUnsavedChangesGuard(dirty)
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('integrations.import.title')"
      :description="t('integrations.import.subtitle')"
    >
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/integrations"
        >
          {{ t('integrations.back') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />
    <IntegrationsImportWizard
      v-else
      @dirty="dirty = $event"
    />
  </div>
</template>
