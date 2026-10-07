<script setup lang="ts">
import { ChevronDown, Lock } from '@lucide/vue'
import type { MustBeat, RankVisibility, ResultPublication } from '~/types/api/competitions'
import { EDITOR_RULE_BOUNDS, issueMessage, minStepMode, percentTextToBps, showPricesForced, type MinStepMode } from '~/stores/competition-editor-rules'
import { WIZARD_FIELD_IDS } from '~/stores/competition-editor-steps'

/**
 * Wizard step 2 «القواعد» (SCREENS W15 `rules`; RELEASE_SCOPE.md §2.1, §2.4): the preset tier cards
 * on top, the prices card (start price always; the hidden target or reserve price and the granularity
 * only with `advanced_rules`), then the disclosure «إعدادات متقدمة», collapsed by default and
 * remembered per draft in `sessionStorage`: must beat, minimum step, standing visibility and prices,
 * auto-extend with the latest possible close, and the flag-gated blocks (result publication and
 * minimum participants with `advanced_rules`, the final pricing window with `final_pricing_window`,
 * the BAFO round with `bafo_round`).
 *
 * Validation is live (client mirror of R1–R13, feedback only); `rules.*` server errors bind to the
 * same controls. Sealed competitions (existing records) disable every live-only control.
 */
const { t } = useI18n()
const editor = useCompetitionEditorStore()
const features = useFeatures()
const { td } = useDirectionCopy(() => editor.form.direction)
const B = EDITOR_RULE_BOUNDS
const IDS = WIZARD_FIELD_IDS

const rules = computed(() => editor.form.rules)
const sealed = computed(() => editor.form.format === 'sealed')
const advanced = computed(() => features.enabled('advanced_rules'))
const advancedId = useId()

// The disclosure state is remembered per draft for the tab's lifetime (FQ: no surprise on return).
const disclosureKey = computed(() => `bafo:rules-advanced:${editor.competitionId ?? 'new'}`)
const advancedOpen = ref(false)
onMounted(() => {
  try {
    advancedOpen.value = window.sessionStorage.getItem(disclosureKey.value) === '1'
  }
  catch {
    advancedOpen.value = false
  }
})
function toggleAdvanced(): void {
  advancedOpen.value = !advancedOpen.value
  try {
    window.sessionStorage.setItem(disclosureKey.value, advancedOpen.value ? '1' : '0')
  }
  catch {
    // Storage blocked: the disclosure simply starts collapsed next time.
  }
}

function issueFor(field: string, when: 'save' | 'publish' = 'save'): string | null {
  // Text that cannot be read as an amount: the field's own message, not a check on its last valid value.
  if (when === 'save' && editor.unreadable.includes(field)) return t('common.money.invalid')
  const server = editor.serverFieldErrors[field]
  if (server) return server
  const issue = editor.rulesIssues.find(item => item.field === field && item.when === when)
  return issue ? issueMessage(issue, t) : null
}

// ---------- Prices ----------

const startPrice = computed<number | null>({
  get: () => rules.value.start_price_minor,
  set: value => editor.patchRules({ start_price_minor: value }),
})
const reservePrice = computed<number | null>({
  get: () => rules.value.reserve_price_minor,
  set: value => editor.patchRules({ reserve_price_minor: value }),
})
const granularity = computed<'100' | '1' | null>({
  get: () => String(rules.value.amount_granularity_minor) as '100' | '1',
  set: value => editor.patchRules({ amount_granularity_minor: value === '1' ? 1 : 100 }),
})
const granularityOptions = computed(() => [
  { value: '100' as const, label: t('rules.granularity.riyals') },
  { value: '1' as const, label: t('rules.granularity.halalas') },
])

// ---------- Advanced ----------

const mustBeat = computed<MustBeat | null>({
  get: () => rules.value.must_beat,
  set: value => value && editor.patchRules({ must_beat: value }),
})
const mustBeatOptions = computed(() => [
  { value: 'own' as const, label: td('rules.must_beat.own'), description: t('rules.must_beat.own_description') },
  { value: 'best' as const, label: t('rules.must_beat.best'), description: t('rules.must_beat.best_description') },
])

