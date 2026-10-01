<script setup lang="ts">
import { CloudUpload, FileText, X } from '@lucide/vue'
import type { RejectedFile } from '~/types/ui'

const props = withDefaults(defineProps<{
  label?: string
  hint?: string
  error?: string | null
  id?: string
  /** Same syntax as the `accept` attribute: ".pdf,image/*". */
  accept?: string
  multiple?: boolean
  maxSizeMb?: number
  maxFiles?: number
  disabled?: boolean
  required?: boolean
}>(), {
  maxSizeMb: 10,
  maxFiles: 10,
})

const emit = defineEmits<{ rejected: [files: RejectedFile[]] }>()
const model = defineModel<File[]>({ default: () => [] })
const { t } = useI18n()
const autoId = useId()
const inputId = computed(() => props.id ?? `files-${autoId}`)
const dragging = ref(false)
const rejections = ref<RejectedFile[]>([])

const limitHint = computed(() => t('common.file_drop.limits', { size: props.maxSizeMb }))

function sizeLabel(bytes: number): string {
  const { value, unit } = fileSizeParts(bytes)
  return t(`common.file_drop.units.${unit}`, { value })
}

function addFiles(list: FileList | null | undefined): void {
  if (!list || props.disabled) return
  const accepted: File[] = []
  const rejected: RejectedFile[] = []
  const capacity = props.multiple ? props.maxFiles - model.value.length : 1
  for (const file of Array.from(list)) {
    if (!fileMatchesAccept(file, props.accept)) rejected.push({ file, reason: 'type' })
    else if (file.size > props.maxSizeMb * 1024 * 1024) rejected.push({ file, reason: 'size' })
    else if (accepted.length >= capacity) rejected.push({ file, reason: 'count' })
    else accepted.push(file)
  }
  if (accepted.length > 0) model.value = props.multiple ? [...model.value, ...accepted] : accepted
  rejections.value = rejected
  if (rejected.length > 0) emit('rejected', rejected)
}

function onChange(event: Event): void {
  const input = event.target as HTMLInputElement
  addFiles(input.files)
  input.value = ''
}

function onDrop(event: DragEvent): void {
  dragging.value = false
  addFiles(event.dataTransfer?.files)
}

function remove(index: number): void {
  model.value = model.value.filter((_, i) => i !== index)
}

const rejectionMessage = computed(() => {
  const first = rejections.value[0]
  if (!first) return null
  return t(`common.file_drop.rejected.${first.reason}`, { name: first.file.name, size: props.maxSizeMb, max: props.maxFiles })
})
</script>

<template>
  <UiField
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint ?? limitHint"
    :error="error ?? rejectionMessage"
    :required="required"
  >
    <label
      :for="inputId"
      class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring"
      :class="[
        dragging ? 'border-brand bg-primary-soft' : invalid ? 'border-danger' : 'border-line-strong/60 hover:bg-surface-muted',
        disabled && 'pointer-events-none opacity-60',
      ]"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <CloudUpload
        :size="28"
        class="text-brand"
        aria-hidden="true"
      />
      <span class="text-sm font-semibold text-fg">
        {{ t('common.file_drop.prompt') }}
        <span class="link">{{ t('common.file_drop.browse') }}</span>
      </span>
      <input
        :id="inputId"
        type="file"
        class="sr-only"
        :accept="accept"
        :multiple="multiple"
        :disabled="disabled"
        :required="required && model.length === 0"
        :aria-describedby="describedby"
        @change="onChange"
      >
    </label>
    <ul
      v-if="model.length > 0"
      class="flex flex-col gap-2"
      :aria-label="t('common.file_drop.selected')"
    >
      <li
        v-for="(file, index) in model"
        :key="`${file.name}-${file.size}-${index}`"
        class="flex items-center gap-3 rounded-md border border-line bg-surface px-3 py-2"
      >
        <FileText
          :size="18"
          class="shrink-0 text-fg-muted"
          aria-hidden="true"
        />
        <span
          class="min-w-0 flex-1 truncate text-sm text-fg"
          dir="auto"
        >{{ file.name }}</span>
        <span class="shrink-0 text-xs text-fg-muted tabular-nums">{{ sizeLabel(file.size) }}</span>
        <UiIconButton
          :icon="X"
          size="sm"
          :label="t('common.file_drop.remove', { name: file.name })"
          @click="remove(index)"
        />
      </li>
    </ul>
  </UiField>
</template>
