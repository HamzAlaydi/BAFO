import type { Ref } from 'vue'
import type Echo from 'laravel-echo'
import type { EchoHandle, RealtimeConnectionState } from '~/types/runtime'

/**
 * Realtime (Laravel Reverb over the Pusher protocol). Client-only: returns null during SSR,
 * when signed out, or before `GET /app-config` has provided the realtime settings.
 *
 *   const echo = useEcho()
 *   echo?.private(`competition.${id}`).listen('.offer.accepted', handler)
 */
export function useEcho(): Echo<'reverb'> | null {
  if (import.meta.server) return null
  return (useNuxtApp().$echo as EchoHandle).connect()
}

/** Whether realtime can connect now (signed in and configured), and the live connection state. */
export function useRealtimeStatus(): { available: Readonly<Ref<boolean>>, state: Readonly<Ref<RealtimeConnectionState>> } {
  if (import.meta.server) return { available: ref(false), state: ref<RealtimeConnectionState>('idle') }
  const echo = useNuxtApp().$echo as EchoHandle
  return { available: echo.available, state: echo.state }
}
