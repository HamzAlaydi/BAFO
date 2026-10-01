<script setup lang="ts">
import { Power, PowerOff, RefreshCw, Send, Trash2, TriangleAlert } from '@lucide/vue'
import {
  deleteWebhookEndpoint,
  fetchWebhookEndpoint,
  listWebhookDeliveries,
  listWebhookEventTypes,
  rotateWebhookSecret,
  testWebhookEndpoint,
  updateWebhookEndpoint,
} from '~/services/integrations'
import type { DeliveryStatus, WebhookDelivery, WebhookEndpoint, WebhookEndpointInput, WebhookEventType } from '~/types/api/integrations'
import type { PagePagination } from '~/types/api/common'
import type { ChoiceOption } from '~/types/ui'

/**
 * W40 Webhook endpoint · `/dashboard/integrations/webhooks/{id}` (`integrations.manage` +
 * `api_enabled`; SCREENS §2.4). Edit URL, events and description; enable or disable; send a test
 * event (202 → the delivery log refreshes after 3 s); rotate the secret (shown once); delete. The
 * delivery log with a status filter, a row drawer and Redeliver. Signature verification snippets.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const props = withDefaults(defineProps<{ testRefreshMs?: number }>(), { testRefreshMs: 3000 })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const toast = useToast()
const localePath = useLocalePath()
const { message, bind } = useErrorMessage()

const allowed = computed(() => auth.can('integrations.manage'))
const apiEnabled = computed(() => auth.features?.api_enabled === true)
const endpointId = computed(() => String(route.params.id ?? ''))

const endpoint = ref<WebhookEndpoint | null>(null)
const eventTypes = ref<WebhookEventType[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const disabledByServer = ref(false)

useSeoMeta({ title: () => t('integrations.webhooks.detail.title') })

// Edit
const formKey = ref(0)
const saving = ref(false)
const saveErrors = ref<Record<string, string>>({})
const saveFormError = ref<string | null>(null)
const dirty = ref(false)
useUnsavedChangesGuard(dirty)

// Actions
const toggling = ref(false)
const testing = ref(false)
const rotateOpen = ref(false)
const rotating = ref(false)
const rotateError = ref<string | null>(null)
const deleteOpen = ref(false)
const deleting = ref(false)
const deleteError = ref<string | null>(null)
const secretOpen = ref(false)
const secret = ref<string | null>(null)

// Deliveries
type DeliveryFilter = 'all' | DeliveryStatus
const filter = ref<DeliveryFilter>('all')
const page = ref(1)
const deliveries = ref<WebhookDelivery[]>([])
const deliveriesPagination = ref<PagePagination | null>(null)
const deliveriesLoading = ref(false)
const deliveriesError = ref<unknown>(null)
let testTimer: ReturnType<typeof setTimeout> | null = null
let deliveriesSeq = 0

async function load(): Promise<void> {
  if (!allowed.value || !apiEnabled.value) return
  loading.value = true
  loadError.value = null
  const [found, types] = await Promise.allSettled([fetchWebhookEndpoint(endpointId.value), listWebhookEventTypes()])
  if (found.status === 'fulfilled') endpoint.value = found.value
  else if (found.reason instanceof ApiError && found.reason.code === 'api_access_disabled') disabledByServer.value = true
  else loadError.value = found.reason
  if (types.status === 'fulfilled') eventTypes.value = types.value
  loading.value = false
  if (endpoint.value) void loadDeliveries()
}

async function loadDeliveries(): Promise<void> {
  const current = ++deliveriesSeq
  deliveriesLoading.value = true
  deliveriesError.value = null
  try {
    const result = await listWebhookDeliveries(endpointId.value, { status: filter.value === 'all' ? undefined : filter.value, page: page.value })
    if (current !== deliveriesSeq) return
    deliveries.value = result.items
    deliveriesPagination.value = result.pagination
  }
  catch (error) {
    if (current === deliveriesSeq) deliveriesError.value = error
  }
  finally {
    if (current === deliveriesSeq) deliveriesLoading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => {
  if (testTimer) clearTimeout(testTimer)
})

watch(filter, () => {
  page.value = 1
  void loadDeliveries()
})
watch(page, () => void loadDeliveries())

const notFound = computed(() => loadError.value instanceof ApiError && loadError.value.isNotFound)
const pageCount = computed(() => deliveriesPagination.value?.last_page ?? (deliveriesPagination.value?.has_more ? page.value + 1 : page.value))

const filterOptions = computed<ChoiceOption<DeliveryFilter>[]>(() => (['all', 'pending', 'succeeded', 'failed'] as const).map(value => ({
  value,
  label: t(`integrations.deliveries.filters.${value}`),
})))
const filterModel = computed<DeliveryFilter | null>({ get: () => filter.value, set: value => value && (filter.value = value) })

async function onSave(input: WebhookEndpointInput): Promise<void> {
  if (!endpoint.value) return
  saving.value = true
  saveErrors.value = {}
  saveFormError.value = null
  try {
    endpoint.value = await updateWebhookEndpoint(endpoint.value.id, input)
    dirty.value = false
    formKey.value += 1
    toast.success(t('integrations.webhooks.detail.saved'))
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'webhook_url_invalid') {
      saveErrors.value = { url: message(error) }
      return
    }
    const bound = bind(error, ['url', 'event_types', 'description'], arrayAliases(error, 'event_types'))
    saveErrors.value = bound.fields
    saveFormError.value = bound.unmatched[0] ?? (Object.keys(bound.fields).length > 0 ? null : message(error))
  }
  finally {
    saving.value = false
  }
}

async function toggleStatus(): Promise<void> {
  if (!endpoint.value) return
  const next = endpoint.value.status === 'active' ? 'disabled' : 'active'
  toggling.value = true
  try {
    endpoint.value = await updateWebhookEndpoint(endpoint.value.id, { status: next })
    toast.success(next === 'active' ? t('integrations.webhooks.detail.enabled') : t('integrations.webhooks.detail.disabled'))
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    toggling.value = false
  }
}

async function sendTest(): Promise<void> {
  if (!endpoint.value) return
  testing.value = true
  try {
    await testWebhookEndpoint(endpoint.value.id)
    toast.success(t('integrations.webhooks.detail.test_sent'))
    if (testTimer) clearTimeout(testTimer)
    testTimer = setTimeout(() => {
      filter.value = 'all'
      if (page.value !== 1) page.value = 1
      else void loadDeliveries()
    }, props.testRefreshMs)
  }
  catch (error) {
    toast.error(message(error))
  }
  finally {
    testing.value = false
  }
}

async function onRotate(): Promise<void> {
  if (!endpoint.value) return
  rotating.value = true
  rotateError.value = null
  try {
    const { secret: value, ...rest } = await rotateWebhookSecret(endpoint.value.id)
    endpoint.value = rest
    rotateOpen.value = false
    secret.value = value
    secretOpen.value = true
  }
  catch (error) {
    rotateError.value = message(error)
  }
  finally {
    rotating.value = false
  }
}

async function onDelete(): Promise<void> {
  if (!endpoint.value) return
  deleting.value = true
  deleteError.value = null
  try {
    await deleteWebhookEndpoint(endpoint.value.id)
    deleteOpen.value = false
    dirty.value = false
    toast.success(t('integrations.webhooks.delete.done'))
    await navigateTo(localePath('/dashboard/integrations/webhooks'))
  }
  catch (error) {
    deleteError.value = message(error)
  }
  finally {
    deleting.value = false
  }
}

function onDeliveryUpdated(delivery: WebhookDelivery): void {
  deliveries.value = deliveries.value.map(item => (item.id === delivery.id ? delivery : item))
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader :title="t('integrations.webhooks.detail.title')">
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/integrations/webhooks"
        >
          {{ t('integrations.webhooks.title') }}
        </UiButton>
      </template>
      <template
        v-if="endpoint"
        #meta
      >
        <div class="mt-2 flex flex-col gap-2">
          <code
            dir="ltr"
            class="self-start font-mono text-sm break-all text-fg"
          >{{ endpoint.url }}</code>
          <div class="flex flex-wrap items-center gap-2">
            <UiBadge
              :tone="endpoint.status === 'active' ? 'primary' : 'neutral'"
              size="sm"
              dot
            >
              {{ t(`integrations.webhooks.statuses.${endpoint.status}`) }}
            </UiBadge>
            <span
              v-if="endpoint.status === 'disabled' && endpoint.disabled_reason"
              class="text-sm text-fg-muted"
            >{{ t(`integrations.webhooks.disabled_reasons.${endpoint.disabled_reason === 'failing' ? 'failing' : 'manual'}`) }}</span>
          </div>
        </div>
      </template>
      <template
        v-if="endpoint"
        #actions
      >
        <UiButton
          variant="secondary"
          :icon="Send"
          flip-icons
          :loading="testing"
          @click="sendTest"
        >
          {{ t('integrations.webhooks.detail.test') }}
        </UiButton>
        <UiButton
          variant="secondary"
          :icon="endpoint.status === 'active' ? PowerOff : Power"
          :loading="toggling"
          @click="toggleStatus"
        >
          {{ endpoint.status === 'active' ? t('integrations.webhooks.detail.disable') : t('integrations.webhooks.detail.enable') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />
    <IntegrationsApiDisabledCallout v-else-if="!apiEnabled || disabledByServer" />

    <UiCard v-else-if="notFound">
      <UiNotFoundState />
    </UiCard>

    <UiCard
      v-else-if="loadError && !endpoint"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
    </UiCard>

    <div
      v-else-if="!endpoint"
      class="flex flex-col gap-6"
      :aria-label="t('common.loading')"
    >
      <UiCard>
        <UiSkeleton :lines="5" />
      </UiCard>
    </div>

    <template v-else>
      <UiAlert
        v-if="endpoint.failing_since && endpoint.status === 'active'"
        tone="warning"
        :icon="TriangleAlert"
      >
        {{ t('integrations.webhooks.failing_since') }}
        <UiDateTime
          :value="endpoint.failing_since"
          format="deadline"
        />
        <p class="mt-1">
          {{ t('integrations.webhooks.detail.failing_hint') }}
        </p>
      </UiAlert>

      <!-- Delivery log -->
      <section
        class="flex flex-col gap-3"
        aria-labelledby="deliveries-title"
      >
        <div class="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2
              id="deliveries-title"
              class="text-lg font-bold text-fg"
            >
              {{ t('integrations.deliveries.title') }}
            </h2>
            <p class="text-sm text-fg-muted">
              {{ t('integrations.deliveries.subtitle') }}
            </p>
          </div>
          <div class="flex flex-wrap items-end gap-2">
            <UiSegmented
              v-model="filterModel"
              :options="filterOptions"
              :label="t('integrations.deliveries.filters.label')"
              size="sm"
            />
            <UiIconButton
              :icon="RefreshCw"
              :label="t('integrations.deliveries.refresh')"
              variant="secondary"
              size="lg"
              @click="loadDeliveries"
            />
          </div>
        </div>
        <UiCard
          v-if="deliveriesError && deliveries.length === 0"
          padding="none"
        >
          <UiErrorState
            :error="deliveriesError"
            :retrying="deliveriesLoading"
            compact
            @retry="loadDeliveries"
          />
        </UiCard>
        <IntegrationsDeliveryLogTable
          v-else
          :deliveries="deliveries"
          :loading="deliveriesLoading"
          @updated="onDeliveryUpdated"
        />
        <UiPagination
          v-if="pageCount > 1"
          v-model:page="page"
          :page-count="pageCount"
        />
      </section>

      <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <UiCard
          as="section"
          :title="t('integrations.webhooks.detail.settings_title')"
        >
          <IntegrationsWebhookForm
            :key="formKey"
            :event-types="eventTypes"
            :initial="{ url: endpoint.url, event_types: endpoint.event_types, description: endpoint.description }"
            :submit-label="t('common.actions.save_changes')"
            :busy="saving"
            :server-errors="saveErrors"
            :form-error="saveFormError"
            @submit="onSave"
            @dirty="dirty = $event"
          />
        </UiCard>

        <div class="flex flex-col gap-6">
          <UiCard
            as="section"
            :title="t('integrations.webhooks.signature.title')"
            :description="t('integrations.webhooks.signature.body')"
          >
            <div class="flex flex-col gap-5">
              <IntegrationsCodeSnippet
                :label="t('integrations.webhooks.signature.node')"
                :code="WEBHOOK_VERIFY_NODE"
              />
              <IntegrationsCodeSnippet
                :label="t('integrations.webhooks.signature.php')"
                :code="WEBHOOK_VERIFY_PHP"
              />
            </div>
          </UiCard>

          <UiCard
            as="section"
            :title="t('integrations.webhooks.detail.danger_title')"
          >
            <div class="flex flex-col gap-3">
              <UiButton
                variant="secondary"
                class="self-start"
                :icon="RefreshCw"
                @click="rotateError = null; rotateOpen = true"
              >
                {{ t('integrations.webhooks.rotate.action') }}
              </UiButton>
              <UiButton
                variant="danger-ghost"
                class="self-start"
                :icon="Trash2"
                @click="deleteError = null; deleteOpen = true"
              >
                {{ t('integrations.webhooks.delete.action') }}
              </UiButton>
            </div>
          </UiCard>
        </div>
      </div>
    </template>

    <UiConfirmDialog
      v-model:open="rotateOpen"
      :title="t('integrations.webhooks.rotate.title')"
      :description="t('integrations.webhooks.rotate.description')"
      :confirm-label="t('integrations.webhooks.rotate.confirm')"
      :busy="rotating"
      :error="rotateError"
      danger
      @confirm="onRotate"
    />

    <UiConfirmDialog
      v-model:open="deleteOpen"
      :title="t('integrations.webhooks.delete.title')"
      :description="endpoint ? t('integrations.webhooks.delete.description', { url: endpoint.url }) : undefined"
      :confirm-label="t('integrations.webhooks.delete.confirm')"
      :busy="deleting"
      :error="deleteError"
      danger
      @confirm="onDelete"
    />

    <IntegrationsSecretDialog
      v-model:open="secretOpen"
      :title="t('integrations.webhooks.rotate.done_title')"
      :secret-label="t('integrations.webhooks.created.secret_label')"
      :secret="secret"
      @done="secret = null"
    />
  </div>
</template>
