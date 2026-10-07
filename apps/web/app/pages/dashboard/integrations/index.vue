<script setup lang="ts">
import type { Component } from 'vue'
import { ArrowLeft, ArrowRight, BookOpen, Contact, ExternalLink, FileDown, FileUp, KeyRound, Webhook } from '@lucide/vue'

/**
 * W36 Integrations · `/dashboard/integrations` (`integrations.manage`; SCREENS §2.4). Entry cards for
 * API clients, webhooks, vendor import, exports and the vendor directory, plus the public API
 * documentation on the API origin (`/docs/api`, `openapi.yaml`). Without `features.api_enabled` the
 * API clients and webhooks cards carry the callout; import and export stay available.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: ['integrations_api', 'csv_import_export'] })

const { t } = useI18n()
const auth = useAuthStore()
const config = useRuntimeConfig()
const locale = useAppLocale()

useSeoMeta({ title: () => t('integrations.title') })

const allowed = computed(() => auth.can('integrations.manage'))
const apiEnabled = computed(() => auth.features?.api_enabled === true)
const origin = computed(() => apiOriginFrom(config.public.apiBase))
const forward = computed(() => (locale.value === 'ar' ? ArrowLeft : ArrowRight))

interface EntryCard {
  key: string
  icon: Component
  to: string
  needsApi: boolean
  permission?: 'competitions.create'
}

const cards: EntryCard[] = [
  { key: 'api_clients', icon: KeyRound, to: '/dashboard/integrations/api-clients', needsApi: true },
  { key: 'webhooks', icon: Webhook, to: '/dashboard/integrations/webhooks', needsApi: true },
  { key: 'import', icon: FileUp, to: '/dashboard/integrations/import', needsApi: false },
  { key: 'exports', icon: FileDown, to: '/dashboard/integrations/exports', needsApi: false },
  { key: 'vendors', icon: Contact, to: '/dashboard/vendors', needsApi: false, permission: 'competitions.create' },
]

const visibleCards = computed(() => cards.filter(card => !card.permission || auth.can(card.permission)))
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('integrations.title')"
      :description="t('integrations.subtitle')"
    />

    <UiForbiddenState v-if="!allowed" />

    <template v-else>
      <IntegrationsApiDisabledCallout
        v-if="!apiEnabled"
        compact
      />

      <ul class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <li
          v-for="card in visibleCards"
          :key="card.key"
        >
          <UiCard
            as="article"
            class="h-full"
          >
            <div class="flex h-full flex-col gap-4">
              <span
                class="inline-flex size-11 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
                aria-hidden="true"
              >
                <component
                  :is="card.icon"
                  :size="22"
                />
              </span>
              <div class="flex flex-1 flex-col gap-1">
                <h2 class="text-lg font-bold text-fg">
                  {{ t(`integrations.cards.${card.key}.title`) }}
                </h2>
                <p class="text-sm text-fg-muted">
                  {{ t(`integrations.cards.${card.key}.body`) }}
                </p>
              </div>
              <p
                v-if="card.needsApi && !apiEnabled"
                class="rounded-md bg-info-soft p-3 text-sm text-info-soft-fg"
              >
                {{ t('errors.api_access_disabled') }}
              </p>
              <UiButton
                v-else
                variant="secondary"
                class="self-start"
                :to="card.to"
                :icon-end="forward"
              >
                {{ t(`integrations.cards.${card.key}.action`) }}
              </UiButton>
            </div>
          </UiCard>
        </li>

        <li>
          <UiCard
            as="article"
            class="h-full"
          >
            <div class="flex h-full flex-col gap-4">
              <span
                class="inline-flex size-11 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
                aria-hidden="true"
              >
                <BookOpen :size="22" />
              </span>
              <div class="flex flex-1 flex-col gap-1">
                <h2 class="text-lg font-bold text-fg">
                  {{ t('integrations.cards.docs.title') }}
                </h2>
                <p class="text-sm text-fg-muted">
                  {{ t('integrations.cards.docs.body') }}
                </p>
              </div>
              <div class="flex flex-wrap gap-2">
                <UiButton
                  variant="secondary"
                  :href="`${origin}/docs/api`"
                  :icon-end="ExternalLink"
                >
                  {{ t('integrations.cards.docs.reference') }}
                </UiButton>
                <UiButton
                  variant="ghost"
                  :href="`${publicApiBase(origin)}/openapi.yaml`"
                  :icon-end="ExternalLink"
                >
                  {{ t('integrations.cards.docs.openapi') }}
                </UiButton>
              </div>
              <p class="text-xs text-fg-muted">
                {{ t('integrations.cards.docs.new_tab') }}
              </p>
            </div>
          </UiCard>
        </li>
      </ul>
    </template>
  </div>
</template>
