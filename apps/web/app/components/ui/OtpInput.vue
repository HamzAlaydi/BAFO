<script setup lang="ts">
/**
 * One-time code (SCREENS S9): a single input with `autocomplete="one-time-code"` and a numeric
 * keypad, drawn as six boxes. Arabic-Indic digits are normalised; `complete` fires at 6 digits.
 */
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = withDefaults(defineProps<{
  label: string
  hint?: string
  error?: string | null
  id?: string
  length?: number
  disabled?: boolean
  autofocus?: boolean
}>(), {
  length: 6,
})

const emit = defineEmits<{ complete: [code: string] }>()
const model = defineModel<string>({ default: '' })
const autoId = useId()
const inputId = computed(() => props.id ?? `otp-${autoId}`)
const input = useTemplateRef<HTMLInputElement>('input')
const focused = ref(false)

const boxes = computed(() => Array.from({ length: props.length }, (_, index) => model.value[index] ?? ''))
const activeIndex = computed(() => Math.min(model.value.length, props.length - 1))

function onInput(event: Event): void {
  const target = event.target as HTMLInputElement
  const cleaned = normalizeDigits(target.value).replace(/\D/g, '').slice(0, props.length)
  target.value = cleaned
  model.value = cleaned
  if (cleaned.length === props.length) emit('complete', cleaned)
}

onMounted(() => {
  if (props.autofocus) input.value?.focus()
})

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    required
  >
    <div
      class="relative w-full max-w-sm"
      dir="ltr"
    >
      <input
        :id="inputId"
        ref="input"
        v-bind="controlAttrs()"
        :value="model"
        type="text"
        inputmode="numeric"
        autocomplete="one-time-code"
        pattern="[0-9]*"
        :maxlength="length"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedby"
        class="absolute inset-0 z-10 size-full cursor-text opacity-0"
        @input="onInput"
        @focus="focused = true"
        @blur="focused = false"
      >
      <div
        class="grid gap-2"
        :style="{ gridTemplateColumns: `repeat(${length}, minmax(0, 1fr))` }"
        aria-hidden="true"
      >
        <span
          v-for="(digit, index) in boxes"
          :key="index"
          class="flex h-13 items-center justify-center rounded-md border-2 bg-surface text-xl font-bold text-fg tabular-nums transition-colors"
          :class="[
            invalid ? 'border-danger' : focused && index === activeIndex ? 'border-ring' : 'border-line-strong',
            disabled && 'bg-surface-muted opacity-70',
          ]"
        >{{ digit }}</span>
      </div>
    </div>
  </UiField>
</template>
