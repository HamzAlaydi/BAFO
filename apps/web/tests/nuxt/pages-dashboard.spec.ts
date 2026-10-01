import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeHome, makeMe, makeMembership, makeNotification, makeOrganization, makeTokenPayload, makeUser } from '../fixtures/api'
import HomePage from '~/pages/dashboard/index.vue'
import NotificationsPage from '~/pages/dashboard/notifications.vue'
import OrganizationPage from '~/pages/dashboard/organization.vue'
import TeamPage from '~/pages/dashboard/team.vue'

const identity = vi.hoisted(() => ({
  fetchTeamMembers: vi.fn(),
  updateTeamMember: vi.fn(),
  removeTeamMember: vi.fn(),
  resendTeamInvitation: vi.fn(),
  createTeamMember: vi.fn(),
  fetchOrganization: vi.fn(),
  updateOrganization: vi.fn(),
  fetchMe: vi.fn(),
  logout: vi.fn(),
}))
const notificationsApi = vi.hoisted(() => ({
  listNotifications: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
  deleteNotification: vi.fn(),
  deleteAllNotifications: vi.fn(),
}))
const competitions = vi.hoisted(() => ({ fetchHome: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/identity', () => identity)
vi.mock('~/services/notifications', () => notificationsApi)
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/catalog', () => catalog)
mockNuxtImport('navigateTo', () => navigate)

const t = (key: string, params: Record<string, unknown> = {}) => useNuxtApp().$i18n.t(key, params)

async function flush(): Promise<void> {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  for (const group of [identity, notificationsApi, competitions, catalog]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  catalog.fetchLookups.mockResolvedValue({ status: 'fresh', lookups: { regions: [{ id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' }], categories: [], close_reasons: [], presets: [] }, etag: null })
  navigate.mockReset()
  useAuthStore().setSession(makeTokenPayload())
})

describe('W26 team', () => {
  const owner = makeMembership({ id: 'owner', role: 'owner', can_award: true, can_purchase: true, user: makeUser() })
  const member = makeMembership({ id: 'member', role: 'member' })
  const invited = makeMembership({ id: 'invited', role: 'admin', status: 'invited', joined_at: null, user: makeUser({ id: 'u3', name: 'Khalid', email: 'khalid@issuer.sa' }) })

  it('renders the forbidden state without team.manage', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['competitions.create'] }))
    const wrapper = await mountSuspended(TeamPage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.forbidden'))
    expect(identity.fetchTeamMembers).not.toHaveBeenCalled()
  })

  it('lists members with seats, and locks the owner and the viewer', async () => {
    identity.fetchTeamMembers.mockResolvedValue({ members: [owner, member, invited], seats: { used: 3, total: 3 } })
    const wrapper = await mountSuspended(TeamPage)
    await flush()
    expect(wrapper.text()).toContain(t('team.seats.used', { used: 3, total: 3 }))
    expect(wrapper.text()).toContain(t('team.locked.owner'))
    expect(wrapper.text()).toContain('Mona')
    expect(wrapper.text()).toContain(t('team.statuses.invited'))
    expect(wrapper.get('[role="meter"]').attributes('aria-valuenow')).toBe('3')
  })

  it('deactivates a member only after the server answers (no optimistic state change)', async () => {
    identity.fetchTeamMembers.mockResolvedValue({ members: [owner, member], seats: { used: 2, total: 3 } })
    let resolveUpdate!: (value: unknown) => void
    identity.updateTeamMember.mockReturnValue(new Promise((resolve) => {
      resolveUpdate = resolve
    }))
    const wrapper = await mountSuspended(TeamPage, { attachTo: document.body })
    await flush()
    const menuButton = wrapper.findAll('button[aria-haspopup="menu"]').find(button => button.text().includes('Mona'))!
    await menuButton.trigger('click')
    await flush()
    const deactivate = wrapper.findAll('[role="menuitem"]').find(item => item.text() === t('team.actions.deactivate'))!
    await deactivate.trigger('click')
    await flush()
    expect(identity.updateTeamMember).toHaveBeenCalledWith('member', { status: 'inactive' })
    expect(wrapper.get('table').text()).not.toContain(t('team.statuses.inactive'))
    identity.fetchTeamMembers.mockResolvedValue({ members: [owner, { ...member, status: 'inactive' }], seats: { used: 1, total: 3 } })
    resolveUpdate({ ...member, status: 'inactive' })
    await flush()
    expect(wrapper.get('table').text()).toContain(t('team.statuses.inactive'))
    wrapper.unmount()
  })

  it('shows the seat limit with the server numbers', async () => {
    identity.fetchTeamMembers.mockResolvedValue({ members: [owner, { ...member, status: 'inactive' }], seats: { used: 3, total: 3 } })
    identity.updateTeamMember.mockRejectedValue(new ApiError({ status: 409, code: 'seat_limit_reached', message: '', errors: {}, details: { seats: { used: 3, total: 3 } } }))
    const wrapper = await mountSuspended(TeamPage, { attachTo: document.body })
    await flush()
    await wrapper.findAll('button[aria-haspopup="menu"]').find(button => button.text().includes('Mona'))!.trigger('click')
    await flush()
    await wrapper.findAll('[role="menuitem"]').find(item => item.text() === t('team.actions.reactivate'))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('errors.seat_limit_reached_detail', { used: 3, total: 3 }))
    wrapper.unmount()
  })

  it('does not offer an admin the award and purchase flags it lacks (SECURITY_REVIEW S-02)', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['team.manage', 'organization.update', 'billing.view', 'competitions.create', 'competitions.manage_all', 'participation.submit_offers'] }))
    identity.fetchTeamMembers.mockResolvedValue({ members: [owner, member], seats: { used: 2, total: 5 } })
    identity.createTeamMember.mockResolvedValue(makeMembership({ id: 'new', role: 'admin' }))
    const wrapper = await mountSuspended(TeamPage, { attachTo: document.body })
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('team.actions.invite'))!.trigger('click')
    await flush()
    const drawer = document.body
    const inputs = [...drawer.querySelectorAll<HTMLInputElement>('form#team-member-form input')]
    const [name, email] = inputs.filter(input => input.type !== 'radio' && input.type !== 'tel')
    name!.value = 'Sock Puppet'
    name!.dispatchEvent(new Event('input'))
    email!.value = 'puppet@issuer.sa'
    email!.dispatchEvent(new Event('input'))
    const admin = inputs.find(input => input.type === 'radio' && input.value === 'admin')!
    admin.checked = true
    admin.dispatchEvent(new Event('change'))
    await flush()
    const switches = [...drawer.querySelectorAll<HTMLButtonElement>('form#team-member-form [role="switch"]')]
    expect(switches).toHaveLength(2)
    for (const toggle of switches) {
      expect(toggle.getAttribute('aria-checked')).toBe('false')
      expect(toggle.disabled).toBe(true)
    }
    drawer.querySelector<HTMLFormElement>('form#team-member-form')!.dispatchEvent(new Event('submit'))
    await flush()
    expect(identity.createTeamMember).toHaveBeenCalledWith(expect.objectContaining({ role: 'admin', can_award: false, can_purchase: false }))
    wrapper.unmount()
  })
})

