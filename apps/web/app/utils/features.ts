import { FEATURE_FLAGS, type AppFeatures, type FeatureFlag, type FeatureFlags, type ReleaseScope } from '~/types/api/platform'

/**
 * Release-scope feature flags (RELEASE_SCOPE.md §1.3–§1.4), as pure functions.
 *
 * The server derives `features.flags` from `platform.release_scope`; the web only reads the map.
 * `CORE_FEATURE_FLAGS` mirrors the `core` column of §1.3 and is what the app assumes before the config
 * has loaded, or when an older server does not send `flags` at all. Keys missing from a map the server
 * did send read as `false` (a newer server may add keys; clients ignore unknown ones).
 */
export const CORE_FEATURE_FLAGS: FeatureFlags = {
  team_management: false,
  vendor_directory: false,
  integrations_api: false,
  csv_import_export: false,
  sponsorship: false,
  bafo_round: false,
  sealed_format: false,
  advanced_rules: false,
  final_pricing_window: false,
  deletion_approval: false,
  deleted_competitions: false,
  offer_report: false,
  login_as: false,
  google_signin: false,
  dark_mode: false,
  billing_invoices: false,
  custom_plan_quote: false,
  coupons: false,
  qa_comments: true,
  attachments: true,
  extend_competition: false,
  cancel_competition: true,
}

/** The `full` column of §1.3 with `sponsorship.enabled = true` (the setting's default). */
export const FULL_FEATURE_FLAGS: FeatureFlags = {
  ...CORE_FEATURE_FLAGS,
  team_management: true,
  vendor_directory: true,
  integrations_api: true,
  csv_import_export: true,
  sponsorship: true,
  bafo_round: true,
  sealed_format: true,
  advanced_rules: true,
  final_pricing_window: true,
  dark_mode: true,
  billing_invoices: true,
  custom_plan_quote: true,
  coupons: true,
  extend_competition: true,
}

/** The whole map for a scope, as a server with that setting would send it. */
export function featureFlagsForScope(scope: ReleaseScope): FeatureFlags {
  return { ...(scope === 'full' ? FULL_FEATURE_FLAGS : CORE_FEATURE_FLAGS) }
}

/**
 * Normalises `AppConfig.features` to a complete map: no config or no `flags` → the core defaults;
 * a map from the server → every catalogue key read as `=== true`.
 */
export function resolveFeatureFlags(features: Partial<AppFeatures> | null | undefined): FeatureFlags {
  const sent = features?.flags as Partial<Record<string, unknown>> | undefined
  if (!sent || typeof sent !== 'object') return { ...CORE_FEATURE_FLAGS }
  const result = {} as FeatureFlags
  for (const flag of FEATURE_FLAGS) result[flag] = sent[flag] === true
  return result
}

/** `true` when any of the given flags is on (nav entries that cover several features). */
export function anyFeatureEnabled(flags: FeatureFlags, needed: FeatureFlag | readonly FeatureFlag[] | undefined): boolean {
  if (needed === undefined) return true
  const list: readonly FeatureFlag[] = typeof needed === 'string' ? [needed] : needed
  return list.some(flag => flags[flag])
}

export function isFeatureFlag(value: unknown): value is FeatureFlag {
  return typeof value === 'string' && (FEATURE_FLAGS as readonly string[]).includes(value)
}
