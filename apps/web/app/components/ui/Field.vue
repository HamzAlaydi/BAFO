<script setup lang="ts">
/**
 * Label + control + hint/error wiring shared by every form control.
 * The control reads `ids` from the default slot to set `aria-describedby` / `aria-invalid`.
 */
const props = defineProps<{
  id: string
  label?: string
  hint?: string
  error?: string | null
  required?: boolean
  /** Render the label as a <legend> inside a <fieldset> (radio groups, segmented controls). */
  group?: boolean
}>()

const { t } = useI18n()
const hintId = computed(() => (props.hint ? `${props.id}-hint` : undefined))
const errorId = computed(() => (props.error ? `${props.id}-error` : undefined))
// The hint is hidden while an error is shown, so only one of them describes the control.
const describedby = computed(() => errorId.value ?? hintId.value)
</script>

<template>
  <component
    :is="group ? 'fieldset' : 'div'"
    class="flex min-w-0 flex-col gap-1.5"
  >
    <component
      :is="group ? 'legend' : 'label'"
      v-if="label"
      :for="group ? undefined : id"
      class="mb-0.5 text-sm font-semibold text-fg"
    >
      {{ label }}
      <span
        v-if="required"
        class="text-danger"
        aria-hidden="true"
      >*</span>
      <span
        v-if="required"
        class="sr-only"
      >({{ t('common.required') }})</span>
    </component>
    <slot
      :describedby="describedby"
      :invalid="Boolean(error)"
    />
    <p
      v-if="error"
      :id="errorId"
      class="text-sm font-medium text-danger"
    >
      {{ error }}
    </p>
    <p
      v-else-if="hint"
      :id="hintId"
      class="text-sm text-fg-muted"
    >
      {{ hint }}
    </p>
  </component>
</template>