describe('W29 notifications', () => {
  it('lists notifications, opens one (mark read, then its route)', async () => {
    const note = makeNotification({ route: '/competitions/01j9comp000000000000000000/live' })
    notificationsApi.listNotifications.mockResolvedValue({ items: [note], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false, total: 1, last_page: 1 }, unread_count: 1 })
    notificationsApi.markNotificationRead.mockResolvedValue({ ...note, read_at: '2026-10-01T09:00:00.000Z' })
    const wrapper = await mountSuspended(NotificationsPage)
    await flush()
    expect(wrapper.text()).toContain(note.title)
    await wrapper.findAll('button').find(button => button.text().includes(note.title))!.trigger('click')
    await flush()
    expect(notificationsApi.markNotificationRead).toHaveBeenCalledWith(note.id)
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/\/dashboard\/competitions\/01j9comp000000000000000000\/live$/))
  })

  it('shows the empty state', async () => {
    notificationsApi.listNotifications.mockResolvedValue({ items: [], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false }, unread_count: 0 })
    const wrapper = await mountSuspended(NotificationsPage)
    await flush()
    expect(wrapper.text()).toContain(t('notifications.list.empty.title'))
  })

  it('prepends realtime notifications on page 1', async () => {
    notificationsApi.listNotifications.mockResolvedValue({ items: [], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false }, unread_count: 0 })
    const wrapper = await mountSuspended(NotificationsPage)
    await flush()
    const live = makeNotification({ title: 'عروض جديدة' })
    useNotificationsStore().applyCreated({ notification: live, unread_count: 1 })
    await flush()
    expect(wrapper.text()).toContain('عروض جديدة')
  })
})