const stepMode = ref<MinStepMode>(minStepMode(rules.value))
const percentText = ref(rules.value.min_step_bps !== null ? formatBps(rules.value.min_step_bps) : '')
const percentInvalid = ref(false)

watch(() => [rules.value.min_step_minor, rules.value.min_step_bps] as const, ([minor, bps]) => {
  if (minor !== null) stepMode.value = 'amount'
  else if (bps !== null) {
    stepMode.value = 'percent'
    if (percentTextToBps(percentText.value) !== bps) percentText.value = formatBps(bps)
  }
  else stepMode.value = 'none'
})

const stepModeModel = computed<MinStepMode | null>({
  get: () => stepMode.value,
  set: (value) => {
    if (!value) return
    stepMode.value = value
    percentInvalid.value = false
    if (value === 'none') editor.patchRules({ min_step_minor: null, min_step_bps: null })
    if (value === 'amount') editor.patchRules({ min_step_bps: null })
    if (value === 'percent') {
      editor.patchRules({ min_step_minor: null, min_step_bps: percentTextToBps(percentText.value) })
    }
  },
})
const stepModeOptions = computed(() => [
  { value: 'none' as const, label: t('rules.min_step.none') },
  { value: 'amount' as const, label: t('rules.min_step.amount') },
  { value: 'percent' as const, label: t('rules.min_step.percent') },
])
const stepAmount = computed<number | null>({
  get: () => rules.value.min_step_minor,
  set: value => editor.patchRules({ min_step_minor: value, min_step_bps: null }),
})

function onPercent(value: string): void {
  percentText.value = value
  const bps = percentTextToBps(value)
  percentInvalid.value = value.trim() !== '' && bps === null
  if (!percentInvalid.value) editor.patchRules({ min_step_bps: bps, min_step_minor: null })
}

const visibility = computed<RankVisibility | null>({
  get: () => rules.value.rank_visibility,
  set: value => value && editor.patchRules({ rank_visibility: value }),
})
const visibilityOptions = computed(() => (['full', 'leading_flag', 'none'] as const).map(value => ({
  value,
  label: t(`rules.visibility.${value}`),
  description: t(`rules.visibility.${value}_description`),
})))

const pricesForced = computed(() => showPricesForced(rules.value))
const showPrices = computed<boolean>({
  get: () => rules.value.show_prices,
  set: value => editor.patchRules({ show_prices: value }),
})
const showPricesDescription = computed(() => {
  if (sealed.value) return t('rules.sealed_unavailable')
  if (rules.value.must_beat === 'best') return t('rules.show_prices.forced_best')
  if (rules.value.result_publication === 'outcome_and_amount') return t('rules.show_prices.forced_result')
  return t('rules.show_prices.description')
})

const resultPublication = computed<ResultPublication | null>({
  get: () => rules.value.result_publication,
  set: value => value && editor.patchRules({ result_publication: value }),
})
const resultOptions = computed(() => (['none', 'outcome_only', 'outcome_and_amount'] as const).map(value => ({
  value,
  label: t(`rules.result.${value}`),
  description: t(`rules.result.${value}_description`),
  disabled: sealed.value && value === 'outcome_and_amount',
})))

const finalWindowOn = computed<boolean>({
  get: () => rules.value.final_window_minutes !== null,
  set: value => editor.patchRules({ final_window_minutes: value ? 60 : null }),
})
const finalWindowMinutes = computed<number | null>({
  get: () => rules.value.final_window_minutes,
  set: value => editor.patchRules({ final_window_minutes: value ?? B.finalWindowMinutes.min }),
})
/** The switch is shown with the flag, or for an existing record that already has a window. */
const showFinalWindow = computed(() => features.enabled('final_pricing_window') || rules.value.final_window_minutes !== null)

