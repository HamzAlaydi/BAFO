<script setup lang="ts">
import { KeyRound } from '@lucide/vue'
import type { ApiKey } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'

/**
 * `ApiKeyTable` (SCREENS W38): prefix, masked key, expiry, last use, state and a revoke action for
 * active keys. The full key is never available here (it is shown once at creation).
 */
defineProps<{
  keys: ApiKey[]
  /** Hide actions for a revoked client. */
  readonly?: boolean
  busyId?: string | null
}>()

const emit = defineEmits<{ revoke: [key: ApiKey] }>()
const { t } = useI18n()
const clock = useServerTime()

const columns = computed<TableColumn[]>(() => [
  { key: 'masked', label: t('integrations.keys.fields.key'), primary: true },
  { key: 'state', label: t('integrations.keys.fields.status') },
  { key: 'expires_at', label: t('integrations.keys.fields.expires') },
  { key: 'last_used_at', label: t('integrations.keys.fields.last_used') },
  { key: 'created_at', label: t('integrations.keys.fields.created') },
  { key: 'actions', label: t('integrations.keys.fields.actions'), align: 'end' },
])

const row = (value: unknown) => value as ApiKey
const stateOf = (key: ApiKey) => apiKeyState(key, clock.now())
</script>

<template>
  <UiTable
    :columns="columns"
    :rows="keys"
    row-key="id"
    :caption="t('integrations.keys.title')"
  >
    <template #cell-masked="{ row: r }">
      <span class="flex flex-col gap-0.5">
        <code
          dir="ltr"
          class="font-mono text-sm break-all text-fg"
        >{{ row(r).masked }}</code>
        <span class="text-xs text-fg-muted">
          {{ t('integrations.keys.fields.prefix') }} <bdi class="font-mono">{{ row(r).prefix }}</bdi>
        </span>
      </span>
    </template>
    <template #cell-state="{ row: r }">
      <UiBadge
        :tone="API_KEY_STATE_TONES[stateOf(row(r))]"
        size="sm"
        dot
      >
        {{ t(`integrations.keys.states.${stateOf(row(r))}`) }}
      </UiBadge>
    </template>
    <template #cell-expires_at="{ row: r }">
      <UiDateTime
        :value="row(r).expires_at"
        format="date"
        :empty="t('integrations.keys.no_expiry')"
      />
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
    <template #cell-created_at="{ row: r }">
      <UiDateTime
        :value="row(r).created_at"
        format="date"
      />
    </template>
    <template #cell-actions="{ row: r }">
      <UiButton
        v-if="!readonly && stateOf(row(r)) === 'active'"
        variant="danger-ghost"
        size="sm"
        :loading="busyId === row(r).id"
        :aria-label="t('integrations.keys.revoke.named', { prefix: row(r).prefix })"
        @click="emit('revoke', row(r))"
      >
        {{ t('integrations.keys.revoke.action') }}
      </UiButton>
      <span v-else>—</span>
    </template>
    <template #empty>
      <UiEmptyState
        compact
        :icon="KeyRound"
        :title="t('integrations.keys.empty.title')"
        :description="t('integrations.keys.empty.body')"
      />
    </template>
  </UiTable>
</template>
