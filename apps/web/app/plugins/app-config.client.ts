import type { Pinia } from 'pinia'

/**
 * Loads `GET /app-config` once the app starts in the browser (SCREENS §2.1), without blocking the
 * first render, and reloads it when the language changes (its maintenance message is localised).
 * Also keeps the signed-in user's stored language in step with the UI language (SCREENS S1).
 */
export default defineNuxtPlugin((nuxtApp) => {
  const pinia = nuxtApp.$pinia as Pinia
  const appConfig = useAppConfigStore(pinia)
  void appConfig.load()

  watch(() => nuxtApp.$i18n.locale.value, (locale, previous) => {
    if (locale === previous) return
    void appConfig.load(true)
    const auth = useAuthStore(pinia)
    if (auth.hasSession) void auth.syncLocale(locale === 'en' ? 'en' : 'ar')
  })
})
