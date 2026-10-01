<script setup lang="ts">
import { extendCompetition } from '~/services/competitions'
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * Manual extension (SCREENS W14/W19 `ExtendDialog`, ARCHITECTURE §7.17): a new close at least
 * 5 minutes after the effective close, and a reason (5–1000) that every participant is told.
 * `extend_invalid` shows the server's earliest allowed close (`details.min_new_close_at`).
 */
const props = defineProps<{ competition: IssuerCompetition, effectiveCloseAt?: string | null }>()
const emit = defineEmits<{ extended: [competition: IssuerCompetition] }>()
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const { message } = useErrorMessage()
const { formatDeadline } = useDate()

const MIN_EXTEND_MS = 5 * 60_000
const REASON_MIN = 5
const REASON_MAX = 1000

const newCloseAt = ref<string | null>(null)
const reason = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const attempted = ref(false)
const serverMin = ref<string | null>(null)

const currentClose = computed(() => props.effectiveCloseAt ?? props.competition.schedule.effective_close_at)
const minIso = computed(() => {
  const base = currentClose.value ? Date.parse(currentClose.value) : Number.NaN
  return Number.isNaN(base) ? undefined : new Date(base + MIN_EXTEND_MS).toISOString()
})

const closeError = computed(() => {
  if (serverMin.value) return t('competitions.issuer.extend.min_error', { time: formatDeadline(serverMin.value) })
  if (!attempted.value) return null
  if (!newCloseAt.value) return t('validation.required')
  if (minIso.value && Date.parse(newCloseAt.value) < Date.parse(minIso.value)) {
    return t('competitions.issuer.extend.min_error', { time: formatDeadline(minIso.value) })
  }
  return null
})
const reasonError = computed(() => {
  if (!attempted.value) return null
  const length = reason.value.trim().length
  return length < REASON_MIN ? t('competitions.issuer.extend.reason_short', { min: REASON_MIN }) : null
})

watch(open, (value) => {
  if (!value) return
  newCloseAt.value = minIso.value ?? null
  reason.value = ''
  error.value = null
  attempted.value = false
  serverMin.value = null
}, { immediate: true })

watch(newCloseAt, () => {
  serverMin.value = null
})

async function confirm(): Promise<void> {
  attempted.value = true
  if (closeError.value || reasonError.value || !newCloseAt.value) return
  busy.value = true
  error.value = null
  try {
    const updated = await extendCompetition(props.competition.id, { new_close_at: newCloseAt.value, reason: reason.value.trim() })
    emit('extended', updated)
    open.value = false
  }
  catch (cause) {
    if (cause instanceof ApiError && cause.code === 'extend_invalid') {
      serverMin.value = cause.detailString('min_new_close_at')
      if (!serverMin.value) error.value = message(cause)
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
    :title="t('competitions.issuer.extend.title')"
    :description="t('competitions.issuer.extend.description')"
    :confirm-label="t('competitions.issuer.extend.confirm')"
    :busy="busy"
    :error="error"
    @confirm="confirm"
  >
    <div class="flex flex-col gap-4">
      <p
        v-if="currentClose"
        class="text-sm text-fg-muted"
      >
        {{ t('competitions.issuer.extend.current') }}
        <UiDateTime
          :value="currentClose"
          format="deadline"
          class="font-semibold text-fg"
        />
      </p>
      <UiDateTimePicker
        v-model="newCloseAt"
        :label="t('competitions.issuer.extend.new_close')"
        :hint="t('competitions.issuer.extend.new_close_hint')"
        :error="closeError"
        :min="minIso"
        required
      />
      <UiTextarea
        v-model="reason"
        :label="t('competitions.issuer.extend.reason')"
        :hint="t('competitions.issuer.extend.reason_hint')"
        :error="reasonError"
        :maxlength="REASON_MAX"
        :rows="3"
        required
      />
    </div>
  </UiConfirmDialog>
</template>
