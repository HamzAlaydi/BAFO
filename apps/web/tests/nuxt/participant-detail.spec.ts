import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { Component } from 'vue'
import { defineComponent, h } from 'vue'
import {
  CompetitionsParticipantHeaderMeta,
  CompetitionsParticipantInviteeView,
  CompetitionsParticipantMyOffers,
  CompetitionsParticipantOverview,
  CompetitionsParticipantQa,
} from '#components'
import { makeTokenPayload } from '../fixtures/api'
import { COMPETITION_ID, INVITATION_ID, comment, inviteeCompetition, myOffer, participantCompetition, participantSnapshot } from '../fixtures/participant'
import type { Competition } from '~/types/api/competitions'

const competitions = vi.hoisted(() => ({
  fetchCompetition: vi.fn(),
  listAttachments: vi.fn(),
  listComments: vi.fn(),
  postComment: vi.fn(),
  joinInvitation: vi.fn(),
  declineInvitation: vi.fn(),
}))
const bidding = vi.hoisted(() => ({ fetchLive: vi.fn(), fetchMyOffers: vi.fn(), fetchAward: vi.fn(), sendHeartbeat: vi.fn() }))
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/bidding', () => bidding)

const echo = vi.hoisted(() => {
  const channels = new Map<string, { listeners: Map<string, (payload: unknown) => void>, subscribed: Array<() => void> }>()
  const api = {
    channels,
    private(name: string) {
      const channel = { listeners: new Map<string, (payload: unknown) => void>(), subscribed: [] as Array<() => void> }
      channels.set(name, channel)
      const handle = {
        listen(event: string, callback: (payload: unknown) => void) {
          channel.listeners.set(event, callback)
          return handle
        },
        subscribed(callback: () => void) {
          channel.subscribed.push(callback)
          return handle
        },
      }
      return handle
    },
    leave() {},
  }
  return api
})
mockNuxtImport('useEcho', () => () => echo)
mockNuxtImport('useRealtimeStatus', () => () => ({ available: ref(true), state: ref('connected') }))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

/** Renders a view under `provideCompetition()`; returns the context too. */
async function mountView(view: Component, competition: Competition) {
  competitions.fetchCompetition.mockResolvedValue(competition)
  bidding.fetchLive.mockResolvedValue('live' in competition ? competition.live : null)
  let context!: ReturnType<typeof provideCompetition>
  const Harness = defineComponent({
    setup() {
      context = provideCompetition(COMPETITION_ID)
      return () => h(view)
    },
  })
  const wrapper = await mountSuspended(Harness, { attachTo: document.body })
  await flush()
  const channel = [...echo.channels.values()][0]
  channel?.subscribed.forEach(callback => callback())
  await flush()
  const emit = async (event: string, payload: unknown) => {
    channel?.listeners.get(event)?.(payload)
    await flush()
  }
  return { wrapper, context, emit }
}

