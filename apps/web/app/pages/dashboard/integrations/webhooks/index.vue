<script setup lang="ts">
import { Plus, TriangleAlert, Webhook } from '@lucide/vue'
import { createWebhookEndpoint, listWebhookEndpoints, listWebhookEventTypes } from '~/services/integrations'
import type { WebhookEndpoint, WebhookEndpointInput, WebhookEventType } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'

/**
 * W39 Webhook endpoints · `/dashboard/integrations/webhooks` (`integrations.manage` + `api_enabled`;
 * SCREENS §2.4). List (URL, events, status with the disabled reason, failing since, last success and
 * failure) and create (URL, events or `*`, description). The signing secret is shown once.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: 'integrations_api' })

const { t } = useI18n()
const auth = useAuthStore()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('integrations.webhooks.title') })

const allowed = computed(() => auth.can('integrations.manage'))
const apiEnabled = computed(() => auth.features?.api_enabled === true)
const disabledByServer = ref(false)

const endpoints = ref<WebhookEndpoint[]>([])
const eventTypes = ref<WebhookEventType[]>([])
const eventTypesError = ref<unknown>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const drawerOpen = ref(false)
const formKey = ref(0)
const creating = ref(false)
const createErrors = ref<Record<string, string>>({})
const createFormError = ref<string | null>(null)
const secretOpen = ref(false)
const createdSecret = ref<string | null>(null)

async function load(): Promise<void> {
  if (!allowed.value || !apiEnabled.value) return
  loading.value = true
  loadError.value = null
  eventTypesError.value = null
  const [list, types] = await Promise.allSettled([listWebhookEndpoints(), listWebhookEventTypes()])
  if (list.status === 'fulfilled') endpoints.value = list.value
  else if (list.reason instanceof ApiError && list.reason.code === 'api_access_disabled') disabledByServer.value = true
  else loadError.value = list.reason
  if (types.status === 'fulfilled') eventTypes.value = types.value
  else eventTypesError.value = types.reason
  loading.value = false
}

onMounted(load)

function openCreate(): void {
  formKey.value += 1
  createErrors.value = {}
  createFormError.value = null
  drawerOpen.value = true
}

async function onCreate(input: WebhookEndpointInput): Promise<void> {
  creating.value = true
  createErrors.value = {}
  createFormError.value = null
  try {
    const { secret, ...endpoint } = await createWebhookEndpoint(input)
    endpoints.value = [endpoint, ...endpoints.value.filter(item => item.id !== endpoint.id)]
    drawerOpen.value = false
    createdSecret.value = secret
    secretOpen.value = true
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'webhook_url_invalid') {
      createErrors.value = { url: message(error) }
      return
    }
    const bound = bind(error, ['url', 'event_types', 'description'], arrayAliases(error, 'event_types'))
    createErrors.value = bound.fields
    createFormError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    creating.value = false
  }
}

const columns = computed<TableColumn[]>(() => [
  { key: 'url', label: t('integrations.webhooks.fields.url'), primary: true },
  { key: 'events', label: t('integrations.webhooks.fields.events') },
  { key: 'status', label: t('integrations.webhooks.fields.status') },
  { key: 'last_success_at', label: t('integrations.webhooks.fields.last_success'), hideOnMobile: true },
  { key: 'last_failure_at', label: t('integrations.webhooks.fields.last_failure'), hideOnMobile: true },
])

const row = (value: unknown) => value as WebhookEndpoint
const eventsLabel = (endpoint: WebhookEndpoint) => (endpoint.event_types.includes('*')
  ? t('integrations.webhooks.all_events')
  : t('integrations.webhooks.events_count', { count: endpoint.event_types.length }, endpoint.event_types.length))
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('integrations.webhooks.title')"
      :description="t('integrations.webhooks.subtitle')"
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
      <template
        v-if="allowed && apiEnabled && !disabledByServer"
        #actions
      >
        <UiButton
          :icon="Plus"
          :disabled="eventTypes.length === 0"
          @click="openCreate"
        >
          {{ t('integrations.webhooks.create') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />
    <IntegrationsApiDisabledCallout v-else-if="!apiEnabled || disabledByServer" />

    <template v-else>
      <UiAlert
        v-if="eventTypesError && !loading"
        tone="warning"
      >
        {{ t('integrations.webhooks.events.load_failed') }}
      </UiAlert>

      <UiCard
        v-if="loadError"
        padding="none"
      >
        <UiErrorState
          :error="loadError"
          :retrying="loading"
          @retry="load"
        />
      </UiCard>

      <UiTable
        v-else
        :columns="columns"
        :rows="endpoints"
        row-key="id"
        :caption="t('integrations.webhooks.title')"
        :loading="loading && endpoints.length === 0"
      >
        <template #cell-url="{ row: r }">
          <span class="flex min-w-0 flex-col gap-1">
            <NuxtLinkLocale
              :to="`/dashboard/integrations/webhooks/${row(r).id}`"
              class="link font-mono text-sm font-semibold break-all"
              dir="ltr"
            >
              {{ row(r).url }}
            </NuxtLinkLocale>
            <span
              v-if="row(r).description"
              class="text-xs text-fg-muted"
            >{{ row(r).description }}</span>
            <span
              v-if="row(r).failing_since && row(r).status === 'active'"
              class="inline-flex items-center gap-1 text-xs font-semibold text-warning-soft-fg"
            >
              <TriangleAlert
                :size="14"
                aria-hidden="true"
              />
              <span>{{ t('integrations.webhooks.failing_since') }} <UiDateTime :value="row(r).failing_since" /></span>
            </span>
          </span>
        </template>
        <template #cell-events="{ row: r }">
          {{ eventsLabel(row(r)) }}
        </template>
        <template #cell-status="{ row: r }">
          <span class="flex flex-col items-start gap-1">
            <UiBadge
              :tone="row(r).status === 'active' ? 'primary' : 'neutral'"
              size="sm"
              dot
            >
              {{ t(`integrations.webhooks.statuses.${row(r).status}`) }}
            </UiBadge>
            <span
              v-if="row(r).status === 'disabled' && row(r).disabled_reason"
              class="text-xs text-fg-muted"
            >{{ t(`integrations.webhooks.disabled_reasons.${row(r).disabled_reason === 'failing' ? 'failing' : 'manual'}`) }}</span>
          </span>
        </template>
        <template #cell-last_success_at="{ row: r }">
          <UiRelativeTime
            v-if="row(r).last_success_at"
            :value="row(r).last_success_at!"
          />
          <span v-else>—</span>
        </template>
        <template #cell-last_failure_at="{ row: r }">
          <UiRelativeTime
            v-if="row(r).last_failure_at"
            :value="row(r).last_failure_at!"
          />
          <span v-else>—</span>
        </template>
        <template #empty>
          <UiEmptyState
            :icon="Webhook"
            :title="t('integrations.webhooks.empty.title')"
            :description="t('integrations.webhooks.empty.body')"
          >
            <UiButton
              :icon="Plus"
              :disabled="eventTypes.length === 0"
              @click="openCreate"
            >
              {{ t('integrations.webhooks.create') }}
            </UiButton>
          </UiEmptyState>
        </template>
      </UiTable>
    </template>

    <UiDrawer
      v-model:open="drawerOpen"
      :title="t('integrations.webhooks.form.title_create')"
      :description="t('integrations.webhooks.form.description')"
      size="lg"
      :dismissible="!creating"
    >
      <div class="px-5 py-4">
        <IntegrationsWebhookForm
          :key="formKey"
          :event-types="eventTypes"
          :submit-label="t('integrations.webhooks.form.submit')"
          :busy="creating"
          :server-errors="createErrors"
          :form-error="createFormError"
          @submit="onCreate"
        />
      </div>
    </UiDrawer>

    <IntegrationsSecretDialog
      v-model:open="secretOpen"
      :title="t('integrations.webhooks.created.title')"
      :description="t('integrations.webhooks.created.body')"
      :secret-label="t('integrations.webhooks.created.secret_label')"
      :secret="createdSecret"
      @done="createdSecret = null"
    />
  </div>
</template>
