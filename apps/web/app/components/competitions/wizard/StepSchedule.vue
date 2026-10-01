<script setup lang="ts">
/**
 * Wizard step 4 «الجدول الزمني» (SCREENS W15 `schedule`): opens «فور النشر» (null) or at a date and
 * time, closes at a date and time (Riyadh time), with the estimated timeline. R16 hints use the
 * server clock; the server re-checks at save and at publish.
 */
const props = defineProps<{ showAll?: boolean }>()

const { t } = useI18n()
const editor = useCompetitionEditorStore()
const clock = useServerTime()
const nowMs = ref(clock.now())

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

const issues = computed(() => editor.scheduleIssuesAt(nowMs.value))
const nowIso = computed(() => new Date(nowMs.value).toISOString())

function errorFor(field: 'bidding_opens_at' | 'scheduled_close_at'): string | null {
  const server = editor.serverFieldErrors[field]
  if (server) return server
  const issue = issues.value.find(item => item.field === field && item.when === 'save')
  if (issue) return t(issue.key, issue.params ?? {})
  if (!props.showAll) return null
  const publish = issues.value.find(item => item.field === field)
  return publish ? t(publish.key, publish.params ?? {}) : null
}

const publishNotes = computed(() => issues.value.filter(issue => issue.when === 'publish'))
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
        v-model="opensAt"
        :label="t('competitions.setup.schedule.opens_at')"
        :hint="t('common.datetime.hint')"
        :error="errorFor('bidding_opens_at')"
        :min="nowIso"
        required
      />
      <UiDateTimePicker
        v-model="closeAt"
        :label="t('competitions.setup.schedule.close_label')"
        :hint="t('competitions.setup.schedule.close_hint')"
        :error="errorFor('scheduled_close_at')"
        :min="opensAt ?? nowIso"
        required
      />
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
            {{ t(note.key, note.params ?? {}) }}
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
