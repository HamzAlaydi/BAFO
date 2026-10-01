<script setup lang="ts" generic="T extends string | number">
import type { ChoiceOption } from '~/types/ui'

/**
 * Segmented control built on native radios: arrow keys, RTL order and form semantics come from
 * the browser. Use for 2–4 mutually exclusive choices (e.g. Tender / Auction).
 */
const props = withDefaults(defineProps<{
  options: ChoiceOption<T>[]
  label?: string
  hint?: string
  error?: string | null
  name?: string
  size?: 'sm' | 'md'
  block?: boolean
  disabled?: boolean
}>(), {
  size: 'md',
})

const model = defineModel<T | null>({ default: null })
const autoId = useId()
const groupId = computed(() => `segmented-${autoId}`)
const groupName = computed(() => props.name ?? groupId.value)
</script>

<template>
  <UiField
    :id="groupId"
    v-slot="{ describedby }"
    :label="label"
    :hint="hint"
    :error="error"
    group
  >
    <div
      class="inline-flex max-w-full gap-1 overflow-x-auto rounded-md border border-line bg-surface-muted p-1"
      :class="block ? 'flex w-full' : 'self-start'"
      :aria-describedby="describedby"
    >
      <label
        v-for="option in options"
        :key="String(option.value)"
        class="relative flex-1"
      >
        <input
          v-model="model"
          type="radio"
          class="peer sr-only"
          :name="groupName"
          :value="option.value"
          :disabled="disabled || option.disabled"
        >
        <span
          class="flex cursor-pointer items-center justify-center gap-2 rounded-sm px-3 font-semibold whitespace-nowrap text-fg-muted transition-colors hover:text-fg peer-checked:bg-surface peer-checked:text-fg peer-checked:shadow-sm peer-focus-visible:outline-2 peer-focus-visible:outline-ring peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
          :class="size === 'sm' ? 'h-8 text-sm' : 'h-9 text-[0.9375rem]'"
        >
          <component
            :is="option.icon"
            v-if="option.icon"
            :size="16"
            aria-hidden="true"
          />
          {{ option.label }}
        </span>
      </label>
    </div>
  </UiField>
</template>
