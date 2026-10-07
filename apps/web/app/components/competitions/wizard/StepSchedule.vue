<script setup lang="ts">
import { activeQuickPick, EDITOR_SCHEDULE_BOUNDS, quickPickCloseAt, quickPickOpensMs, type DurationQuickPick } from '~/stores/competition-editor-schedule'
import { issueMessage } from '~/stores/competition-editor-rules'
import { WIZARD_FIELD_IDS } from '~/stores/competition-editor-steps'

/**
 * Wizard step 3 «الجدول الزمني» (SCREENS W15 `schedule`; RELEASE_SCOPE.md §2.3): opens «فور النشر»
 * (null) or at a date and time; the duration quick picks («ساعة … أسبوع، مخصص») set the close from the
 * opening time (or from the server time rounded up to 5 minutes, «يُحتسب من لحظة النشر»); the close
 * picker shows for «مخصص» and as a read-only summary otherwise; a relative hint says when it closes
 * («يُغلق بعد 3 أيام: …»); the estimated timeline explains the joining deadline. Messages state the
 * value and the bound; a close time in the past blocks saving (R16 hints use the server clock).
 */
const props = defineProps<{ showAll?: boolean }>()

const { t } = useI18n()
const editor = useCompetitionEditorStore()
const date = useDate()
const clock = useServerTime()
const nowMs = ref(clock.now())
const IDS = WIZARD_FIELD_IDS

useIntervalFn(() => {
  nowMs.value = clock.now()
}, 30_000)

const opensMode = ref<'publish' | 'at'>(editor.form.bidding_opens_at ? 'at' : 'publish')

watch(() => editor.form.bidding_opens_at, (value) => {
  if (value) opensMode.value = 'at'
})

const opensModel = computed<'publish' | 'at' | null>({
  get: () => opensMode.value,
  set: (value) => {
    if (!value) return
    opensMode.value = value
    if (value === 'publish') editor.update('bidding_opens_at', null)
  },
})

const opensOptions = computed(() => [
  { value: 'publish' as const, label: t('competitions.setup.schedule.opens_on_publish'), description: t('competitions.setup.schedule.opens_on_publish_hint') },
  { value: 'at' as const, label: t('competitions.setup.schedule.opens_at'), description: t('competitions.setup.schedule.opens_at_hint') },
])

const opensAt = computed<string | null>({ get: () => editor.form.bidding_opens_at, set: value => editor.update('bidding_opens_at', value) })
const closeAt = computed<string | null>({ get: () => editor.form.scheduled_close_at, set: value => editor.update('scheduled_close_at', value) })

// ---------- Quick picks ----------

/** The chip that matches the current close; `custom` opens the picker. A chosen chip is kept until the close changes. */
const pick = computed<DurationQuickPick>({
  get: () => (customChosen.value ? 'custom' : activeQuickPick(opensAt.value, closeAt.value, nowMs.value)),
  set: (value) => {
    customChosen.value = value === 'custom'
    if (value !== 'custom') closeAt.value = quickPickCloseAt(opensAt.value, value, nowMs.value)
  },
})
const customChosen = ref(activeQuickPick(editor.form.bidding_opens_at, editor.form.scheduled_close_at, nowMs.value) === 'custom' && editor.form.scheduled_close_at !== null)

// Changing the opening time while a pick is selected keeps the chosen duration.
watch(opensAt, (value, previous) => {
  if (value === previous) return
  const current = activeQuickPick(previous, closeAt.value, nowMs.value)
  if (current !== 'custom' && !customChosen.value) closeAt.value = quickPickCloseAt(value, current, nowMs.value)
})

const showClosePicker = computed(() => pick.value === 'custom' || closeAt.value === null)
const quickHint = computed(() => (opensAt.value === null ? t('competitions.setup.schedule.quick.computed_from_publish') : t('competitions.setup.schedule.quick.computed_from_opens')))

// ---------- Validation ----------

const issues = computed(() => editor.scheduleIssuesAt(nowMs.value))
const nowIso = computed(() => new Date(nowMs.value).toISOString())

