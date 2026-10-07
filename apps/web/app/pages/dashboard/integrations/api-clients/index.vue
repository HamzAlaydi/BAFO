<script setup lang="ts">
import { KeyRound, Plus } from '@lucide/vue'
import { createApiClient, listApiClients } from '~/services/integrations'
import type { ApiClient, ApiClientInput } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'

/**
 * W37 API clients · `/dashboard/integrations/api-clients` (`integrations.manage` + `api_enabled`;
 * SCREENS §2.4). List and create. After create, the `client_id` and the `client_secret` are shown
 * once, and the dialog closes only after the user confirms they stored the secret. The secret is
 * dropped from memory when the dialog closes.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: 'integrations_api' })

const { t } = useI18n()
const auth = useAuthStore()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('integrations.clients.title') })

const allowed = computed(() => auth.can('integrations.manage'))
const apiEnabled = computed(() => auth.features?.api_enabled === true)
const disabledByServer = ref(false)

const clients = ref<ApiClient[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const drawerOpen = ref(false)
const formKey = ref(0)
const creating = ref(false)
const createErrors = ref<Record<string, string>>({})
const createFormError = ref<string | null>(null)
const secretOpen = ref(false)
const created = ref<{ clientId: string, secret: string } | null>(null)

async function load(): Promise<void> {
  if (!allowed.value || !apiEnabled.value) return
  loading.value = true
  loadError.value = null
  try {
    clients.value = await listApiClients()
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'api_access_disabled') disabledByServer.value = true
    else loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate(): void {
  formKey.value += 1
  createErrors.value = {}
  createFormError.value = null
  drawerOpen.value = true
}

async function onCreate(input: ApiClientInput): Promise<void> {
  creating.value = true
  createErrors.value = {}
  createFormError.value = null
  try {
    const result = await createApiClient(input)
    const { client_secret: secret, ...client } = result
    clients.value = [client, ...clients.value.filter(item => item.id !== client.id)]
    drawerOpen.value = false
    created.value = { clientId: client.client_id, secret }
    secretOpen.value = true
  }
  catch (error) {
    const bound = bind(error, ['name', 'description', 'scopes'], arrayAliases(error, 'scopes'))
    createErrors.value = bound.fields
    createFormError.value = bound.unmatched[0] ?? (Object.keys(createErrors.value).length > 0 ? null : message(error))
    if (error instanceof ApiError && error.code === 'api_access_disabled') disabledByServer.value = true
  }
  finally {
    creating.value = false
  }
}

function onSecretDone(): void {
  created.value = null
}

const columns = computed<TableColumn[]>(() => [
  { key: 'name', label: t('integrations.clients.fields.name'), primary: true },
  { key: 'status', label: t('integrations.clients.fields.status') },
  { key: 'scopes', label: t('integrations.clients.fields.scopes'), numeric: true },
  { key: 'keys', label: t('integrations.clients.fields.keys'), numeric: true },
  { key: 'last_used_at', label: t('integrations.clients.fields.last_used') },
  { key: 'created_by', label: t('integrations.clients.fields.created_by'), hideOnMobile: true },
])

const row = (value: unknown) => value as ApiClient
const CLIENT_TONES = { active: 'primary', suspended: 'warning', revoked: 'neutral' } as const
const activeKeys = (client: ApiClient) => client.keys.filter(key => !key.revoked_at).length
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('integrations.clients.title')"
      :description="t('integrations.clients.subtitle')"
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
          @click="openCreate"
        >
          {{ t('integrations.clients.create') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />
    <IntegrationsApiDisabledCallout v-else-if="!apiEnabled || disabledByServer" />

    <template v-else>
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
        :rows="clients"
        row-key="id"
        :caption="t('integrations.clients.title')"
        :loading="loading && clients.length === 0"
      >
        <template #cell-name="{ row: r }">
          <span class="flex min-w-0 flex-col">
            <NuxtLinkLocale
              :to="`/dashboard/integrations/api-clients/${row(r).id}`"
              class="link font-semibold"
            >
              {{ row(r).name }}
            </NuxtLinkLocale>
            <span
              v-if="row(r).description"
              class="truncate text-xs text-fg-muted"
            >{{ row(r).description }}</span>
          </span>
        </template>
        <template #cell-status="{ row: r }">
          <UiBadge
            :tone="CLIENT_TONES[row(r).status]"
            size="sm"
            dot
          >
            {{ t(`integrations.clients.statuses.${row(r).status}`) }}
          </UiBadge>
        </template>
        <template #cell-scopes="{ row: r }">
          <bdi>{{ row(r).scopes.length }}</bdi>
        </template>
        <template #cell-keys="{ row: r }">
          <bdi>{{ activeKeys(row(r)) }}</bdi>
        </template>
        <template #cell-last_used_at="{ row: r }">
          <UiRelativeTime
            v-if="row(r).last_used_at"
            :value="row(r).last_used_at!"
          />
          <span
            v-else
            class="text-fg-muted"
          >{{ t('integrations.common.never_used') }}</span>
        </template>
        <template #cell-created_by="{ row: r }">
          {{ row(r).created_by?.name ?? '—' }}
        </template>
        <template #empty>
          <UiEmptyState
            :icon="KeyRound"
            :title="t('integrations.clients.empty.title')"
            :description="t('integrations.clients.empty.body')"
          >
            <UiButton
              :icon="Plus"
              @click="openCreate"
            >
              {{ t('integrations.clients.create') }}
            </UiButton>
          </UiEmptyState>
        </template>
      </UiTable>
    </template>

    <UiDrawer
      v-model:open="drawerOpen"
      :title="t('integrations.clients.form.title')"
      :description="t('integrations.clients.form.description')"
      size="lg"
      :dismissible="!creating"
    >
      <div class="px-5 py-4">
        <IntegrationsApiClientForm
          :key="formKey"
          :submit-label="t('integrations.clients.form.submit')"
          :busy="creating"
          :server-errors="createErrors"
          :form-error="createFormError"
          @submit="onCreate"
        />
      </div>
    </UiDrawer>

    <IntegrationsSecretDialog
      v-model:open="secretOpen"
      :title="t('integrations.clients.created.title')"
      :description="t('integrations.clients.created.body')"
      :secret-label="t('integrations.clients.created.secret_label')"
      :secret="created?.secret ?? null"
      :identifiers="created ? [{ label: t('integrations.clients.fields.client_id'), value: created.clientId }] : []"
      @done="onSecretDone"
    />
  </div>
</template>
