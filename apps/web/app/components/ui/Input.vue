<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = withDefaults(defineProps<{
  label?: string
  hint?: string
  error?: string | null
  id?: string
  type?: 'text' | 'email' | 'password' | 'tel' | 'url' | 'search' | 'number'
  placeholder?: string
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  autocomplete?: string
  inputmode?: 'text' | 'email' | 'tel' | 'url' | 'numeric' | 'decimal' | 'search' | 'none'
  /** Force a direction, e.g. 'ltr' for e-mail, phone and IDs inside Arabic forms. */
  dir?: 'ltr' | 'rtl' | 'auto'
  maxlength?: number
}>(), {
  type: 'text',
})

const model = defineModel<string>({ default: '' })
const autoId = useId()
const inputId = computed(() => props.id ?? `input-${autoId}`)
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
      class="flex h-11 items-center gap-2 rounded-md border bg-surface px-3 transition-colors focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
      :class="[
        invalid ? 'border-danger' : 'border-line-strong',
        disabled && 'cursor-not-allowed bg-surface-muted opacity-70',
      ]"
    >
      <slot name="leading" />
      <input
        :id="inputId"
        v-model="model"
        v-bind="controlAttrs()"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :readonly="readonly"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :dir="dir"
        :maxlength="maxlength"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedby"
        class="h-full min-w-0 flex-1 bg-transparent text-fg outline-none placeholder:text-fg-muted focus-visible:outline-none disabled:cursor-not-allowed"
        :class="dir === 'ltr' && 'text-start'"
      >
      <slot name="trailing" />
    </div>
  </UiField>
</template>