function errorFor(field: 'bidding_opens_at' | 'scheduled_close_at'): string | null {
  const server = editor.serverFieldErrors[field]
  if (server) return server
  const issue = issues.value.find(item => item.field === field && item.when === 'save')
  if (issue) return issueMessage(issue, t)
  if (!props.showAll) return null
  const publish = issues.value.find(item => item.field === field)
  return publish ? issueMessage(publish, t) : null
}

const publishNotes = computed(() => issues.value.filter(issue => issue.when === 'publish'))

// ---------- Relative close hint ----------

const relativeClose = computed(() => {
  const close = closeAt.value ? Date.parse(closeAt.value) : Number.NaN
  if (Number.isNaN(close)) return null
  const from = quickPickOpensMs(opensAt.value, nowMs.value)
  const relative = relativeDuration(close - from)
  const phrase = t(`common.relative.in_${relative.unit}`, { count: relative.value }, relative.value)
  return t('competitions.setup.schedule.relative_close', { relative: phrase, deadline: date.formatDeadline(close) })
})

const cutoffMinutes = EDITOR_SCHEDULE_BOUNDS.inviteCutoffMinutes
</script>

<template>
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="flex flex-col gap-6">
      <UiRadioGroup
        v-model="opensModel"
        :options="opensOptions"
        :label="t('competitions.setup.schedule.opens_label')"
      />
      <UiDateTimePicker
        v-if="opensMode === 'at'"
        :id="IDS.bidding_opens_at"
        v-model="opensAt"
        :label="t('competitions.setup.schedule.opens_at')"
        :hint="t('competitions.setup.schedule.opens_at_field_hint')"
        :error="errorFor('bidding_opens_at')"
        :min="nowIso"
        required
      />

      <CompetitionsWizardDurationQuickPicks
        v-model="pick"
        :label="t('competitions.setup.schedule.quick.legend')"
        :hint="quickHint"
      />

      <UiDateTimePicker
        v-if="showClosePicker"
        :id="IDS.scheduled_close_at"
        v-model="closeAt"
        :label="t('competitions.setup.schedule.close_label')"
        :hint="t('competitions.setup.schedule.close_hint')"
        :error="errorFor('scheduled_close_at')"
        :min="opensAt ?? nowIso"
        required
      />
      <div
        v-else
        class="flex flex-col gap-1.5"
      >
        <p class="text-sm font-semibold text-fg">
          {{ t('competitions.setup.schedule.close_label') }}
        </p>
        <p
          :id="IDS.scheduled_close_at"
          class="rounded-md border border-line bg-surface-muted px-3 py-2.5 text-fg"
          tabindex="-1"
        >
          <UiDateTime
            v-if="closeAt"
            :value="closeAt"
            format="deadline"
            class="font-semibold"
          />
        </p>
        <p
          v-if="errorFor('scheduled_close_at')"
          class="text-sm font-medium text-danger"
          role="alert"
        >
          {{ errorFor('scheduled_close_at') }}
        </p>
        <p
          v-else
          class="text-sm text-fg-muted"
        >
          {{ t('competitions.setup.schedule.close_hint') }}
        </p>
      </div>

      <p
        v-if="relativeClose && !errorFor('scheduled_close_at')"
        class="text-sm text-fg"
        role="status"
      >
        {{ relativeClose }}
      </p>

      <UiAlert
        tone="info"
        :title="t('competitions.setup.schedule.join_deadline_title')"
      >
        {{ t('competitions.setup.schedule.join_deadline_hint', { minutes: cutoffMinutes }) }}
      </UiAlert>

      <UiAlert
        v-if="publishNotes.length > 0"
        tone="warning"
        :title="t('competitions.setup.schedule.publish_notes')"
      >
        <ul class="list-inside list-disc">
          <li
            v-for="note in publishNotes"
            :key="note.key"
          >
            {{ issueMessage(note, t) }}
          </li>
        </ul>
      </UiAlert>
    </div>
    <UiCard padding="sm">
      <CompetitionsWizardSchedulePreview
        :bidding-opens-at="editor.form.bidding_opens_at"
        :scheduled-close-at="editor.form.scheduled_close_at"
        :rules="editor.form.rules"
        :now-ms="nowMs"
      />
    </UiCard>
  </div>
</template>
