<script setup lang="ts">
import { RotateCw, Send } from '@lucide/vue'
import { redeliverWebhook } from '~/services/integrations'
import type { WebhookDelivery } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'

/**
 * `DeliveryLogTable` (SCREENS W40): event, time, status, attempts, last HTTP status, duration and next
 * attempt; a row drawer with the last error, the response excerpt and **Redeliver**. The row changes
 * only when the server answers (no optimistic state).
 */
defineProps<{
  deliveries: WebhookDelivery[]
  loading?: boolean
}>()

const emit = defineEmits<{ updated: [delivery: WebhookDelivery] }>()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const selected = ref<WebhookDelivery | null>(null)
const drawerOpen = ref(false)
const redelivering = ref(false)
const redeliverError = ref<string | null>(null)

const columns = computed<TableColumn[]>(() => [
  { key: 'event', label: t('integrations.deliveries.fields.event'), primary: true },
  { key: 'status', label: t('integrations.deliveries.fields.status') },
  { key: 'attempts', label: t('integrations.deliveries.fields.attempts'), numeric: true },
  { key: 'http', label: t('integrations.deliveries.fields.http_status'), numeric: true },
  { key: 'duration', label: t('integrations.deliveries.fields.duration'), numeric: true, hideOnMobile: true },
  { key: 'next', label: t('integrations.deliveries.fields.next_attempt'), hideOnMobile: true },
  { key: 'actions', label: t('integrations.deliveries.fields.actions'), align: 'end' },
])

const row = (value: unknown) => value as WebhookDelivery

function open(delivery: WebhookDelivery): void {
  selected.value = delivery
  redeliverError.value = null
  drawerOpen.value = true
}

async function redeliver(): Promise<void> {
  const target = selected.value
  if (!target) return
  redelivering.value = true
  redeliverError.value = null
  try {
    const updated = await redeliverWebhook(target.id)
    selected.value = updated
    emit('updated', updated)
    toast.success(t('integrations.deliveries.redelivered'))
  }
  catch (error) {
    redeliverError.value = message(error)
  }
  finally {
    redelivering.value = false
  }
}
</script>

<template>
  <div>
    <UiTable
      :columns="columns"
      :rows="deliveries"
      row-key="id"
      :caption="t('integrations.deliveries.title')"
      :loading="loading && deliveries.length === 0"
    >
      <template #cell-event="{ row: r }">
        <span class="flex flex-col gap-0.5">
          <code
            dir="ltr"
            class="font-mono text-sm text-fg"
          >{{ row(r).event.type }}</code>
          <UiDateTime
            class="text-xs text-fg-muted"
            :value="row(r).event.occurred_at"
          />
        </span>
      </template>
      <template #cell-status="{ row: r }">
        <UiBadge
          :tone="DELIVERY_STATUS_TONES[row(r).status]"
          size="sm"
          dot
        >
          {{ t(`integrations.deliveries.statuses.${row(r).status}`) }}
        </UiBadge>
      </template>
      <template #cell-attempts="{ row: r }">
        <bdi>{{ row(r).attempts }}</bdi>
      </template>
      <template #cell-http="{ row: r }">
        <bdi v-if="row(r).last_http_status !== null">{{ row(r).last_http_status }}</bdi>
        <span v-else>—</span>
      </template>
      <template #cell-duration="{ row: r }">
        <span v-if="row(r).last_duration_ms !== null">{{ t('integrations.deliveries.duration_ms', { value: row(r).last_duration_ms }) }}</span>
        <span v-else>—</span>
      </template>
      <template #cell-next="{ row: r }">
        <UiDateTime
          :value="row(r).next_attempt_at"
          format="datetime"
        />
      </template>
      <template #cell-actions="{ row: r }">
        <UiButton
          variant="ghost"
          size="sm"
          :aria-label="t('integrations.deliveries.details_named', { type: row(r).event.type })"
          @click="open(row(r))"
        >
          {{ t('integrations.deliveries.details') }}
        </UiButton>
      </template>
      <template #empty>
        <UiEmptyState
          compact
          :icon="Send"
          :title="t('integrations.deliveries.empty.title')"
          :description="t('integrations.deliveries.empty.body')"
        />
      </template>
    </UiTable>

    <UiDrawer
      v-model:open="drawerOpen"
      :title="t('integrations.deliveries.drawer_title')"
      size="lg"
    >
      <div
        v-if="selected"
        class="flex flex-col gap-5 px-5 py-4"
      >
        <UiKeyValueList
          :items="[
            { key: 'event', label: t('integrations.deliveries.fields.event'), value: selected.event.type, ltr: true },
            { key: 'event_id', label: t('integrations.deliveries.fields.event_id'), value: selected.event.id, ltr: true },
            { key: 'status', label: t('integrations.deliveries.fields.status'), value: null },
            { key: 'attempts', label: t('integrations.deliveries.fields.attempts'), value: selected.attempts, ltr: true },
            { key: 'http', label: t('integrations.deliveries.fields.http_status'), value: selected.last_http_status, ltr: true },
            { key: 'last_attempt', label: t('integrations.deliveries.fields.last_attempt'), value: null },
            { key: 'next', label: t('integrations.deliveries.fields.next_attempt'), value: null },
          ]"
        >
          <template #value-status>
            <UiBadge
              :tone="DELIVERY_STATUS_TONES[selected.status]"
              size="sm"
              dot
            >
              {{ t(`integrations.deliveries.statuses.${selected.status}`) }}
            </UiBadge>
          </template>
          <template #value-last_attempt>
            <UiDateTime
              :value="selected.last_attempt_at"
              format="deadline"
            />
          </template>
          <template #value-next>
            <UiDateTime
              :value="selected.next_attempt_at"
              format="deadline"
            />
          </template>
        </UiKeyValueList>

        <div class="flex flex-col gap-1.5">
          <p class="text-sm font-semibold text-fg">
            {{ t('integrations.deliveries.fields.last_error') }}
          </p>
          <p
            v-if="selected.last_error"
            dir="auto"
            class="rounded-md bg-surface-muted px-3 py-2 font-mono text-sm break-words text-fg"
          >
            {{ selected.last_error }}
          </p>
          <p
            v-else
            class="text-sm text-fg-muted"
          >
            —
          </p>
        </div>

        <div
          v-if="selected.last_response_excerpt"
          class="flex flex-col gap-1.5"
        >
          <p class="text-sm font-semibold text-fg">
            {{ t('integrations.deliveries.fields.response') }}
          </p>
          <UiJsonPane
            :value="selected.last_response_excerpt"
            :label="t('integrations.deliveries.fields.response')"
          />
        </div>

        <UiAlert
          v-if="redeliverError"
          tone="danger"
          role="alert"
        >
          {{ redeliverError }}
        </UiAlert>
      </div>
      <template #footer>
        <UiButton
          :icon="RotateCw"
          :loading="redelivering"
          :disabled="!selected"
          @click="redeliver"
        >
          {{ t('integrations.deliveries.redeliver') }}
        </UiButton>
        <UiButton
          variant="secondary"
          :disabled="redelivering"
          @click="drawerOpen = false"
        >
          {{ t('common.actions.close') }}
        </UiButton>
      </template>
    </UiDrawer>
  </div>
</template>
