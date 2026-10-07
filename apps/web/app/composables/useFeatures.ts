import type { FeatureFlag } from '~/types/api/platform'

/**
 * Release-scope feature flags for components (RELEASE_SCOPE.md §1, §5). Reads `features.flags` from the
 * app config store; before the config has loaded the core defaults apply, so nothing hidden in the
 * first release flashes on screen.
 *
 *   const features = useFeatures()
 *   features.enabled('team_management')          // one flag
 *   features.anyEnabled(['integrations_api', 'csv_import_export'])
 *   features.flags.value.sponsorship             // the whole map, reactive
 */
export function useFeatures() {
  const appConfig = useAppConfigStore()

  const flags = computed(() => appConfig.flags)
  const releaseScope = computed(() => appConfig.releaseScope)
  /** The server's map has been read (false while loading or when the config request failed). */
  const ready = computed(() => appConfig.loaded)

  function enabled(flag: FeatureFlag): boolean {
    return appConfig.flags[flag] === true
  }

  function anyEnabled(needed: FeatureFlag | readonly FeatureFlag[] | undefined): boolean {
    return anyFeatureEnabled(appConfig.flags, needed)
  }

  return { flags, releaseScope, ready, enabled, anyEnabled }
}
