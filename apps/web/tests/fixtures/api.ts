/**
 * API fixtures shaped exactly like API.md §2 (for mocked services in tests). Override any field:
 *   makeMe({ permissions: ['team.manage'] })
 */
import type { AuthTokenPayload, Me, Membership, Organization, User } from '../../app/types/api/identity'
import type { Home } from '../../app/types/api/competitions'
import type { Notification } from '../../app/types/api/notifications'

export function makeUser(overrides: Partial<User> = {}): User {
  return {
    id: '01j9zq4m1x2a3b4c5d6e7f8g9h',
    name: 'سارة العتيبي',
    email: 'sara@issuer.sa',
    phone: '+966501234567',
    locale: 'ar',
    avatar_url: null,
    status: 'active',
    email_verified_at: '2026-10-01T08:00:00.000Z',
    created_at: '2026-10-01T07:55:00.000Z',
    ...overrides,
  }
}

export function makeOrganization(overrides: Partial<Organization> = {}): Organization {
  return {
    id: '01j9org0000000000000000000',
    name: 'شركة المصدر',
    legal_name_ar: null,
    legal_name_en: null,
    cr_number: '1010123456',
    vat_registered: false,
    vat_number: null,
    region: { id: '01j9reg0000000000000000000', code: 'RIY', name: 'الرياض' },
    city: 'الرياض',
    national_address: { building_number: null, street: null, district: null, postal_code: null, additional_number: null, short_address: null },
    website: null,
    email: 'info@masdar.sa',
    phone: '+966501234567',
    logo_url: null,
    profile_document: null,
    categories: [],
    visible_in_suggestions: true,
    status: 'active',
    verified: false,
    features: { api_enabled: false, auction_enabled: false, sponsorship_enabled: false },
    billing_profile_complete: false,
    billing_profile_missing: ['legal_name_ar', 'national_address.building_number'],
    trial_available: true,
    created_at: '2026-10-01T07:55:00.000Z',
    ...overrides,
  }
}

export function makeMe(overrides: Partial<Me> = {}): Me {
  return {
    user: makeUser(),
    organization: makeOrganization(),
    membership: { id: '01j9mem0000000000000000000', role: 'owner', can_award: true, can_purchase: true, status: 'active' },
    permissions: ['organization.update', 'team.manage', 'billing.view', 'billing.purchase', 'competitions.create', 'competitions.manage_all', 'competitions.award', 'participation.submit_offers', 'integrations.manage', 'account.delete_organization'],
    subscription: null,
    entitlements: { can_issue: false, seats_used: 1, seats_total: 1 },
    unread_notifications_count: 4,
    ...overrides,
  }
}

export function makeTokenPayload(overrides: Partial<Me> = {}, token = 'plain-text-token'): AuthTokenPayload {
  return { ...makeMe(overrides), token, token_type: 'Bearer' }
}

export function makeMembership(overrides: Partial<Membership> = {}): Membership {
  return {
    id: '01j9mem0000000000000000001',
    role: 'member',
    can_award: false,
    can_purchase: false,
    status: 'active',
    joined_at: '2026-10-02T08:00:00.000Z',
    invited_at: '2026-10-01T08:00:00.000Z',
    user: makeUser({ id: '01j9usr0000000000000000001', name: 'Mona', email: 'mona@issuer.sa' }),
    ...overrides,
  }
}

let notificationSeq = 0
export function makeNotification(overrides: Partial<Notification> = {}): Notification {
  notificationSeq += 1
  return {
    id: `01jd${String(notificationSeq).padStart(22, '0')}`,
    type: 'competition.invited',
    title: 'دعوة للمشاركة في مناقصة',
    body: 'تدعوك شركة المصدر للمشاركة في «توريد أجهزة».',
    subject: { type: 'competition', id: '01j9comp000000000000000000' },
    route: '/competitions/01j9comp000000000000000000',
    params: {},
    read_at: null,
    created_at: new Date().toISOString(),
    ...overrides,
  }
}

export function makeHome(overrides: Partial<Home> = {}): Home {
  return {
    issuer: { active_competitions: 3, draft_competitions: 1, live_now: 1, awaiting_award: 1, offers_received_30d: 42 },
    participant: { pending_invitations: 2, active_participations: 3, offers_submitted_30d: 17, awards_won: 1 },
    team: { members: 3, seats_total: 5 },
    subscription: null,
    alerts: [{ code: 'trial_available', params: {} }, { code: 'billing_profile_incomplete', params: {} }],
    activities: [],
    ...overrides,
  }
}
