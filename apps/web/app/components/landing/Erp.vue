<script setup lang="ts">
import type { Component } from 'vue'
import { BookOpen, FileSpreadsheet, KeyRound, Webhook } from '@lucide/vue'

/**
 * ERP connectivity, shown only with the `integrations_api` flag (RELEASE_SCOPE §1.3): public API,
 * signed webhooks, CSV/Excel files and the link to `{API}/docs/api`.
 */
const { t } = useI18n()
const config = useRuntimeConfig()
const apiOrigin = computed(() => apiOriginFrom(config.public.apiBase))

const items: ReadonlyArray<{ key: string, icon: Component }> = [
  { key: 'api', icon: KeyRound },
  { key: 'webhooks', icon: Webhook },
  { key: 'files', icon: FileSpreadsheet },
]
</script>

<template>
  <section
    class="border-y border-line bg-surface-muted"
    aria-labelledby="erp-title"
  >
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
      <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <LandingSectionHeading
          id="erp-title"
          :title="t('landing.erp.title')"
          :subtitle="t('landing.erp.body')"
        />
        <UiButton
          variant="secondary"
          :href="`${apiOrigin}/docs/api`"
          :icon="BookOpen"
        >
          {{ t('landing.erp.docs') }}
        </UiButton>
      </div>
      <ul class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <li
          v-for="item in items"
          :key="item.key"
          class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-5"
        >
          <span
            class="inline-flex size-10 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
            aria-hidden="true"
          >
            <component
              :is="item.icon"
              :size="20"
            />
          </span>
          <h3 class="text-lg font-bold text-fg">
            {{ t(`landing.erp.items.${item.key}.title`) }}
          </h3>
          <p class="text-sm text-fg-muted">
            {{ t(`landing.erp.items.${item.key}.body`) }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
