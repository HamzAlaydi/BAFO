<script setup lang="ts">
import { CircleCheck, LinkIcon, LogIn, UserPlus } from '@lucide/vue'
import { declineInvitationByToken, lookupInvitation } from '~/services/competitions'
import type { InvitationLookup } from '~/types/api/competitions'

/**
 * W03 Invitation landing · `/invitations#t={token}` (optionally `&action=decline`), the target of the
 * competition invitation e-mail (SCREENS §2.4, CD7). An SSR shell: the token is read client-side from
 * the fragment, removed from the address bar and kept in `sessionStorage` while the visitor registers
 * or signs in. `POST /invitations/lookup` → teaser → register, sign in, or decline.
 * A signed-in visitor goes straight to the claim (W24), unless the link asked to decline.
 */
const { t } = useI18n()
const auth = useAuthStore()
const localePath = useLocalePath()
const pending = usePendingToken('invitation')
const pendingEmail = useState<string | null>('bafo:pending-invitation-email', () => null)
const { message } = useErrorMessage()

useSeoMeta({ title: () => t('invitations.landing.title'), robots: 'noindex, nofollow' })

type State = 'loading' | 'ready' | 'invalid' | 'error' | 'declined' | 'missing'
const state = ref<State>('loading')
const lookup = ref<InvitationLookup | null>(null)
const loadError = ref<unknown>(null)
const declineOpen = ref(false)
const declining = ref(false)
const declineError = ref<string | null>(null)
let token: string | null = null

const canClaimNow = computed(() => auth.isAuthenticated)

async function load(): Promise<void> {
  const fromLink = pending.captureFromLocation()
  token = fromLink?.token ?? pending.read()
  if (!token) {
    state.value = 'missing'
    return
  }
  if (canClaimNow.value && fromLink?.action !== 'decline') {
    await navigateTo(localePath('/dashboard/invitations/claim'), { replace: true })
    return
  }
  state.value = 'loading'
  try {
    lookup.value = await lookupInvitation(token)
    pendingEmail.value = lookup.value.invitation.email_masked
    state.value = 'ready'
    if (fromLink?.action === 'decline') declineOpen.value = true
  }
  catch (error) {
    if (error instanceof ApiError && (error.code === 'invitation_invalid' || error.isNotFound)) {
      pending.clear()
      state.value = 'invalid'
    }
    else {
      loadError.value = error
      state.value = 'error'
    }
  }
}

onMounted(load)

async function decline(reason: string | null): Promise<void> {
  if (!token) return
  declining.value = true
  declineError.value = null
  try {
    await declineInvitationByToken(token, reason)
    pending.clear()
    declineOpen.value = false
    state.value = 'declined'
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'invalid_state_transition') {
      declineError.value = t('invitations.decline.not_possible')
    }
    else if (error instanceof ApiError && (error.code === 'invitation_invalid' || error.isNotFound)) {
      pending.clear()
      declineOpen.value = false
      state.value = 'invalid'
    }
    else {
      declineError.value = message(error)
    }
  }
  finally {
    declining.value = false
  }
}
</script>

<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-8 px-4 py-10 sm:px-6 sm:py-14">
    <div
      v-if="state === 'loading'"
      class="flex flex-col gap-4"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-10 w-1/2" />
      <UiSkeleton class="h-8 w-3/4" />
      <UiSkeleton :lines="4" />
    </div>

    <UiEmptyState
      v-else-if="state === 'invalid' || state === 'missing'"
      :icon="LinkIcon"
      :title="t('invitations.landing.invalid_title')"
      :description="t('errors.invitation_invalid')"
    >
      <UiButton
        to="/"
        variant="secondary"
      >
        {{ t('errors.page.back_home') }}
      </UiButton>
    </UiEmptyState>

    <UiErrorState
      v-else-if="state === 'error'"
      :error="loadError"
      @retry="load"
    />

    <UiEmptyState
      v-else-if="state === 'declined'"
      :icon="CircleCheck"
      :title="t('invitations.decline.done_title')"
      :description="t('invitations.decline.done_body')"
    >
      <UiButton
        to="/"
        variant="secondary"
      >
        {{ t('errors.page.back_home') }}
      </UiButton>
    </UiEmptyState>

    <template v-else-if="lookup">
      <div>
        <p class="text-sm font-semibold text-brand">
          {{ t('invitations.landing.eyebrow') }}
        </p>
        <h1 class="mt-1 text-2xl font-bold text-fg sm:text-3xl">
          {{ t('invitations.landing.title') }}
        </h1>
      </div>

      <UiCard>
        <InvitationsTeaser
          :competition="lookup.competition"
          :join-deadline="lookup.invitation.join_deadline"
          :masked-email="lookup.invitation.email_masked"
          :sponsored="lookup.invitation.sponsored"
        />
      </UiCard>

      <UiCard
        v-if="lookup.competition.rules_summary.length > 0"
        :title="t('invitations.landing.rules_title')"
      >
        <CompetitionsRulesSummary :lines="lookup.competition.rules_summary" />
      </UiCard>

      <div class="flex flex-col gap-3">
        <template v-if="auth.isAuthenticated">
          <UiButton
            v-if="canClaimNow"
            to="/dashboard/invitations/claim"
            size="lg"
            block
          >
            {{ t('invitations.landing.continue_signed_in') }}
          </UiButton>
          <UiAlert
            v-else
            tone="info"
          >
            {{ t('invitations.landing.claim_in_dashboard') }}
          </UiAlert>
        </template>
        <template v-else>
          <UiButton
            v-if="lookup.next_step === 'register'"
            to="/auth/register"
            size="lg"
            block
            :icon="UserPlus"
          >
            {{ t('invitations.landing.register_cta') }}
          </UiButton>
          <UiButton
            :to="{ path: '/auth/login' }"
            size="lg"
            block
            :variant="lookup.next_step === 'register' ? 'secondary' : 'primary'"
            :icon="LogIn"
            flip-icons
          >
            {{ t('invitations.landing.login_cta') }}
          </UiButton>
        </template>
        <UiButton
          variant="danger-ghost"
          block
          @click="declineOpen = true"
        >
          {{ t('invitations.decline.open') }}
        </UiButton>
      </div>

      <InvitationsDeclineDialog
        v-model:open="declineOpen"
        :busy="declining"
        :error="declineError"
        @confirm="decline"
      />
    </template>
  </div>
</template>
