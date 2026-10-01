/**
 * Sign-in, registration and forgot-password pages: visitors who already hold a session go to the
 * same-origin `redirect` target or the dashboard. `verify`, `reset` and `accept-invite` work in both
 * states and do not use this middleware (SCREENS §2.1).
 */
export default defineNuxtRouteMiddleware((to) => {
  const auth = useAuthStore()
  if (!auth.isAuthenticated) return
  return navigateTo(safeRedirect(to.query.redirect, useLocalePath()('/dashboard')))
})
