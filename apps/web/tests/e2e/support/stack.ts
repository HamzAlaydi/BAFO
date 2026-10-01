import { mkdirSync, readFileSync } from 'node:fs'
import { expect, type APIRequestContext, type Browser, type BrowserContext, type Page, type TestInfo, type WebSocket } from '@playwright/test'
import { labelRx, tr, trPrefix, type Locale } from './i18n'

/**
 * Settings and helpers of the live end-to-end specs (`live-*.spec.ts`). They drive a real stack:
 * the web app (E2E_BASE_URL), the app API (E2E_API_BASE) with its queue worker and scheduler, and a
 * Reverb server, on a database seeded with `DemoSeeder`. Opt-in with E2E_LIVE=1 (see the README of
 * `tests/e2e`).
 */
export const LIVE_ENABLED = process.env.E2E_LIVE === '1'
export const API_BASE = process.env.E2E_API_BASE ?? 'http://localhost:8000/api/app/v1'
export const API_ORIGIN = new URL(API_BASE).origin
export const OTP_CODE = process.env.E2E_OTP_CODE ?? '123456'
export const SCREENSHOT_DIR = new URL('../../../docs/screenshots/e2e/', import.meta.url).pathname

/** DemoSeeder accounts (docs/build/DEMO.md). */
export const ACCOUNTS = {
  issuerOwner: 'issuer.owner@demo.bafo.test',
  issuerAdmin: 'issuer.admin@demo.bafo.test',
  supplierA: 'supplier-a.owner@demo.bafo.test',
  supplierB: 'supplier-b.owner@demo.bafo.test',
  supplierC: 'supplier-c.owner@demo.bafo.test',
  buyerD: 'buyer-d.owner@demo.bafo.test',
} as const

/** Display names (shown in both languages) and the legal names printed on documents. */
export const ORGS = {
  issuer: { name: 'Issuer Co', legal: ['شركة الطارح التجريبية', 'Issuer Co Co. Ltd.'] },
  supplierA: { name: 'Supplier A', legal: ['مورد أ التجريبي', 'Supplier A Co. Ltd.'] },
  supplierB: { name: 'Supplier B', legal: ['مورد ب التجريبي', 'Supplier B Co. Ltd.'] },
  supplierC: { name: 'Supplier C', legal: ['مورد ج التجريبي', 'Supplier C Co. Ltd.'] },
  buyerD: { name: 'Buyer D', legal: ['المشتري د التجريبي', 'Buyer D Co. Ltd.'] },
} as const

/** Every name an organisation is known by, for "never shown" checks. */
export function orgNames(org: { name: string, legal: readonly string[] }): string[] {
  return [org.name, ...org.legal]
}

/** E2E_PASSWORD, else the `DemoSeeder::PASSWORD` constant of the API (never logged). */
export function demoPassword(): string {
  if (process.env.E2E_PASSWORD) return process.env.E2E_PASSWORD
  const seeder = readFileSync(new URL('../../../../api/database/seeders/DemoSeeder.php', import.meta.url), 'utf8')
  const match = /const string PASSWORD = '([^']+)'/.exec(seeder)
  if (!match?.[1]) throw new Error('Set E2E_PASSWORD (DemoSeeder::PASSWORD was not found)')
  return match[1]
}

export function shotPath(name: string): string {
  mkdirSync(SCREENSHOT_DIR, { recursive: true })
  return `${SCREENSHOT_DIR}${name}.png`
}

export interface Actor {
  context: BrowserContext
  page: Page
  locale: Locale
  email: string
  /** Every WebSocket frame text received from Reverb, in order. */
  frames: string[]
  sockets: WebSocket[]
  /** App API requests (method + path), for "no polling" assertions. */
  requests: string[]
  /** Socket lifecycle and received event names with times, for diagnostics. */
  timeline: string[]
}

/** Actors signed in by the current test (per worker), for the failure diagnostics. */
export const activeActors: Actor[] = []

/**
 * Socket timelines and the last API requests of every actor of the test, attached to a failed test
 * (`test.afterEach(async ({}, info) => attachDiagnostics(info))`).
 */
export async function attachDiagnostics(info: TestInfo): Promise<void> {
  if (info.status !== info.expectedStatus) {
    const body = activeActors.map(actor => [
      `== ${actor.email} (${actor.locale}) ${actor.page.isClosed() ? '' : actor.page.url()}`,
      ...actor.timeline,
      `-- requests: ${actor.requests.slice(-25).join(', ')}`,
    ].join('\n')).join('\n\n')
    await info.attach('socket-timelines', { body, contentType: 'text/plain' })
  }
  activeActors.length = 0
}

