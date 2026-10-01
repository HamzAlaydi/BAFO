<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = withDefaults(defineProps<{
  label?: string
  hint?: string
  error?: string | null
  id?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  readonly?: boolean
  rows?: number
  maxlength?: number
}>(), {
  rows: 4,
})

const model = defineModel<string>({ default: '' })
const { t } = useI18n()
const autoId = useId()
const textareaId = computed(() => props.id ?? `textarea-${autoId}`)
const counterId = computed(() => `${textareaId.value}-counter`)
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="textareaId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
  >
    <textarea
      :id="textareaId"
      v-model="model"
      v-bind="controlAttrs()"
      :rows="rows"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :readonly="readonly"
      :maxlength="maxlength"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy(describedby, maxlength ? counterId : undefined)"
      class="block w-full resize-y rounded-md border bg-surface px-3 py-2.5 text-fg outline-none transition-colors placeholder:text-fg-muted focus:outline-2 focus:outline-offset-0 focus:outline-ring disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-70"
      :class="invalid ? 'border-danger' : 'border-line-strong'"
    />
    <p
      v-if="maxlength"
      :id="counterId"
      class="text-end text-xs text-fg-muted tabular-nums"
    >
      {{ t('common.character_count', { count: model.length, max: maxlength }) }}
    </p>
  </UiField>
</template>