beforeEach(() => {
  for (const group of [competitions, bidding]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  competitions.listAttachments.mockResolvedValue([])
  bidding.fetchMyOffers.mockResolvedValue([])
  bidding.fetchAward.mockResolvedValue(null)
  bidding.sendHeartbeat.mockResolvedValue(undefined)
  echo.channels.clear()
  useAuthStore().setSession(makeTokenPayload())
  useLiveRoomStore().reset()
  useToast().clear()
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('W14 participant overview', () => {
  it('shows the standing summary, rules, dates and the documents the server lists', async () => {
    competitions.listAttachments.mockResolvedValue([
      { id: 'a1', kind: 'document', title: 'كراسة الشروط', file: { id: 'f1', name: 'rfp.pdf', mime_type: 'application/pdf', extension: 'pdf', size_bytes: 482_133, download_path: '/api/app/v1/files/f1/download', created_at: 'x' }, url: null, is_addendum: true, sort_order: 0, created_at: 'x' },
      { id: 'a2', kind: 'external_link', title: 'المخططات', file: null, url: 'https://example.sa/plans', is_addendum: false, sort_order: 1, created_at: 'x' },
    ])
    const competition = participantCompetition({ access: { state: 'full', coverage: 'sponsored', sponsor_name: 'شركة المصدر', join_deadline: null } })
    const { wrapper } = await mountView(CompetitionsParticipantOverview, competition)
    const overview = wrapper.get('[data-testid="participant-overview"]')
    expect(overview.get('[data-testid="standing-banner"]').text()).toContain(t('live.status.not_leading.tender'))
    expect(overview.text()).toContain(competition.rules_summary[0])
    expect(overview.text()).toContain(t('sponsorship.badge.fees_covered_by', { sponsor: 'شركة المصدر' }))
    const documents = overview.get('[data-testid="attachments"]').text()
    expect(documents).toContain('كراسة الشروط')
    expect(documents).toContain(t('competitions.participant.attachments.addendum'))
    expect(documents).toContain('المخططات')
    expect(overview.find('a[href="https://example.sa/plans"]').attributes('target')).toBe('_blank')
    wrapper.unmount()
  })

  it('refetches documents when the issuer adds an addendum', async () => {
    const { wrapper, emit } = await mountView(CompetitionsParticipantOverview, participantCompetition())
    expect(competitions.listAttachments).toHaveBeenCalledTimes(1)
    await emit('.competition.updated', { competition_id: COMPETITION_ID, fields: ['attachments'], server_time: 'x' })
    expect(competitions.listAttachments).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('shows the result panel once the competition ends', async () => {
    const snapshot = participantSnapshot({ status: 'not_awarded', phase: null, accepting_offers: false, result: { outcome: 'not_awarded', winning_amount_minor: null } })
    const { wrapper } = await mountView(CompetitionsParticipantOverview, participantCompetition({ status: 'not_awarded', phase: null, live: snapshot }))
    expect(wrapper.get('[data-testid="result-panel"]').text()).toContain(t('award.outcome.not_awarded'))
    wrapper.unmount()
  })
})

describe('W14 invitee view', () => {
  it('shows the sponsored access state with Join and Decline from the server flags', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantInviteeView, inviteeCompetition())
    const card = wrapper.get('[data-testid="access-state-card"]')
    expect(card.attributes('data-state')).toBe('join_required')
    expect(card.text()).toContain(t('invitations.access_card.sponsored', { sponsor: 'شركة المصدر' }))
    expect(card.find('[data-testid="join-open"]').exists()).toBe(true)
    expect(card.find('[data-testid="decline-open"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('hides Join without permissions.can_join and explains plan_required', async () => {
    const competition = inviteeCompetition({
      access: { state: 'plan_required', coverage: 'none', sponsor_name: null, join_deadline: null },
      permissions: { ...inviteeCompetition().permissions, can_join: false },
    })
    const { wrapper } = await mountView(CompetitionsParticipantInviteeView, competition)
    const card = wrapper.get('[data-testid="access-state-card"]')
    expect(card.text()).toContain(t('invitations.access_card.plan_required'))
    expect(card.find('[data-testid="join-open"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('joins only after accepting the terms and re-renders as participant', async () => {
    const { wrapper, context } = await mountView(CompetitionsParticipantInviteeView, inviteeCompetition())
    await wrapper.get('[data-testid="join-open"]').trigger('click')
    await flush()
    const dialog = wrapper.get('[data-testid="join-dialog"]')
    expect(dialog.text()).toContain(inviteeCompetition().rules_summary[0])
    const confirm = wrapper.findAll('button').find(button => button.text() === t('invitations.join.confirm'))!
    expect(confirm.attributes('disabled')).toBeDefined()

    competitions.joinInvitation.mockResolvedValue(participantCompetition())
    await dialog.get('input[type="checkbox"]').setValue(true)
    await flush()
    await confirm.trigger('click')
    await flush()
    expect(competitions.joinInvitation).toHaveBeenCalledWith(INVITATION_ID)
    expect(context.viewerRole.value).toBe('participant')
    expect(useToast().toasts.value.at(-1)?.message).toBe(t('invitations.join.joined'))
    wrapper.unmount()
  })

  it('shows plan_required from the join answer with its access details', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantInviteeView, inviteeCompetition({ access: { state: 'join_required', coverage: 'own_plan', sponsor_name: null, join_deadline: null } }))
    competitions.joinInvitation.mockRejectedValue(new ApiError({ status: 403, code: 'plan_required', message: '', errors: {}, details: { access: { state: 'plan_required', coverage: 'none', sponsor_name: null, join_deadline: null } } }))
    await wrapper.get('[data-testid="join-open"]').trigger('click')
    await flush()
    await wrapper.get('[data-testid="join-dialog"] input[type="checkbox"]').setValue(true)
    await flush()
    await wrapper.findAll('button').find(button => button.text() === t('invitations.join.confirm'))!.trigger('click')
    await flush()
    expect(wrapper.text()).toContain(t('errors.plan_required'))
    expect(wrapper.get('[data-testid="access-state-card"]').attributes('data-state')).toBe('plan_required')
    wrapper.unmount()
  })

  it('declines with an optional reason, then refetches', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantInviteeView, inviteeCompetition())
    competitions.declineInvitation.mockResolvedValue({ id: INVITATION_ID, status: 'declined', join_deadline: null, sent_at: null, competition: inviteeCompetition() })
    competitions.fetchCompetition.mockResolvedValue(inviteeCompetition({ access: { state: 'unavailable', coverage: 'none', sponsor_name: null, join_deadline: null }, invitation: { ...inviteeCompetition().invitation, status: 'declined' } }))
    await wrapper.get('[data-testid="decline-open"]').trigger('click')
    await flush()
    await wrapper.get('textarea').setValue('لا نورد هذا الصنف')
    await wrapper.findAll('button').find(button => button.text() === t('invitations.decline.confirm'))!.trigger('click')
    await flush()
    expect(competitions.declineInvitation).toHaveBeenCalledWith(INVITATION_ID, 'لا نورد هذا الصنف')
    expect(wrapper.get('[data-testid="access-state-card"]').text()).toContain(t('invitations.access_card.unavailable.declined'))
    wrapper.unmount()
  })
})

describe('W18 participant Q&A', () => {
  const page = (items: unknown[], hasMore = false) => ({ items, pagination: { type: 'page', current_page: 1, per_page: 20, has_more: hasMore, total: items.length, last_page: 1 } })

  it('labels authors by the projection: issuer name, «أنتم», aliases only for others', async () => {
    competitions.listComments.mockResolvedValue(page([
      comment('c1', { author: { kind: 'me' }, replies: [comment('r1', { parent_id: 'c1', body: 'نعم.', author: { kind: 'issuer', organization_name: 'شركة المصدر' } })] }),
      comment('c2', { author: { kind: 'participant', alias_no: 3 } }),
    ]))
    const { wrapper } = await mountView(CompetitionsParticipantQa, participantCompetition())
    const threads = wrapper.findAll('[data-testid="qa-thread"]')
    expect(threads[0]!.text()).toContain(t('qa.participant.author.me'))
    expect(threads[0]!.text()).toContain('شركة المصدر')
    expect(threads[0]!.text()).toContain(t('qa.participant.reply_open'))
    expect(threads[1]!.text()).toContain(t('qa.participant.author.participant', { alias: 3 }))
    // A participant may follow up only on its own organisation's questions.
    expect(threads[1]!.text()).not.toContain(t('qa.participant.reply_open'))
    wrapper.unmount()
  })

  it('posts a question and inserts the server answer once, even when realtime echoes it', async () => {
    competitions.listComments.mockResolvedValue(page([]))
    const { wrapper, emit } = await mountView(CompetitionsParticipantQa, participantCompetition())
    expect(wrapper.text()).toContain(t('qa.participant.empty.title'))
    const posted = comment('c9', { author: { kind: 'me' }, body: 'هل يشمل السعر التوصيل؟' })
    competitions.postComment.mockResolvedValue(posted)
    await wrapper.get('textarea').setValue('هل يشمل السعر التوصيل؟')
    await wrapper.get('[data-testid="participant-qa"] form').trigger('submit')
    await flush()
    expect(competitions.postComment).toHaveBeenCalledWith(COMPETITION_ID, 'هل يشمل السعر التوصيل؟', null)
    await emit('.comment.created', posted)
    expect(wrapper.findAll('[data-testid="qa-thread"]')).toHaveLength(1)
    await emit('.comment.created', comment('r9', { parent_id: 'c9', body: 'نعم', author: { kind: 'issuer', organization_name: 'شركة المصدر' } }))
    expect(wrapper.get('[data-testid="qa-thread"]').text()).toContain('نعم')
    wrapper.unmount()
  })

  it('replaces the composer with a notice when comments are closed', async () => {
    competitions.listComments.mockResolvedValue(page([comment('c1')]))
    const closed = participantCompetition({ status: 'closed', phase: null, permissions: { ...participantCompetition().permissions, can_comment: false } })
    const { wrapper } = await mountView(CompetitionsParticipantQa, closed)
    expect(wrapper.get('[data-testid="qa-closed"]').text()).toContain(t('errors.comments_closed'))
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid="qa-thread"]')).toHaveLength(1)
    wrapper.unmount()
  })
})

describe('W21 my offers', () => {
  it('lists own offers with the neutral change against the previous one and voided badges', async () => {
    bidding.fetchMyOffers.mockResolvedValue([
      myOffer(1, 10_000_000, { accepted_at: '2026-11-09T11:00:00.000Z' }),
      myOffer(2, 9_950_000, { accepted_at: '2026-11-09T11:10:00.000Z' }),
      myOffer(3, 9_900_000, { accepted_at: '2026-11-09T11:20:00.000Z', voided: true }),
    ])
    const { wrapper } = await mountView(CompetitionsParticipantMyOffers, participantCompetition())
    const text = wrapper.get('[data-testid="participant-my-offers"]').text()
    expect(text).toContain('2026-11-09 14:10:00.000 (KSA)')
    expect(text).toContain(t('offers.mine.change_lower', { pct: '0.5%' }))
    expect(text).toContain(t('offers.mine.voided'))
    expect(text).toContain(t('offers.mine.first'))
    wrapper.unmount()
  })

  it('shows the empty state', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantMyOffers, participantCompetition())
    expect(wrapper.text()).toContain(t('offers.mine.empty.title'))
    wrapper.unmount()
  })
})

describe('W13 header meta (participant and invitee)', () => {
  it('shows fees covered with the sponsor and the join deadline countdown to invitees', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantHeaderMeta, inviteeCompetition())
    const meta = wrapper.get('[data-testid="participant-header-meta"]')
    expect(meta.text()).toContain(t('sponsorship.badge.fees_covered_by', { sponsor: 'شركة المصدر' }))
    expect(meta.text()).toContain(t('invitations.landing.join_before'))
    wrapper.unmount()
  })

  it('counts down to the close for participants and never shows fees covered to others', async () => {
    const { wrapper } = await mountView(CompetitionsParticipantHeaderMeta, participantCompetition())
    const meta = wrapper.get('[data-testid="participant-header-meta"]')
    expect(meta.text()).toContain(t('live.countdown.closes'))
    expect(meta.text()).not.toContain(t('sponsorship.badge.fees_covered'))
    wrapper.unmount()
  })
})
