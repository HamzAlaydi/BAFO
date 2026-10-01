<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue'

/**
 * Seats for the custom plan (SCREENS W31): a numeric field with − / + buttons, bounded by the
 * server's `custom.min_seats`–`max_seats`. The model is null while the text is not a whole number.
 */
const props = defineProps<{
  min: number
  max: number
  error?: string | null
  disabled?: boolean
}>()

const model = defineModel<number | null>({ required: true })
const { t } = useI18n()
const id = useId()
const text = ref(model.value === null ? '' : String(model.value))

watch(model, (value) => {
  if (value !== null && parsePositiveInt(text.value) !== value) text.value = String(value)
})

const outOfRange = computed(() => model.value !== null && (model.value < props.min || model.value > props.max))
const shownError = computed(() => {
  if (props.error) return props.error
  if (text.value.trim() !== '' && model.value === null) return t('billing.plans.custom.seats_invalid')
  if (outOfRange.value) return t('billing.plans.custom.seats_range', { min: props.min, max: props.max })
  return null
})

function onInput(event: Event): void {
  text.value = (event.target as HTMLInputElement).value
  model.value = parsePositiveInt(text.value)
}

function step(delta: number): void {
  const current = model.value ?? props.min
  const next = Math.min(props.max, Math.max(props.min, current + delta))
  model.value = next
  text.value = String(next)
}
</script>

<template>
  <UiField
    :id="id"
    v-slot="{ describedby, invalid }"
    :label="t('billing.plans.custom.seats_label')"
    :hint="t('billing.plans.custom.seats_hint', { min, max })"
    :error="shownError"
    required
  >
    <div class="flex items-center gap-2">
      <UiIconButton
        :icon="Minus"
        :label="t('billing.plans.custom.seats_decrease')"
        variant="secondary"
        size="lg"
        :disabled="disabled || (model !== null && model <= min)"
        @click="step(-1)"
      />
      <input
        :id="id"
        :value="text"
        type="text"
        inputmode="numeric"
        dir="ltr"
        autocomplete="off"
        class="h-11 w-24 rounded-md border border-line-strong bg-surface px-3 text-center font-semibold text-fg tabular-nums focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring aria-invalid:border-danger"
        :aria-describedby="describedby"
        :aria-invalid="invalid || undefined"
        :disabled="disabled"
        required
        @input="onInput"
      >
      <UiIconButton
        :icon="Plus"
        :label="t('billing.plans.custom.seats_increase')"
        variant="secondary"
        size="lg"
        :disabled="disabled || (model !== null && model >= max)"
        @click="step(1)"
      />
    </div>
  </UiField>
</template>
