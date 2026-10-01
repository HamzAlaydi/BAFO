<script setup lang="ts">
import type { CloseReasonKind } from '~/types/api/catalog'
import type { CloseReasonRequest } from '~/types/api/competitions'

/**
 * A confirmation that needs a close reason (SCREENS S7): cancel (`kind = cancel`) and close without
 * award (`kind = not_awarded`). The reason list comes from the lookups; a note becomes required when
 * the reason `requires_note` (≤ 1000). The red button names the action; nothing changes until the
 * server answers.
 */
const props = withDefaults(defineProps<{
  kind: Extract<CloseReasonKind, 'cancel' | 'not_awarded'>
  title: string
  description?: string
  confirmLabel: string
  cancelLabel?: string
  submit: (body: CloseReasonRequest) => Promise<void>
  danger?: boolean
}>(), {
  description: undefined,
  cancelLabel: undefined,
  danger: true,
})

const open = defineModel<boolean>('open', { default: false })
const { t } = useI18n()
const { message } = useErrorMessage()
const lookups = useLookupsStore()

const reasonId = ref<string | null>(null)
const note = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const attempted = ref(false)
const fieldErrors = ref<Record<string, string>>({})

const reasons = computed(() => lookups.closeReasonsOf(props.kind))
const selected = computed(() => reasons.value.find(reason => reason.id === reasonId.value) ?? null)
const noteRequired = computed(() => selected.value?.requires_note === true)
const options = computed(() => reasons.value.map(reason => ({ value: reason.id, label: reason.name })))

const reasonError = computed(() => fieldErrors.value.close_reason_id ?? (attempted.value && !reasonId.value ? t('competitions.issuer.reason.required') : null))
const noteError = computed(() => fieldErrors.value.note ?? (attempted.value && noteRequired.value && !note.value.trim() ? t('competitions.issuer.reason.note_required') : null))

watch(open, (value) => {
  if (!value) return
  reasonId.value = null
  note.value = ''
  error.value = null
  attempted.value = false
  fieldErrors.value = {}
  void lookups.ensureLoaded().catch(() => {})
}, { immediate: true })

async function confirm(): Promise<void> {
  attempted.value = true
  if (!reasonId.value || (noteRequired.value && !note.value.trim())) return
  busy.value = true
  error.value = null
  fieldErrors.value = {}
  try {
    await props.submit({ close_reason_id: reasonId.value, note: note.value.trim() || null })
    open.value = false
  }
  catch (cause) {
    if (cause instanceof ApiError && cause.isValidation) {
      fieldErrors.value = Object.fromEntries(Object.entries(cause.errors).map(([key, messages]) => [key, messages[0] ?? '']))
      if (!fieldErrors.value.close_reason_id && !fieldErrors.value.note) error.value = message(cause)
    }
    else {
      error.value = message(cause)
    }
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <UiConfirmDialog
    v-model:open="open"
    :title="title"
    :description="description"
    :confirm-label="confirmLabel"
    :cancel-label="cancelLabel"
    :busy="busy"
    :error="error"
    :danger="danger"
    @confirm="confirm"
  >
    <div class="flex flex-col gap-4">
      <UiAlert
        v-if="lookups.loaded && reasons.length === 0"
        tone="warning"
      >
        {{ t('common.lookups_failed') }}
      </UiAlert>
      <UiRadioGroup
        v-else
        v-model="reasonId"
        :options="options"
        :label="t('competitions.issuer.reason.label')"
        :error="reasonError"
        required
      />
      <UiTextarea
        v-model="note"
        :label="t('competitions.issuer.reason.note')"
        :hint="noteRequired ? t('competitions.issuer.reason.note_required_hint') : t('common.optional')"
        :error="noteError"
        :required="noteRequired"
        :maxlength="1000"
        :rows="3"
      />
    </div>
  </UiConfirmDialog>
</template>
