<script setup lang="ts">
import type { Preset } from '~/types/api/catalog'
import type { Direction, Format } from '~/types/api/competitions'

/**
 * The competition type (SCREENS W12 step 1, W15 `type`; RELEASE_SCOPE.md §2.4): direction with a
 * plain explanation and, when the props allow, the format cards, the BAFO round switch and the preset
 * cards filtered by direction and format. Inside the 5-step wizard it renders at the top of step 1 with
 * `showFormat = flags.sealed_format` and without BAFO or presets (they moved to the rules step); the
 * defaults keep the full component for any other caller.
 *
 * Auction is gated by `me.organization.features.auction_enabled` (S10). On an existing draft,
 * changing direction or format asks first, because the rules return to the chosen preset (prices kept).
 */
withDefaults(defineProps<{
  /** The live / sealed cards (`flags.sealed_format`). */
  showFormat?: boolean
  /** The BAFO round switch (`flags.bafo_round`; the rules step owns it in the 5-step wizard). */
  showBafo?: boolean
  /** The preset cards (the rules step shows the tier cards instead). */
  showPresets?: boolean
}>(), {
  showFormat: true,
  showBafo: true,
  showPresets: true,
})

const { t } = useI18n()
const auth = useAuthStore()
const editor = useCompetitionEditorStore()

const auctionEnabled = computed(() => auth.features?.auction_enabled === true)
const pending = ref<{ direction: Direction, format: Format } | null>(null)
const confirmOpen = ref(false)

const directionOptions = computed(() => (['tender', 'auction'] as const).map(direction => ({
  value: direction,
  title: t(`competitions.direction.${direction}`),
  description: t(`competitions.setup.type.explain.${direction}`),
  lines: [t(`competitions.direction.rule.${direction}`), t(`competitions.setup.type.issuer_role.${direction}`)],
  disabled: direction === 'auction' && !auctionEnabled.value && editor.form.direction !== 'auction',
  disabledReason: t('competitions.setup.type.auction_disabled'),
})))

const formatOptions = computed(() => (['live', 'sealed'] as const).map(format => ({
  value: format,
  title: t(`competitions.format.${format}`),
  description: t(`competitions.setup.type.format_explain.${format}`),
})))

function presetChips(preset: Preset): string[] {
  const rules = preset.rules
  const chips: string[] = []
  if (preset.format === 'sealed') chips.push(t('competitions.setup.type.preset_chips.sealed'))
  else chips.push(t(`competitions.setup.type.preset_chips.must_beat_${rules.must_beat === 'best' ? 'best' : 'own'}`))
  if (preset.format === 'live') chips.push(t(`competitions.setup.type.preset_chips.visibility_${rules.rank_visibility ?? 'leading_flag'}`))
  if (rules.auto_extend?.enabled) chips.push(t('competitions.setup.type.preset_chips.auto_extend'))
  else if (rules.final_window_minutes) chips.push(t('competitions.setup.type.preset_chips.final_window'))
  if (rules.bafo_round?.enabled) chips.push(t('competitions.setup.type.preset_chips.bafo'))
  return chips.slice(0, 3)
}

const presetOptions = computed(() => editor.availablePresets.map(preset => ({
  value: preset.code,
  title: preset.name,
  description: preset.description,
  lines: presetChips(preset),
  badge: preset.code === editor.form.preset_code && editor.customised ? t('competitions.setup.type.presets.customised') : undefined,
})))

function request(direction: Direction, format: Format): void {
  if (direction === editor.form.direction && format === editor.form.format) return
  if (editor.isNew) {
    editor.setType(direction, format)
    return
  }
  pending.value = { direction, format }
  confirmOpen.value = true
}

function confirmChange(): void {
  if (pending.value) editor.setType(pending.value.direction, pending.value.format)
  pending.value = null
  confirmOpen.value = false
}

const direction = computed<Direction | null>({
  get: () => editor.form.direction,
  set: value => value && request(value, editor.form.format),
})

const format = computed<Format | null>({
  get: () => editor.form.format,
  set: value => value && request(editor.form.direction, value),
})

const presetCode = computed<string | null>({
  get: () => editor.form.preset_code,
  set: value => editor.choosePreset(value),
})

const bafoEnabled = computed<boolean>({
  get: () => editor.form.rules.bafo_round.enabled,
  set: value => editor.patchRules({ bafo_round: { enabled: value, duration_minutes: editor.form.rules.bafo_round.duration_minutes } }),
})

watch(confirmOpen, (open) => {
  if (!open) pending.value = null
})
</script>

<template>
  <div class="flex flex-col gap-8">
    <CompetitionsWizardChoiceCards
      v-model="direction"
      :options="directionOptions"
      :legend="t('competitions.setup.type.direction_legend')"
      :hint="t('competitions.setup.type.direction_hint')"
    >
      <template #glyph="{ option }">
        <svg
          viewBox="0 0 24 24"
          class="size-5 shrink-0 text-fg"
          fill="none"
          stroke="currentColor"
          stroke-width="3"
          stroke-linecap="round"
          stroke-linejoin="round"
          aria-hidden="true"
          focusable="false"
        >
          <polyline
            v-if="option.value === 'tender'"
            points="5,8 12,17 19,8"
          />
          <polyline
            v-else
            points="5,16 12,7 19,16"
          />
        </svg>
      </template>
    </CompetitionsWizardChoiceCards>

    <CompetitionsWizardChoiceCards
      v-if="showFormat || editor.form.format === 'sealed'"
      v-model="format"
      :options="formatOptions"
      :legend="t('competitions.setup.type.format_legend')"
      :disabled="!showFormat"
      :hint="showFormat ? undefined : t('competitions.setup.type.format_kept')"
    />

    <UiCard
      v-if="showBafo"
      padding="sm"
    >
      <UiSwitch
        v-model="bafoEnabled"
        :label="t('competitions.setup.type.bafo_label')"
        :description="t('competitions.setup.type.bafo_description')"
      />
    </UiCard>

    <template v-if="showPresets">
      <CompetitionsWizardChoiceCards
        v-if="presetOptions.length > 0"
        v-model="presetCode"
        :options="presetOptions"
        :legend="t('competitions.setup.type.presets.legend')"
        :hint="t('competitions.setup.type.presets.hint')"
        :columns="presetOptions.length > 2 ? 3 : 2"
      />
      <UiAlert
        v-else
        tone="info"
      >
        {{ t('competitions.setup.type.presets.none') }}
      </UiAlert>
    </template>

    <UiConfirmDialog
      v-model:open="confirmOpen"
      :title="t('competitions.setup.type.reset_confirm.title')"
      :description="t('competitions.setup.type.reset_confirm.body')"
      :confirm-label="t('competitions.setup.type.reset_confirm.confirm')"
      @confirm="confirmChange"
    />
  </div>
</template>