/** A fresh browser context signed in through the web sign-in page. */
export async function signIn(browser: Browser, email: string, locale: Locale): Promise<Actor> {
  const context = await browser.newContext({ locale: locale === 'ar' ? 'ar-SA' : 'en-US', timezoneId: 'Asia/Riyadh' })
  const page = await context.newPage()
  const actor: Actor = { context, page, locale, email, frames: [], sockets: [], requests: [], timeline: [] }
  activeActors.push(actor)
  const stamp = () => new Date().toISOString().slice(11, 23)
  page.on('websocket', (socket) => {
    actor.sockets.push(socket)
    actor.timeline.push(`${stamp()} open ${actor.sockets.length}`)
    socket.on('close', () => actor.timeline.push(`${stamp()} close ${actor.sockets.indexOf(socket) + 1}`))
    socket.on('framereceived', (frame) => {
      if (typeof frame.payload !== 'string') return
      actor.frames.push(frame.payload)
      actor.timeline.push(`${stamp()} ${actor.sockets.indexOf(socket) + 1} ${describeFrame(frame.payload)}`)
    })
    socket.on('framesent', (frame) => {
      if (typeof frame.payload === 'string' && frame.payload.includes('subscribe')) actor.timeline.push(`${stamp()} ${actor.sockets.indexOf(socket) + 1} sent ${describeFrame(frame.payload)}`)
    })
  })
  page.on('request', (request) => {
    if (request.url().startsWith(API_ORIGIN)) actor.requests.push(`${request.method()} ${new URL(request.url()).pathname}`)
  })
  await page.goto(`/${locale}/auth/login`)
  await waitForHydration(page)
  await page.getByRole('textbox', { name: labelRx(tr(locale, 'auth.fields.email')) }).fill(email)
  await page.getByRole('textbox', { name: labelRx(tr(locale, 'auth.fields.password')) }).fill(demoPassword())
  const dashboard = new RegExp(`/${locale}/dashboard`)
  const submit = page.getByRole('button', { name: new RegExp(`^(${escapeRx(tr(locale, 'auth.login.submit'))}|${escapeRx(trPrefix(locale, 'common.retry_in'))})`) })
  // Parallel specs share one IP: the sign-in limiter (10 a minute per IP) may ask to wait. The page
  // shows a countdown on the button; sign in again once it is enabled, as a person would.
  for (let attempt = 1; ; attempt++) {
    await submit.click()
    try {
      await expect(page).toHaveURL(dashboard, { timeout: 15_000 })
      return actor
    }
    catch (error) {
      const alert = (await page.getByRole('alert').allInnerTexts().catch(() => [])).join(' | ')
      if (attempt >= 4 || !alert.includes(tr(locale, 'errors.too_many_requests'))) {
        throw new Error(`Sign-in of ${email} did not reach the dashboard: ${alert}`, { cause: error })
      }
      await expect(submit).toBeEnabled({ timeout: 75_000 })
    }
  }
}

function escapeRx(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/** Server-rendered pages (sign-in, register) take input only once Vue has hydrated them. */
export async function waitForHydration(page: Page): Promise<void> {
  await page.waitForFunction(() => Boolean((document.querySelector('#__nuxt') as { __vue_app__?: unknown } | null)?.__vue_app__))
  await page.waitForLoadState('networkidle').catch(() => undefined)
}

/** A bearer token for direct API reads (assertions only; the flows go through the UI). */
const tokens = new Map<string, string>()

export async function apiToken(request: APIRequestContext, email: string): Promise<string> {
  const cached = tokens.get(email)
  if (cached) return cached
  // The sign-in limiter (10 a minute per IP) is shared with the parallel specs: wait it out.
  for (let attempt = 1; ; attempt++) {
    const response = await request.post(`${API_BASE}/auth/login`, {
      headers: { 'Accept': 'application/json', 'X-Platform': 'web' },
      data: { email, password: demoPassword(), device_name: 'Web · e2e' },
    })
    if (response.status() === 429 && attempt < 6) {
      const body = await response.json().catch(() => ({})) as { details?: { retry_after_seconds?: number } }
      await new Promise(resolve => setTimeout(resolve, 1000 * Math.min(60, (body.details?.retry_after_seconds ?? 20) + 1)))
      continue
    }
    expect(response.ok(), `API sign-in for ${email}: ${response.status()}`).toBeTruthy()
    const token = (await response.json() as { data: { token: string } }).data.token
    tokens.set(email, token)
    return token
  }
}

export async function apiGet<T>(request: APIRequestContext, token: string, path: string): Promise<T> {
  const response = await request.get(`${API_BASE}${path}`, {
    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}`, 'X-Platform': 'web' },
  })
  expect(response.ok(), `GET ${path}: ${response.status()}`).toBeTruthy()
  return (await response.json() as { data: T }).data
}

/** Riyadh wall time (UTC+3, no DST) of an instant, as the `datetime-local` value the pickers take. */
export function riyadhLocal(at: Date): string {
  const shifted = new Date(at.getTime() + 3 * 3_600_000)
  return shifted.toISOString().slice(0, 16)
}

/** Waits until the given server-clock instant has passed. */
export async function waitUntil(at: number, page: Page): Promise<void> {
  const remaining = at - Date.now()
  if (remaining > 0) await page.waitForTimeout(remaining)
}

/** `event channel v status seq` of a Pusher frame (diagnostics). */
function describeFrame(payload: string): string {
  try {
    const event = JSON.parse(payload) as { event?: string, channel?: string, data?: unknown }
    const data = (typeof event.data === 'string' ? JSON.parse(event.data) : event.data) as Record<string, unknown> | null
    const channel = event.channel ?? (data && typeof data.channel === 'string' ? data.channel : '')
    const extra = data ? ['v', 'status', 'seq'].filter(key => key in data).map(key => `${key}=${String(data[key])}`).join(' ') : ''
    return `${event.event ?? '?'} ${channel.replace(/^private-/, '').slice(0, 60)} ${extra}`.trim()
  }
  catch {
    return payload.slice(0, 80)
  }
}

/** The Reverb event names received so far on a page (`live.updated`, `offer.accepted`, …). */
export function socketEvents(actor: Actor): string[] {
  return actor.frames.flatMap((frame) => {
    try {
      const parsed = JSON.parse(frame) as { event?: string }
      return parsed.event ? [parsed.event] : []
    }
    catch {
      return []
    }
  })
}
