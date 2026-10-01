import Echo from 'laravel-echo'
import type { Pinia } from 'pinia'
import Pusher from 'pusher-js'
import type { EchoHandle, RealtimeConnectionState } from '~/types/runtime'

/**
 * Laravel Echo for Reverb (Pusher protocol, private channels only; ARCHITECTURE §9.1).
 *
 * - Settings come from `AppConfig.realtime` (`useAppConfigStore`); `NUXT_PUBLIC_REVERB_*` overrides
 *   them in development only (`resolveRealtimeSettings`).
 * - Private channels are authorised at `/broadcasting/auth` with the bearer token (never in a URL).
 * - The connection opens lazily on the first `useEcho()` call and is torn down whenever the token
 *   or the realtime settings change.
 * - `state` mirrors the Pusher connection for the connection indicator (SCREENS S4).
 */
export default defineNuxtPlugin((nuxtApp) => {
  const runtime = useRuntimeConfig().public
  const token = useAuthToken()
  const appConfig = useAppConfigStore(nuxtApp.$pinia as Pinia)
  const state = ref<RealtimeConnectionState>('idle')
  let echo: Echo<'reverb'> | null = null

  const settings = computed(() => resolveRealtimeSettings(appConfig.config?.realtime, runtime.reverb, import.meta.dev))
  const available = computed(() => settings.value !== null && Boolean(token.value))

  function connect(): Echo<'reverb'> | null {
    if (echo) return echo
    const current = settings.value
    if (!current || !token.value) return null

    echo = new Echo({
      broadcaster: 'reverb',
      key: current.key,
      wsHost: current.host,
      wsPort: current.port,
      wssPort: current.port,
      forceTLS: current.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: runtime.broadcastAuthEndpoint,
      bearerToken: token.value,
      auth: { headers: { Accept: 'application/json' } },
      Pusher,
      withoutInterceptors: true,
    })
    state.value = 'connecting'
    echo.connector.pusher.connection.bind('state_change', (change: { current: RealtimeConnectionState }) => {
      state.value = change.current
    })
    return echo
  }

  function disconnect(): void {
    echo?.disconnect()
    echo = null
    state.value = 'idle'
  }

  watch(token, (next, previous) => {
    if (next !== previous) disconnect()
  })
  watch(() => JSON.stringify(settings.value), (next, previous) => {
    if (next !== previous) disconnect()
  })

  const handle: EchoHandle = { connect, disconnect, state: readonly(state), available }
  return { provide: { echo: handle } }
})
