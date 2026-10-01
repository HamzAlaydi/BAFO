/**
 * Pages that need a signed-in user (`/dashboard/**`). Loads `Me` once per visit; a missing or rejected
 * token sends the visitor to `/auth/login` with a same-origin `redirect` back (SCREENS S8, R-W1).
 * An unverified e-mail goes to the OTP page.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuthStore()
  const localePath = useLocalePath()
  const toLogin = () => navigateTo(localePath({ path: '/auth/login', query: { redirect: to.fullPath } }))

  if (!auth.isAuthenticated) return toLogin()

  if (!auth.me) {
    try {
      await auth.fetchMe()
    }
    catch (error) {
      if (error instanceof ApiError && error.isUnauthenticated) {
        auth.clear()
        return toLogin()
      }
      // API unreachable or failing: keep the token and let the page show its own error state.
    }
  }

  if (auth.gate === 'email_not_verified') {
    return navigateTo(localePath({ path: '/auth/verify', query: auth.user?.email ? { email: auth.user.email } : {} }))
  }
})
