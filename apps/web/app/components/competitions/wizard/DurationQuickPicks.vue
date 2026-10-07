<script setup lang="ts">
import { DURATION_QUICK_PICKS, type DurationQuickPick } from '~/stores/competition-editor-schedule'

/**
 * The duration chips of the schedule step (RELEASE_SCOPE.md §2.3): «ساعة، 3 ساعات، يوم، 3 أيام،
 * أسبوع، مخصص». Native radios styled as chips (arrow keys and RTL order come from the browser); the
 * row wraps on narrow screens and every chip is at least 44 px tall (FQ10).
 */
defineProps<{
  label: string
  hint?: string
  disabled?: boolean
}>()

const model = defineModel<DurationQuickPick>({ default: 'custom' })
const { t } = useI18n()
const name = `quick-pick-${useId()}`
</script>

<template>
  <UiField
    :id="name"
    v-slot="{ describedby }"
    :label="label"
    :hint="hint"
    group
  >
    <div
      class="flex flex-wrap gap-2"
      :aria-describedby="describedby"
    >
      <label
        v-for="pick in DURATION_QUICK_PICKS"
        :key="pick.key"
        class="relative"
      >
        <input
          v-model="model"
          type="radio"
          class="peer sr-only"
          :name="name"
          :value="pick.key"
          :disabled="disabled"
        >
        <span
          class="inline-flex min-h-11 cursor-pointer items-center rounded-full border px-4 text-sm font-semibold transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
          :class="model === pick.key ? 'border-primary bg-primary-soft text-primary-soft-fg' : 'border-line bg-surface text-fg-muted hover:border-line-strong hover:text-fg'"
        >
          {{ t(`competitions.setup.schedule.quick.${pick.key}`) }}
        </span>
      </label>
    </div>
  </UiField>
</template>
