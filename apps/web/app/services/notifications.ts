/** In-app notifications and devices (API.md §1.8). The badge and bell live in `useNotificationsStore()`. */
import type { ApiResponse, Page } from '~/types/api/common'
import type { Device, Notification, NotificationListQuery, RegisterDeviceRequest, UnreadCount } from '~/types/api/notifications'
import { getData, getEnvelope, pageFrom, send, sendData, seg } from './http'

export interface NotificationPage extends Page<Notification> {
  unread_count: number
}

/** Newest first, plus `meta.unread_count`. */
export async function listNotifications(query: NotificationListQuery = {}): Promise<NotificationPage> {
  const response: ApiResponse<Notification[]> = await getEnvelope<Notification[]>('/notifications', {
    unread: query.unread ? true : undefined,
    page: query.page,
    per_page: query.per_page ?? 20,
  })
  const unread = response.meta?.unread_count
  return { ...pageFrom(response), unread_count: typeof unread === 'number' ? unread : 0 }
}

export function fetchUnreadCount(): Promise<UnreadCount> {
  return getData<UnreadCount>('/notifications/unread-count')
}

export function markNotificationRead(id: string): Promise<Notification> {
  return sendData<Notification>('POST', `/notifications/${seg(id)}/read`)
}

export function markAllNotificationsRead(): Promise<UnreadCount> {
  return sendData<UnreadCount>('POST', '/notifications/read-all')
}

export function deleteNotification(id: string): Promise<void> {
  return send('DELETE', `/notifications/${seg(id)}`)
}

export function deleteAllNotifications(): Promise<void> {
  return send('DELETE', '/notifications')
}

/** Upsert by token (web push is out of scope for the MVP; kept for completeness). */
export function registerDevice(body: RegisterDeviceRequest): Promise<Device> {
  return sendData<Device>('POST', '/devices', body)
}

export function deleteDevice(id: string): Promise<void> {
  return send('DELETE', `/devices/${seg(id)}`)
}
