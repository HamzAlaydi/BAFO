import { describe, expect, it } from 'vitest'
import { FEATURE_FLAGS } from '~/types/api/platform'
import { anyFeatureEnabled, CORE_FEATURE_FLAGS, featureFlagsForScope, FULL_FEATURE_FLAGS, isFeatureFlag, resolveFeatureFlags } from '~/utils/features'

/** RELEASE_SCOPE.md §1.3: the 22 flags and their values per scope. */
describe('release-scope feature flags', () => {
  it('lists the 22 catalogue flags in order', () => {
    expect(FEATURE_FLAGS).toHaveLength(22)
    expect(FEATURE_FLAGS.slice(0, 4)).toEqual(['team_management', 'vendor_directory', 'integrations_api', 'csv_import_export'])
    expect(FEATURE_FLAGS.at(-1)).toBe('cancel_competition')
    expect(Object.keys(CORE_FEATURE_FLAGS).sort()).toEqual([...FEATURE_FLAGS].sort())
    expect(Object.keys(FULL_FEATURE_FLAGS).sort()).toEqual([...FEATURE_FLAGS].sort())
  })

  it('keeps only the core features on in `core`, and the reserved flags off in both scopes', () => {
    const coreOn = Object.entries(CORE_FEATURE_FLAGS).filter(([, on]) => on).map(([flag]) => flag).sort()
    expect(coreOn).toEqual(['attachments', 'cancel_competition', 'qa_comments'])
    for (const reserved of ['deletion_approval', 'deleted_competitions', 'offer_report', 'login_as', 'google_signin'] as const) {
      expect(CORE_FEATURE_FLAGS[reserved]).toBe(false)
      expect(FULL_FEATURE_FLAGS[reserved]).toBe(false)
    }
    const fullOff = Object.entries(FULL_FEATURE_FLAGS).filter(([, on]) => !on).map(([flag]) => flag).sort()
    expect(fullOff).toEqual(['deleted_competitions', 'deletion_approval', 'google_signin', 'login_as', 'offer_report'])
    expect(featureFlagsForScope('core')).toEqual(CORE_FEATURE_FLAGS)
    expect(featureFlagsForScope('full')).toEqual(FULL_FEATURE_FLAGS)
  })

  it('reads the core defaults when the config or its flags are missing (older servers)', () => {
    expect(resolveFeatureFlags(null)).toEqual(CORE_FEATURE_FLAGS)
    expect(resolveFeatureFlags(undefined)).toEqual(CORE_FEATURE_FLAGS)
    expect(resolveFeatureFlags({ sponsorship: true } as never)).toEqual(CORE_FEATURE_FLAGS)
  })

  it('reads a server map key by key: missing keys are false, unknown keys are ignored', () => {
    const flags = resolveFeatureFlags({
      release_scope: 'full',
      sponsorship: true,
      flags: { team_management: true, sponsorship: true, qa_comments: 'yes', brand_new_feature: true } as never,
    })
    expect(flags.team_management).toBe(true)
    expect(flags.sponsorship).toBe(true)
    expect(flags.qa_comments).toBe(false)
    expect(flags.vendor_directory).toBe(false)
    expect('brand_new_feature' in flags).toBe(false)
    expect(Object.keys(flags)).toHaveLength(22)
  })

  it('answers "any of" for nav entries that cover several features', () => {
    expect(anyFeatureEnabled(CORE_FEATURE_FLAGS, undefined)).toBe(true)
    expect(anyFeatureEnabled(CORE_FEATURE_FLAGS, 'qa_comments')).toBe(true)
    expect(anyFeatureEnabled(CORE_FEATURE_FLAGS, 'team_management')).toBe(false)
    expect(anyFeatureEnabled(CORE_FEATURE_FLAGS, ['integrations_api', 'csv_import_export'])).toBe(false)
    expect(anyFeatureEnabled({ ...CORE_FEATURE_FLAGS, csv_import_export: true }, ['integrations_api', 'csv_import_export'])).toBe(true)
    expect(isFeatureFlag('dark_mode')).toBe(true)
    expect(isFeatureFlag('release_scope')).toBe(false)
  })
})
