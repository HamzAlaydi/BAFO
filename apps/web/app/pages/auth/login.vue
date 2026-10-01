<script setup lang="ts">
import { Info } from '@lucide/vue'

/**
 * W04 Sign in · `/auth/login?redirect=` (SCREENS §2.4).
 * `POST /auth/login` → session → the pending invitation claim, the same-origin `redirect`, or W10.
 * `email_not_verified` → W06 (an OTP was sent); `account_inactive` / `organization_suspended` →
 * the full-page account gate; 429 → a countdown on the button.
 */
definePageMeta({ layout: 'auth', middleware: 'guest' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const localePath = useLocalePath()
const postSignIn = usePostSignIn()
const pendingInvitation = usePendingToken('invitation')
const otpHandoff = useOtpHandoff('email_verification')
const cooldown = useCooldown()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('auth.login.title') })

const form = reactive({ email: '', password: '' })
const submitting = ref(false)
const formError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string | undefined>>({})
const gate = ref<'account_inactive' | 'organization_suspended' | null>(null)
const hasPendingInvitation = ref(false)

onMounted(() => {
  hasPendingInvitation.value = Boolean(pendingInvitation.read())
})

function validate(): boolean {
  fieldErrors.value = {
    email: !form.email.trim() ? t('validation.required') : !isEmail(form.email) ? t('validation.email') : undefined,
    password: !form.password ? t('validation.required') : undefined,
  }
  return Object.values(fieldErrors.value).every(value => !value)
}

async function submit(): Promise<void> {
  formError.value = null
  if (cooldown.active.value || !validate()) return
  submitting.value = true
  try {
    await auth.login(form.email, form.password)
    await postSignIn.go(route.query.redirect)
  }
  catch (error) {
    if (!(error instanceof ApiError)) {
      formError.value = t('errors.unknown')
      return
    }
    if (error.code === 'email_not_verified') {
      otpHandoff.set(form.email, error.detailString('otp_expires_at'))
      await navigateTo(localePath({ path: '/auth/verify', query: { email: form.email.trim() } }))
      return
    }
    if (error.code === 'account_inactive' || error.code === 'organization_suspended') {
      gate.value = error.code
      return
    }
    if (error.code === 'too_many_requests') cooldown.start(error.retryAfterSeconds ?? 60)
    fieldErrors.value = { email: error.fieldError('email'), password: error.fieldError('password') }
    formError.value = error.isValidation ? null : message(error)
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppAccountGate
    v-if="gate"
    :gate="gate"
    signed-out
    @back="gate = null"
  />
  <div
    v-else
    class="flex flex-col gap-8"
  >
    <div>
      <h1 class="text-2xl font-bold text-fg sm:text-3xl">
        {{ t('auth.login.title') }}
      </h1>
      <p class="mt-2 text-fg-muted">
        {{ t('auth.login.subtitle') }}
      </p>
    </div>

    <UiAlert
      v-if="hasPendingInvitation"
      tone="info"
      :icon="Info"
    >
      {{ t('auth.login.pending_invitation') }}
    </UiAlert>

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
        v-model="form.email"
        :label="t('auth.fields.email')"
        type="email"
        autocomplete="email"
        inputmode="email"
        dir="ltr"
        required
        :error="fieldErrors.email"
      />
      <div class="flex flex-col gap-2">
        <UiPasswordInput
          v-model="form.password"
          :label="t('auth.fields.password')"
          autocomplete="current-password"
          required
          :error="fieldErrors.password"
        />
        <NuxtLinkLocale
          to="/auth/forgot"
          class="link self-end text-sm"
        >
          {{ t('auth.login.forgot_link') }}
        </NuxtLinkLocale>
      </div>
      <UiButton
        type="submit"
        size="lg"
        block
        :loading="submitting"
        :disabled="cooldown.active.value"
      >
        {{ cooldown.active.value ? t('common.retry_in', { seconds: cooldown.seconds.value }) : t('auth.login.submit') }}
      </UiButton>
    </form>

    <p class="text-center text-sm text-fg-muted">
      {{ t('auth.login.no_account') }}
      <NuxtLinkLocale
        to="/auth/register"
        class="link"
      >
        {{ t('auth.login.register_link') }}
      </NuxtLinkLocale>
    </p>
  </div>
</template>
