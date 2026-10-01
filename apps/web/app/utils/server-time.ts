/**
 * Clock offset between the server (authoritative for bidding) and this device (ARCHITECTURE §9.6, SCREENS S3).
 *
 * `sentAt`/`receivedAt` are the local timestamps around the request that carried `server_time`;
 * the server stamped its time somewhere in between, so the midpoint halves the network delay.
 * Returns `serverNow - localNow` in ms, or null when `serverTime` is not a valid timestamp.
 */
export function computeClockOffset(serverTime: string, receivedAt: number, sentAt?: number): number | null {
  const server = Date.parse(serverTime)
  if (Number.isNaN(server)) return null
  const localReference = sentAt !== undefined && sentAt <= receivedAt ? (sentAt + receivedAt) / 2 : receivedAt
  return Math.round(server - localReference)
}

/** How many recent samples the offset is the median of (SCREENS S3 step 2). */
export const CLOCK_SAMPLE_WINDOW = 3

/** A round trip above this is a slow connection (SCREENS S3 step 7). */
export const SLOW_ROUND_TRIP_MS = 2000

/** Median of the numbers (mean of the two middle values for an even count); null when empty. */
export function median(values: readonly number[]): number | null {
  if (values.length === 0) return null
  const sorted = [...values].sort((a, b) => a - b)
  const middle = Math.floor(sorted.length / 2)
  if (sorted.length % 2 === 1) return sorted[middle] ?? null
  return Math.round(((sorted[middle - 1] ?? 0) + (sorted[middle] ?? 0)) / 2)
}

/** Appends a sample and keeps only the last `CLOCK_SAMPLE_WINDOW`. */
export function pushSample(samples: readonly number[], sample: number): number[] {
  return [...samples, sample].slice(-CLOCK_SAMPLE_WINDOW)
}

/**
 * Finds a `server_time` in an API response body: top level, `meta.server_time` or `data.server_time`.
 */
export function extractServerTime(body: unknown): string | null {
  if (typeof body !== 'object' || body === null) return null
  const record = body as Record<string, unknown>
  const candidates = [
    record.server_time,
    (record.meta as Record<string, unknown> | undefined)?.server_time,
    (record.data as Record<string, unknown> | undefined)?.server_time,
  ]
  const found = candidates.find((value): value is string => typeof value === 'string' && value !== '')
  return found ?? null
}
