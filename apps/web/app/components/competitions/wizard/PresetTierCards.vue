<script setup lang="ts">
import { RotateCcw } from '@lucide/vue'
import type { Preset } from '~/types/api/catalog'

/**
 * The three preset tier cards of the rules step (RELEASE_SCOPE.md §2.2): simple, standard and
 * maximum protection, each with the name and one-sentence description the server sends with
 * `GET /lookups` (the admin can edit both; the local tier label is only a fallback for an empty
 * name). Choosing a card replaces the rules (prices kept); editing a control afterwards keeps the
 * card selected with the «مخصّص» badge and offers «إعادة الضبط إلى {tier}».
 *
 * With `advanced_rules`, the untiered reference presets appear below as «قوالب أخرى» (a select, not
 * cards). On a catalogue without tiers (older servers), every preset of the combination is shown as a
 * card so the step still works.
 */
const { t } = useI18n()
const editor = useCompetitionEditorStore()
const features = useFeatures()

const tiered = computed(() => editor.tieredPresets)
/** Fallback for a catalogue without tiers: every preset of the direction and format as a card. */
const cards = computed<Preset[]>(() => (tiered.value.length > 0 ? tiered.value : editor.availablePresets))
const others = computed(() => (tiered.value.length > 0 && features.enabled('advanced_rules') ? editor.otherPresets : []))

/** Card title: the preset's server name (admin-editable, §2.2), else the tier's own label. */
const tierLabel = (preset: Pick<Preset, 'name' | 'tier'> | null | undefined): string => {
  const name = preset?.name?.trim()
  if (name) return name
  return preset?.tier ? t(`rules.tiers.names.${preset.tier}`) : ''
}

const options = computed(() => cards.value.map(preset => ({
  value: preset.code,
  title: tierLabel(preset),
  description: preset.description,
  badge: preset.code === editor.form.preset_code && editor.customised ? t('competitions.setup.type.presets.customised') : undefined,
})))

const otherOptions = computed(() => [
  ...others.value.map(preset => ({ value: preset.code, label: preset.name })),
])

const selected = computed<string | null>({
  get: () => editor.form.preset_code,
  set: value => value && editor.choosePreset(value),
})

/** The select shows the chosen untiered preset, or nothing while a tier card is selected. */
const otherSelected = computed<string | null>({
  get: () => (others.value.some(preset => preset.code === editor.form.preset_code) ? editor.form.preset_code : null),
  set: value => value && editor.choosePreset(value),
})

const selectedPreset = computed(() => editor.preset)
const resetLabel = computed(() => t('rules.tiers.reset_to', { tier: tierLabel(selectedPreset.value) }))

function resetToPreset(): void {
  if (editor.form.preset_code) editor.choosePreset(editor.form.preset_code)
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <CompetitionsWizardChoiceCards
      v-if="options.length > 0"
      v-model="selected"
      :options="options"
      :legend="t('rules.tiers.legend')"
      :hint="t('rules.tiers.hint')"
      :columns="options.length > 2 ? 3 : 2"
    />
    <UiAlert
      v-else
      tone="info"
    >
      {{ t('rules.tiers.none') }}
    </UiAlert>

    <div
      v-if="editor.customised && selectedPreset"
      class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-surface-muted px-4 py-3"
    >
      <p class="text-sm text-fg-muted">
        {{ t('rules.tiers.customised_note') }}
      </p>
      <UiButton
        variant="secondary"
        size="sm"
        :icon="RotateCcw"
        flip-icons
        @click="resetToPreset"
      >
        {{ resetLabel }}
      </UiButton>
    </div>

    <UiSelect
      v-if="otherOptions.length > 0"
      v-model="otherSelected"
      :options="otherOptions"
      :label="t('rules.tiers.other_presets')"
      :hint="t('rules.tiers.other_presets_hint')"
      :placeholder="t('common.select_placeholder')"
      class="max-w-md"
    />
  </div>
</template>
