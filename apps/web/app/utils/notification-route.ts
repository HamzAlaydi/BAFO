/**
 * Maps a notification's locale-free `route` to a dashboard path (SCREENS S11, CONVENTIONS §4.3).
 * The result has no locale prefix: pass it through `localePath()`. Unknown or malformed routes fall
 * back to the notifications list.
 */
export const NOTIFICATIONS_PATH = '/dashboard/notifications'

const ULID = '[0-9A-Za-z]{26}'

const ROUTES: ReadonlyArray<{ pattern: RegExp, to: (match: RegExpExecArray) => string }> = [
  { pattern: new RegExp(`^/competitions/(${ULID})$`), to: m => `/dashboard/competitions/${m[1]}` },
  { pattern: new RegExp(`^/competitions/(${ULID})/live$`), to: m => `/dashboard/competitions/${m[1]}/live` },
  { pattern: new RegExp(`^/competitions/(${ULID})/qa$`), to: m => `/dashboard/competitions/${m[1]}/qa` },
  { pattern: /^\/billing$/, to: () => '/dashboard/billing' },
  { pattern: new RegExp(`^/billing/invoices/(${ULID})$`), to: m => `/dashboard/billing/invoices/${m[1]}` },
  { pattern: /^\/integrations$/, to: () => '/dashboard/integrations' },
  { pattern: /^\/notifications$/, to: () => NOTIFICATIONS_PATH },
]

export function notificationPath(route: string | null | undefined): string {
  if (!route) return NOTIFICATIONS_PATH
  const clean = route.split(/[?#]/)[0]?.replace(/\/+$/, '') ?? ''
  for (const { pattern, to } of ROUTES) {
    const match = pattern.exec(clean)
    if (match) return to(match)
  }
  return NOTIFICATIONS_PATH
}

/** Icon groups of SCREENS S11 (`notifications.type.*`). */
export type NotificationIconGroup
  = | 'invitation'
    | 'update'
    | 'timer'
    | 'flag'
    | 'offer'
    | 'standing'
    | 'bafo'
    | 'award'
    | 'qa'
    | 'billing'
    | 'integrations'
    | 'other'

const ICON_GROUP: Record<string, NotificationIconGroup> = {
  'competition.invited': 'invitation',
  'invitation.joined': 'invitation',
  'invitation.declined': 'invitation',
  'competition.updated': 'update',
  'competition.opened': 'timer',
  'competition.final_window_started': 'timer',
  'competition.closing_soon': 'timer',
  'competition.extended': 'timer',
  'competition.closed': 'flag',
  'competition.cancelled': 'flag',
  'competition.not_awarded': 'flag',
  'offer.received': 'offer',
  'offer.voided': 'offer',
  'standing.lost_lead': 'standing',
  'bafo.invited': 'bafo',
  'bafo.ended': 'bafo',
  'award.won': 'award',
  'award.not_selected': 'award',
  'award.revoked': 'award',
  'comment.created': 'qa',
  'subscription.activated': 'billing',
  'subscription.expiring': 'billing',
  'subscription.expired': 'billing',
  'payment.failed': 'billing',
  'invoice.issued': 'billing',
  'sponsorship.unused_passes': 'billing',
  'sponsorship.publish_failed': 'billing',
  'voucher.issued': 'billing',
  'webhook.endpoint_disabled': 'integrations',
  'import.finished': 'integrations',
  'export.finished': 'integrations',
}

export function notificationIconGroup(type: string): NotificationIconGroup {
  return ICON_GROUP[type] ?? 'other'
}
