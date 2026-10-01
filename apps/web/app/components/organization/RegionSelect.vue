<script setup lang="ts">
import type { SelectOption } from '~/types/ui'

/** Region picker from `useLookupsStore()` (localised names; loads on first use). */
defineOptions({ inheritAttrs: false })

defineProps<{
  label: string
  error?: string | null
  required?: boolean
  disabled?: boolean
}>()

const model = defineModel<string | null>({ default: null })
const { t } = useI18n()
const lookups = useLookupsStore()

onMounted(() => {
  lookups.ensureLoaded().catch(() => {})
})

const options = computed<SelectOption<string>[]>(() => lookups.regions.map(region => ({ value: region.id, label: region.name })))
</script>

<template>
  <UiSelect
    v-model="model"
    v-bind="$attrs"
    :options="options"
    :label="label"
    :placeholder="lookups.loading && options.length === 0 ? t('common.loading') : t('common.select_placeholder')"
    :hint="lookups.error && options.length === 0 ? t('common.lookups_failed') : undefined"
    :error="error"
    :required="required"
    :disabled="disabled"
  />
</template>
