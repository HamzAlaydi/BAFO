<script setup lang="ts">
const props = defineProps<{
  label: string
  description?: string
  id?: string
  disabled?: boolean
}>()

const model = defineModel<boolean>({ default: false })
const autoId = useId()
const switchId = computed(() => props.id ?? `switch-${autoId}`)

function toggle(): void {
  if (!props.disabled) model.value = !model.value
}
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <label
        :id="`${switchId}-label`"
        :for="switchId"
        class="text-sm font-semibold text-fg"
      >{{ label }}</label>
      <p
        v-if="description"
        :id="`${switchId}-desc`"
        class="text-sm text-fg-muted"
      >
        {{ description }}
      </p>
    </div>
    <button
      :id="switchId"
      type="button"
      role="switch"
      :aria-checked="model"
      :aria-labelledby="`${switchId}-label`"
      :aria-describedby="description ? `${switchId}-desc` : undefined"
      :disabled="disabled"
      class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
      :class="model ? 'bg-primary' : 'bg-line-strong'"
      @click="toggle"
    >
      <span
        class="pointer-events-none block size-5 rounded-full bg-white shadow-sm transition-transform duration-150"
        :class="model ? 'ltr:translate-x-5 rtl:-translate-x-5' : 'translate-x-0'"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
