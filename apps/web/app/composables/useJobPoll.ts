import type { Ref } from 'vue'

/**
 * Polls an import, export or report job until it reaches a terminal status (SCREENS §2.1):
 * every 2 s by default (reports: 3 s). Network hiccups keep polling; other errors stop it.
 *
 *   const job = useJobPoll(() => fetchExportJob(id), { initial: created })
 *   job.start()
 */
export interface JobPollOptions<T> {
  intervalMs?: number
  /** Terminal when this returns true. Default: status `completed`, `failed` or `ready`. */
  isTerminal?: (value: T) => boolean
  /** First value (e.g. the 202 response) to show before the first poll. */
  initial?: T | null
}

const DEFAULT_TERMINAL = new Set(['completed', 'failed', 'ready'])

export function useJobPoll<T extends { status: string }>(fetcher: () => Promise<T>, options: JobPollOptions<T> = {}) {
  const intervalMs = options.intervalMs ?? 2000
  const isTerminal = options.isTerminal ?? ((value: T) => DEFAULT_TERMINAL.has(value.status))
  const data = ref<T | null>(options.initial ?? null) as Ref<T | null>
  const polling = ref(false)
  const error = ref<ApiError | null>(null)
  let timer: ReturnType<typeof setTimeout> | null = null
  let generation = 0

  async function tick(run: number): Promise<void> {
    try {
      const value = await fetcher()
      if (run !== generation) return
      data.value = value
      error.value = null
      if (isTerminal(value)) {
        polling.value = false
        return
      }
    }
    catch (cause) {
      if (run !== generation) return
      const apiError = cause instanceof ApiError ? cause : null
      if (!apiError || !apiError.isRetryable) {
        error.value = apiError
        polling.value = false
        return
      }
    }
    timer = setTimeout(() => void tick(run), intervalMs)
  }

  /** Starts polling (after one interval when an initial value is known, immediately otherwise). */
  function start(initial?: T | null): void {
    stop()
    if (initial !== undefined) data.value = initial
    if (data.value && isTerminal(data.value)) return
    generation += 1
    const run = generation
    polling.value = true
    error.value = null
    if (data.value) timer = setTimeout(() => void tick(run), intervalMs)
    else void tick(run)
  }

  function stop(): void {
    generation += 1
    if (timer) clearTimeout(timer)
    timer = null
    polling.value = false
  }

  if (getCurrentScope()) onScopeDispose(stop)

  return { data, polling, error, start, stop }
}
