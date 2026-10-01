<script setup lang="ts">
import { CircleCheck } from '@lucide/vue'
import { checkOtp, resetPassword, sendOtp } from '~/services/identity'

/**
 * W08 Reset password · `/auth/reset?email=` (SCREENS §2.4). Works signed in or out.
 * The e-mailed code (checked early with `POST /auth/otp/check` once 6 digits are typed), a new
 * password with the rule checklist → `POST /auth/password/reset` (204; every session of the user is
 * revoked) → toast → W04. "Resend code" as W06 with `purpose: password_reset`.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()
const localePath = useLocalePath()
const toast = useToast()
const pendingCode = usePendingToken('password_reset')
const resetRequested = useState<boolean>('bafo:reset-requested', () => false)
const resendCooldown = useCooldown()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('auth.reset.title') })

const email = ref(typeof route.query.email === 'string' ? route.query.email.trim() : '')
const form = reactive({ code: '', password: '', password_confirmation: '' })
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const notice = ref<string | null>(resetRequested.value ? t('auth.reset.sent_notice') : null)
const codeValid = ref(false)
const checking = ref(false)
const submitting = ref(false)
const resending = ref(false)

onMounted(() => {
  const fromLink = pendingCode.captureFromLocation()
  const stored = fromLink?.token ?? pendingCode.read()
  if (stored && isOtpCode(stored)) {
    form.code = normalizeDigits(stored)
    void check(form.code)
  }
  if (resetRequested.value) resendCooldown.start(60)
  resetRequested.value = false
})

watch(() => form.code, (value) => {
  if (value.length < 6) codeValid.value = false
})

async function check(code: string): Promise<void> {
  if (!email.value || !isOtpCode(code)) return
  checking.value = true
  errors.value = { ...errors.value, code: undefined }
  try {
    const result = await checkOtp(email.value, normalizeDigits(code))
    codeValid.value = result.valid
  }
  catch (error) {
    codeValid.value = false
    if (error instanceof ApiError && error.status !== null && error.status < 500) errors.value = { ...errors.value, code: message(error) }
  }
  finally {
    checking.value = false
  }
}

function validate(): boolean {
  errors.value = {
    code: isOtpCode(form.code) ? undefined : t('validation.otp'),
    password: !form.password ? t('validation.required') : isStrongPassword(form.password) ? undefined : t('validation.password'),
    password_confirmation: form.password_confirmation === form.password ? undefined : t('validation.password_confirmation'),
  }
  return Object.values(errors.value).every(value => !value)
}

async function submit(): Promise<void> {
  formError.value = null
  if (!validate()) return
  submitting.value = true
  try {
    await resetPassword({
      email: email.value,
      code: normalizeDigits(form.code),
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    pendingCode.clear()
    // Every token of this user was revoked by the server.
    if (auth.isAuthenticated && auth.user?.email.toLowerCase() === email.value.toLowerCase()) auth.clear()
    toast.success(t('auth.reset.done'))
    await navigateTo(localePath('/auth/login'))
  }
  catch (error) {
    if (error instanceof ApiError && ['otp_invalid', 'otp_expired', 'otp_too_many_attempts'].includes(error.code)) {
      errors.value = { ...errors.value, code: message(error) }
      codeValid.value = false
      return
    }
    const bound = bind(error, ['code', 'password', 'password_confirmation'])
    errors.value = { ...errors.value, ...bound.fields }
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    submitting.value = false
  }
}

async function resend(): Promise<void> {
  if (resendCooldown.active.value || !email.value) return
  resending.value = true
  formError.value = null
  try {
    await sendOtp(email.value, 'password_reset')
    notice.value = t('auth.reset.sent_notice')
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
</script>

<template>
  <div class="flex flex-col gap-8">
    <div>
      <h1 class="text-2xl font-bold text-fg sm:text-3xl">
        {{ t('auth.reset.title') }}
      </h1>
      <p
        v-if="email"
        class="mt-2 text-fg-muted"
      >
        {{ t('auth.reset.subtitle') }} <bdi class="font-semibold text-fg">{{ email }}</bdi>
      </p>
    </div>

    <UiAlert
      v-if="notice"
      tone="info"
      dismissible
      @dismiss="notice = null"
    >
      {{ notice }}
    </UiAlert>
    <UiAlert
      v-if="formError"
      tone="danger"
      dismissible
      @dismiss="formError = null"
    >
      {{ formError }}
    </UiAlert>

    <div
      v-if="!email"
      class="flex flex-col gap-4"
    >
      <p class="text-fg-muted">
        {{ t('auth.reset.missing_email') }}
      </p>
      <UiButton
        to="/auth/forgot"
        variant="secondary"
      >
        {{ t('auth.forgot.title') }}
      </UiButton>
    </div>

    <form
      v-else
      class="flex flex-col gap-5"
      novalidate
      @submit.prevent="submit"
    >
      <div class="flex flex-col gap-2">
        <UiOtpInput
          v-model="form.code"
          :label="t('auth.fields.otp')"
          :hint="t('auth.fields.otp_hint')"
          :error="errors.code"
          :disabled="submitting"
          autofocus
          @complete="check"
        />
        <p
          v-if="codeValid && !errors.code"
          class="flex items-center gap-1.5 text-sm font-semibold text-success-soft-fg"
          role="status"
        >
          <CircleCheck
            :size="16"
            aria-hidden="true"
          />
          {{ t('auth.reset.code_valid') }}
        </p>
        <p
          v-else-if="checking"
          class="text-sm text-fg-muted"
          role="status"
        >
          {{ t('auth.reset.checking') }}
        </p>
      </div>
      <UiPasswordInput
        v-model="form.password"
        :label="t('auth.fields.new_password')"
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
      <UiButton
        type="submit"
        size="lg"
        block
        :loading="submitting"
      >
        {{ t('auth.reset.submit') }}
      </UiButton>
      <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
        <span class="text-fg-muted">{{ t('auth.verify.no_code') }}</span>
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
