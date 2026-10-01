<script setup lang="ts">
/**
 * Confirmation on `UiModal` (SCREENS S7). The confirm button names the action («إلغاء المنافسة»,
 * never «نعم»); `danger` makes it red for destructive actions. While `busy`, the dialog cannot be
 * dismissed and both buttons are disabled. Extra fields (reasons, password) go in the default slot.
 */
withDefaults(defineProps<{
  title: string
  description?: string
  confirmLabel: string
  cancelLabel?: string
  danger?: boolean
  busy?: boolean
  /** Disable confirm until the slot's fields are valid. */
  confirmDisabled?: boolean
  /** Form-level error from the server. */
  error?: string | null
}>(), {
  danger: false,
  busy: false,
  confirmDisabled: false,
})

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()
const { t } = useI18n()
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="title"
    :description="description"
    size="sm"
    :dismissible="!busy"
  >
    <form
      class="flex flex-col gap-4"
      novalidate
      @submit.prevent="!confirmDisabled && !busy && emit('confirm')"
    >
      <UiAlert
        v-if="error"
        tone="danger"
      >
        {{ error }}
      </UiAlert>
      <slot />
    </form>
    <template #footer>
      <UiButton
        variant="secondary"
        :disabled="busy"
        @click="open = false"
      >
        {{ cancelLabel ?? t('common.actions.cancel') }}
      </UiButton>
      <UiButton
        :variant="danger ? 'danger' : 'primary'"
        :loading="busy"
        :disabled="confirmDisabled"
        @click="emit('confirm')"
      >
        {{ confirmLabel }}
      </UiButton>
    </template>
  </UiModal>
</template>
