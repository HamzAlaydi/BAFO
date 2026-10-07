import type { Component } from 'vue'
import { Building2, ClipboardList, Contact, CreditCard, Handshake, LayoutDashboard, Plug, Users } from '@lucide/vue'
import type { Permission } from '~/types/api/identity'
import type { FeatureFlag } from '~/types/api/platform'

/**
 * Dashboard side navigation (SCREENS §2.3). Entries are hidden when the user lacks `permission` (CD8)
 * or when none of the entry's release-scope `feature` flags is on (RELEASE_SCOPE.md §5).
 */
export interface DashboardNavItem {
  /** i18n key suffix: `nav.<key>`. */
  key: string
  /** Unlocalised path; links go through `localePath`. */
  to: string
  icon: Component
  /** Shown only when `Me.permissions` includes it. */
  permission?: Permission
  /** Shown only when at least one of these flags is on (`features.flags`). */
  feature?: FeatureFlag | FeatureFlag[]
}

export interface DashboardNavGroup {
  /** i18n key suffix: `nav.groups.<key>`; null for the ungrouped first entry. */
  key: string | null
  items: DashboardNavItem[]
}

export const DASHBOARD_NAV: readonly DashboardNavGroup[] = [
  {
    key: null,
    items: [
      { key: 'overview', to: '/dashboard', icon: LayoutDashboard },
    ],
  },
  {
    key: 'competitions',
    items: [
      { key: 'my_competitions', to: '/dashboard/competitions', icon: ClipboardList, permission: 'competitions.create' },
      { key: 'participating', to: '/dashboard/participating', icon: Handshake },
      { key: 'vendors', to: '/dashboard/vendors', icon: Contact, permission: 'competitions.create', feature: 'vendor_directory' },
    ],
  },
  {
    key: 'organization',
    items: [
      { key: 'team', to: '/dashboard/team', icon: Users, permission: 'team.manage', feature: 'team_management' },
      { key: 'organization', to: '/dashboard/organization', icon: Building2 },
      { key: 'billing', to: '/dashboard/billing', icon: CreditCard, permission: 'billing.view' },
      { key: 'integrations', to: '/dashboard/integrations', icon: Plug, permission: 'integrations.manage', feature: ['integrations_api', 'csv_import_export'] },
    ],
  },
]

/**
 * The primary "Create competition" action above the list (SCREENS §2.3, S10): shown with
 * `competitions.create`; disabled with an explanation without `entitlements.can_issue`.
 */
export const CREATE_COMPETITION_ACTION = {
  to: '/dashboard/competitions/new',
  permission: 'competitions.create' as Permission,
}
