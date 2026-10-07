import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { makeFlags, makeHome, makeMe, makeNotification, makeTokenPayload } from '../fixtures/api'
import type { AppConfig } from '~/types/api/platform'

const platform = vi.hoisted(() => ({ fetchAppConfig: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const notifications = vi.hoisted(() => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
  deleteNotification: vi.fn(),
  deleteAllNotifications: vi.fn(),
}))
const competitions = vi.hoisted(() => ({ fetchHome: vi.fn() }))
vi.mock('~/services/platform', () => platform)
vi.mock('~/services/catalog', () => catalog)
vi.mock('~/services/notifications', () => notifications)
vi.mock('~/services/competitions', () => competitions)

function appConfig(overrides: Partial<AppConfig> = {}): AppConfig {
  return {
    min_version: { ios: '1.0.0', android: '1.0.0' },
    latest_version: { ios: '1.0.0', android: '1.0.0' },
    store_links: { ios: '', android: '' },
    maintenance: { enabled: false, message: '' },
    support: { email: 'help@bafo.test', phone: '+966500000000', whatsapp: '' },
    realtime: { key: 'reverb-key', host: 'localhost', port: 8085, scheme: 'http' },
    legal: { terms: { version: '2026-10-01' } },
    features: { release_scope: 'full', sponsorship: true, flags: makeFlags('full') },
    currency: 'SAR',
    vat_rate_bp: 1500,
    supported_locales: ['ar', 'en'],
    server_time: '2026-10-01T09:00:00.000Z',
    ...overrides,
  }
}

const lookups = (name: string) => ({
  regions: [{ id: 'r1', code: 'RIY', name }],
  categories: [{ id: 'c1', code: 'it', name: 'IT', is_other: false, auction_allowed: true }],
  close_reasons: [
    { id: 'x1', code: 'budget', kind: 'cancel', name: 'Budget', requires_note: false },
    { id: 'x2', code: 'other', kind: 'not_awarded', name: 'Other', requires_note: true },
  ],
  presets: [],
})

beforeEach(() => {
  setActivePinia(createPinia())
  for (const group of [platform, catalog, notifications, competitions]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  useAuthToken().value = null
})

describe('useAppConfigStore', () => {
  it('loads once for concurrent callers and exposes the contract values', async () => {
    platform.fetchAppConfig.mockResolvedValue(appConfig())
    const store = useAppConfigStore()
    await Promise.all([store.load(), store.load()])
    expect(platform.fetchAppConfig).toHaveBeenCalledOnce()
    expect(store.loaded).toBe(true)
    expect(store.support.email).toBe('help@bafo.test')
    expect(store.legalVersion('terms')).toBe('2026-10-01')
    expect(store.legalVersion('privacy')).toBeNull()
    expect(store.sponsorshipEnabled).toBe(true)
  })

  it('enters maintenance from the config or from a 503, and leaves it when the server says so', async () => {
    platform.fetchAppConfig.mockResolvedValue(appConfig({ maintenance: { enabled: true, message: 'صيانة' } }))
    const store = useAppConfigStore()
    await store.load()
    expect(store.inMaintenance).toBe(true)
    expect(store.maintenanceMessage).toBe('صيانة')

    platform.fetchAppConfig.mockResolvedValue(appConfig())
    expect(await store.checkMaintenance()).toBe(false)

    store.enterMaintenance('from 503')
    expect(store.inMaintenance).toBe(true)
    expect(store.maintenanceMessage).toBe('from 503')
  })

  it('keeps the last config when a reload fails', async () => {
    platform.fetchAppConfig.mockResolvedValueOnce(appConfig())
    const store = useAppConfigStore()
    await store.load()
    platform.fetchAppConfig.mockRejectedValueOnce(new Error('down'))
    await store.load(true)
    expect(store.status).toBe('error')
    expect(store.config?.realtime.key).toBe('reverb-key')
  })
})

describe('useLookupsStore', () => {
  it('caches per locale and revalidates with the ETag (304 keeps the cache)', async () => {
    catalog.fetchLookups.mockResolvedValueOnce({ status: 'fresh', lookups: lookups('الرياض'), etag: '"v1"' })
    const store = useLookupsStore()
    await store.ensureLoaded()
    expect(store.regions[0]?.name).toBe('الرياض')
    expect(catalog.fetchLookups).toHaveBeenLastCalledWith(undefined)

    await store.ensureLoaded()
    expect(catalog.fetchLookups).toHaveBeenCalledOnce()

    catalog.fetchLookups.mockResolvedValueOnce({ status: 'not_modified' })
    await store.load()
    expect(catalog.fetchLookups).toHaveBeenLastCalledWith('"v1"')
    expect(store.regions[0]?.name).toBe('الرياض')
  })

  it('filters close reasons by kind and finds rows by id', async () => {
    catalog.fetchLookups.mockResolvedValueOnce({ status: 'fresh', lookups: lookups('Riyadh'), etag: null })
    const store = useLookupsStore()
    await store.ensureLoaded()
    expect(store.closeReasonsOf('cancel').map(reason => reason.code)).toEqual(['budget'])
    expect(store.regionById('r1')?.code).toBe('RIY')
    expect(store.categoryById('missing')).toBeNull()
  })

  it('reports failures', async () => {
    catalog.fetchLookups.mockRejectedValueOnce(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    const store = useLookupsStore()
    await expect(store.ensureLoaded()).rejects.toBeInstanceOf(ApiError)
    expect(store.error?.code).toBe('server_error')
    expect(store.loaded).toBe(false)
  })
})

describe('useNotificationsStore', () => {
  function signIn(unread = 4) {
    useAuthStore().setSession(makeTokenPayload({ unread_notifications_count: unread }))
  }

  it('starts the badge from Me and loads the latest 10', async () => {
    signIn(7)
    const store = useNotificationsStore()
    expect(store.unreadCount).toBe(7)

    const items = Array.from({ length: 12 }, () => makeNotification())
    notifications.listNotifications.mockResolvedValue({ items, pagination: { type: 'page', current_page: 1, per_page: 10, has_more: true }, unread_count: 9 })
    await store.loadLatest()
    expect(notifications.listNotifications).toHaveBeenCalledWith({ per_page: 10 })
    expect(store.latest).toHaveLength(10)
    expect(store.unreadCount).toBe(9)
  })

  it('marks as read optimistically and reverts on failure', async () => {
    signIn(2)
    const store = useNotificationsStore()
    const unread = makeNotification()
    store.applyCreated({ notification: unread, unread_count: 3 })

    notifications.markNotificationRead.mockRejectedValueOnce(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    await expect(store.markRead(unread)).rejects.toBeInstanceOf(ApiError)
    expect(store.unreadCount).toBe(3)
    expect(store.latest[0]?.read_at).toBeNull()

    notifications.markNotificationRead.mockResolvedValueOnce({ ...unread, read_at: '2026-10-01T09:00:00.000Z' })
    await store.markRead(unread)
    expect(store.unreadCount).toBe(2)
    expect(store.latest[0]?.read_at).toBe('2026-10-01T09:00:00.000Z')
  })

  it('prepends realtime notifications once, caps the bell and notifies listeners', () => {
    signIn(0)
    const store = useNotificationsStore()
    const seen: string[] = []
    const off = store.onCreated(notification => seen.push(notification.id))
    const first = makeNotification()
    store.applyCreated({ notification: first, unread_count: 1 })
    store.applyCreated({ notification: first, unread_count: 1 })
    for (let i = 0; i < 12; i++) store.applyCreated({ notification: makeNotification(), unread_count: 2 + i })
    expect(store.latest).toHaveLength(10)
    expect(store.unreadCount).toBe(13)
    expect(seen).toHaveLength(14)
    off()
    store.applyCreated({ notification: makeNotification(), unread_count: 14 })
    expect(seen).toHaveLength(14)
    store.applyUnreadCount({ unread_count: 0 })
    expect(store.unreadCount).toBe(0)
  })

  it('marks all as read and deletes', async () => {
    signIn(1)
    const store = useNotificationsStore()
    const item = makeNotification()
    store.applyCreated({ notification: item, unread_count: 1 })
    notifications.markAllNotificationsRead.mockResolvedValue({ unread_count: 0 })
    await store.markAllRead()
    expect(store.unreadCount).toBe(0)
    expect(store.latest.every(entry => entry.read_at !== null)).toBe(true)

    notifications.deleteNotification.mockResolvedValue(undefined)
    await store.remove(store.latest[0]!)
    expect(store.latest).toHaveLength(0)

    notifications.deleteAllNotifications.mockResolvedValue(undefined)
    await store.removeAll()
    expect(notifications.deleteAllNotifications).toHaveBeenCalledOnce()
  })

  it('forgets the previous user’s notifications on a user switch', async () => {
    signIn(1)
    const store = useNotificationsStore()
    store.applyCreated({ notification: makeNotification(), unread_count: 1 })
    useAuthStore().setSession(makeTokenPayload({ user: { ...makeMe().user, id: '01j9otheruser0000000000000' }, unread_notifications_count: 0 }))
    await nextTick()
    expect(store.latest).toHaveLength(0)
    expect(store.unreadCount).toBe(0)
  })
})

describe('useHomeStore', () => {
  it('loads once per session and hides dismissed alerts for the session', async () => {
    competitions.fetchHome.mockResolvedValue(makeHome())
    const store = useHomeStore()
    await store.ensureLoaded()
    await store.ensureLoaded()
    expect(competitions.fetchHome).toHaveBeenCalledOnce()
    expect(store.alerts.map(alert => alert.code)).toEqual(['trial_available', 'billing_profile_incomplete'])
    store.dismiss('trial_available')
    expect(store.alerts.map(alert => alert.code)).toEqual(['billing_profile_incomplete'])
    expect(window.sessionStorage.getItem('bafo.dismissed_alerts')).toContain('trial_available')
    window.sessionStorage.removeItem('bafo.dismissed_alerts')
  })

  it('keeps an error for the page to render', async () => {
    competitions.fetchHome.mockRejectedValue(new ApiError({ status: 503, code: 'service_unavailable', message: '', errors: {} }))
    const store = useHomeStore()
    await store.load()
    expect(store.error?.code).toBe('service_unavailable')
    expect(store.home).toBeNull()
  })
})