const autoExtendOn = computed<boolean>({
  get: () => rules.value.auto_extend.enabled,
  set: value => editor.patchRules({ auto_extend: { ...rules.value.auto_extend, enabled: value } }),
})
function minutesModel(key: 'window_seconds' | 'by_seconds') {
  return computed<number | null>({
    get: () => {
      const seconds = rules.value.auto_extend[key]
      return seconds === null ? null : Math.round(seconds / 60)
    },
    set: value => editor.patchRules({ auto_extend: { ...rules.value.auto_extend, [key]: value === null ? null : value * 60 } }),
  })
}
const autoWindow = minutesModel('window_seconds')
const autoBy = minutesModel('by_seconds')
const autoMax = computed<number | null>({
  get: () => rules.value.auto_extend.max_extensions,
  set: value => editor.patchRules({ auto_extend: { ...rules.value.auto_extend, max_extensions: value } }),
})
const latestClose = computed(() => {
  const close = editor.form.scheduled_close_at ? Date.parse(editor.form.scheduled_close_at) : Number.NaN
  const extend = rules.value.auto_extend
  if (Number.isNaN(close) || !extend.enabled || extend.by_seconds === null || extend.max_extensions === null) return null
  return new Date(close + extend.by_seconds * extend.max_extensions * 1000).toISOString()
})

const bafoOn = computed<boolean>({
  get: () => rules.value.bafo_round.enabled,
  set: value => editor.patchRules({ bafo_round: { ...rules.value.bafo_round, enabled: value } }),
})
const bafoMinutes = computed<number | null>({
  get: () => rules.value.bafo_round.duration_minutes,
  set: value => editor.patchRules({ bafo_round: { enabled: true, duration_minutes: value } }),
})
/** Shown with the flag, or for an existing record that already has a round configured. */
const showBafo = computed(() => features.enabled('bafo_round') || rules.value.bafo_round.enabled)

const minParticipants = computed<number | null>({
  get: () => rules.value.min_participants,
  set: value => editor.patchRules({ min_participants: value ?? 0 }),
})

const startHint = computed(() => issueFor('rules.start_price_minor', 'publish') ?? `${td('rules.start_price.hint')} ${t('rules.price_example')}`)

/** Which advanced controls carry an error, so a collapsed disclosure can say so. */
const advancedErrorCount = computed(() => editor.blockingIssues('rules', Date.now()).filter(issue => !issue.field.endsWith('_price_minor')).length)
</script>

