/**
 * Where to go after signing in, verifying the e-mail or accepting a team invitation
 * (SCREENS W04, W06, W09): the pending competition invitation claim (W24) when a token is waiting,
 * otherwise the same-origin `redirect`, otherwise the dashboard.
 */
export function usePostSignIn() {
  const localePath = useLocalePath()
  const pendingInvitation = usePendingToken('invitation')

  function target(redirect?: unknown): string {
    if (pendingInvitation.read()) return localePath('/dashboard/invitations/claim')
    const fallback = localePath('/dashboard')
    const safe = safeRedirect(redirect, fallback)
    // Never bounce back into the auth pages.
    return /^\/(ar|en)\/auth(\/|$)/.test(safe) ? fallback : safe
  }

  async function go(redirect?: unknown): Promise<void> {
    await navigateTo(target(redirect), { replace: true })
  }

  return { target, go }
}
