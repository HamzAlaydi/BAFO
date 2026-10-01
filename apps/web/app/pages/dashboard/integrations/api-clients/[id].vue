<script setup lang="ts">
import { Ban, KeyRound, Plus, RefreshCw } from '@lucide/vue'
import {
  createApiKey,
  fetchApiClient,
  revokeApiClient,
  revokeApiKey,
  rotateApiClientSecret,
  updateApiClient,
} from '~/services/integrations'
import type { ApiClient, ApiClientInput, ApiKey } from '~/types/api/integrations'

/**
 * W38 API client · `/dashboard/integrations/api-clients/{id}` (`integrations.manage` + `api_enabled`;
 * SCREENS §2.4). Edit name, description and scopes; keys (create → shown once, revoke); rotate the
 * client secret (shown once); revoke the client. A usage snippet with placeholders only. Every change
 * waits for the server.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const toast = useToast()
const localePath = useLocalePath()
const config = useRuntimeConfig()
const { message, bind } = useErrorMessage()

const allowed = computed(() => auth.can('integrations.manage'))
const apiEnabled = computed(() => auth.features?.api_enabled === true)
const clientId = computed(() => String(route.params.id ?? ''))
const origin = computed(() => apiOriginFrom(config.public.apiBase))

const client = ref<ApiClient | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)
const disabledByServer = ref(false)

useSeoMeta({ title: () => client.value?.name ?? t('integrations.clients.title') })

// Edit
const formKey = ref(0)
const saving = ref(false)
const saveErrors = ref<Record<string, string>>({})
const saveFormError = ref<string | null>(null)
const dirty = ref(false)
useUnsavedChangesGuard(dirty)

// Secrets shown once
const secretOpen = ref(false)
const secret = ref<{ title: string, label: string, value: string } | null>(null)

// Keys
const keyDialogOpen = ref(false)
const keyDays = ref('365')
const keyDaysError = ref<string | null>(null)
const creatingKey = ref(false)
const keyError = ref<string | null>(null)
const revokeKeyTarget = ref<ApiKey | null>(null)
const revokeKeyOpen = ref(false)
const revokingKey = ref(false)
const revokeKeyError = ref<string | null>(null)

// Client actions
const rotateOpen = ref(false)
const rotating = ref(false)
const rotateError = ref<string | null>(null)
const revokeOpen = ref(false)
const revoking = ref(false)
const revokeError = ref<string | null>(null)

const readonly = computed(() => client.value?.status !== 'active')

async function load(): Promise<void> {
  if (!allowed.value || !apiEnabled.value) return
  loading.value = true
  loadError.value = null
  try {
    client.value = await fetchApiClient(clientId.value)
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

const notFound = computed(() => loadError.value instanceof ApiError && loadError.value.isNotFound)

function showSecret(title: string, label: string, value: string): void {
  secret.value = { title, label, value }
  secretOpen.value = true
}

async function onSave(input: ApiClientInput): Promise<void> {
  if (!client.value) return
  saving.value = true
  saveErrors.value = {}
  saveFormError.value = null
  try {
    client.value = await updateApiClient(client.value.id, input)
    dirty.value = false
    formKey.value += 1
    toast.success(t('integrations.clients.detail.saved'))
  }
  catch (error) {
    const bound = bind(error, ['name', 'description', 'scopes'], arrayAliases(error, 'scopes'))
    saveErrors.value = bound.fields
    saveFormError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    saving.value = false
  }
}

function openKeyDialog(): void {
  keyDays.value = '365'
  keyDaysError.value = null
  keyError.value = null
  keyDialogOpen.value = true
}

async function onCreateKey(): Promise<void> {
  if (!client.value) return
  const days = parsePositiveInt(keyDays.value)
  if (days === null || days > 730) {
    keyDaysError.value = t('integrations.keys.create_dialog.days_error')
    return
  }
  creatingKey.value = true
  keyDaysError.value = null
  keyError.value = null
  try {
    const { key, ...created } = await createApiKey(client.value.id, days)
    client.value = { ...client.value, keys: [created, ...client.value.keys.filter(item => item.id !== created.id)] }
    keyDialogOpen.value = false
    showSecret(t('integrations.keys.created.title'), t('integrations.keys.created.key_label'), key)
  }
  catch (error) {
    const bound = bind(error, ['expires_in_days'])
    keyDaysError.value = bound.fields.expires_in_days ?? null
    keyError.value = bound.unmatched[0] ?? (bound.fields.expires_in_days ? null : message(error))
  }
  finally {
    creatingKey.value = false
  }
}

function askRevokeKey(key: ApiKey): void {
  revokeKeyTarget.value = key
  revokeKeyError.value = null
  revokeKeyOpen.value = true
}

async function onRevokeKey(): Promise<void> {
  const target = revokeKeyTarget.value
  if (!client.value || !target) return
  revokingKey.value = true
  revokeKeyError.value = null
  try {
    await revokeApiKey(client.value.id, target.id)
    revokeKeyOpen.value = false
    toast.success(t('integrations.keys.revoke.done', { prefix: target.prefix }))
    await load()
  }
  catch (error) {
    revokeKeyError.value = message(error)
  }
  finally {
    revokingKey.value = false
  }
}

async function onRotate(): Promise<void> {
  if (!client.value) return
  rotating.value = true
  rotateError.value = null
  try {
    const { client_secret: value, ...rest } = await rotateApiClientSecret(client.value.id)
    client.value = rest
    rotateOpen.value = false
    showSecret(t('integrations.clients.rotate.done_title'), t('integrations.clients.created.secret_label'), value)
  }
  catch (error) {
    rotateError.value = message(error)
  }
  finally {
    rotating.value = false
  }
}

async function onRevoke(): Promise<void> {
  if (!client.value) return
  const name = client.value.name
  revoking.value = true
  revokeError.value = null
  try {
    await revokeApiClient(client.value.id)
    revokeOpen.value = false
    dirty.value = false
    toast.success(t('integrations.clients.revoke.done', { name }))
    await navigateTo(localePath('/dashboard/integrations/api-clients'))
  }
  catch (error) {
    revokeError.value = message(error)
  }
  finally {
    revoking.value = false
  }
}

function onSecretDone(): void {
  secret.value = null
}

const CLIENT_TONES = { active: 'primary', suspended: 'warning', revoked: 'neutral' } as const
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="client?.name ?? t('integrations.clients.title')"
      :description="client?.description ?? undefined"
    >
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/integrations/api-clients"
        >
          {{ t('integrations.clients.title') }}
        </UiButton>
      </template>
      <template
        v-if="client"
        #meta
      >
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <UiBadge
            :tone="CLIENT_TONES[client.status]"
            size="sm"
            dot
          >
            {{ t(`integrations.clients.statuses.${client.status}`) }}
          </UiBadge>
          <span class="text-sm text-fg-muted">{{ t('integrations.clients.fields.client_id') }}</span>
          <code
            dir="ltr"
            class="rounded-xs bg-surface-muted px-1.5 py-0.5 font-mono text-sm break-all text-fg"
          >{{ client.client_id }}</code>
          <UiCopyButton
            :value="client.client_id"
            :label="t('integrations.clients.copy_client_id')"
          />
        </div>
      </template>
      <template
        v-if="client && !readonly"
        #actions
      >
        <UiButton
          variant="secondary"
          :icon="RefreshCw"
          @click="rotateError = null; rotateOpen = true"
        >
          {{ t('integrations.clients.rotate.action') }}
        </UiButton>
        <UiButton
          variant="danger-ghost"
          :icon="Ban"
          @click="revokeError = null; revokeOpen = true"
        >
          {{ t('integrations.clients.revoke.action') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />
    <IntegrationsApiDisabledCallout v-else-if="!apiEnabled || disabledByServer" />

    <UiCard v-else-if="notFound">
      <UiNotFoundState />
    </UiCard>

    <UiCard
      v-else-if="loadError && !client"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
    </UiCard>

    <div
      v-else-if="!client"
      class="flex flex-col gap-6"
      :aria-label="t('common.loading')"
    >
      <UiCard>
        <UiSkeleton :lines="5" />
      </UiCard>
      <UiCard>
        <UiSkeleton :lines="4" />
      </UiCard>
    </div>

    <template v-else>
      <UiAlert
        v-if="readonly"
        tone="info"
      >
        {{ client.status === 'revoked' ? t('integrations.clients.detail.revoked_notice') : t('integrations.clients.detail.suspended_notice') }}
      </UiAlert>

      <!-- Keys -->
      <section
        class="flex flex-col gap-3"
        aria-labelledby="keys-title"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2
              id="keys-title"
              class="text-lg font-bold text-fg"
            >
              {{ t('integrations.keys.title') }}
            </h2>
            <p class="text-sm text-fg-muted">
              {{ t('integrations.keys.subtitle') }}
            </p>
          </div>
          <UiButton
            v-if="!readonly"
            variant="secondary"
            :icon="Plus"
            @click="openKeyDialog"
          >
            {{ t('integrations.keys.create') }}
          </UiButton>
        </div>
        <IntegrationsApiKeyTable
          :keys="client.keys"
          :readonly="readonly"
          :busy-id="revokingKey ? revokeKeyTarget?.id : null"
          @revoke="askRevokeKey"
        />
      </section>

      <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <!-- Settings -->
        <UiCard
          as="section"
          :title="t('integrations.clients.detail.settings_title')"
        >
          <IntegrationsApiClientForm
            :key="formKey"
            :initial="{ name: client.name, description: client.description, scopes: client.scopes }"
            :submit-label="t('common.actions.save_changes')"
            :busy="saving"
            :disabled="readonly"
            :server-errors="saveErrors"
            :form-error="saveFormError"
            @submit="onSave"
            @dirty="dirty = $event"
          />
        </UiCard>

        <!-- Usage -->
        <UiCard
          as="section"
          :title="t('integrations.clients.usage.title')"
          :description="t('integrations.clients.usage.body')"
        >
          <div class="flex flex-col gap-5">
            <IntegrationsCodeSnippet
              :label="t('integrations.clients.usage.token_label')"
              :code="tokenRequestSnippet(origin)"
            />
            <IntegrationsCodeSnippet
              :label="t('integrations.clients.usage.key_label')"
              :code="apiKeyRequestSnippet(origin)"
            />
            <UiButton
              variant="link"
              size="sm"
              class="self-start"
              :href="`${origin}/docs/api`"
            >
              {{ t('integrations.cards.docs.reference') }}
            </UiButton>
          </div>
        </UiCard>
      </div>
    </template>

    <!-- Create key -->
    <UiModal
      v-model:open="keyDialogOpen"
      :title="t('integrations.keys.create_dialog.title')"
      :description="t('integrations.keys.create_dialog.body')"
      size="sm"
      :dismissible="!creatingKey"
    >
      <form
        class="flex flex-col gap-4"
        novalidate
        @submit.prevent="onCreateKey"
      >
        <UiAlert
          v-if="keyError"
          tone="danger"
          role="alert"
        >
          {{ keyError }}
        </UiAlert>
        <UiInput
          v-model="keyDays"
          inputmode="numeric"
          dir="ltr"
          :label="t('integrations.keys.create_dialog.days_label')"
          :hint="t('integrations.keys.create_dialog.days_hint')"
          :error="keyDaysError"
          :maxlength="3"
          required
        />
      </form>
      <template #footer>
        <UiButton
          variant="secondary"
          :disabled="creatingKey"
          @click="keyDialogOpen = false"
        >
          {{ t('common.actions.cancel') }}
        </UiButton>
        <UiButton
          :icon="KeyRound"
          :loading="creatingKey"
          @click="onCreateKey"
        >
          {{ t('integrations.keys.create_dialog.submit') }}
        </UiButton>
      </template>
    </UiModal>

    <UiConfirmDialog
      v-model:open="revokeKeyOpen"
      :title="t('integrations.keys.revoke.title')"
      :description="revokeKeyTarget ? t('integrations.keys.revoke.description', { prefix: revokeKeyTarget.prefix }) : undefined"
      :confirm-label="t('integrations.keys.revoke.confirm')"
      :busy="revokingKey"
      :error="revokeKeyError"
      danger
      @confirm="onRevokeKey"
    />

    <UiConfirmDialog
      v-model:open="rotateOpen"
      :title="t('integrations.clients.rotate.title')"
      :description="t('integrations.clients.rotate.description')"
      :confirm-label="t('integrations.clients.rotate.confirm')"
      :busy="rotating"
      :error="rotateError"
      danger
      @confirm="onRotate"
    />

    <UiConfirmDialog
      v-model:open="revokeOpen"
      :title="t('integrations.clients.revoke.title')"
      :description="client ? t('integrations.clients.revoke.description', { name: client.name }) : undefined"
      :confirm-label="t('integrations.clients.revoke.confirm')"
      :busy="revoking"
      :error="revokeError"
      danger
      @confirm="onRevoke"
    />

    <IntegrationsSecretDialog
      v-model:open="secretOpen"
      :title="secret?.title ?? ''"
      :secret-label="secret?.label ?? ''"
      :secret="secret?.value ?? null"
      @done="onSecretDone"
    />
  </div>
</template>
