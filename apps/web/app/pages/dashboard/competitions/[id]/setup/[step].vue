<script setup lang="ts">
import type { IssuerCompetition } from '~/types/api/competitions'
import type { StepItem } from '~/types/ui'
import type { EditorSection } from '~/stores/competition-editor'
import type { WizardAutosaveState } from '~/components/competitions/wizard/Shell.vue'
import { issueMessage } from '~/stores/competition-editor-rules'
import { resolveWizardStep, WIZARD_FIELD_IDS, wizardStepKeys, type WizardStepKey } from '~/stores/competition-editor-steps'

/**
 * W15 Setup wizard · `/dashboard/competitions/{id}/setup/{step}` (SCREENS CD3, §2.4; RELEASE_SCOPE.md
 * §2.1): the 5 steps of an existing draft. Steps 1–3 edit the draft form and save with `PATCH` (the
 * whole `rules` object with `preset_code`) on Continue, on Save, and automatically 1.5 s after the last
 * change when the step has no blocking issue (FQ6); step 4 saves through its own endpoints as the
 * issuer works; step 5 publishes. Legacy step keys (`type`, `documents`, `fees`) redirect to the step
 * that holds their content now.
 *
 * Guards: `viewer_role = issuer`, `status = draft` and `permissions.can_edit`; anything else returns to
 * the overview. An unsaved-changes guard protects the form steps; Continue saves first. A failed save
 * lists every field problem at the top with a link that focuses the field (FQ8).
 */
const ctx = useCompetitionContext()
const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const editor = useCompetitionEditorStore()
const lookups = useLookupsStore()
const localePath = useLocalePath()
const toast = useToast()
const clock = useServerTime()
const { message } = useErrorMessage()

const AUTOSAVE_DELAY_MS = 1500

/** The editor sections each form step saves (step 1 saves the type and the basics together). */
const STEP_SECTIONS: Partial<Record<WizardStepKey, EditorSection[]>> = {
  basics: ['type', 'basics'],
  rules: ['rules'],
  schedule: ['schedule'],
}

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const feesEnabled = computed(() => features.enabled('sponsorship') && auth.features?.sponsorship_enabled === true)
const stepKeys = wizardStepKeys()
const resolved = computed(() => resolveWizardStep(String(route.params.step ?? '')))
const step = computed<WizardStepKey | null>(() => resolved.value?.step ?? null)
const index = computed(() => (step.value ? stepKeys.indexOf(step.value) : 0))
const steps = computed<StepItem[]>(() => stepKeys.map(key => ({ key, label: t(`competitions.setup.steps.${key}`) })))
const sections = computed<EditorSection[] | null>(() => (step.value ? STEP_SECTIONS[step.value] ?? null : null))
const base = computed(() => `/dashboard/competitions/${String(route.params.id ?? '')}`)

useSeoMeta({ title: () => (step.value ? t(`competitions.setup.titles.${step.value}`) : t('competitions.setup.new_title')) })

const showAll = ref(false)
const formError = ref<string | null>(null)
const leaving = ref(false)
const autosave = ref<WizardAutosaveState>('idle')
const savedAt = ref<string | null>(null)
const summaryEl = useTemplateRef<HTMLElement>('summary')

// ---------- Guards ----------

watch([competition, resolved], ([current, target]) => {
  if (!current) return
  if (current.status !== 'draft' || !current.permissions.can_edit) {
    void navigateTo(localePath(base.value), { replace: true })
    return
  }
  if (!target) {
    void navigateTo(localePath(`${base.value}/setup`), { replace: true })
    return
  }
  // Old 8-step links keep working: `/setup/type` → basics, `/setup/documents` and `/setup/fees` → participants.
  if (target.alias) void navigateTo(localePath(`${base.value}/setup/${target.step}`), { replace: true })
}, { immediate: true })

// Load the draft into the form once, and again after server changes when nothing is unsaved.
watch(competition, (current) => {
  if (!current || current.status !== 'draft') return
  if (editor.competitionId !== current.id || !editor.dirty) editor.load(current)
}, { immediate: true })

onMounted(() => {
  void lookups.ensureLoaded().catch(() => {})
})

watch(step, () => {
  showAll.value = false
  formError.value = null
  autosave.value = 'idle'
  editor.clearError()
})

useUnsavedChangesGuard(() => !leaving.value && sections.value !== null && editor.isDirty(sections.value))

// ---------- Autosave (FQ6) ----------

const dirty = computed(() => sections.value !== null && editor.isDirty(sections.value))

const autosaveSoon = useDebounceFn(async () => {
  const current = sections.value
  if (!current || !editor.isDirty(current) || editor.saving) return
  if (editor.blockingIssues(current, clock.now()).length > 0) return
  autosave.value = 'saving'
  try {
    const updated = await editor.save(current)
    if (updated) ctx.competition.value = updated
    savedAt.value = new Date(clock.now()).toISOString()
    autosave.value = 'saved'
  }
  catch {
    // The field errors bind through `serverFieldErrors`; the footer says the autosave failed.
    autosave.value = 'failed'
  }
}, AUTOSAVE_DELAY_MS)

watch(() => editor.form, () => {
  if (dirty.value) void autosaveSoon()
}, { deep: true })

// ---------- Error summary (FQ8) ----------

