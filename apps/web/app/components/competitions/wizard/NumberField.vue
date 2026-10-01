<script setup lang="ts">
/**
 * Whole-number input for rule values (minutes, counts): accepts Arabic-Indic digits, keeps the typed
 * text while focused, and emits `null` for an empty or invalid value. Bounds are shown as the hint and
 * checked by the caller's validation (the server re-checks).
 */
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  label: string
  hint?: string
  error?: string | null
  suffix?: string
  disabled?: boolean
  required?: boolean
  id?: string
}>()

const model = defineModel<number | null>({ default: null })
const { t } = useI18n()
const text = ref(model.value === null ? '' : String(model.value))
const focused = ref(false)
const invalid = ref(false)

watch(model, (value) => {
  if (focused.value) return
  text.value = value === null ? '' : String(value)
  invalid.value = false
})

function onInput(value: string): void {
  text.value = value
  const cleaned = normalizeDigits(value).trim()
  if (cleaned === '') {
    invalid.value = false
    model.value = null
    return
  }
  invalid.value = !/^\d{1,6}$/.test(cleaned)
  if (!invalid.value) model.value = Number(cleaned)
}

const shownError = computed(() => props.error ?? (invalid.value ? t('rules.validation.whole_number') : null))
</script>

<template>
  <UiInput
    :id="id"
    :model-value="text"
    v-bind="$attrs"
    :label="label"
    :hint="hint"
    :error="shownError"
    :disabled="disabled"
    :required="required"
    inputmode="numeric"
    dir="ltr"
    @update:model-value="onInput"
    @focus="focused = true"
    @blur="focused = false"
  >
    <template
      v-if="suffix"
      #trailing
    >
      <span class="shrink-0 text-sm text-fg-muted">{{ suffix }}</span>
    </template>
  </UiInput>
</template>
