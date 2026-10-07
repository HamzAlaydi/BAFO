<script setup lang="ts">
import { CreditCard } from '@lucide/vue'
import type { StepItem } from '~/types/ui'
import { issueMessage } from '~/stores/competition-editor-rules'
import { WIZARD_FIELD_IDS, wizardStepKeys } from '~/stores/competition-editor-steps'

/**
 * W12 New competition · `/dashboard/competitions/new` (SCREENS CD3, §2.4; RELEASE_SCOPE.md §2.1): step
 * 1 «النوع والأساسيات» runs in the browser; finishing it sends `POST /competitions` with the type, the
 * basics and the full rules of the standard tier preset, then replaces the URL with the draft's step 2
 * (W15 `setup/rules`).
 *
 * Access: `competitions.create` and `entitlements.can_issue` (S10). Errors: `issuer_plan_required`
 * (plans prompt), `auction_not_enabled`, 422 bound to the fields; a failed submit lists every field
 * problem at the top with a link that focuses the field (FQ8).
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const lookups = useLookupsStore()
const editor = useCompetitionEditorStore()
const localePath = useLocalePath()
const toast = useToast()
const { message } = useErrorMessage()
const clock = useServerTime()

useSeoMeta({ title: () => t('competitions.setup.new_title') })

const canCreate = computed(() => auth.can('competitions.create'))
const canIssue = computed(() => auth.entitlements?.can_issue === true)
const stepKeys = wizardStepKeys()
const steps = computed<StepItem[]>(() => stepKeys.map(key => ({ key, label: t(`competitions.setup.steps.${key}`) })))

const ready = ref(false)
const created = ref(false)
const showAll = ref(false)
const formError = ref<{ message: string, plans?: boolean } | null>(null)
const summaryEl = useTemplateRef<HTMLElement>('summary')

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

/** Field problems to list at the top after a submit attempt: client issues first, then unmatched server errors. */
const fieldSummary = computed(() => {
  if (!showAll.value) return []
  const items = editor.blockingIssues(['type', 'basics'], clock.now()).map(issue => ({ id: WIZARD_FIELD_IDS[issue.field] ?? null, message: issueMessage(issue, t) }))
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

function back(): void {
  void navigateTo(localePath('/dashboard/competitions'))
}

async function next(): Promise<void> {
  formError.value = null
  showAll.value = true
  if (editor.blockingIssues(['type', 'basics'], clock.now()).length > 0) {
    await nextTick()
    summaryEl.value?.focus()
    return
  }
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
    }
    else if (error instanceof ApiError && error.isValidation) {
      const unmatched = Object.keys(error.errors).filter(path => !(path in WIZARD_FIELD_IDS))
      formError.value = { message: unmatched.length > 0 ? error.errors[unmatched[0] ?? '']?.[0] ?? message(error) : t('competitions.setup.fix_fields') }
    }
    else {
      formError.value = { message: message(error) }
    }
    await nextTick()
    summaryEl.value?.focus()
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
      :current="0"
      :title="t('competitions.setup.titles.basics')"
      :description="t('competitions.setup.descriptions.basics')"
      :busy="editor.saving"
      :continue-label="t('competitions.setup.create_draft')"
      :dirty="false"
      @back="back"
      @continue="next"
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
          <p v-if="formError">
            {{ formError.message }}
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
          <NuxtLinkLocale
            v-if="formError?.plans"
            to="/dashboard/billing/plans"
            class="link mt-2 inline-block"
          >
            {{ t('competitions.list.view_plans') }}
          </NuxtLinkLocale>
        </UiAlert>
      </div>
      <CompetitionsWizardStepTypeBasics :show-all="showAll" />
      <template #aside>
        <CompetitionsWizardRulesAside :server-lines="null" />
      </template>
    </CompetitionsWizardShell>
  </div>
</template>
