<script setup lang="ts">
import type { IssuerCompetition } from '~/types/api/competitions'
import type { StepItem } from '~/types/ui'
import type { EditorSection } from '~/stores/competition-editor'
import { isWizardStepKey, wizardStepKeys, type WizardStepKey } from '~/stores/competition-editor-steps'

/**
 * W15 Setup wizard · `/dashboard/competitions/{id}/setup/{step}` (SCREENS CD3, §2.4): the 8 steps of
 * an existing draft (step 7 hidden when fees cannot be covered). Steps 1–4 edit the draft form and
 * save with `PATCH` (the whole `rules` object with `preset_code`); steps 5–7 save through their own
 * endpoints as the issuer works; step 8 publishes.
 *
 * Guards: `viewer_role = issuer`, `status = draft` and `permissions.can_edit`; anything else returns to
 * the overview. An unsaved-changes guard protects the form steps; Continue saves first.
 */
const ctx = useCompetitionContext()
const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const appConfig = useAppConfigStore()
const editor = useCompetitionEditorStore()
const lookups = useLookupsStore()
const localePath = useLocalePath()
const toast = useToast()
const clock = useServerTime()
const { message } = useErrorMessage()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const feesEnabled = computed(() => auth.features?.sponsorship_enabled === true && appConfig.sponsorshipEnabled)
const stepKeys = computed(() => wizardStepKeys(feesEnabled.value))
const step = computed<WizardStepKey | null>(() => {
  const value = String(route.params.step ?? '')
  return isWizardStepKey(value) && stepKeys.value.includes(value) ? value : null
})
const index = computed(() => (step.value ? stepKeys.value.indexOf(step.value) : 0))
const steps = computed<StepItem[]>(() => stepKeys.value.map(key => ({ key, label: t(`competitions.setup.steps.${key}`) })))
const section = computed<EditorSection | null>(() => (step.value && ['type', 'basics', 'rules', 'schedule'].includes(step.value) ? step.value as EditorSection : null))
const base = computed(() => `/dashboard/competitions/${String(route.params.id ?? '')}`)

useSeoMeta({ title: () => (step.value ? t(`competitions.setup.titles.${step.value}`) : t('competitions.setup.new_title')) })

const showAll = ref(false)
const formError = ref<string | null>(null)
const leaving = ref(false)

// ---------- Guards ----------

watch([competition, step], ([current, currentStep]) => {
  if (!current) return
  if (current.status !== 'draft' || !current.permissions.can_edit) {
    void navigateTo(localePath(base.value), { replace: true })
    return
  }
  if (!currentStep) void navigateTo(localePath(`${base.value}/setup`), { replace: true })
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
  editor.clearError()
})

useUnsavedChangesGuard(() => !leaving.value && section.value !== null && editor.isDirty(section.value))

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
  if (!section.value || !editor.isDirty(section.value)) return true
  if (!window.confirm(t('common.unsaved_confirm'))) return false
  editor.reset(section.value)
  return true
}

function back(): void {
  const previous = stepKeys.value[index.value - 1]
  if (!confirmDiscard()) return
  if (previous) void go(previous)
  else void navigateTo(localePath(base.value))
}

function onSelect(target: number): void {
  const key = stepKeys.value[target]
  if (key && confirmDiscard()) void go(key)
}

/** Saves the current form section. Returns false when it could not be saved. */
async function saveSection(): Promise<boolean> {
  if (!section.value) return true
  formError.value = null
  showAll.value = true
  if (editor.blockingIssues(section.value, clock.now()).length > 0) return false
  try {
    const updated = await editor.save(section.value)
    if (updated) ctx.competition.value = updated
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
    return false
  }
}

async function save(): Promise<void> {
  if (await saveSection()) toast.success(t('competitions.setup.saved'))
}

async function next(): Promise<void> {
  if (!(await saveSection())) return
  const following = stepKeys.value[index.value + 1]
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
const showAside = computed(() => step.value === 'type' || step.value === 'rules' || step.value === 'schedule')
</script>

<template>
  <CompetitionsWizardShell
    v-if="competition && step && competition.status === 'draft'"
    :steps="steps"
    :current="index"
    :title="t(`competitions.setup.titles.${step}`)"
    :description="t(`competitions.setup.descriptions.${step}`)"
    :busy="editor.saving"
    :show-save="section !== null"
    :save-disabled="section === null || !editor.isDirty(section)"
    :hide-continue="step === 'review'"
    :dirty="section !== null && editor.isDirty(section)"
    @back="back"
    @save="save"
    @continue="next"
    @select="onSelect"
  >
    <UiAlert
      v-if="formError"
      tone="danger"
    >
      {{ formError }}
    </UiAlert>

    <CompetitionsWizardStepType v-if="step === 'type'" />
    <CompetitionsWizardStepBasics
      v-else-if="step === 'basics'"
      :show-all="showAll"
    />
    <CompetitionsWizardStepRules v-else-if="step === 'rules'" />
    <CompetitionsWizardStepSchedule
      v-else-if="step === 'schedule'"
      :show-all="showAll"
    />
    <CompetitionsWizardStepDocuments
      v-else-if="step === 'documents'"
      :competition="competition"
      @changed="onChanged"
    />
    <CompetitionsWizardStepParticipants
      v-else-if="step === 'participants'"
      :competition="competition"
      @changed="onChanged"
    />
    <CompetitionsWizardStepFees
      v-else-if="step === 'fees'"
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