const fieldSummary = computed(() => {
  if (!showAll.value || !sections.value) return []
  const items = editor.blockingIssues(sections.value, clock.now()).map(issue => ({ id: WIZARD_FIELD_IDS[issue.field] ?? null, message: issueMessage(issue, t) }))
  for (const [path, text] of Object.entries(editor.serverFieldErrors)) {
    if (!items.some(item => item.id === WIZARD_FIELD_IDS[path])) items.push({ id: WIZARD_FIELD_IDS[path] ?? null, message: text })
  }
  return items
})

function focusField(id: string | null): void {
  if (!id) return
  const element = document.getElementById(id)
  element?.scrollIntoView({ block: 'center' })
  element?.focus()
}

// ---------- Navigation ----------

async function go(target: WizardStepKey): Promise<void> {
  leaving.value = true
  try {
    await navigateTo(localePath(`${base.value}/setup/${target}`))
  }
  finally {
    leaving.value = false
  }
}

/** Leaving a form step with unsaved edits asks first; confirming discards them. */
function confirmDiscard(): boolean {
  if (!sections.value || !editor.isDirty(sections.value)) return true
  if (!window.confirm(t('common.unsaved_confirm'))) return false
  editor.reset(sections.value)
  return true
}

function back(): void {
  const previous = stepKeys[index.value - 1]
  if (!confirmDiscard()) return
  if (previous) void go(previous)
  else void navigateTo(localePath(base.value))
}

function onSelect(target: number): void {
  const key = stepKeys[target]
  if (key && confirmDiscard()) void go(key)
}

/** Saves the current form sections. Returns false when they could not be saved. */
async function saveSections(): Promise<boolean> {
  if (!sections.value) return true
  formError.value = null
  showAll.value = true
  if (editor.blockingIssues(sections.value, clock.now()).length > 0) {
    await nextTick()
    summaryEl.value?.focus()
    return false
  }
  try {
    const updated = await editor.save(sections.value)
    if (updated) {
      ctx.competition.value = updated
      savedAt.value = new Date(clock.now()).toISOString()
      autosave.value = 'saved'
    }
    return true
  }
  catch (error) {
    if (error instanceof ApiError && error.isValidation) {
      formError.value = t('competitions.setup.fix_fields')
    }
    else {
      formError.value = message(error)
      if (error instanceof ApiError && error.code === 'competition_not_editable') void ctx.refetch()
    }
    await nextTick()
    summaryEl.value?.focus()
    return false
  }
}

async function save(): Promise<void> {
  if (await saveSections()) toast.success(t('competitions.setup.saved'))
}

async function next(): Promise<void> {
  if (!(await saveSections())) return
  const following = stepKeys[index.value + 1]
  if (following) await go(following)
}

function onChanged(): void {
  void ctx.refetch()
}

function onPublished(updated: IssuerCompetition): void {
  ctx.competition.value = updated
  toast.success(t('competitions.setup.published'))
  void ctx.resync()
  void navigateTo(localePath(base.value))
}

const serverLines = computed(() => competition.value?.rules_summary ?? null)
const showAside = computed(() => step.value === 'basics' || step.value === 'rules' || step.value === 'schedule')
</script>

<template>
  <CompetitionsWizardShell
    v-if="competition && step && !resolved?.alias && competition.status === 'draft'"
    :steps="steps"
    :current="index"
    :title="t(`competitions.setup.titles.${step}`)"
    :description="t(`competitions.setup.descriptions.${step}`)"
    :busy="editor.saving"
    :show-save="sections !== null"
    :save-disabled="!dirty"
    :hide-continue="step === 'review'"
    :dirty="dirty"
    :autosave="autosave"
    :saved-at="savedAt"
    @back="back"
    @save="save"
    @continue="next"
    @select="onSelect"
  >
    <div
      v-if="formError || fieldSummary.length > 0"
      ref="summary"
      tabindex="-1"
      class="outline-none"
    >
      <UiAlert
        tone="danger"
        :title="fieldSummary.length > 0 ? t('competitions.setup.error_summary.title') : undefined"
      >
        <p v-if="formError && fieldSummary.length === 0">
          {{ formError }}
        </p>
        <ul
          v-if="fieldSummary.length > 0"
          class="mt-1 flex flex-col gap-1"
        >
          <li
            v-for="item in fieldSummary"
            :key="`${item.id}-${item.message}`"
          >
            <a
              v-if="item.id"
              :href="`#${item.id}`"
              class="link"
              @click.prevent="focusField(item.id)"
            >{{ item.message }}</a>
            <span v-else>{{ item.message }}</span>
          </li>
        </ul>
      </UiAlert>
    </div>

    <CompetitionsWizardStepTypeBasics
      v-if="step === 'basics'"
      :show-all="showAll"
    />
    <CompetitionsWizardStepRules v-else-if="step === 'rules'" />
    <CompetitionsWizardStepSchedule
      v-else-if="step === 'schedule'"
      :show-all="showAll"
    />
    <CompetitionsWizardStepParticipantsDocs
      v-else-if="step === 'participants'"
      :competition="competition"
      @changed="onChanged"
    />
    <CompetitionsWizardStepReview
      v-else-if="step === 'review'"
      :competition="competition"
      :fees-enabled="feesEnabled"
      @published="onPublished"
    />

    <template
      v-if="showAside"
      #aside
    >
      <CompetitionsWizardRulesAside :server-lines="serverLines" />
    </template>
  </CompetitionsWizardShell>
  <div
    v-else
    class="flex flex-col gap-4"
    :aria-label="t('common.loading')"
  >
    <UiSkeleton class="h-16 w-full" />
    <UiSkeleton class="h-64 w-full" />
  </div>
</template>
