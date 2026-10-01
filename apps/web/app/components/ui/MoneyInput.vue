<script setup lang="ts">
/**
 * SAR amount input. The model is integer halalas (`amount_minor`); the field shows riyals with two
 * decimals and Latin digits, and accepts Arabic-Indic digits while typing.
 */
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = defineProps<{
  label?: string
  hint?: string
  error?: string | null
  id?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  /** Inclusive bounds in halalas. */
  min?: number
  max?: number
  /** Show the 15% VAT and the VAT-inclusive total under the field (prices exclude VAT). */
  showVat?: boolean
}>()

const model = defineModel<number | null>({ default: null })
const { t } = useI18n()
const money = useMoney()
const autoId = useId()
const inputId = computed(() => props.id ?? `money-${autoId}`)

const focused = ref(false)
const text = ref(model.value === null ? '' : formatAmount(model.value))
const invalidText = ref(false)
/** The last value this field emitted from typing; `undefined` before any typing. */
let typedValue: number | null | undefined

// A value set from outside (a "Use this amount" action, a reset) replaces the text even while the
// field has focus; the field's own typing is left as typed ("12." stays "12.", not "12.00").
watch(model, (value) => {
  if (focused.value && value === typedValue) return
  typedValue = undefined
  text.value = value === null ? '' : formatAmount(value, { grouping: !focused.value })
  invalidText.value = false
})

const rangeError = computed(() => {
  if (model.value === null) return null
  if (props.min !== undefined && model.value < props.min) return t('common.money.min', { amount: money.format(props.min) })
  if (props.max !== undefined && model.value > props.max) return t('common.money.max', { amount: money.format(props.max) })
  return null
})

const displayedError = computed(() => props.error ?? (invalidText.value ? t('common.money.invalid') : rangeError.value))

function onInput(event: Event): void {
  text.value = (event.target as HTMLInputElement).value
  const parsed = parseAmountToMinor(text.value)
  invalidText.value = text.value.trim() !== '' && parsed === null
  if (!invalidText.value) {
    typedValue = parsed
    model.value = parsed
  }
}

function onFocus(): void {
  focused.value = true
  if (model.value !== null && !invalidText.value) text.value = formatAmount(model.value, { grouping: false })
}

function onBlur(): void {
  focused.value = false
  if (model.value !== null && !invalidText.value) text.value = formatAmount(model.value)
}
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="displayedError"
    :required="required"
  >
    <div
      class="flex h-11 items-center gap-2 rounded-md border bg-surface ps-3 focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
      :class="[invalid ? 'border-danger' : 'border-line-strong', disabled && 'bg-surface-muted opacity-70']"
    >
      <input
        :id="inputId"
        v-bind="controlAttrs()"
        :value="text"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        dir="ltr"
        :placeholder="placeholder ?? '0.00'"
        :required="required"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy(describedby, showVat && model !== null ? `${inputId}-vat` : undefined)"
        class="h-full min-w-0 flex-1 bg-transparent text-start text-fg tabular-nums outline-none placeholder:text-fg-muted focus-visible:outline-none"
        @input="onInput"
        @focus="onFocus"
        @blur="onBlur"
      >
      <span class="flex h-full items-center border-s border-line px-3 text-sm font-semibold text-fg-muted">
        {{ money.currency.value }}
      </span>
    </div>
    <p
      v-if="showVat && model !== null"
      :id="`${inputId}-vat`"
      class="text-sm text-fg-muted tabular-nums"
    >
      {{ t('common.money.vat_breakdown', { vat: money.format(money.vatOf(model)), total: money.format(money.withVat(model)) }) }}
    </p>
  </UiField>
</template>