<template>
  <div class="flex flex-col gap-6">
    <CompetitionsWizardPresetTierCards />

    <UiCard :title="t('rules.prices_title')">
      <div class="flex flex-col gap-5">
        <div
          class="grid gap-5"
          :class="advanced && 'md:grid-cols-2'"
        >
          <UiMoneyInput
            :id="IDS['rules.start_price_minor']"
            v-model="startPrice"
            :label="td('rules.start_price.label')"
            :hint="startHint"
            :error="issueFor('rules.start_price_minor')"
            :required="editor.form.direction === 'auction'"
            @unreadable="value => editor.setUnreadable('rules.start_price_minor', value)"
          />
          <UiMoneyInput
            v-if="advanced || reservePrice !== null"
            :id="IDS['rules.reserve_price_minor']"
            v-model="reservePrice"
            :label="td('rules.reserve_price.label')"
            :hint="t('rules.reserve_price.hint')"
            :error="issueFor('rules.reserve_price_minor')"
            @unreadable="value => editor.setUnreadable('rules.reserve_price_minor', value)"
          />
        </div>
        <UiSegmented
          v-if="advanced || rules.amount_granularity_minor !== 100"
          v-model="granularity"
          :options="granularityOptions"
          :label="t('rules.granularity.label')"
          :hint="t('rules.granularity.hint')"
          size="sm"
        />
        <p class="text-sm text-fg-muted">
          {{ t('common.prices_exclude_vat') }}
        </p>
      </div>
    </UiCard>

    <UiCard padding="none">
      <button
        type="button"
        class="flex min-h-14 w-full items-center justify-between gap-3 px-5 py-4 text-start focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-6"
        :aria-expanded="advancedOpen"
        :aria-controls="advancedId"
        @click="toggleAdvanced"
      >
        <span class="flex min-w-0 flex-col gap-0.5">
          <span class="flex flex-wrap items-center gap-2">
            <span class="text-base font-bold text-fg">{{ t('rules.advanced.title') }}</span>
            <UiBadge
              v-if="editor.customised"
              tone="info"
              size="sm"
            >
              {{ t('competitions.setup.type.presets.customised') }}
            </UiBadge>
            <UiBadge
              v-if="!advancedOpen && advancedErrorCount > 0"
              tone="danger"
              size="sm"
            >
              {{ t('rules.advanced.errors_inside', { count: advancedErrorCount }, advancedErrorCount) }}
            </UiBadge>
          </span>
          <span class="text-sm text-fg-muted">{{ t('rules.advanced.hint') }}</span>
        </span>
        <ChevronDown
          :size="20"
          class="shrink-0 text-fg-muted transition-transform"
          :class="advancedOpen && 'rotate-180'"
          aria-hidden="true"
        />
      </button>

      <div
        v-show="advancedOpen"
        :id="advancedId"
        class="flex flex-col gap-7 border-t border-line px-5 py-5 sm:px-6"
      >
        <UiAlert
          v-if="sealed"
          tone="info"
          :icon="Lock"
        >
          {{ t('rules.sealed_note') }}
        </UiAlert>

        <UiRadioGroup
          v-model="mustBeat"
          :options="mustBeatOptions"
          :label="t('rules.must_beat.label')"
          :hint="sealed ? t('rules.sealed_unavailable') : undefined"
          :error="issueFor('rules.must_beat')"
          :disabled="sealed"
        />

        <div class="flex flex-col gap-3">
          <UiSegmented
            v-model="stepModeModel"
            :options="stepModeOptions"
            :label="td('rules.min_step.label')"
            :hint="sealed ? t('rules.sealed_unavailable') : t('rules.min_step.hint')"
            :disabled="sealed"
            size="sm"
          />
          <UiMoneyInput
            v-if="stepMode === 'amount' && !sealed"
            :id="IDS['rules.min_step_minor']"
            v-model="stepAmount"
            :label="t('rules.min_step.amount_label')"
            :hint="t('rules.price_example')"
            :error="issueFor('rules.min_step_minor')"
            @unreadable="value => editor.setUnreadable('rules.min_step_minor', value)"
          />
          <UiInput
            v-if="stepMode === 'percent' && !sealed"
            :id="IDS['rules.min_step_bps']"
            :model-value="percentText"
            :label="t('rules.min_step.percent_label')"
            :hint="t('rules.min_step.percent_hint', { min: '0.01', max: '50' })"
            :error="issueFor('rules.min_step_bps') ?? (percentInvalid ? t('rules.validation.percent_format') : null)"
            inputmode="decimal"
            dir="ltr"
            @update:model-value="onPercent"
          >
            <template #trailing>
              <span class="shrink-0 text-sm text-fg-muted">{{ '%' }}</span>
            </template>
          </UiInput>
        </div>

        <UiRadioGroup
          v-model="visibility"
          :options="visibilityOptions"
          :label="t('rules.visibility.label')"
          :hint="sealed ? t('rules.sealed_unavailable') : undefined"
          :error="issueFor('rules.rank_visibility')"
          :disabled="sealed"
        />

        <UiSwitch
          v-model="showPrices"
          :label="t('rules.show_prices.label')"
          :description="showPricesDescription"
          :disabled="sealed || pricesForced"
        />

        <div class="flex flex-col gap-3">
          <UiSwitch
            v-model="autoExtendOn"
            :label="t('rules.auto_extend.label')"
            :description="sealed ? t('rules.sealed_unavailable') : t('rules.auto_extend.description')"
            :disabled="sealed"
          />
          <template v-if="autoExtendOn && !sealed">
            <div class="grid gap-4 sm:grid-cols-3">
              <CompetitionsWizardNumberField
                :id="IDS['rules.auto_extend.window_seconds']"
                v-model="autoWindow"
                :label="t('rules.auto_extend.window_label')"
                :hint="t('rules.bounds_minutes', { min: 1, max: 30 })"
                :error="issueFor('rules.auto_extend.window_seconds')"
                :suffix="t('rules.minutes_suffix')"
              />
              <CompetitionsWizardNumberField
                :id="IDS['rules.auto_extend.by_seconds']"
                v-model="autoBy"
                :label="t('rules.auto_extend.by_label')"
                :hint="t('rules.bounds_minutes', { min: 1, max: 30 })"
                :error="issueFor('rules.auto_extend.by_seconds')"
                :suffix="t('rules.minutes_suffix')"
              />
              <CompetitionsWizardNumberField
                :id="IDS['rules.auto_extend.max_extensions']"
                v-model="autoMax"
                :label="t('rules.auto_extend.max_label')"
                :hint="t('rules.bounds_count', { min: B.autoExtendMax.min, max: B.autoExtendMax.max })"
                :error="issueFor('rules.auto_extend.max_extensions')"
              />
            </div>
            <p class="text-sm text-fg-muted">
              <template v-if="latestClose">
                {{ t('rules.auto_extend.latest_close') }}
                <UiDateTime
                  :value="latestClose"
                  format="deadline"
                  class="font-semibold text-fg"
                />
              </template>
              <template v-else>
                {{ t('rules.auto_extend.latest_close_pending') }}
              </template>
            </p>
          </template>
        </div>

        <UiRadioGroup
          v-if="advanced"
          v-model="resultPublication"
          :options="resultOptions"
          :label="t('rules.result.label')"
          :hint="t('rules.result.hint')"
          :error="issueFor('rules.result_publication')"
        />

        <div
          v-if="showFinalWindow"
          class="flex flex-col gap-3"
        >
          <UiSwitch
            v-model="finalWindowOn"
            :label="t('rules.final_window.label')"
            :description="sealed ? t('rules.sealed_unavailable') : t('rules.final_window.description')"
            :disabled="sealed"
          />
          <CompetitionsWizardNumberField
            v-if="finalWindowOn && !sealed"
            :id="IDS['rules.final_window_minutes']"
            v-model="finalWindowMinutes"
            :label="t('rules.final_window.minutes_label')"
            :hint="t('rules.bounds_minutes', { min: B.finalWindowMinutes.min, max: B.finalWindowMinutes.max })"
            :error="issueFor('rules.final_window_minutes')"
            :suffix="t('rules.minutes_suffix')"
          />
        </div>

        <div
          v-if="showBafo"
          class="flex flex-col gap-3"
        >
          <UiSwitch
            v-model="bafoOn"
            :label="t('rules.bafo.label')"
            :description="t('rules.bafo.description')"
          />
          <CompetitionsWizardNumberField
            v-if="bafoOn"
            :id="IDS['rules.bafo_round.duration_minutes']"
            v-model="bafoMinutes"
            :label="t('rules.bafo.duration_label')"
            :hint="t('rules.bounds_minutes', { min: B.bafoDurationMinutes.min, max: B.bafoDurationMinutes.max })"
            :error="issueFor('rules.bafo_round.duration_minutes')"
            :suffix="t('rules.minutes_suffix')"
          />
        </div>

        <div
          v-if="advanced"
          class="max-w-xs"
        >
          <CompetitionsWizardNumberField
            :id="IDS['rules.min_participants']"
            v-model="minParticipants"
            :label="t('rules.min_participants.label')"
            :hint="t('rules.min_participants.hint', { min: B.minParticipants.min, max: B.minParticipants.max })"
            :error="issueFor('rules.min_participants')"
          />
        </div>
      </div>
    </UiCard>
  </div>
</template>
