<script setup lang="ts">
/**
 * `DeclineDialog` (SCREENS W03, W14, W23): an optional reason (≤ 500) and a confirm button that names
 * the action. The caller sends the request (by token or in-app) and passes `busy` / `error`.
 */
defineProps<{
  busy?: boolean
  error?: string | null
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [reason: string | null] }>()
const { t } = useI18n()
const reason = ref('')

watch(open, (isOpen) => {
  if (isOpen) reason.value = ''
})
</script>

<template>
  <UiConfirmDialog
    v-model:open="open"
    :title="t('invitations.decline.title')"
    :description="t('invitations.decline.description')"
    :confirm-label="t('invitations.decline.confirm')"
    :busy="busy"
    :error="error"
    danger
    @confirm="emit('confirm', reason.trim() || null)"
  >
    <UiTextarea
      v-model="reason"
      :label="t('invitations.decline.reason')"
      :hint="t('common.optional')"
      :maxlength="500"
      :rows="3"
    />
  </UiConfirmDialog>
</template>
