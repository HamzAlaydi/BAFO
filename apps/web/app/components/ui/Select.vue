<script setup lang="ts" generic="T extends string | number">
import { ChevronDown } from '@lucide/vue'
import type { SelectOption } from '~/types/ui'

defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = defineProps<{
  options: SelectOption<T>[]
  label?: string
  hint?: string
  error?: string | null
  id?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
}>()

const model = defineModel<T | null>({ default: null })
const autoId = useId()
const selectId = computed(() => props.id ?? `select-${autoId}`)

// Native <select> values are strings; map back to the typed option value.
const selectedIndex = computed(() => props.options.findIndex(option => option.value === model.value))

function onChange(event: Event): void {
  const index = Number((event.target as HTMLSelectElement).value)
  model.value = props.options[index]?.value ?? null
}
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="selectId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
  >
    <div class="relative">
      <select
        :id="selectId"
        v-bind="controlAttrs()"
        :value="selectedIndex === -1 ? '' : String(selectedIndex)"
        :required="required"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedby"
        class="block h-11 w-full appearance-none rounded-md border bg-surface ps-3 pe-10 text-fg outline-none transition-colors focus:outline-2 focus:outline-offset-0 focus:outline-ring disabled:cursor-not-allowed disabled:bg-surface-muted disabled:opacity-70"
        :class="[invalid ? 'border-danger' : 'border-line-strong', selectedIndex === -1 && 'text-fg-muted']"
        @change="onChange"
      >
        <option
          value=""
          disabled
          :hidden="required"
        >
          {{ placeholder ?? '' }}
        </option>
        <option
          v-for="(option, index) in options"
          :key="String(option.value)"
          :value="String(index)"
          :disabled="option.disabled"
          class="text-fg"
        >
          {{ option.label }}
        </option>
      </select>
      <ChevronDown
        :size="18"
        class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-fg-muted"
        aria-hidden="true"
      />
    </div>
  </UiField>
</template>