describe('W27 organization', () => {
  it('is read-only without organization.update and never offers to edit the CR', async () => {
    useAuthStore().setSession(makeTokenPayload({ permissions: ['competitions.create'] }))
    identity.fetchOrganization.mockResolvedValue(makeOrganization())
    const wrapper = await mountSuspended(OrganizationPage)
    await flush()
    expect(wrapper.text()).toContain(t('organization.read_only'))
    expect(wrapper.find('button[type="submit"]').exists()).toBe(false)
    const cr = wrapper.findAll('input').find(input => (input.element as HTMLInputElement).value === '1010123456')!
    expect(cr.attributes('readonly')).toBeDefined()
  })

  it('lists the missing billing fields and saves without sending the CR', async () => {
    identity.fetchOrganization.mockResolvedValue(makeOrganization())
    identity.updateOrganization.mockResolvedValue(makeOrganization({ name: 'شركة المصدر الجديدة' }))
    const wrapper = await mountSuspended(OrganizationPage)
    await flush()
    expect(wrapper.text()).toContain(t('organization.billing_profile.title'))
    expect(wrapper.text()).toContain(t('organization.fields.building_number'))
    const name = wrapper.findAll('input').find(input => (input.element as HTMLInputElement).value === 'شركة المصدر')!
    await name.setValue('شركة المصدر الجديدة')
    await wrapper.get('form').trigger('submit')
    await flush()
    const body = identity.updateOrganization.mock.calls[0]![0]
    expect(body.name).toBe('شركة المصدر الجديدة')
    expect(body).not.toHaveProperty('cr_number')
    expect(useAuthStore().organization?.name).toBe('شركة المصدر الجديدة')
  })
})

describe('W10 overview', () => {
  it('renders the stats and activity from GET /home', async () => {
    competitions.fetchHome.mockResolvedValue(makeHome({
      activities: [{ id: 'a1', action: 'competition.published', occurred_at: new Date().toISOString(), actor: { name: 'سارة' }, subject: { type: 'competition', id: 'c1', title: 'توريد أجهزة' } }],
    }))
    const wrapper = await mountSuspended(HomePage)
    await flush()
    expect(wrapper.text()).toContain(t('home.stats.issuer.active_competitions'))
    expect(wrapper.text()).toContain('42')
    expect(wrapper.text()).toContain(t('home.activity.competition_published', { actor: 'سارة', title: 'توريد أجهزة' }))
    expect(wrapper.text()).toContain(t('home.team.seats', { used: 3, total: 5 }))
  })

  it('shows a retry state when the first load fails', async () => {
    competitions.fetchHome.mockRejectedValue(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    useAuthStore().applyMe(makeMe({ user: makeUser({ id: 'another-user-id-000000000' }) }))
    const wrapper = await mountSuspended(HomePage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.server_error'))
  })
})
