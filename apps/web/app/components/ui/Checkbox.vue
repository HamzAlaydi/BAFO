<script setup lang="ts">
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = defineProps<{
  label: string
  description?: string
  error?: string | null
  id?: string
  disabled?: boolean
  required?: boolean
}>()

const model = defineModel<boolean>({ default: false })
const autoId = useId()
const checkboxId = computed(() => props.id ?? `checkbox-${autoId}`)
</script>

<template>
  <div
    v-bind="rootAttrs()"
    class="flex flex-col gap-1"
  >
    <div class="flex items-start gap-3">
      <input
        :id="checkboxId"
        v-model="model"
        v-bind="controlAttrs()"
        type="checkbox"
        :disabled="disabled"
        :required="required"
        :aria-invalid="Boolean(error) || undefined"
        :aria-describedby="describedBy(description && `${checkboxId}-desc`, error && `${checkboxId}-error`)"
        class="mt-1 size-[1.125rem] shrink-0 cursor-pointer rounded-xs accent-primary disabled:cursor-not-allowed"
      >
      <div class="min-w-0">
        <label
          :for="checkboxId"
          class="cursor-pointer text-sm font-semibold text-fg"
          :class="disabled && 'cursor-not-allowed opacity-70'"
        >{{ label }}</label>
        <p
          v-if="description"
          :id="`${checkboxId}-desc`"
          class="text-sm text-fg-muted"
        >
          {{ description }}
        </p>
      </div>
    </div>
    <p
      v-if="error"
      :id="`${checkboxId}-error`"
      class="ps-8 text-sm font-medium text-danger"
    >
      {{ error }}
    </p>
  </div>
</template>
