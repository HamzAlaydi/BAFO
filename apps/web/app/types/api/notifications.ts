/** In-app notifications and devices (API.md §1.8, §2.11, §5; ARCHITECTURE §11.3). */
import type { IsoDateTime, PageQuery, Ulid } from './common'

/** The notification catalogue (ARCHITECTURE §11.3). Unknown types must still render. */
export type NotificationType
  = | 'competition.invited'
    | 'competition.updated'
    | 'competition.opened'
    | 'competition.final_window_started'
    | 'competition.closing_soon'
    | 'competition.extended'
    | 'competition.closed'
    | 'competition.cancelled'
    | 'competition.not_awarded'
    | 'offer.received'
    | 'standing.lost_lead'
    | 'bafo.invited'
    | 'bafo.ended'
    | 'award.won'
    | 'award.not_selected'
    | 'award.revoked'
    | 'offer.voided'
    | 'comment.created'
    | 'invitation.joined'
    | 'invitation.declined'
    | 'subscription.activated'
    | 'subscription.expiring'
    | 'subscription.expired'
    | 'payment.failed'
    | 'invoice.issued'
    | 'sponsorship.unused_passes'
    | 'sponsorship.publish_failed'
    | 'voucher.issued'
    | 'webhook.endpoint_disabled'
    | 'import.finished'
    | 'export.finished'

export interface Notification {
  id: Ulid
  type: NotificationType | string
  /** Rendered in the request locale. */
  title: string
  body: string
  subject: { type: string, id: Ulid } | null
  /** Locale-free client route, e.g. `/competitions/01j…/live` (CONVENTIONS §4.3). */
  route: string | null
  params: Record<string, unknown>
  read_at: IsoDateTime | null
  created_at: IsoDateTime
}

export interface NotificationListQuery extends PageQuery {
  unread?: boolean
}

export interface UnreadCount {
  unread_count: number
}

export type DevicePlatform = 'ios' | 'android' | 'web'

export interface Device {
  id: Ulid
  platform: DevicePlatform
  device_name: string | null
  app_version: string | null
  last_seen_at: IsoDateTime | null
}

export interface RegisterDeviceRequest {
  token: string
  platform: DevicePlatform
  device_name?: string | null
  app_version?: string | null
}

/** `notification.created` on `private-user.{userId}`. */
export interface NotificationCreatedEvent {
  notification: Notification
  unread_count: number
}

/** `notifications.unread_count` on `private-user.{userId}`. */
export type UnreadCountEvent = UnreadCount
