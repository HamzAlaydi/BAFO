<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue'
import type { ExternalRefDraft } from '~/utils/integrations-display'

/**
 * `ExternalRefsEditor` (SCREENS W25): the vendor's keys in other systems (system slug, type, id,
 * optional number and URL). `errors` maps `external_refs.{i}.{field}` server paths to messages.
 */
const props = defineProps<{
  errors?: Record<string, string>
  showErrors?: boolean
  disabled?: boolean
}>()

const model = defineModel<ExternalRefDraft[]>({ required: true })
const { t } = useI18n()
let nextKey = Date.now()

function add(): void {
  model.value = [...model.value, { key: nextKey++, system: '', type: 'supplier', id: '', number: '', url: '' }]
}

function remove(index: number): void {
  model.value = model.value.filter((_, i) => i !== index)
}

function update(index: number, field: keyof Omit<ExternalRefDraft, 'key'>, value: string): void {
  model.value = model.value.map((ref, i) => (i === index ? { ...ref, [field]: value } : ref))
}

function fieldError(index: number, field: 'system' | 'type' | 'id' | 'number' | 'url'): string | null {
  const server = props.errors?.[`external_refs.${index}.${field}`] ?? (field === 'id' ? props.errors?.[`external_refs.${index}`] : undefined)
  if (server) return server
  if (!props.showErrors) return null
  const ref = model.value[index]
  if (!ref) return null
  if (field === 'system') {
    if (!ref.system.trim()) return t('validation.required')
    return isExternalSystemSlug(ref.system.trim()) ? null : t('vendors.refs.system_invalid')
  }
  if ((field === 'type' || field === 'id') && !ref[field].trim()) return t('validation.required')
  return null
}
</script>

<template>
  <fieldset class="flex min-w-0 flex-col gap-3">
    <legend class="mb-1 text-sm font-semibold text-fg">
      {{ t('vendors.refs.title') }}
    </legend>
    <p class="-mt-2 text-sm text-fg-muted">
      {{ t('vendors.refs.hint') }}
    </p>
    <div
      v-for="(ref, index) in model"
      :key="ref.key"
      class="flex flex-col gap-3 rounded-md border border-line p-4"
      role="group"
      :aria-label="t('vendors.refs.row_label', { index: index + 1 })"
    >
      <div class="flex items-center justify-between gap-2">
        <p class="text-sm font-semibold text-fg">
          {{ t('vendors.refs.row_label', { index: index + 1 }) }}
        </p>
        <UiIconButton
          :icon="Trash2"
          variant="danger-ghost"
          size="sm"
          :label="t('vendors.refs.remove', { index: index + 1 })"
          :disabled="disabled"
          @click="remove(index)"
        />
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <UiInput
          :model-value="ref.system"
          :label="t('vendors.refs.system')"
          :hint="t('vendors.refs.system_hint')"
          :error="fieldError(index, 'system')"
          dir="ltr"
          autocomplete="off"
          :maxlength="64"
          :disabled="disabled"
          required
          @update:model-value="update(index, 'system', $event)"
        />
        <UiInput
          :model-value="ref.type"
          :label="t('vendors.refs.type')"
          :error="fieldError(index, 'type')"
          dir="ltr"
          autocomplete="off"
          :maxlength="32"
          :disabled="disabled"
          required
          @update:model-value="update(index, 'type', $event)"
        />
        <UiInput
          :model-value="ref.id"
          :label="t('vendors.refs.id')"
          :error="fieldError(index, 'id')"
          dir="ltr"
          autocomplete="off"
          :maxlength="128"
          :disabled="disabled"
          required
          @update:model-value="update(index, 'id', $event)"
        />
        <UiInput
          :model-value="ref.number"
          :label="t('vendors.refs.number')"
          :error="fieldError(index, 'number')"
          dir="ltr"
          autocomplete="off"
          :maxlength="128"
          :disabled="disabled"
          @update:model-value="update(index, 'number', $event)"
        />
        <div class="sm:col-span-2">
          <UiInput
            type="url"
            :model-value="ref.url"
            :label="t('vendors.refs.url')"
            :error="fieldError(index, 'url')"
            dir="ltr"
            autocomplete="off"
            :maxlength="1000"
            :disabled="disabled"
            @update:model-value="update(index, 'url', $event)"
          />
        </div>
      </div>
    </div>
    <UiButton
      variant="secondary"
      size="sm"
      class="self-start"
      :icon="Plus"
      :disabled="disabled"
      @click="add"
    >
      {{ t('vendors.refs.add') }}
    </UiButton>
  </fieldset>
</template>
