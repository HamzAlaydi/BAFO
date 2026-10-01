import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import type { Ref } from 'vue'
import { makeTokenPayload } from '../fixtures/api'
import { COMPETITION_ID, makeInvitation, makeIssuerCompetition, makeQuote } from '../fixtures/issuer'
import type { Comment, IssuerCompetition } from '~/types/api/competitions'
import AttachmentManager from '~/components/competitions/issuer/AttachmentManager.vue'
import EmailChips from '~/components/ui/EmailChipsInput.vue'
import InvitationsPanel from '~/components/competitions/issuer/InvitationsPanel.vue'
import QaBoard from '~/components/competitions/issuer/QaBoard.vue'
import StepFees from '~/components/competitions/wizard/StepFees.vue'

const competitions = vi.hoisted(() => ({
  listComments: vi.fn(),
  postComment: vi.fn(),
  removeInvitation: vi.fn(),
  resendInvitation: vi.fn(),
  updateInvitation: vi.fn(),
  listInvitations: vi.fn(),
  listAttachments: vi.fn(),
  uploadAttachmentWithProgress: vi.fn(),
  createLinkAttachment: vi.fn(),
  updateAttachment: vi.fn(),
  deleteAttachment: vi.fn(),
}))
const billing = vi.hoisted(() => ({
  fetchSponsorship: vi.fn(),
  fetchSponsorshipQuote: vi.fn(),
  updateSponsorship: vi.fn(),
  validateCoupon: vi.fn(),
}))
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/billing', () => billing)

const ctx = vi.hoisted(() => ({
  competition: null as Ref<IssuerCompetition | null> | null,
  listeners: new Map<string, Array<(payload: unknown) => void>>(),
}))
mockNuxtImport('useCompetitionContext', () => () => ({
  id: computed(() => COMPETITION_ID),
  competition: ctx.competition!,
  viewerRole: computed(() => 'issuer'),
  loading: ref(false),
  error: ref(null),
  live: computed(() => null),
  connection: computed(() => 'connected'),
  canSubmit: computed(() => true),
  refetch: vi.fn(),
  resync: vi.fn(),
  applyLive: () => true,
  on: (event: string, listener: (payload: unknown) => void) => {
    ctx.listeners.set(event, [...(ctx.listeners.get(event) ?? []), listener])
    return () => {}
  },
}))

const t = (key: string, params: Record<string, unknown> = {}, plural?: number) =>
  plural === undefined ? useNuxtApp().$i18n.t(key, params) : useNuxtApp().$i18n.t(key, params, plural)

async function flush(): Promise<void> {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

function comment(id: string, overrides: Partial<Comment> = {}): Comment {
  return { id, parent_id: null, body: `سؤال ${id}`, author: { kind: 'participant', alias_no: 3, organization_name: 'شركة الريادة' }, created_at: '2026-11-09T09:00:00.000Z', replies: [], ...overrides }
}

beforeEach(() => {
  for (const group of [competitions, billing]) {
    for (const fn of Object.values(group)) fn.mockReset()
  }
  ctx.listeners = new Map()
  ctx.competition = ref(makeIssuerCompetition({ status: 'live', permissions: { ...makeIssuerCompetition().permissions, can_comment: true } })) as Ref<IssuerCompetition | null>
  useAuthStore().setSession(makeTokenPayload())
})

describe('W18 Q&A for the issuer', () => {
  it('shows participants with alias and organisation, publishes announcements and inserts realtime comments once', async () => {
    competitions.listComments.mockResolvedValue({ items: [comment('c1')], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false } })
    competitions.postComment.mockResolvedValue(comment('a1', { body: 'إعلان', author: { kind: 'issuer', organization_name: 'شركة المصدر' } }))
    const wrapper = await mountSuspended(QaBoard)
    await flush()
    expect(wrapper.text()).toContain(`${t('offers.participant_alias', { number: 3 })} · شركة الريادة`)

    await wrapper.find('textarea').setValue('إعلان')
    await wrapper.find('form').trigger('submit')
    await flush()
    expect(competitions.postComment).toHaveBeenCalledWith(COMPETITION_ID, 'إعلان', null)
    expect(wrapper.text()).toContain(t('glossary.issuer'))

    const reply = comment('r1', { parent_id: 'c1', body: 'رد متأخر' })
    for (const listener of ctx.listeners.get('commentCreated') ?? []) {
      listener(reply)
      listener(reply)
    }
    await flush()
    expect(wrapper.text().match(/رد متأخر/g)).toHaveLength(1)
  })

  it('turns read-only on comments_closed', async () => {
    competitions.listComments.mockResolvedValue({ items: [], pagination: { type: 'page', current_page: 1, per_page: 20, has_more: false } })
    competitions.postComment.mockRejectedValue(new ApiError({ status: 409, code: 'comments_closed', message: 'closed', errors: {} }))
    const wrapper = await mountSuspended(QaBoard)
    await flush()
    await wrapper.find('textarea').setValue('سؤال')
    await wrapper.find('form').trigger('submit')
    await flush()
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.text()).toContain(t('qa.closed'))
  })
})

describe('e-mail chips', () => {
  it('splits pasted and typed lists and flags invalid addresses', async () => {
    const wrapper = await mountSuspended(EmailChips, { props: { label: 'emails', modelValue: [] } })
    const input = wrapper.find('input')
    await input.setValue('a@x.sa, bad b@x.sa ')
    await flush()
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toEqual(['a@x.sa', 'bad', 'b@x.sa'])
    await wrapper.setProps({ modelValue: ['a@x.sa', 'bad', 'b@x.sa'] })
    expect(wrapper.text()).toContain(t('invitations.issuer.picker.invalid_count', { count: 1 }, 1))
  })
})

