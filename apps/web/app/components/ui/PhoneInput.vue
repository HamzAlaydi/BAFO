<script setup lang="ts">
/**
 * Saudi mobile input (SCREENS S6): a fixed `+966` prefix, 9 digits starting with 5, numeric keypad,
 * LTR. The model is E.164 (`+9665XXXXXXXX`) or null while incomplete. Pasting any local format
 * (05…, 9665…, +9665…, Arabic-Indic digits) is normalised.
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
  readonly?: boolean
}>()

const model = defineModel<string | null>({ default: null })
// Codes, not copy: the country code and the digit pattern read the same in every language.
const COUNTRY_CODE = '+966'
const PATTERN_HINT = '5XXXXXXXX'
const autoId = useId()
const inputId = computed(() => props.id ?? `phone-${autoId}`)
const digits = ref(nationalMobileDigits(model.value))

watch(model, (value) => {
  const national = nationalMobileDigits(value)
  if (national !== digits.value && (value !== null || digits.value.length === 9)) digits.value = national
})

function onInput(event: Event): void {
  const raw = (event.target as HTMLInputElement).value
  const e164 = toSaudiE164(raw)
  const cleaned = e164 ? (nationalMobileDigits(e164)) : normalizeDigits(raw).replace(/\D/g, '').slice(0, 9)
  digits.value = cleaned
  ;(event.target as HTMLInputElement).value = cleaned
  model.value = cleaned.length === 9 ? toSaudiE164(cleaned) : null
}
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
  >
    <div
      dir="ltr"
      class="flex h-11 items-center rounded-md border bg-surface transition-colors focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
      :class="[invalid ? 'border-danger' : 'border-line-strong', (disabled || readonly) && 'bg-surface-muted']"
    >
      <span
        class="flex h-full items-center border-e border-line px-3 text-sm font-semibold text-fg-muted tabular-nums"
        aria-hidden="true"
      >{{ COUNTRY_CODE }}</span>
      <input
        :id="inputId"
        v-bind="controlAttrs()"
        :value="digits"
        type="tel"
        inputmode="numeric"
        autocomplete="tel-national"
        maxlength="16"
        :placeholder="PATTERN_HINT"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedby"
        class="h-full min-w-0 flex-1 bg-transparent px-3 text-start text-fg tabular-nums outline-none placeholder:text-fg-muted focus-visible:outline-none disabled:cursor-not-allowed"
        @input="onInput"
      >
    </div>
  </UiField>
</template>
