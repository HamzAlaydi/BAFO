<script setup lang="ts">
import { MailCheck } from '@lucide/vue'
import { sendOtp } from '~/services/identity'

/**
 * W06 Verify e-mail · `/auth/verify?email=` (SCREENS §2.4). Works signed in or out.
 * The 6-digit code → `POST /auth/otp/verify` → signed in → the pending invitation claim or W10.
 * "Resend code" waits 60 s (`otp_resend_cooldown` → `details.retry_after_seconds`).
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const postSignIn = usePostSignIn()
const otpHandoff = useOtpHandoff('email_verification')
const resendCooldown = useCooldown()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('auth.verify.title') })

const email = ref(typeof route.query.email === 'string' ? route.query.email.trim() : '')
const emailInput = ref(email.value)
const code = ref('')
const expiresAt = ref<string | null>(email.value ? otpHandoff.expiresAtFor(email.value) : null)
const verifying = ref(false)
const resending = ref(false)
const codeError = ref<string | null>(null)
const formError = ref<string | null>(null)
const notice = ref<string | null>(null)
const highlightResend = ref(false)

// A code was just sent by the previous page (register or sign-in): the resend waits its 60 s.
onMounted(() => {
  if (expiresAt.value) resendCooldown.start(60)
})

async function verify(): Promise<void> {
  codeError.value = null
  formError.value = null
  if (!isOtpCode(code.value)) {
    codeError.value = t('validation.otp')
    return
  }
  verifying.value = true
  try {
    await auth.verifyEmail(email.value, normalizeDigits(code.value))
    await postSignIn.go()
  }
  catch (error) {
    code.value = ''
    if (!(error instanceof ApiError)) {
      formError.value = t('errors.unknown')
      return
    }
    if (error.code === 'otp_expired' || error.code === 'otp_too_many_attempts') highlightResend.value = true
    if (error.code === 'otp_invalid' || error.code === 'otp_expired' || error.code === 'otp_too_many_attempts') {
      codeError.value = message(error)
    }
    else if (error.isValidation) {
      codeError.value = error.fieldError('code') ?? message(error)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    verifying.value = false
  }
}

async function resend(): Promise<void> {
  if (resendCooldown.active.value || !email.value) return
  resending.value = true
  formError.value = null
  notice.value = null
  try {
    const result = await sendOtp(email.value, 'email_verification')
    expiresAt.value = result.otp_expires_at
    highlightResend.value = false
    codeError.value = null
    notice.value = t('auth.verify.resent')
    resendCooldown.start(60)
  }
  catch (error) {
    if (error instanceof ApiError && (error.code === 'otp_resend_cooldown' || error.code === 'too_many_requests')) {
      resendCooldown.start(error.retryAfterSeconds ?? 60)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    resending.value = false
  }
}

async function useEmail(): Promise<void> {
  if (!isEmail(emailInput.value)) {
    formError.value = t('validation.email')
    return
  }
  email.value = emailInput.value.trim()
  await navigateTo({ query: { email: email.value } }, { replace: true })
  await resend()
}
</script>

<template>
  <div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4">
      <span
        class="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-soft-fg"
        aria-hidden="true"
      >
        <MailCheck :size="24" />
      </span>
      <div>
        <h1 class="text-2xl font-bold text-fg sm:text-3xl">
          {{ t('auth.verify.title') }}
        </h1>
        <p
          v-if="email"
          class="mt-2 text-fg-muted"
        >
          {{ t('auth.verify.subtitle') }} <bdi class="font-semibold text-fg">{{ email }}</bdi>
        </p>
        <p
          v-else
          class="mt-2 text-fg-muted"
        >
          {{ t('auth.verify.enter_email') }}
        </p>
      </div>
    </div>

    <UiAlert
      v-if="formError"
      tone="danger"
      dismissible
      @dismiss="formError = null"
    >
      {{ formError }}
    </UiAlert>
    <UiAlert
      v-if="notice"
      tone="success"
      dismissible
      @dismiss="notice = null"
    >
      {{ notice }}
    </UiAlert>

    <form
      v-if="!email"
      class="flex flex-col gap-5"
      novalidate
      @submit.prevent="useEmail"
    >
      <UiInput
        v-model="emailInput"
        :label="t('auth.fields.email')"
        type="email"
        autocomplete="email"
        inputmode="email"
        dir="ltr"
        required
      />
      <UiButton
        type="submit"
        size="lg"
        block
        :loading="resending"
      >
        {{ t('auth.verify.send_code') }}
      </UiButton>
    </form>

    <form
      v-else
      class="flex flex-col gap-5"
      novalidate
      @submit.prevent="verify"
    >
      <UiOtpInput
        v-model="code"
        :label="t('auth.fields.otp')"
        :hint="t('auth.fields.otp_hint')"
        :error="codeError"
        :disabled="verifying"
        autofocus
        @complete="verify"
      />
      <p
        v-if="expiresAt"
        class="flex flex-wrap items-center gap-1.5 text-sm text-fg-muted"
      >
        {{ t('auth.verify.expires_in') }}
        <UiCountdown
          :ends-at="expiresAt"
          size="sm"
          :ended-label="t('auth.verify.expired')"
          @expire="highlightResend = true"
        />
      </p>
      <UiButton
        type="submit"
        size="lg"
        block
        :loading="verifying"
      >
        {{ t('auth.verify.submit') }}
      </UiButton>
      <div
        class="flex flex-wrap items-center justify-center gap-2 rounded-md p-2 text-sm"
        :class="highlightResend && 'bg-warning-soft text-warning-soft-fg'"
      >
        <span>{{ t('auth.verify.no_code') }}</span>
        <UiButton
          variant="link"
          :loading="resending"
          :disabled="resendCooldown.active.value"
          @click="resend"
        >
          {{ resendCooldown.active.value ? t('auth.verify.resend_in', { seconds: resendCooldown.seconds.value }) : t('auth.verify.resend') }}
        </UiButton>
      </div>
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
