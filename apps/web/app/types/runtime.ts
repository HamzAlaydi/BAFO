/**
 * Explicit types of the app-wide injections (`$api`, `$echo`). Declared here instead of inferred from
 * the plugins, so stores and services can use them without circular type inference.
 */
import type { ComputedRef, Ref } from 'vue'
import type Echo from 'laravel-echo'
import type { FetchOptions, FetchResponse, MappedResponseType, ResponseType } from 'ofetch'

export interface ApiClient {
  <T = unknown>(request: string, options?: FetchOptions<'json'>): Promise<T>
  /** Full response (status and headers) for ETag, 202-vs-200 and `Idempotent-Replayed` handling. */
  raw<T = unknown, R extends ResponseType = 'json'>(
    request: string,
    options?: FetchOptions<R>,
  ): Promise<FetchResponse<MappedResponseType<R, T>>>
}

/** Pusher connection states (plus `idle` before the first connect). */
export type RealtimeConnectionState = 'idle' | 'initialized' | 'connecting' | 'connected' | 'unavailable' | 'failed' | 'disconnected'

export interface EchoHandle {
  /** Opens (or returns) the connection; null when signed out or not configured. */
  connect: () => Echo<'reverb'> | null
  disconnect: () => void
  state: Readonly<Ref<RealtimeConnectionState>>
  /** Signed in and realtime settings known. */
  available: ComputedRef<boolean>
}
