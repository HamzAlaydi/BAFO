export type ThemePreference = 'system' | 'light' | 'dark'

export const THEME_PREFERENCES: readonly ThemePreference[] = ['system', 'light', 'dark']

/**
 * Colour-scheme preference, kept in a cookie so SSR renders the right `data-theme` without a flash.
 * 'system' leaves `data-theme` unset and the CSS follows `prefers-color-scheme`.
 */
export function useTheme() {
  const preference = useSharedCookie<ThemePreference>('bafo_theme', {
    default: () => 'system',
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 365,
  })

  const dataTheme = computed(() => (preference.value === 'system' ? undefined : preference.value))

  function setPreference(value: ThemePreference): void {
    preference.value = THEME_PREFERENCES.includes(value) ? value : 'system'
  }

  return { preference: readonly(preference), dataTheme, setPreference }
}
