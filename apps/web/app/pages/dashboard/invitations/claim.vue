<script setup lang="ts">
import { Building2, LinkIcon, LoaderCircle, MailCheck } from '@lucide/vue'
import { claimInvitation } from '~/services/competitions'
import type { InviteeInvitation } from '~/types/api/competitions'

/**
 * W24 Claim invitation · `/dashboard/invitations/claim` (SCREENS §2.4, flows F4 and F5). Reads the
 * pending invitation token (CD7: kept in `sessionStorage` by W03, or taken from `#t=…` here and
 * stripped from the address bar); no token → W23. `POST /invitations/claim {token}`:
 * - 200 `Invitation` → the token is cleared → the competition (W14);
 * - 202 `{otp_sent_to, otp_expires_at}` → a code sent to the invited e-mail → `{token, code}`.
 * Errors: `invitation_belongs_to_another_organization`, `invitation_invalid`, and the OTP errors of
 * W06. The token never leaves the JSON body.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const localePath = useLocalePath()
const toast = useToast()
const pending = usePendingToken('invitation')
const resendCooldown = useCooldown()
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('invitations.claim.title'), robots: 'noindex, nofollow' })

type State = 'claiming' | 'otp' | 'invalid' | 'other_org' | 'error'
const state = ref<State>('claiming')
const loadError = ref<unknown>(null)
const otpSentTo = ref<string | null>(null)
const otpExpiresAt = ref<string | null>(null)
const code = ref('')
const codeError = ref<string | null>(null)
const formError = ref<string | null>(null)
const notice = ref<string | null>(null)
const verifying = ref(false)
const resending = ref(false)
let token: string | null = null

async function finish(invitation: InviteeInvitation): Promise<void> {
  pending.clear()
  toast.success(t('invitations.claim.bound'))
  const competitionId = invitation.competition?.id
  const target = competitionId ? `/dashboard/competitions/${competitionId}` : '/dashboard/participating'
  await navigateTo(localePath(target), { replace: true })
}

/** Errors that end the flow; returns false for the ones the OTP step shows inline. */
function endWith(error: unknown): boolean {
  if (error instanceof ApiError && (error.code === 'invitation_invalid' || error.isNotFound)) {
    pending.clear()
    state.value = 'invalid'
    return true
  }
  if (error instanceof ApiError && error.code === 'invitation_belongs_to_another_organization') {
    pending.clear()
    state.value = 'other_org'
    return true
  }
  return false
}

async function claim(): Promise<void> {
  if (!token) return
  state.value = 'claiming'
  loadError.value = null
  try {
    const result = await claimInvitation(token)
    if (result.kind === 'bound') {
      await finish(result.invitation)
      return
    }
    otpSentTo.value = result.otp_sent_to
    otpExpiresAt.value = result.otp_expires_at
    state.value = 'otp'
    resendCooldown.start(60)
  }
  catch (error) {
    if (endWith(error)) return
    loadError.value = error
    state.value = 'error'
  }
}

onMounted(async () => {
  token = pending.captureFromLocation()?.token ?? pending.read()
  if (!token) {
    await navigateTo(localePath('/dashboard/participating'), { replace: true })
    return
  }
  await claim()
})

