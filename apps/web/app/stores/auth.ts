import * as identity from '~/services/identity'
import type { AppLocale } from '~/types/api/common'
import type { AuthTokenPayload, Me, Permission } from '~/types/api/identity'

/** Account states that replace the dashboard with a full-page gate (SCREENS S8). */
export type AccountGate = 'account_inactive' | 'organization_suspended' | 'email_not_verified'

/** Why the session ended: a normal sign-out, or a token the API rejected (401). */
export type SessionEndReason = 'signed_out' | 'expired'

/**
 * The gate implied by a `Me` payload. `GET /me` is exempt from the API account gate
 * (ARCHITECTURE §8.1), so the statuses are checked here too.
 */
export function gateFromMe(me: Me): AccountGate | null {
  if (me.organization.status === 'suspended') return 'organization_suspended'
  if (me.user.status === 'pending_verification' || me.user.email_verified_at === null) return 'email_not_verified'
  if (me.user.status !== 'active' || me.membership.status !== 'active') return 'account_inactive'
  return null
}

/** `Me` without the token fields, so the plain-text token never lands in store state. */
export function meFromTokenPayload(payload: AuthTokenPayload): Me {
  return {
    user: payload.user,
    organization: payload.organization,
    membership: payload.membership,
    permissions: payload.permissions,
    subscription: payload.subscription,
    entitlements: payload.entitlements,
    unread_notifications_count: payload.unread_notifications_count,
  }
}

/**
 * Session state for first-party clients: the Sanctum personal access token (the `bafo_token`
 * cookie, never in Pinia state or a URL) and the current `Me` (API.md §2.3).
 *
 * Render controls from server decisions only: `can(permission)`, `entitlements`, `organization.features`.
 */
export const useAuthStore = defineStore('auth', () => {
  const token = useAuthToken()
  const me = ref<Me | null>(null)
  const apiGate = ref<AccountGate | null>(null)
  const endedReason = ref<SessionEndReason | null>(null)

  // Exposed as a getter, never as state: Pinia state is serialised into the SSR payload (page HTML).
  const accessToken = computed(() => token.value)
  const isAuthenticated = computed(() => Boolean(token.value))
  const hasSession = computed(() => isAuthenticated.value && me.value !== null)

  const user = computed(() => me.value?.user ?? null)
  const organization = computed(() => me.value?.organization ?? null)
  const membership = computed(() => me.value?.membership ?? null)
  const permissions = computed<Permission[]>(() => me.value?.permissions ?? [])
  const subscription = computed(() => me.value?.subscription ?? null)
  const entitlements = computed(() => me.value?.entitlements ?? null)
  const features = computed(() => me.value?.organization.features ?? null)
  const isOwner = computed(() => me.value?.membership.role === 'owner')

  /** The account gate to show, from an API error or from the loaded `Me`. */
  const gate = computed<AccountGate | null>(() => apiGate.value ?? (me.value ? gateFromMe(me.value) : null))

  function can(permission: Permission): boolean {
    return permissions.value.includes(permission)
  }

  function applyMe(next: Me): void {
    me.value = next
    apiGate.value = null
    endedReason.value = null
  }

  /** Stores the token and the session from a sign-in, OTP verification or team-invitation accept. */
  function setSession(payload: AuthTokenPayload): void {
    token.value = payload.token
    applyMe(meFromTokenPayload(payload))
  }

  /** Forgets the session locally (token cookie included). */
  function clear(): void {
    token.value = null
    me.value = null
    apiGate.value = null
  }

  /** Ends the session and remembers why (the dashboard shows "session expired" for `expired`). */
  function endSession(reason: SessionEndReason): void {
    clear()
    endedReason.value = reason
  }

  function raiseGate(next: AccountGate): void {
    apiGate.value = next
  }

  /** Loads `GET /me`. Throws `ApiError`; a 401 ends the session (API client). */
  async function fetchMe(): Promise<Me> {
    const next = await identity.fetchMe()
    applyMe(next)
    return next
  }

  async function login(email: string, password: string): Promise<void> {
    setSession(await identity.login({ email: email.trim(), password, device_name: currentDeviceName() }))
  }

  /** `POST /auth/otp/verify` (e-mail verification) → signed in. */
  async function verifyEmail(email: string, code: string): Promise<void> {
    setSession(await identity.verifyOtp({ email: email.trim(), code, purpose: 'email_verification', device_name: currentDeviceName() }))
  }

  /** `POST /auth/team-invitations/accept` → signed in. */
  async function acceptTeamInvitation(tokenValue: string, password: string, passwordConfirmation: string): Promise<void> {
    setSession(await identity.acceptTeamInvitation({
      token: tokenValue,
      password,
      password_confirmation: passwordConfirmation,
      device_name: currentDeviceName(),
      accept_terms: true,
    }))
  }

  /**
   * Keeps `users.locale` (mail and push language) in step with the UI language (SCREENS S1).
   * Best effort: a failure never blocks the language switch.
   */
  async function syncLocale(locale: AppLocale): Promise<void> {
    if (!me.value || me.value.user.locale === locale) return
    try {
      applyMe(await identity.updateMe({ locale }))
    }
    catch {
      // The UI language already changed; the stored preference is retried on the next switch.
    }
  }

  /** Revokes the token on the server (best effort) and always clears the local session. */
  async function logout(): Promise<void> {
    try {
      if (token.value) await identity.logout()
    }
    catch {
      // The token may already be invalid; signing out locally is what matters.
    }
    finally {
      endSession('signed_out')
    }
  }

  return {
    token: accessToken,
    me,
    user,
    organization,
    membership,
    permissions,
    subscription,
    entitlements,
    features,
    isOwner,
    isAuthenticated,
    hasSession,
    gate,
    endedReason,
    can,
    applyMe,
    setSession,
    clear,
    endSession,
    raiseGate,
    fetchMe,
    login,
    verifyEmail,
    acceptTeamInvitation,
    syncLocale,
    logout,
  }
})
