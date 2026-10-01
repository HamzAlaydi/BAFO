import type { AppLocale } from '~/types/api'

/**
 * The active locale, narrowed to the locales this app ships. Reads the global i18n instance,
 * so it also works outside component setup (stores, plugins, middleware).
 */
export function useAppLocale() {
  const { $i18n } = useNuxtApp()
  return computed<AppLocale>(() => ($i18n.locale.value === 'en' ? 'en' : 'ar'))
}
