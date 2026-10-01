<script setup lang="ts">
import type { ImportJob } from '~/types/api/integrations'
import type { TableColumn } from '~/types/ui'

type ImportRowError = ImportJob['errors_preview'][number]

/**
 * `ImportErrorsTable` (SCREENS W41): the first 100 row errors (`errors_preview`) with the row number,
 * column, a localised code label (`integrations.import.row_codes.*`) and the server message.
 */
defineProps<{ errors: ImportRowError[] }>()
const { t } = useI18n()

const columns = computed<TableColumn[]>(() => [
  { key: 'row', label: t('integrations.import.errors.row'), numeric: true, primary: true },
  { key: 'column', label: t('integrations.import.errors.column') },
  { key: 'code', label: t('integrations.import.errors.code') },
  { key: 'message', label: t('integrations.import.errors.message') },
])

const row = (value: unknown) => value as ImportRowError
const keyOf = (value: object) => {
  const item = value as ImportRowError
  return `${item.row}:${item.column}:${item.code}`
}
</script>

<template>
  <UiTable
    :columns="columns"
    :rows="errors"
    :row-key="keyOf"
    :caption="t('integrations.import.errors.caption')"
  >
    <template #cell-row="{ row: r }">
      <span>{{ t('integrations.import.errors.row_number', { row: row(r).row }) }}</span>
    </template>
    <template #cell-column="{ row: r }">
      <code
        dir="ltr"
        class="font-mono text-sm"
      >{{ row(r).column }}</code>
    </template>
    <template #cell-code="{ row: r }">
      <UiBadge
        tone="warning"
        size="sm"
      >
        {{ isKnownImportRowCode(row(r).code) ? t(`integrations.import.row_codes.${row(r).code}`) : row(r).code }}
      </UiBadge>
    </template>
    <template #cell-message="{ row: r }">
      <span class="text-sm text-fg-muted">{{ row(r).message }}</span>
    </template>
  </UiTable>
</template>
