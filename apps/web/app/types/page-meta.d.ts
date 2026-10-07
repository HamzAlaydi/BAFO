import type { FeatureFlag } from './api/platform'

declare module '#app' {
  interface PageMeta {
    /** Auth layout: use the wide form column (registration). */
    authWide?: boolean
    /**
     * `middleware: 'feature'`: the page needs one of these release-scope flags (RELEASE_SCOPE.md §5).
     * When none is on, the dashboard layout renders the 404 state in place (no redirect), or, when
     * `featureFallback` is set, the visitor is sent there with a notice.
     */
    feature?: FeatureFlag | FeatureFlag[]
    featureFallback?: string
  }
}

export {}
