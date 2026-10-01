<script setup lang="ts" generic="T extends string | number">
import type { ChoiceOption } from '~/types/ui'

const props = withDefaults(defineProps<{
  options: ChoiceOption<T>[]
  label?: string
  hint?: string
  error?: string | null
  name?: string
  orientation?: 'vertical' | 'horizontal'
  required?: boolean
  disabled?: boolean
}>(), {
  orientation: 'vertical',
})

const model = defineModel<T | null>({ default: null })
const autoId = useId()
const groupId = computed(() => `radio-${autoId}`)
const groupName = computed(() => props.name ?? groupId.value)
</script>

<template>
  <UiField
    :id="groupId"
    v-slot="{ describedby }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
    group
  >
    <div
      class="flex gap-3"
      :class="orientation === 'vertical' ? 'flex-col' : 'flex-row flex-wrap'"
      :aria-describedby="describedby"
    >
      <label
        v-for="option in options"
        :key="String(option.value)"
        class="flex cursor-pointer items-start gap-3"
        :class="(disabled || option.disabled) && 'cursor-not-allowed opacity-60'"
      >
        <input
          v-model="model"
          type="radio"
          :name="groupName"
          :value="option.value"
          :disabled="disabled || option.disabled"
          :required="required"
          class="mt-1 size-[1.125rem] shrink-0 cursor-pointer accent-primary disabled:cursor-not-allowed"
        >
        <span class="min-w-0">
          <span class="block text-sm font-semibold text-fg">{{ option.label }}</span>
          <span
            v-if="option.description"
            class="block text-sm text-fg-muted"
          >{{ option.description }}</span>
        </span>
      </label>
    </div>
  </UiField>
</template>
