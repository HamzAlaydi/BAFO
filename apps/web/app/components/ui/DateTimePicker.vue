<script setup lang="ts">
import { CalendarClock } from '@lucide/vue'

/**
 * Native `datetime-local` wrapper. The model is a UTC ISO-8601 string (the API format); the input
 * shows and edits wall-clock time in Riyadh, and the formatted deadline is echoed underneath.
 */
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = defineProps<{
  label?: string
  hint?: string
  error?: string | null
  id?: string
  required?: boolean
  disabled?: boolean
  /** UTC ISO bounds. */
  min?: string
  max?: string
}>()

const model = defineModel<string | null>({ default: null })
const { t } = useI18n()
const { formatDeadline, timeZone } = useDate()
const autoId = useId()
const inputId = computed(() => props.id ?? `datetime-${autoId}`)

const localValue = computed(() => (model.value ? utcIsoToZonedInput(model.value, timeZone) : ''))
const minValue = computed(() => (props.min ? utcIsoToZonedInput(props.min, timeZone) : undefined))
const maxValue = computed(() => (props.max ? utcIsoToZonedInput(props.max, timeZone) : undefined))
const preview = computed(() => (model.value ? formatDeadline(model.value) : null))

function onInput(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  model.value = value ? zonedInputToUtcIso(value, timeZone) : null
}
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint ?? t('common.datetime.hint')"
    :error="error"
    :required="required"
  >
    <div
      class="flex h-11 items-center gap-2 rounded-md border bg-surface px-3 focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
      :class="[invalid ? 'border-danger' : 'border-line-strong', disabled && 'bg-surface-muted opacity-70']"
    >
      <CalendarClock
        :size="18"
        class="shrink-0 text-fg-muted"
        aria-hidden="true"
      />
      <input
        :id="inputId"
        v-bind="controlAttrs()"
        type="datetime-local"
        :value="localValue"
        :min="minValue"
        :max="maxValue"
        :required="required"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy(describedby, preview ? `${inputId}-preview` : undefined)"
        dir="ltr"
        class="h-full min-w-0 flex-1 bg-transparent text-start text-fg tabular-nums outline-none focus-visible:outline-none"
        @change="onInput"
      >
    </div>
    <p
      v-if="preview"
      :id="`${inputId}-preview`"
      class="text-sm text-fg-muted"
    >
      {{ preview }}
    </p>
  </UiField>
</template>