describe('invitations table', () => {
  it('revokes a sent invitation only after confirmation, and shows the server result', async () => {
    const sent = makeInvitation('inv1', { status: 'sent', sent_at: '2026-11-01T09:00:00.000Z' })
    competitions.removeInvitation.mockResolvedValue({ ...sent, status: 'revoked' })
    const wrapper = await mountSuspended(InvitationsPanel, {
      props: { competitionId: COMPETITION_ID, invitations: [sent], status: 'live', canManage: true },
      attachTo: document.body,
    })
    await flush()
    await wrapper.find('button[aria-haspopup="menu"]').trigger('click')
    await flush()
    await wrapper.findAll('[role="menuitem"]').find(item => item.text() === t('invitations.issuer.actions.revoke'))!.trigger('click')
    await flush()
    expect(competitions.removeInvitation).not.toHaveBeenCalled()
    Array.from(document.querySelectorAll('button')).find(button => button.textContent?.trim() === t('invitations.issuer.revoke.confirm'))!.click()
    await flush()
    expect(competitions.removeInvitation).toHaveBeenCalledWith(COMPETITION_ID, 'inv1')
    expect(wrapper.emitted('updated')?.[0]?.[0]).toMatchObject({ status: 'revoked' })
    wrapper.unmount()
  })

  it('explains the daily resend limit', async () => {
    const sent = makeInvitation('inv1', { status: 'viewed' })
    competitions.resendInvitation.mockRejectedValue(new ApiError({ status: 429, code: 'too_many_requests', message: 'slow', errors: {} }))
    const wrapper = await mountSuspended(InvitationsPanel, {
      props: { competitionId: COMPETITION_ID, invitations: [sent], status: 'live', canManage: true },
      attachTo: document.body,
    })
    await flush()
    await wrapper.find('button[aria-haspopup="menu"]').trigger('click')
    await flush()
    await wrapper.findAll('[role="menuitem"]').find(item => item.text() === t('invitations.issuer.actions.resend'))!.trigger('click')
    await flush()
    expect(useToast().toasts.value.map(toast => toast.message)).toContain(t('invitations.issuer.resend_limit'))
    wrapper.unmount()
  })
})

describe('W15 fees step', () => {
  const sponsorship = {
    mode: 'none' as const, max_passes: null, unit_price_minor: 20_000, vat_rate_bp: 1500, currency: 'SAR' as const, status: 'draft' as const,
    funded_passes: 0, enabled: true, counts: { pending: 0, reserved: 0, joined: 0, released: 0, unused: 0, void: 0, free_slots: 0 }, unused_count: null, voucher: null,
  }

  it('changes the mode only after the server saves it, then re-quotes', async () => {
    billing.fetchSponsorship.mockResolvedValue(sponsorship)
    competitions.listInvitations.mockResolvedValue({ invitations: [makeInvitation('inv1')], counts: {} })
    billing.fetchSponsorshipQuote.mockResolvedValue(makeQuote({ mode: 'none', passes_to_buy: 0 }))
    let release!: (value: unknown) => void
    billing.updateSponsorship.mockReturnValue(new Promise((resolve) => {
      release = resolve
    }))
    const draft = makeIssuerCompetition()
    const wrapper = await mountSuspended(StepFees, { props: { competition: draft } })
    await flush()
    const all = wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'all')!
    await all.trigger('change')
    await flush()
    expect(billing.updateSponsorship).toHaveBeenCalledWith(COMPETITION_ID, { mode: 'all', max_passes: null })
    // Still the server's mode while the request runs (no optimistic change).
    expect((wrapper.findAll('input[type="radio"]').find(input => input.attributes('value') === 'none')!.element as HTMLInputElement).checked).toBe(true)

    billing.fetchSponsorshipQuote.mockResolvedValue(makeQuote({ lines: [{ invitation_id: 'inv1', email: 'inv1@supplier.sa', organization_name: null, coverage: 'sponsored', reason: null }] }))
    release({ ...sponsorship, mode: 'all' })
    await flush()
    expect(wrapper.text()).toContain('460.00')
    expect(wrapper.text()).toContain(t('sponsorship.coverage.sponsored'))
    expect(wrapper.emitted('changed')).toBeTruthy()
  })
})

describe('attachments', () => {
  it('allows adding but not deleting after publish (addenda)', async () => {
    competitions.listAttachments.mockResolvedValue([{ id: 'a1', kind: 'document', title: 'كراسة الشروط', file: { id: 'f1', name: 'specs.pdf', mime_type: 'application/pdf', extension: 'pdf', size_bytes: 482133, download_path: '/api/app/v1/files/f1/download', created_at: '2026-11-01T09:00:00.000Z' }, url: null, is_addendum: false, sort_order: 0, created_at: '2026-11-01T09:00:00.000Z' }])
    const live = await mountSuspended(AttachmentManager, { props: { competitionId: COMPETITION_ID, status: 'live', canManage: true } })
    await flush()
    expect(live.text()).toContain('كراسة الشروط')
    expect(live.text()).toContain(t('competitions.issuer.attachments.addendum_hint'))
    expect(live.findAll('button').some(button => button.attributes('aria-label') === t('competitions.issuer.attachments.delete', { name: 'كراسة الشروط' }))).toBe(false)

    const draft = await mountSuspended(AttachmentManager, { props: { competitionId: COMPETITION_ID, status: 'draft', canManage: true } })
    await flush()
    expect(draft.findAll('button').some(button => button.attributes('aria-label') === t('competitions.issuer.attachments.delete', { name: 'كراسة الشروط' }))).toBe(true)
  })
})
