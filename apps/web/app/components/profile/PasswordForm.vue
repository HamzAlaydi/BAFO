<script setup lang="ts">
import { ShieldCheck } from '@lucide/vue'
import { changePassword } from '~/services/identity'

/**
 * Change password (SCREENS W28): current password, new password with the rule checklist and its
 * confirmation → `PUT /me/password` (204). Every other session is signed out; this one stays.
 */
const { t } = useI18n()
const toast = useToast()
const { message, bind } = useErrorMessage()

const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const saving = ref(false)

function validate(): boolean {
  errors.value = {
    current_password: form.current_password ? undefined : t('validation.required'),
    password: !form.password ? t('validation.required') : isStrongPassword(form.password) ? undefined : t('validation.password'),
    password_confirmation: form.password_confirmation === form.password ? undefined : t('validation.password_confirmation'),
  }
  return Object.values(errors.value).every(value => !value)
}

async function save(): Promise<void> {
  formError.value = null
  if (!validate()) return
  saving.value = true
  try {
    await changePassword({ ...form })
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    toast.success(t('profile.password.changed'))
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'password_incorrect') {
      errors.value = { current_password: message(error) }
      return
    }
    const bound = bind(error, ['current_password', 'password', 'password_confirmation'])
    errors.value = bound.fields
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UiCard
    :title="t('profile.sections.security')"
    :description="t('profile.password.other_sessions')"
  >
    <UiAlert
      v-if="formError"
      tone="danger"
      class="mb-5"
      dismissible
      @dismiss="formError = null"
    >
      {{ formError }}
    </UiAlert>
    <form
      class="flex flex-col gap-5"
      novalidate
      @submit.prevent="save"
    >
      <UiPasswordInput
        v-model="form.current_password"
        :label="t('profile.password.current')"
        autocomplete="current-password"
        required
        :error="errors.current_password"
      />
      <UiPasswordInput
        v-model="form.password"
        :label="t('profile.password.new')"
        autocomplete="new-password"
        checklist
        required
        :error="errors.password"
      />
      <UiPasswordInput
        v-model="form.password_confirmation"
        :label="t('auth.fields.password_confirmation')"
        autocomplete="new-password"
        required
        :error="errors.password_confirmation"
      />
      <div class="flex justify-end">
        <UiButton
          type="submit"
          :icon="ShieldCheck"
          :loading="saving"
        >
          {{ t('profile.password.submit') }}
        </UiButton>
      </div>
    </form>
  </UiCard>
</template>
