<script setup lang="ts">
import { CreditCard } from '@lucide/vue'
import type { StepItem } from '~/types/ui'
import { wizardStepKeys, type WizardStepKey } from '~/stores/competition-editor-steps'

/**
 * W12 New competition · `/dashboard/competitions/new` (SCREENS CD3, §2.4): steps 1 (type) and 2
 * (basics) run in the browser; finishing step 2 sends `POST /competitions` with the basics, the type
 * and the full preset rules, then replaces the URL with the draft's step 3 (W15 `setup/rules`).
 *
 * Access: `competitions.create` and `entitlements.can_issue` (S10). Errors: `issuer_plan_required`
 * (plans prompt), `auction_not_enabled`, 422 bound to the fields.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const appConfig = useAppConfigStore()
const lookups = useLookupsStore()
const editor = useCompetitionEditorStore()
const localePath = useLocalePath()
const toast = useToast()
const { message } = useErrorMessage()
const clock = useServerTime()

useSeoMeta({ title: () => t('competitions.setup.new_title') })

const canCreate = computed(() => auth.can('competitions.create'))
const canIssue = computed(() => auth.entitlements?.can_issue === true)
const feesEnabled = computed(() => auth.features?.sponsorship_enabled === true && appConfig.sponsorshipEnabled)
const stepKeys = computed(() => wizardStepKeys(feesEnabled.value))
const steps = computed<StepItem[]>(() => stepKeys.value.map(key => ({ key, label: t(`competitions.setup.steps.${key}`) })))

const current = ref<WizardStepKey>('type')
const index = computed(() => Math.max(0, stepKeys.value.indexOf(current.value)))
const ready = ref(false)
const created = ref(false)
const showAll = ref(false)
const formError = ref<{ message: string, plans?: boolean } | null>(null)

onMounted(async () => {
  try {
    await lookups.ensureLoaded()
  }
  catch {
    // The steps show the lookups error; presets may be empty.
  }
  editor.startNew()
  ready.value = true
})

useUnsavedChangesGuard(() => ready.value && !created.value && editor.dirty)

function onSelect(target: number): void {
  if (stepKeys.value[target] === 'type') current.value = 'type'
}

function back(): void {
  if (current.value === 'basics') {
    current.value = 'type'
    return
  }
  void navigateTo(localePath('/dashboard/competitions'))
}

async function next(): Promise<void> {
  formError.value = null
  if (current.value === 'type') {
    current.value = 'basics'
    return
  }
  showAll.value = true
  if (editor.blockingIssues('basics', clock.now()).length > 0) return
  try {
    const competition = await editor.create()
    created.value = true
    toast.success(t('competitions.setup.created'))
    await navigateTo(localePath(`/dashboard/competitions/${competition.id}/setup/rules`), { replace: true })
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'issuer_plan_required') {
      formError.value = { message: message(error), plans: true }
    }
    else if (error instanceof ApiError && error.code === 'auction_not_enabled') {
      formError.value = { message: message(error) }
      current.value = 'type'
    }
    else if (error instanceof ApiError && error.isValidation) {
      const unmatched = Object.keys(error.errors).filter(path => !['title', 'description', 'category_id', 'category_other_text', 'region_id'].includes(path))
      if (unmatched.length > 0) formError.value = { message: error.errors[unmatched[0] ?? '']?.[0] ?? message(error) }
    }
    else {
      formError.value = { message: message(error) }
    }
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('competitions.setup.new_title')"
      :description="t('competitions.setup.new_subtitle')"
    />

    <UiForbiddenState v-if="!canCreate" />

    <UiCard v-else-if="!canIssue">
      <UiEmptyState
        :title="t('competitions.list.plan_required_title')"
        :description="t('nav.create_competition_requires_plan')"
        :icon="CreditCard"
      >
        <UiButton
          to="/dashboard/billing/plans"
        >
          {{ t('competitions.list.view_plans') }}
        </UiButton>
      </UiEmptyState>
    </UiCard>

    <div
      v-else-if="!ready"
      class="flex flex-col gap-4"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-16 w-full" />
      <UiSkeleton class="h-64 w-full" />
    </div>

    <CompetitionsWizardShell
      v-else
      :steps="steps"
      :current="index"
      :title="t(`competitions.setup.titles.${current}`)"
      :description="t(`competitions.setup.descriptions.${current}`)"
      :busy="editor.saving"
      :continue-label="current === 'basics' ? t('competitions.setup.create_draft') : undefined"
      :dirty="false"
      @back="back"
      @continue="next"
      @select="onSelect"
    >
      <UiAlert
        v-if="formError"
        tone="danger"
      >
        <p>{{ formError.message }}</p>
        <NuxtLinkLocale
          v-if="formError.plans"
          to="/dashboard/billing/plans"
          class="link mt-2 inline-block"
        >
          {{ t('competitions.list.view_plans') }}
        </NuxtLinkLocale>
      </UiAlert>
      <CompetitionsWizardStepType v-if="current === 'type'" />
      <CompetitionsWizardStepBasics
        v-else
        :show-all="showAll"
      />
      <template #aside>
        <CompetitionsWizardRulesAside :server-lines="null" />
      </template>
    </CompetitionsWizardShell>
  </div>
</template>
