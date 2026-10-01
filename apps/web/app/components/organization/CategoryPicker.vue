<script setup lang="ts">
import type { SelectOption } from '~/types/ui'

/** Activity categories (≤ 20) from `useLookupsStore()`, as a searchable multi-select. */
defineProps<{
  label: string
  hint?: string
  error?: string | null
  disabled?: boolean
}>()

const model = defineModel<string[]>({ default: () => [] })
const { t } = useI18n()
const lookups = useLookupsStore()

onMounted(() => {
  lookups.ensureLoaded().catch(() => {})
})

const options = computed<SelectOption<string>[]>(() => lookups.categories.map(category => ({ value: category.id, label: category.name })))
</script>

<template>
  <UiCombobox
    v-model="model"
    :options="options"
    :label="label"
    :hint="hint"
    :error="error"
    :disabled="disabled"
    :loading="lookups.loading && options.length === 0"
    :placeholder="t('organization.fields.categories_placeholder')"
    :max="MAX_CATEGORIES"
  />
</template>
