import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeTokenPayload, makeUser } from '../fixtures/api'
import { COMPETITION_ID, myOffer, participantSnapshot } from '../fixtures/participant'

const bidding = vi.hoisted(() => ({ fetchMyOffers: vi.fn() }))
vi.mock('~/services/bidding', () => bidding)

async function flush(ms = 0): Promise<void> {
  await new Promise(resolve => setTimeout(resolve, ms))
  for (let i = 0; i < 4; i++) await new Promise(resolve => setTimeout(resolve, 0))
}

beforeEach(() => {
  bidding.fetchMyOffers.mockReset()
  useAuthStore().setSession(makeTokenPayload())
  useLiveRoomStore().reset()
})

describe('useLiveRoomStore (W19 my offer, W21 my offers)', () => {
  it('loads the history newest first', async () => {
    bidding.fetchMyOffers.mockResolvedValue([myOffer(1, 10_000), myOffer(3, 9_000), myOffer(2, 9_500)])
    const store = useLiveRoomStore()
    await store.load(COMPETITION_ID)
    expect(bidding.fetchMyOffers).toHaveBeenCalledWith(COMPETITION_ID)
    expect(store.offers.map(offer => offer.seq)).toEqual([3, 2, 1])
    expect(store.latest?.seq).toBe(3)
    expect(store.loaded).toBe(true)
  })

  it('adds a server-accepted offer once and never before the response', async () => {
    bidding.fetchMyOffers.mockResolvedValue([myOffer(1, 10_000)])
    const store = useLiveRoomStore()
    await store.load(COMPETITION_ID)
    const accepted = { ...myOffer(2, 9_900) }
    store.recordAccepted(COMPETITION_ID, accepted)
    store.recordAccepted(COMPETITION_ID, accepted)
    store.recordAccepted('another-competition', myOffer(9, 1))
    expect(store.offers.map(offer => offer.seq)).toEqual([2, 1])
  })

  it('refetches when a snapshot shows an unknown own offer or a void', async () => {
    bidding.fetchMyOffers.mockResolvedValue([myOffer(1, 10_000)])
    const store = useLiveRoomStore()
    await store.load(COMPETITION_ID)
    bidding.fetchMyOffers.mockClear()

    store.syncWithSnapshot(COMPETITION_ID, participantSnapshot({ my_offer: { id: myOffer(1, 10_000).id, seq: 1, amount_minor: 10_000, stage: 'live', accepted_at: 'x' } }))
    await flush(450)
    expect(bidding.fetchMyOffers).not.toHaveBeenCalled()

    store.syncWithSnapshot(COMPETITION_ID, participantSnapshot({ my_offer: { id: '01jbotherdevice00000000000', seq: 2, amount_minor: 9_900, stage: 'live', accepted_at: 'x' } }))
    await flush(450)
    expect(bidding.fetchMyOffers).toHaveBeenCalledTimes(1)

    store.syncWithSnapshot(COMPETITION_ID, participantSnapshot({ my_offer: null, last_change: { kind: 'void', reason: null } }))
    await flush(450)
    expect(bidding.fetchMyOffers).toHaveBeenCalledTimes(2)
  })

  it('keeps an error without losing loaded data, and drops everything on another competition or user', async () => {
    bidding.fetchMyOffers.mockResolvedValueOnce([myOffer(1, 10_000)]).mockRejectedValueOnce(new ApiError({ status: 500, code: 'server_error', message: '', errors: {} }))
    const store = useLiveRoomStore()
    await store.load(COMPETITION_ID)
    await store.load(COMPETITION_ID)
    expect(store.error?.code).toBe('server_error')
    expect(store.offers).toHaveLength(1)

    store.select('01j9other00000000000000000')
    expect(store.offers).toEqual([])
    expect(store.loaded).toBe(false)

    bidding.fetchMyOffers.mockResolvedValue([myOffer(1, 10_000)])
    await store.load(COMPETITION_ID)
    useAuthStore().setSession(makeTokenPayload({ user: makeUser({ id: '01j9usr0000000000000000099' }) }))
    await flush()
    expect(store.offers).toEqual([])
    expect(store.competitionId).toBeNull()
  })
})
