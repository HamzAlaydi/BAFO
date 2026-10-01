<script setup lang="ts">
import { startBafoRound } from '~/services/bidding'
import type { ParticipantStandingRow } from '~/types/api/bidding'
import type { IssuerCompetition } from '~/types/api/competitions'
import { EDITOR_RULE_BOUNDS } from '~/stores/competition-editor-rules'

/**
 * Start a best-and-final-offer round (SCREENS W22 `BafoShortlistDialog`, ARCHITECTURE §7.11): a
 * manual shortlist of 1–50 participants with a current offer and a duration (15–4320 minutes,
 * default the competition's). Errors: `bafo_not_enabled`, `bafo_already_used`, and
 * `bafo_shortlist_invalid` with `details.invalid_participant_ids` marked in the list.
 */
const props = defineProps<{ competition: IssuerCompetition, rows: ParticipantStandingRow[] }>()
const emit = defineEmits<{ started: [competition: IssuerCompetition] }>()
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const { message } = useErrorMessage()
const B = EDITOR_RULE_BOUNDS.bafoDurationMinutes

const chosen = ref<Set<string>>(new Set())
const duration = ref<number | null>(null)
const busy = ref(false)
const error = ref<string | null>(null)
const invalid = ref<string[]>([])
const attempted = ref(false)

const candidates = computed(() => props.rows.filter(row => row.current_amount_minor !== null && row.offers_count > 0))
const durationError = computed(() => {
  if (!attempted.value) return null
  return duration.value === null || duration.value < B.min || duration.value > B.max ? t('rules.bounds_minutes', { min: B.min, max: B.max }) : null
})
const selectionError = computed(() => {
  if (!attempted.value) return null
  if (chosen.value.size === 0) return t('bafo.shortlist.required')
  if (chosen.value.size > 50) return t('bafo.shortlist.too_many', { max: 50 })
  return null
})

watch(open, (value) => {
  if (!value) return
  chosen.value = new Set()
  duration.value = props.competition.rules.bafo_round.duration_minutes ?? B.default
  error.value = null
  invalid.value = []
  attempted.value = false
}, { immediate: true })

function toggle(id: string, value: boolean): void {
  const next = new Set(chosen.value)
  if (value) next.add(id)
  else next.delete(id)
  chosen.value = next
}

async function confirm(): Promise<void> {
  attempted.value = true
  if (selectionError.value || durationError.value) return
  busy.value = true
  error.value = null
  invalid.value = []
  try {
    const updated = await startBafoRound(props.competition.id, { participant_ids: [...chosen.value], duration_minutes: duration.value })
    emit('started', updated)
    open.value = false
  }
  catch (cause) {
    if (cause instanceof ApiError && cause.code === 'bafo_shortlist_invalid') {
      const ids = cause.details.invalid_participant_ids
      invalid.value = Array.isArray(ids) ? ids.filter((id): id is string => typeof id === 'string') : []
    }
    error.value = message(cause)
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <UiConfirmDialog
    v-model:open="open"
    :title="t('bafo.shortlist.title')"
    :description="t('bafo.shortlist.description')"
    :confirm-label="t('bafo.shortlist.confirm', { count: chosen.size }, chosen.size)"
    :busy="busy"
    :error="error"
    @confirm="confirm"
  >
    <div class="flex flex-col gap-4">
      <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-bold text-fg">
          {{ t('bafo.shortlist.participants') }}
        </legend>
        <p
          v-if="candidates.length === 0"
          class="text-sm text-fg-muted"
        >
          {{ t('bafo.shortlist.none') }}
        </p>
        <UiCheckbox
          v-for="row in candidates"
          :key="row.participant.id"
          :model-value="chosen.has(row.participant.id)"
          :label="`${t('offers.participant_alias', { number: row.participant.alias_no })} · ${row.participant.organization.name}`"
          :description="row.rank !== null ? t('bafo.shortlist.rank', { rank: row.rank }) : undefined"
          :error="invalid.includes(row.participant.id) ? t('bafo.shortlist.invalid_row') : null"
          @update:model-value="value => toggle(row.participant.id, value)"
        />
        <p
          v-if="selectionError"
          class="text-sm text-danger"
          role="alert"
        >
          {{ selectionError }}
        </p>
      </fieldset>
      <CompetitionsWizardNumberField
        v-model="duration"
        :label="t('bafo.shortlist.duration')"
        :hint="t('rules.bounds_minutes', { min: B.min, max: B.max })"
        :error="durationError"
        :suffix="t('rules.minutes_suffix')"
      />
    </div>
  </UiConfirmDialog>
</template>
