<script setup lang="ts">
import { Wrench } from '@lucide/vue'

/**
 * Maintenance (SCREENS S8): the localised `AppConfig.maintenance.message`, an automatic retry every
 * 60 s and a manual retry. The app returns to normal as soon as the server stops reporting it.
 */
const { t } = useI18n()
const appConfig = useAppConfigStore()
const checking = ref(false)

async function check(): Promise<void> {
  if (checking.value) return
  checking.value = true
  try {
    await appConfig.checkMaintenance()
  }
  finally {
    checking.value = false
  }
}

useIntervalFn(() => void check(), 60_000)
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-page">
    <header class="flex h-16 items-center justify-between gap-3 px-4 sm:px-8">
      <AppLogo size="sm" />
      <AppLanguageSwitch compact />
    </header>
    <main
      id="main"
      class="flex flex-1 items-center justify-center px-4 py-10"
    >
      <div class="flex max-w-lg flex-col items-center gap-5 text-center">
        <span
          class="flex size-16 items-center justify-center rounded-full bg-info-soft text-info-soft-fg"
          aria-hidden="true"
        >
          <Wrench :size="30" />
        </span>
        <h1 class="text-2xl font-bold text-fg">
          {{ t('common.maintenance.title') }}
        </h1>
        <p
          class="text-fg-muted"
          role="status"
        >
          {{ appConfig.maintenanceMessage || t('errors.maintenance') }}
        </p>
        <p class="text-sm text-fg-muted">
          {{ t('common.maintenance.auto_retry') }}
        </p>
        <UiButton
          variant="secondary"
          :loading="checking"
          @click="check"
        >
          {{ t('common.actions.retry') }}
        </UiButton>
        <AppSupportContacts />
      </div>
    </main>
  </div>
</template>
