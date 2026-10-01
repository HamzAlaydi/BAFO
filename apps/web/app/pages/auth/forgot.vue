<script setup lang="ts">
import { forgotPassword } from '~/services/identity'

/**
 * W07 Forgot password · `/auth/forgot` (SCREENS §2.4): e-mail → `POST /auth/password/forgot`
 * (always 202, never says whether the e-mail exists) → W08 with `?email=` and a neutral message.
 */
definePageMeta({ layout: 'auth', middleware: 'guest' })

const { t } = useI18n()
const localePath = useLocalePath()
const resetNotice = useState<boolean>('bafo:reset-requested', () => false)
const cooldown = useCooldown()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('auth.forgot.title') })

const email = ref('')
const emailError = ref<string | null>(null)
const formError = ref<string | null>(null)
const submitting = ref(false)

async function submit(): Promise<void> {
  formError.value = null
  emailError.value = !email.value.trim() ? t('validation.required') : !isEmail(email.value) ? t('validation.email') : null
  if (emailError.value || cooldown.active.value) return
  submitting.value = true
  try {
    await forgotPassword(email.value.trim())
    resetNotice.value = true
    await navigateTo(localePath({ path: '/auth/reset', query: { email: email.value.trim() } }))
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'too_many_requests') cooldown.start(error.retryAfterSeconds ?? 60)
    if (error instanceof ApiError && error.isValidation) emailError.value = error.fieldError('email') ?? message(error)
    else formError.value = message(error)
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-8">
    <div>
      <h1 class="text-2xl font-bold text-fg sm:text-3xl">
        {{ t('auth.forgot.title') }}
      </h1>
      <p class="mt-2 text-fg-muted">
        {{ t('auth.forgot.subtitle') }}
      </p>
    </div>

    <UiAlert
      v-if="formError"
      tone="danger"
      dismissible
      @dismiss="formError = null"
    >
      {{ formError }}
    </UiAlert>

    <form
      class="flex flex-col gap-5"
      novalidate
      @submit.prevent="submit"
    >
      <UiInput
        v-model="email"
        :label="t('auth.fields.email')"
        type="email"
        autocomplete="email"
        inputmode="email"
        dir="ltr"
        required
        :error="emailError"
      />
      <UiButton
        type="submit"
        size="lg"
        block
        :loading="submitting"
        :disabled="cooldown.active.value"
      >
        {{ cooldown.active.value ? t('common.retry_in', { seconds: cooldown.seconds.value }) : t('auth.forgot.submit') }}
      </UiButton>
    </form>

    <p class="text-center text-sm text-fg-muted">
      <NuxtLinkLocale
        to="/auth/login"
        class="link"
      >
        {{ t('auth.login.back_to_sign_in') }}
      </NuxtLinkLocale>
    </p>
  </div>
</template>