async function verify(): Promise<void> {
  if (!token || verifying.value) return
  codeError.value = null
  formError.value = null
  notice.value = null
  if (!isOtpCode(code.value)) {
    codeError.value = t('validation.otp')
    return
  }
  verifying.value = true
  try {
    const result = await claimInvitation(token, normalizeDigits(code.value))
    if (result.kind === 'bound') {
      await finish(result.invitation)
      return
    }
    // A new code was sent instead (the previous one was consumed): keep going with the new one.
    otpSentTo.value = result.otp_sent_to
    otpExpiresAt.value = result.otp_expires_at
    code.value = ''
    notice.value = t('invitations.claim.resent')
  }
  catch (error) {
    code.value = ''
    if (endWith(error)) return
    if (error instanceof ApiError && ['otp_invalid', 'otp_expired', 'otp_too_many_attempts'].includes(error.code)) {
      codeError.value = message(error)
    }
    else if (error instanceof ApiError && error.isValidation) {
      codeError.value = error.fieldError('code') ?? message(error)
    }
    else if (error instanceof ApiError && error.code === 'too_many_requests') {
      formError.value = message(error)
      resendCooldown.start(error.retryAfterSeconds ?? 60)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    verifying.value = false
  }
}

/** A claim without a code sends a new OTP to the invited e-mail. */
async function resend(): Promise<void> {
  if (!token || resendCooldown.active.value || resending.value) return
  resending.value = true
  formError.value = null
  notice.value = null
  try {
    const result = await claimInvitation(token)
    if (result.kind === 'bound') {
      await finish(result.invitation)
      return
    }
    otpSentTo.value = result.otp_sent_to
    otpExpiresAt.value = result.otp_expires_at
    codeError.value = null
    notice.value = t('invitations.claim.resent')
    resendCooldown.start(60)
  }
  catch (error) {
    if (endWith(error)) return
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
  <div class="mx-auto flex w-full max-w-lg flex-col gap-6 py-4 sm:py-8">
    <div
      v-if="state === 'claiming'"
      class="flex flex-col items-center gap-4 py-12 text-center"
      role="status"
      data-testid="claim-checking"
    >
      <LoaderCircle
        :size="32"
        class="animate-spin text-brand"
        aria-hidden="true"
      />
      <p class="font-semibold text-fg">
        {{ t('invitations.claim.checking') }}
      </p>
    </div>

    <UiCard v-else-if="state === 'invalid'">
      <UiEmptyState
        :icon="LinkIcon"
        :title="t('invitations.landing.invalid_title')"
        :description="t('errors.invitation_invalid')"
      >
        <UiButton
          to="/dashboard/participating"
          variant="secondary"
        >
          {{ t('invitations.claim.to_participating') }}
        </UiButton>
      </UiEmptyState>
    </UiCard>

    <UiCard v-else-if="state === 'other_org'">
      <UiEmptyState
        :icon="Building2"
        :title="t('invitations.claim.other_org_title')"
        :description="t('invitations.claim.other_org_body')"
      >
        <UiButton
          to="/dashboard/participating"
          variant="secondary"
        >
          {{ t('invitations.claim.to_participating') }}
        </UiButton>
      </UiEmptyState>
    </UiCard>

    <UiErrorState
      v-else-if="state === 'error'"
      :error="loadError"
      @retry="claim"
    />

    <UiCard
      v-else
      padding="lg"
      data-testid="claim-otp"
    >
      <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-4">
          <span
            class="flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary-soft-fg"
            aria-hidden="true"
          >
            <MailCheck :size="24" />
          </span>
          <div>
            <h1 class="text-2xl font-bold text-fg">
              {{ t('invitations.claim.otp_title') }}
            </h1>
            <p class="mt-2 text-fg-muted">
              <i18n-t
                keypath="invitations.claim.otp_body"
                scope="global"
              >
                <template #email>
                  <bdi class="font-semibold text-fg">{{ otpSentTo }}</bdi>
                </template>
              </i18n-t>
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
          class="flex flex-col gap-4"
          novalidate
          @submit.prevent="verify"
        >
          <UiOtpInput
            v-model="code"
            :label="t('invitations.claim.code_label')"
            :error="codeError"
            autofocus
            @complete="verify"
          />
          <p
            v-if="otpExpiresAt"
            class="flex flex-wrap items-baseline gap-1.5 text-sm text-fg-muted"
          >
            <span>{{ t('invitations.claim.expires_in') }}</span>
            <UiCountdown
              :ends-at="otpExpiresAt"
              :label="t('invitations.claim.expires_in')"
              size="sm"
            />
          </p>
          <UiButton
            type="submit"
            size="lg"
            block
            :loading="verifying"
          >
            {{ t('invitations.claim.verify') }}
          </UiButton>
        </form>

        <UiButton
          variant="link"
          class="self-center"
          :disabled="resendCooldown.active.value"
          :loading="resending"
          @click="resend"
        >
          {{ resendCooldown.active.value ? t('invitations.claim.resend_in', { seconds: resendCooldown.seconds.value }) : t('invitations.claim.resend') }}
        </UiButton>
      </div>
    </UiCard>
  </div>
</template>
