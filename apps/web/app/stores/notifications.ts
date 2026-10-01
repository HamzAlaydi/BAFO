import * as service from '~/services/notifications'
import type { Notification, NotificationCreatedEvent, UnreadCountEvent } from '~/types/api/notifications'

/** How many notifications the bell menu shows (SCREENS §2.3). */
export const BELL_SIZE = 10

type CreatedListener = (notification: Notification) => void

/**
 * The unread badge and the bell's latest notifications (SCREENS §2.1).
 *
 * - The count starts from `Me.unread_notifications_count` and follows `private-user.{id}`:
 *   `notification.created` (prepends, updates the count) and `notifications.unread_count`.
 * - Marking as read is optimistic (CD9 allows it for simple list edits) and reverts on failure.
 * - Pages can react to new notifications with `onCreated()` (home refetch, notifications page 1).
 */
export const useNotificationsStore = defineStore('notifications', () => {
  const auth = useAuthStore()
  const unreadCount = ref(0)
  const latest = ref<Notification[]>([])
  const latestLoaded = ref(false)
  const loadingLatest = ref(false)
  const latestError = ref<ApiError | null>(null)
  const listeners = new Set<CreatedListener>()

  watch(() => auth.me?.unread_notifications_count, (count) => {
    if (typeof count === 'number') unreadCount.value = count
  }, { immediate: true })

  // A different user (or sign-out) must never see the previous user's notifications.
  watch(() => auth.user?.id ?? null, (id, previous) => {
    if (id !== previous) {
      latest.value = []
      latestLoaded.value = false
      latestError.value = null
    }
  })

  async function loadLatest(): Promise<void> {
    loadingLatest.value = true
    latestError.value = null
    try {
      const page = await service.listNotifications({ per_page: BELL_SIZE })
      latest.value = page.items.slice(0, BELL_SIZE)
      unreadCount.value = page.unread_count
      latestLoaded.value = true
    }
    catch (error) {
      latestError.value = error instanceof ApiError ? error : null
    }
    finally {
      loadingLatest.value = false
    }
  }

  function patchLatest(id: string, patch: Partial<Notification>): void {
    latest.value = latest.value.map(item => (item.id === id ? { ...item, ...patch } : item))
  }

  /** Marks one notification read. Returns the server copy; reverts and rethrows on failure. */
  async function markRead(notification: Notification): Promise<Notification> {
    if (notification.read_at) return notification
    const optimisticReadAt = new Date().toISOString()
    patchLatest(notification.id, { read_at: optimisticReadAt })
    unreadCount.value = Math.max(0, unreadCount.value - 1)
    try {
      const updated = await service.markNotificationRead(notification.id)
      patchLatest(notification.id, { read_at: updated.read_at })
      return updated
    }
    catch (error) {
      patchLatest(notification.id, { read_at: null })
      unreadCount.value += 1
      throw error
    }
  }

  async function markAllRead(): Promise<void> {
    const result = await service.markAllNotificationsRead()
    const now = new Date().toISOString()
    latest.value = latest.value.map(item => (item.read_at ? item : { ...item, read_at: now }))
    unreadCount.value = result.unread_count
  }

  async function remove(notification: Notification): Promise<void> {
    await service.deleteNotification(notification.id)
    latest.value = latest.value.filter(item => item.id !== notification.id)
    if (!notification.read_at) unreadCount.value = Math.max(0, unreadCount.value - 1)
  }

  async function removeAll(): Promise<void> {
    await service.deleteAllNotifications()
    latest.value = []
    unreadCount.value = 0
  }

  /** `notification.created` (realtime). */
  function applyCreated(event: NotificationCreatedEvent): void {
    unreadCount.value = event.unread_count
    if (!latest.value.some(item => item.id === event.notification.id)) {
      latest.value = [event.notification, ...latest.value].slice(0, BELL_SIZE)
    }
    for (const listener of listeners) listener(event.notification)
  }

  /** `notifications.unread_count` (realtime, after read, read-all or delete elsewhere). */
  function applyUnreadCount(event: UnreadCountEvent): void {
    unreadCount.value = event.unread_count
  }

  /** Listens for new notifications; stops automatically with the calling component or scope. */
  function onCreated(listener: CreatedListener): () => void {
    listeners.add(listener)
    const off = () => {
      listeners.delete(listener)
    }
    if (getCurrentScope()) onScopeDispose(off)
    return off
  }

  /**
   * Subscribes to `private-user.{id}` while signed in and realtime is configured. Call from a
   * component's setup (the dashboard layout); the watcher and the subscription end with it.
   */
  function startRealtime(): () => void {
    if (import.meta.server) return () => {}
    const { available } = useRealtimeStatus()
    let channelName: string | null = null
    let subscribedWith: object | null = null

    function leave(): void {
      if (channelName) useEcho()?.leave(channelName)
      channelName = null
      subscribedWith = null
    }

    // The token is a dependency too: a new token means a new Echo connection to subscribe on.
    const stop = watch([() => auth.user?.id ?? null, available, () => auth.token], ([userId, isAvailable]) => {
      const next = userId && isAvailable ? `user.${userId}` : null
      const echo = next ? useEcho() : null
      if (next === channelName && echo === subscribedWith) return
      leave()
      if (!next || !echo) return
      channelName = next
      subscribedWith = echo
      echo.private(next)
        .listen('.notification.created', (event: NotificationCreatedEvent) => applyCreated(event))
        .listen('.notifications.unread_count', (event: UnreadCountEvent) => applyUnreadCount(event))
    }, { immediate: true })

    return () => {
      stop()
      leave()
    }
  }

  return {
    unreadCount,
    latest,
    latestLoaded,
    loadingLatest,
    latestError,
    loadLatest,
    markRead,
    markAllRead,
    remove,
    removeAll,
    applyCreated,
    applyUnreadCount,
    onCreated,
    startRealtime,
  }
})
