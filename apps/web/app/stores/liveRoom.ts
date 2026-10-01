import { fetchMyOffers } from '~/services/bidding'
import type { AcceptedOffer, MyOffer, ParticipantLiveSnapshot } from '~/types/api/bidding'

/**
 * The participant's own offer history for the open competition (W19 "My offer" card, W21 My offers),
 * shared by the tabs of one competition. It holds **server data only**: the list comes from
 * `GET …/my-offers`, a submitted offer is added from the `POST …/offers` response (never before),
 * and a snapshot showing an unknown own offer or a void triggers a refetch (CD9, CD10).
 */
export const useLiveRoomStore = defineStore('liveRoom', () => {
  const auth = useAuthStore()
  const competitionId = ref<string | null>(null)
  const offers = ref<MyOffer[]>([])
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref<ApiError | null>(null)
  let requestSeq = 0

  const latest = computed(() => offers.value.find(offer => !offer.voided) ?? null)

  function newestFirst(list: readonly MyOffer[]): MyOffer[] {
    return [...list].sort((a, b) => b.seq - a.seq)
  }

  /** Switches to another competition, dropping the previous one's history. */
  function select(id: string): void {
    if (competitionId.value === id) return
    requestSeq += 1
    competitionId.value = id
    offers.value = []
    loaded.value = false
    loading.value = false
    error.value = null
  }

  async function load(id: string): Promise<void> {
    select(id)
    const seq = ++requestSeq
    loading.value = true
    try {
      const list = await fetchMyOffers(id)
      if (seq !== requestSeq) return
      offers.value = newestFirst(list)
      loaded.value = true
      error.value = null
    }
    catch (cause) {
      if (seq !== requestSeq) return
      error.value = cause instanceof ApiError ? cause : new ApiError({ status: null, code: 'network_error', message: '', errors: {} })
    }
    finally {
      if (seq === requestSeq) loading.value = false
    }
  }

  const refreshSoon = useDebounceFn((id: string) => load(id), 400)

  /** Adds an offer the server just accepted (the `POST …/offers` response). */
  function recordAccepted(id: string, offer: AcceptedOffer): void {
    if (competitionId.value !== id || !loaded.value) return
    if (offers.value.some(item => item.id === offer.id)) return
    offers.value = newestFirst([{ id: offer.id, seq: offer.seq, amount_minor: offer.amount_minor, stage: offer.stage, accepted_at: offer.accepted_at, voided: offer.voided }, ...offers.value])
  }

  /** Keeps the loaded history in step with applied snapshots (an offer from another tab, a void). */
  function syncWithSnapshot(id: string, snapshot: ParticipantLiveSnapshot): void {
    if (competitionId.value !== id || !loaded.value) return
    const mine = snapshot.my_offer
    const unknownOffer = mine !== null && !offers.value.some(item => item.id === mine.id)
    if (unknownOffer || snapshot.last_change.kind === 'void') void refreshSoon(id)
  }

  function reset(): void {
    requestSeq += 1
    competitionId.value = null
    offers.value = []
    loaded.value = false
    loading.value = false
    error.value = null
  }

  // Another user's offers must never show after a sign-in switch.
  watch(() => auth.user?.id ?? null, (id, previous) => {
    if (id !== previous) reset()
  })

  return { competitionId, offers, latest, loaded, loading, error, select, load, recordAccepted, syncWithSnapshot, reset }
})
