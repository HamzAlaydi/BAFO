import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { makeTokenPayload } from '../fixtures/api'
import { COMPETITION_ID, inviteeCompetition, participantListItem } from '../fixtures/participant'
import ClaimPage from '~/pages/dashboard/invitations/claim.vue'
import ParticipatingPage from '~/pages/dashboard/participating/index.vue'
import InvitationLanding from '~/pages/invitations/index.vue'

const competitions = vi.hoisted(() => ({
  listParticipantCompetitions: vi.fn(),
  fetchCompetition: vi.fn(),
  joinInvitation: vi.fn(),
  declineInvitation: vi.fn(),
  claimInvitation: vi.fn(),
  lookupInvitation: vi.fn(),
  declineInvitationByToken: vi.fn(),
}))
const navigate = vi.hoisted(() => vi.fn())
vi.mock('~/services/competitions', () => competitions)
mockNuxtImport('navigateTo', () => navigate)

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

function page<T>(items: T[]) {
  return { items, pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false, total: items.length, last_page: 1 } }
}

beforeEach(() => {
  for (const fn of Object.values(competitions)) fn.mockReset()
  navigate.mockReset()
  useAuthStore().setSession(makeTokenPayload())
  usePendingToken('invitation').clear()
  useToast().clear()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('W23 participating', () => {
  const joined = participantListItem('01j9comp0000000000000joined', { is_leading: false, my_offer_amount_minor: 9_900_000 })
  const pending = participantListItem('01j9comp000000000000pending', {
    status: 'scheduled',
    invitation: { id: '01j9inv00000000000000pending', status: 'viewed', join_deadline: new Date(Date.now() + 86_400_000).toISOString() },
    access: { state: 'join_required', coverage: 'sponsored', sponsor_name: 'شركة المصدر', join_deadline: new Date(Date.now() + 86_400_000).toISOString() },
    my_offer_amount_minor: null,
    is_leading: null,
  })
  const won = participantListItem('01j9comp00000000000000won', { status: 'awarded', phase: null, access: { state: 'read_only', coverage: 'own_plan', sponsor_name: null, join_deadline: null }, result: { outcome: 'won' } })

  it('loads the active tab and lists cards needing action first', async () => {
    competitions.listParticipantCompetitions.mockResolvedValue(page([joined, won, pending]))
    const wrapper = await mountSuspended(ParticipatingPage)
    await flush()
    expect(competitions.listParticipantCompetitions).toHaveBeenCalledWith({ status_group: 'active', direction: undefined, q: undefined, page: 1 })
    const cards = wrapper.findAll('article')
    expect(cards[0]!.attributes('data-testid')).toBe(`participating-card-${pending.id}`)
    expect(wrapper.text()).toContain(t('competitions.participating.needs_action', { count: 1 }, 1))

    const pendingCard = wrapper.get(`[data-testid="participating-card-${pending.id}"]`)
    expect(pendingCard.text()).toContain(t('invitations.access.join_required'))
    expect(pendingCard.text()).toContain(t('sponsorship.badge.fees_covered_by', { sponsor: 'شركة المصدر' }))
    expect(pendingCard.find('[data-testid="card-join"]').exists()).toBe(true)
    expect(pendingCard.find('[data-testid="card-decline"]').exists()).toBe(true)

    const joinedCard = wrapper.get(`[data-testid="participating-card-${joined.id}"]`)
    expect(joinedCard.text()).toContain('99,000.00')
    expect(joinedCard.text()).toContain(t('competitions.participating.card.not_leading'))
    expect(joinedCard.text()).not.toContain(t('sponsorship.badge.fees_covered'))
    expect(wrapper.get(`[data-testid="participating-card-${won.id}"]`).text()).toContain(t('award.outcome.won'))
  })

  it('switches the status group and resets to the first page', async () => {
    competitions.listParticipantCompetitions.mockResolvedValue(page([joined]))
    const wrapper = await mountSuspended(ParticipatingPage)
    await flush()
    await wrapper.get('[role="tab"][data-key="ended"]').trigger('click')
    await flush()
    expect(competitions.listParticipantCompetitions).toHaveBeenLastCalledWith({ status_group: 'ended', direction: undefined, q: undefined, page: 1 })
  })

  it('shows the empty and error states', async () => {
    competitions.listParticipantCompetitions.mockResolvedValue(page([]))
    const empty = await mountSuspended(ParticipatingPage)
    await flush()
    expect(empty.text()).toContain(t('competitions.participating.empty.title'))
    empty.unmount()

    competitions.listParticipantCompetitions.mockRejectedValue(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    const failed = await mountSuspended(ParticipatingPage)
    await flush()
    expect(failed.text()).toContain(t('errors.server_error'))
  })

  it('joins from a card after accepting the terms (rules loaded from the invitee projection)', async () => {
    competitions.listParticipantCompetitions.mockResolvedValue(page([pending]))
    competitions.fetchCompetition.mockResolvedValue(inviteeCompetition())
    competitions.joinInvitation.mockResolvedValue({ ...inviteeCompetition(), id: pending.id, viewer_role: 'participant' })
    const wrapper = await mountSuspended(ParticipatingPage, { attachTo: document.body })
    await flush()
    await wrapper.get('[data-testid="card-join"]').trigger('click')
    await flush()
    expect(competitions.fetchCompetition).toHaveBeenCalledWith(pending.id)
    expect(wrapper.get('[data-testid="join-dialog"]').text()).toContain(inviteeCompetition().rules_summary[0])
    await wrapper.get('[data-testid="join-dialog"] input[type="checkbox"]').setValue(true)
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('invitations.join.confirm'))!.trigger('click')
    await flush()
    expect(competitions.joinInvitation).toHaveBeenCalledWith(pending.invitation.id)
    expect(useToast().toasts.value.at(-1)?.message).toBe(t('invitations.join.joined'))
    wrapper.unmount()
  })

  it('declines from a card and reloads', async () => {
    competitions.listParticipantCompetitions.mockResolvedValue(page([pending]))
    competitions.declineInvitation.mockResolvedValue({})
    const wrapper = await mountSuspended(ParticipatingPage, { attachTo: document.body })
    await flush()
    await wrapper.get('[data-testid="card-decline"]').trigger('click')
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('invitations.decline.confirm'))!.trigger('click')
    await flush()
    expect(competitions.declineInvitation).toHaveBeenCalledWith(pending.invitation.id, null)
    expect(competitions.listParticipantCompetitions).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })
})

describe('W24 claim invitation', () => {
  const bound = { kind: 'bound', invitation: { id: 'inv', status: 'viewed', join_deadline: null, sent_at: null, competition: inviteeCompetition() } }

  it('goes to Participating without a pending token', async () => {
    await mountSuspended(ClaimPage)
    await flush()
    expect(competitions.claimInvitation).not.toHaveBeenCalled()
    expect(navigate).toHaveBeenCalledWith('/ar/dashboard/participating', { replace: true })
  })

  it('binds straight away when the e-mail matches, then clears the token', async () => {
    usePendingToken('invitation').store('tok-123')
    competitions.claimInvitation.mockResolvedValue(bound)
    await mountSuspended(ClaimPage)
    await flush()
    expect(competitions.claimInvitation).toHaveBeenCalledWith('tok-123')
    expect(usePendingToken('invitation').read()).toBeNull()
    expect(useToast().toasts.value.at(-1)?.message).toBe(t('invitations.claim.bound'))
    expect(navigate).toHaveBeenCalledTimes(1)
    const target = navigate.mock.calls[0]![0] as string
    expect([`/ar/dashboard/competitions/${COMPETITION_ID}`, '/ar/dashboard/participating']).toContain(target)
  })

  it('asks for the code sent to the invited e-mail, then binds with it', async () => {
    usePendingToken('invitation').store('tok-123')
    competitions.claimInvitation.mockResolvedValueOnce({ kind: 'otp_sent', otp_sent_to: 's***@acme.sa', otp_expires_at: new Date(Date.now() + 600_000).toISOString() })
    const wrapper = await mountSuspended(ClaimPage)
    await flush()
    expect(wrapper.get('[data-testid="claim-otp"]').text()).toContain('s***@acme.sa')
    competitions.claimInvitation.mockRejectedValueOnce(new ApiError({ status: 422, code: 'otp_invalid', message: '', errors: {} }))
    await wrapper.get('input[autocomplete="one-time-code"]').setValue('123456')
    await flush()
    expect(competitions.claimInvitation).toHaveBeenLastCalledWith('tok-123', '123456')
    expect(wrapper.text()).toContain(t('errors.otp_invalid'))

    competitions.claimInvitation.mockResolvedValueOnce(bound)
    await wrapper.get('input[autocomplete="one-time-code"]').setValue('654321')
    await flush()
    expect(competitions.claimInvitation).toHaveBeenLastCalledWith('tok-123', '654321')
    expect(navigate).toHaveBeenCalled()
  })

  it('explains an invitation bound to another organisation and drops the token', async () => {
    usePendingToken('invitation').store('tok-123')
    competitions.claimInvitation.mockRejectedValue(new ApiError({ status: 409, code: 'invitation_belongs_to_another_organization', message: '', errors: {} }))
    const wrapper = await mountSuspended(ClaimPage)
    await flush()
    expect(wrapper.text()).toContain(t('invitations.claim.other_org_title'))
    expect(usePendingToken('invitation').read()).toBeNull()
  })

  it('shows an invalid invitation', async () => {
    usePendingToken('invitation').store('tok-123')
    competitions.claimInvitation.mockRejectedValue(new ApiError({ status: 404, code: 'invitation_invalid', message: '', errors: {} }))
    const wrapper = await mountSuspended(ClaimPage)
    await flush()
    expect(wrapper.text()).toContain(t('errors.invitation_invalid'))
  })
})

describe('W03 invitation landing, signed in', () => {
  it('goes straight to the claim, keeping the token out of the URL', async () => {
    window.history.replaceState(null, '', '/ar/invitations#t=tok-abc')
    await mountSuspended(InvitationLanding)
    await flush()
    expect(window.location.hash).toBe('')
    expect(usePendingToken('invitation').read()).toBe('tok-abc')
    expect(competitions.lookupInvitation).not.toHaveBeenCalled()
    expect(navigate).toHaveBeenCalledWith('/ar/dashboard/invitations/claim', { replace: true })
  })

  it('opens the lookup and the decline dialog when the link asks to decline', async () => {
    window.history.replaceState(null, '', '/ar/invitations#t=tok-abc&action=decline')
    competitions.lookupInvitation.mockResolvedValue({
      invitation: { id: 'inv', status: 'viewed', email_masked: 's***@acme.sa', join_deadline: null, sponsored: false },
      competition: inviteeCompetition(),
      next_step: 'login',
    })
    await mountSuspended(InvitationLanding)
    await flush()
    expect(competitions.lookupInvitation).toHaveBeenCalledWith('tok-abc')
    expect(navigate).not.toHaveBeenCalled()
  })
})
