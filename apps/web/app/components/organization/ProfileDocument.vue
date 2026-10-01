<script setup lang="ts">
import { Download, FileText, FileUp, Trash2 } from '@lucide/vue'
import { deleteProfileDocument, uploadProfileDocument } from '~/services/identity'
import type { Organization } from '~/types/api/identity'

/** Company profile PDF (≤ 20 MB, private file): upload, download with the bearer, replace, remove. */
const props = defineProps<{
  organization: Organization
  editable: boolean
}>()

const emit = defineEmits<{ updated: [organization: Organization] }>()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const { download, downloading } = useFileDownload()
const input = useTemplateRef<HTMLInputElement>('input')
const busy = ref<'upload' | 'delete' | null>(null)
const error = ref<string | null>(null)

const MAX_BYTES = 20 * 1024 * 1024
const ACCEPT = '.pdf,application/pdf'

const profileDocument = computed(() => props.organization.profile_document)
const size = computed(() => {
  if (!profileDocument.value) return ''
  const { value, unit } = fileSizeParts(profileDocument.value.size_bytes)
  return t(`common.file_drop.units.${unit}`, { value })
})

async function onFile(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  target.value = ''
  if (!file) return
  error.value = null
  if (!fileMatchesAccept(file, ACCEPT)) {
    error.value = t('errors.file_type_not_allowed')
    return
  }
  if (file.size > MAX_BYTES) {
    error.value = t('organization.profile_document.too_large', { size: 20 })
    return
  }
  busy.value = 'upload'
  try {
    emit('updated', await uploadProfileDocument(file))
    toast.success(t('organization.profile_document.uploaded'))
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    busy.value = null
  }
}

async function remove(): Promise<void> {
  busy.value = 'delete'
  error.value = null
  try {
    emit('updated', await deleteProfileDocument())
    toast.success(t('organization.profile_document.removed'))
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    busy.value = null
  }
}

async function save(): Promise<void> {
  if (!profileDocument.value) return
  error.value = null
  try {
    await download(profileDocument.value.download_path, profileDocument.value.name)
  }
  catch (cause) {
    error.value = message(cause)
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div
      v-if="profileDocument"
      class="flex flex-wrap items-center gap-3 rounded-md border border-line bg-surface-muted px-3 py-2.5"
    >
      <FileText
        :size="20"
        class="shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <span class="min-w-0 flex-1">
        <span
          class="block truncate text-sm font-semibold text-fg"
          dir="auto"
        >{{ profileDocument.name }}</span>
        <span class="text-xs text-fg-muted tabular-nums">{{ size }}</span>
      </span>
      <UiButton
        variant="ghost"
        size="sm"
        :icon="Download"
        :loading="downloading"
        @click="save"
      >
        {{ t('common.actions.download') }}
      </UiButton>
    </div>
    <p
      v-else
      class="text-sm text-fg-muted"
    >
      {{ t('organization.profile_document.none') }}
    </p>
    <div
      v-if="editable"
      class="flex flex-wrap gap-2"
    >
      <input
        ref="input"
        type="file"
        class="sr-only"
        :accept="ACCEPT"
        tabindex="-1"
        :aria-label="t('organization.profile_document.upload')"
        @change="onFile"
      >
      <UiButton
        variant="secondary"
        size="sm"
        :icon="FileUp"
        :loading="busy === 'upload'"
        :disabled="busy !== null"
        @click="input?.click()"
      >
        {{ profileDocument ? t('organization.profile_document.replace') : t('organization.profile_document.upload') }}
      </UiButton>
      <UiButton
        v-if="profileDocument"
        variant="danger-ghost"
        size="sm"
        :icon="Trash2"
        :loading="busy === 'delete'"
        :disabled="busy !== null"
        @click="remove"
      >
        {{ t('organization.profile_document.remove') }}
      </UiButton>
    </div>
    <p
      v-if="editable"
      class="text-sm text-fg-muted"
    >
      {{ t('organization.profile_document.hint', { size: 20 }) }}
    </p>
    <p
      v-if="error"
      class="text-sm font-medium text-danger"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
