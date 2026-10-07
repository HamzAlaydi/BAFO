export type ThemePreference = 'system' | 'light' | 'dark'

export const THEME_PREFERENCES: readonly ThemePreference[] = ['system', 'light', 'dark']

/**
 * Colour-scheme preference, kept in a cookie so SSR renders the right `data-theme` without a flash.
 * 'system' leaves `data-theme` unset and the CSS follows `prefers-color-scheme`.
 *
 * Release scope (RELEASE_SCOPE.md §1.3 `dark_mode`): while the flag is off, `dataTheme` is always
 * `'light'` and the preference is kept untouched for a later release. The flag reads as off until the
 * app config has loaded, so the first release never paints a dark frame.
 */
export function useTheme() {
  const preference = useSharedCookie<ThemePreference>('bafo_theme', {
    default: () => 'system',
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 365,
  })
  const appConfig = useAppConfigStore()

  const darkModeAvailable = computed(() => appConfig.featureEnabled('dark_mode'))
  const dataTheme = computed<ThemePreference | undefined>(() => {
    if (!darkModeAvailable.value) return 'light'
    return preference.value === 'system' ? undefined : preference.value
  })

  function setPreference(value: ThemePreference): void {
    preference.value = THEME_PREFERENCES.includes(value) ? value : 'system'
  }

  return { preference: readonly(preference), dataTheme, darkModeAvailable, setPreference }
}
