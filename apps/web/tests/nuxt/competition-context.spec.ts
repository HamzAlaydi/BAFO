import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { makeTokenPayload } from '../fixtures/api'
import type { CompetitionContext } from '~/composables/useCompetitionContext'

const services = vi.hoisted(() => ({ fetchCompetition: vi.fn(), fetchLive: vi.fn() }))
vi.mock('~/services/competitions', () => ({ fetchCompetition: services.fetchCompetition }))
vi.mock('~/services/bidding', () => ({ fetchLive: services.fetchLive }))

/** A fake Echo: records channel listeners and lets the test fire events and subscription success. */
const echo = vi.hoisted(() => {
  const channels = new Map<string, { listeners: Map<string, (payload: unknown) => void>, subscribed: Array<() => void> }>()
  const api = {
    channels,
    left: [] as string[],
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
    leave(name: string) {
      api.left.push(name)
    },
  }
  return api
})

const realtime = vi.hoisted(() => ({ available: { value: true }, state: { value: 'connected' } }))

mockNuxtImport('useEcho', () => () => echo)
mockNuxtImport('useRealtimeStatus', () => () => ({ available: toRef(realtime.available, 'value'), state: toRef(realtime.state, 'value') }))

const ID = '01j9comp000000000000000000'

function competition(overrides: Record<string, unknown> = {}) {
  return {
    id: ID,
    viewer_role: 'participant',
    status: 'live',
    phase: 'open',
    direction: 'tender',
    live: null,
    ...overrides,
  }
}

function snapshot(v: number, overrides: Record<string, unknown> = {}) {
  return { v, competition_id: ID, status: 'live', effective_close_at: new Date(Date.now() + 3_600_000).toISOString(), last_change: { kind: 'offer', reason: null }, ...overrides }
}

async function mountContext(): Promise<{ context: CompetitionContext, unmount: () => void }> {
  let context!: CompetitionContext
  const wrapper = await mountSuspended(defineComponent({
    setup() {
      context = provideCompetition(ID)
      return () => h('div')
    },
  }))
  await flushPromises()
  return { context, unmount: () => wrapper.unmount() }
}

async function flushPromises(): Promise<void> {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  services.fetchCompetition.mockReset()
  services.fetchLive.mockReset()
  echo.channels.clear()
  echo.left = []
  realtime.available.value = true
  realtime.state.value = 'connected'
  useAuthStore().setSession(makeTokenPayload())
})

afterEach(() => {
  vi.useRealTimers()
})

describe('provideCompetition (SCREENS S4, ARCHITECTURE §9.4)', () => {
  it('subscribes to the participant channel of the viewer’s organisation', async () => {
    services.fetchCompetition.mockResolvedValue(competition())
    const { unmount } = await mountContext()
    const orgId = useAuthStore().organization!.id
    expect([...echo.channels.keys()]).toEqual([`competition.${ID}.participant.${orgId}`])
    const channel = echo.channels.get(`competition.${ID}.participant.${orgId}`)!
    expect([...channel.listeners.keys()].sort()).toEqual(['.comment.created', '.competition.updated', '.live.updated'])
    unmount()
    expect(echo.left).toContain(`competition.${ID}.participant.${orgId}`)
  })

  it('subscribes issuers to the issuer channel with the issuer-only events', async () => {
    services.fetchCompetition.mockResolvedValue(competition({ viewer_role: 'issuer' }))
    const { unmount } = await mountContext()
    const channel = echo.channels.get(`competition.${ID}`)!
    expect([...channel.listeners.keys()]).toEqual(expect.arrayContaining(['.offer.accepted', '.invitation.updated']))
    unmount()
  })

  it('never subscribes invitees or drafts', async () => {
    services.fetchCompetition.mockResolvedValue(competition({ viewer_role: 'invitee' }))
    const first = await mountContext()
    expect(echo.channels.size).toBe(0)
    first.unmount()

    services.fetchCompetition.mockResolvedValue(competition({ viewer_role: 'issuer', status: 'draft' }))
    const second = await mountContext()
    expect(echo.channels.size).toBe(0)
    second.unmount()
  })

  it('buffers realtime snapshots until the REST snapshot, then applies by v', async () => {
    services.fetchCompetition.mockResolvedValue(competition())
    let resolveLive!: (value: unknown) => void
    services.fetchLive.mockReturnValue(new Promise((resolve) => {
      resolveLive = resolve
    }))
    const { context, unmount } = await mountContext()
    const channel = [...echo.channels.values()][0]!
    const onLive = channel.listeners.get('.live.updated')!

    channel.subscribed[0]!()
    onLive(snapshot(12))
    onLive(snapshot(10))
    expect(context.live.value).toBeNull()

    resolveLive(snapshot(11))
    await flushPromises()
    expect(context.live.value?.v).toBe(12)

    onLive(snapshot(9))
    expect(context.live.value?.v).toBe(12)
    expect(context.applyLive(snapshot(13) as never)).toBe(true)
    expect(context.connection.value).toBe('connected')
    unmount()
  })

  it('forwards events and refetches the competition on competition.updated', async () => {
    services.fetchCompetition.mockResolvedValue(competition({ viewer_role: 'issuer' }))
    services.fetchLive.mockResolvedValue(snapshot(1))
    const { context, unmount } = await mountContext()
    const channel = echo.channels.get(`competition.${ID}`)!
    const comments: unknown[] = []
    const offers: unknown[] = []
    context.on('commentCreated', comment => comments.push(comment))
    context.on('offerAccepted', entry => offers.push(entry))
    channel.listeners.get('.comment.created')!({ id: 'c1' })
    channel.listeners.get('.offer.accepted')!({ id: 'o1', seq: 1 })
    expect(comments).toHaveLength(1)
    expect(offers).toHaveLength(1)

    const calls = services.fetchCompetition.mock.calls.length
    channel.listeners.get('.competition.updated')!({ competition_id: ID, fields: ['title'], server_time: '' })
    await new Promise(resolve => setTimeout(resolve, 350))
    expect(services.fetchCompetition.mock.calls.length).toBe(calls + 1)
    unmount()
  })

  it('refetches the competition when a snapshot changes the status', async () => {
    services.fetchCompetition.mockResolvedValue(competition())
    services.fetchLive.mockResolvedValue(snapshot(1))
    const { unmount } = await mountContext()
    const channel = [...echo.channels.values()][0]!
    channel.subscribed[0]!()
    await flushPromises()
    const calls = services.fetchCompetition.mock.calls.length
    channel.listeners.get('.live.updated')!(snapshot(2, { status: 'closed', last_change: { kind: 'status', reason: null } }))
    await new Promise(resolve => setTimeout(resolve, 350))
    expect(services.fetchCompetition.mock.calls.length).toBe(calls + 1)
    unmount()
  })

  it('falls back to polling /live when the socket is down for 10 s', async () => {
    vi.useFakeTimers()
    realtime.state.value = 'unavailable'
    services.fetchCompetition.mockResolvedValue(competition())
    services.fetchLive.mockResolvedValue(snapshot(5))
    let context!: CompetitionContext
    const wrapper = await mountSuspended(defineComponent({
      setup() {
        context = provideCompetition(ID)
        return () => h('div')
      },
    }))
    await vi.advanceTimersByTimeAsync(1000)
    expect(context.connection.value).toBe('connecting')
    await vi.advanceTimersByTimeAsync(10_000)
    expect(context.connection.value).toBe('polling')
    expect(services.fetchLive).toHaveBeenCalled()
    expect(context.live.value?.v).toBe(5)
    expect(context.canSubmit.value).toBe(true)
    wrapper.unmount()
  })
})
