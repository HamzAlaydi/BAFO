import { describe, expect, it } from 'vitest'
import { NOTIFICATIONS_PATH, notificationIconGroup, notificationPath } from '~/utils/notification-route'

const ID = '01j9zq4m1x2a3b4c5d6e7f8g9h'

describe('notificationPath (SCREENS S11, CONVENTIONS §4.3)', () => {
  it('maps every canonical route to its dashboard page (locale-free)', () => {
    expect(notificationPath(`/competitions/${ID}`)).toBe(`/dashboard/competitions/${ID}`)
    expect(notificationPath(`/competitions/${ID}/live`)).toBe(`/dashboard/competitions/${ID}/live`)
    expect(notificationPath(`/competitions/${ID}/qa`)).toBe(`/dashboard/competitions/${ID}/qa`)
    expect(notificationPath('/billing')).toBe('/dashboard/billing')
    expect(notificationPath(`/billing/invoices/${ID}`)).toBe(`/dashboard/billing/invoices/${ID}`)
    expect(notificationPath('/integrations')).toBe('/dashboard/integrations')
    expect(notificationPath('/notifications')).toBe(NOTIFICATIONS_PATH)
  })

  it('falls back to the notifications list for unknown or unsafe routes', () => {
    expect(notificationPath(null)).toBe(NOTIFICATIONS_PATH)
    expect(notificationPath('/competitions/not-an-id')).toBe(NOTIFICATIONS_PATH)
    expect(notificationPath('https://evil.example/competitions/x')).toBe(NOTIFICATIONS_PATH)
    expect(notificationPath(`/competitions/${ID}/offers`)).toBe(NOTIFICATIONS_PATH)
  })

  it('ignores a trailing slash, query or fragment', () => {
    expect(notificationPath(`/competitions/${ID}/live/`)).toBe(`/dashboard/competitions/${ID}/live`)
    expect(notificationPath('/billing?x=1#y')).toBe('/dashboard/billing')
  })
})

describe('notificationIconGroup', () => {
  it('groups the catalogue types and tolerates unknown ones', () => {
    expect(notificationIconGroup('competition.invited')).toBe('invitation')
    expect(notificationIconGroup('competition.closing_soon')).toBe('timer')
    expect(notificationIconGroup('bafo.invited')).toBe('bafo')
    expect(notificationIconGroup('award.won')).toBe('award')
    expect(notificationIconGroup('invoice.issued')).toBe('billing')
    expect(notificationIconGroup('something.new')).toBe('other')
  })
})
