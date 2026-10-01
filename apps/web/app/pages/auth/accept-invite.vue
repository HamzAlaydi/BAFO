<script setup lang="ts">
import { LinkIcon, UserRoundCheck } from '@lucide/vue'
import { lookupTeamInvitation } from '~/services/identity'
import type { TeamInvitationLookup } from '~/types/api/identity'

/**
 * W09 Accept team invitation · `/auth/accept-invite#t={token}` (SCREENS §2.4, CD7). Works signed in
 * or out. The token is read from the fragment, removed from the address bar and kept in
 * `sessionStorage` for this flow. Lookup → set a password and accept the terms →
 * `POST /auth/team-invitations/accept` → signed in → W10.
 */
definePageMeta({ layout: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const pending = usePendingToken('team_invitation')
const postSignIn = usePostSignIn()
const { message, bind } = useErrorMessage()

useSeoMeta({ title: () => t('auth.accept_invite.title') })

type State = 'loading' | 'ready' | 'invalid' | 'error'
const state = ref<State>('loading')
const invitation = ref<TeamInvitationLookup | null>(null)
const loadError = ref<unknown>(null)
const token = ref<string | null>(null)
const form = reactive({ password: '', password_confirmation: '', accept_terms: false })
const errors = ref<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const submitting = ref(false)
const signingOut = ref(false)

async function load(): Promise<void> {
  token.value = pending.captureFromLocation()?.token ?? pending.read()
  if (!token.value) {
    state.value = 'invalid'
    return
  }
  state.value = 'loading'
  try {
    invitation.value = await lookupTeamInvitation(token.value)
    state.value = 'ready'
  }
  catch (error) {
    if (error instanceof ApiError && (error.code === 'team_invitation_invalid' || error.isNotFound || error.isValidation)) {
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

function validate(): boolean {
  errors.value = {
    password: !form.password ? t('validation.required') : isStrongPassword(form.password) ? undefined : t('validation.password'),
    password_confirmation: form.password_confirmation === form.password ? undefined : t('validation.password_confirmation'),
    accept_terms: form.accept_terms ? undefined : t('validation.accept_terms'),
  }
  return Object.values(errors.value).every(value => !value)
}

async function accept(): Promise<void> {
  formError.value = null
  if (!token.value || !validate()) return
  submitting.value = true
  try {
    await auth.acceptTeamInvitation(token.value, form.password, form.password_confirmation)
    pending.clear()
    await postSignIn.go()
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'team_invitation_invalid') {
      pending.clear()
      state.value = 'invalid'
      return
    }
    const bound = bind(error, ['password', 'password_confirmation', 'accept_terms'])
    errors.value = { ...errors.value, ...bound.fields }
    formError.value = bound.unmatched[0] ?? (error instanceof ApiError && error.isValidation ? null : message(error))
  }
  finally {
    submitting.value = false
  }
}

async function signOutAndContinue(): Promise<void> {
  signingOut.value = true
  try {
    await auth.logout()
  }
  finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-8">
    <div
      v-if="state === 'loading'"
      class="flex flex-col gap-4"
      :aria-label="t('common.loading')"
      aria-busy="true"
    >
      <UiSkeleton class="h-8 w-2/3" />
      <UiSkeleton :lines="3" />
    </div>

    <UiEmptyState
      v-else-if="state === 'invalid'"
      :icon="LinkIcon"
      :title="t('auth.accept_invite.invalid_title')"
      :description="t('errors.team_invitation_invalid')"
    >
      <UiButton
        to="/auth/login"
        variant="secondary"
      >
        {{ t('auth.login.back_to_sign_in') }}
      </UiButton>
    </UiEmptyState>

    <UiErrorState
      v-else-if="state === 'error'"
      :error="loadError"
      @retry="load"
    />

    <template v-else-if="invitation">
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-4">
          <UiOrgLogo
            :name="invitation.organization.name"
            :src="invitation.organization.logo_url"
            size="lg"
          />
          <div class="min-w-0">
            <p class="text-sm text-fg-muted">
              {{ t('auth.accept_invite.invited_to') }}
            </p>
            <p class="truncate text-lg font-bold text-fg">
              {{ invitation.organization.name }}
            </p>
          </div>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-fg sm:text-3xl">
            {{ t('auth.accept_invite.title') }}
          </h1>
          <p class="mt-2 text-fg-muted">
            {{ t('auth.accept_invite.subtitle', { name: invitation.name }) }}
          </p>
        </div>
        <UiKeyValueList
          :items="[
            { key: 'email', label: t('auth.fields.email'), value: invitation.email, ltr: true },
            { key: 'role', label: t('team.fields.role'), value: t(`team.roles.${invitation.role}`) },
          ]"
          class="rounded-lg border border-line bg-surface px-4 py-3"
        />
        <p class="text-sm text-fg-muted">
          {{ t('auth.accept_invite.expires') }}
          <UiDateTime
            :value="invitation.expires_at"
            format="deadline"
          />
        </p>
      </div>

      <UiAlert
        v-if="auth.isAuthenticated"
        tone="warning"
        :icon="UserRoundCheck"
        :title="t('auth.accept_invite.signed_in_title')"
      >
        <p>{{ t('auth.accept_invite.signed_in_body') }}</p>
        <UiButton
          class="mt-3"
          size="sm"
          variant="secondary"
          :loading="signingOut"
          @click="signOutAndContinue"
        >
          {{ t('auth.accept_invite.sign_out_continue') }}
        </UiButton>
      </UiAlert>

      <template v-else>
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
          @submit.prevent="accept"
        >
          <UiPasswordInput
            v-model="form.password"
            :label="t('auth.fields.password')"
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
          <div class="flex flex-col gap-2">
            <UiCheckbox
              v-model="form.accept_terms"
              :label="t('auth.accept_invite.accept_terms')"
              required
              :error="errors.accept_terms"
            />
            <p class="ps-8 text-sm">
              <NuxtLinkLocale
                to="/legal/terms"
                target="_blank"
                class="link"
              >
                {{ t('legal.codes.terms') }}
              </NuxtLinkLocale>
              ·
              <NuxtLinkLocale
                to="/legal/privacy"
                target="_blank"
                class="link"
              >
                {{ t('legal.codes.privacy') }}
              </NuxtLinkLocale>
            </p>
          </div>
          <UiButton
            type="submit"
            size="lg"
            block
            :loading="submitting"
          >
            {{ t('auth.accept_invite.submit') }}
          </UiButton>
        </form>
      </template>
    </template>
  </div>
</template>
