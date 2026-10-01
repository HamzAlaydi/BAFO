<script setup lang="ts">
import { ImageUp, Trash2 } from '@lucide/vue'
import { deleteOrganizationLogo, uploadOrganizationLogo } from '~/services/identity'
import type { Organization } from '~/types/api/identity'

/** Organisation logo (images ≤ 2 MB, API.md §1.3): upload, replace, remove. Emits the updated organisation. */
const props = defineProps<{
  organization: Organization
  editable: boolean
}>()

const emit = defineEmits<{ updated: [organization: Organization] }>()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const input = useTemplateRef<HTMLInputElement>('input')
const busy = ref<'upload' | 'delete' | null>(null)
const error = ref<string | null>(null)

const MAX_BYTES = 2 * 1024 * 1024
const ACCEPT = '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp'

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
    error.value = t('organization.logo.too_large', { size: 2 })
    return
  }
  busy.value = 'upload'
  try {
    emit('updated', await uploadOrganizationLogo(file))
    toast.success(t('organization.logo.uploaded'))
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
    emit('updated', await deleteOrganizationLogo())
    toast.success(t('organization.logo.removed'))
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-4">
      <UiOrgLogo
        :name="props.organization.name"
        :src="props.organization.logo_url"
        size="lg"
      />
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
          :aria-label="t('organization.logo.upload')"
          @change="onFile"
        >
        <UiButton
          variant="secondary"
          size="sm"
          :icon="ImageUp"
          :loading="busy === 'upload'"
          :disabled="busy !== null"
          @click="input?.click()"
        >
          {{ props.organization.logo_url ? t('organization.logo.replace') : t('organization.logo.upload') }}
        </UiButton>
        <UiButton
          v-if="props.organization.logo_url"
          variant="danger-ghost"
          size="sm"
          :icon="Trash2"
          :loading="busy === 'delete'"
          :disabled="busy !== null"
          @click="remove"
        >
          {{ t('organization.logo.remove') }}
        </UiButton>
      </div>
    </div>
    <p
      v-if="editable"
      class="text-sm text-fg-muted"
    >
      {{ t('organization.logo.hint', { size: 2 }) }}
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
