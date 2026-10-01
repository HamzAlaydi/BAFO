import { mkdirSync } from 'node:fs'
import { expect, test, type APIRequestContext, type Page } from '@playwright/test'

/**
 * Visual review pass (opt-in): signs in through the app API, opens the key pages in Arabic and English
 * at 360, 768 and 1280 px, saves full-page screenshots under `docs/screenshots/<width>/`, and fails a
 * page that scrolls sideways (SCREENS S1: layouts wrap, they never overflow the viewport).
 *
 * It needs a running web app and a seeded API (DemoSeeder), so it is skipped unless enabled:
 *
 *   E2E_SCREENSHOTS=1 E2E_BASE_URL=http://localhost:3000 E2E_API_BASE=http://localhost:8000/api/app/v1 \
 *   E2E_PASSWORD=<DemoSeeder password> pnpm exec playwright test screenshots --project chromium
 *
 * Optional: E2E_ISSUER_EMAIL, E2E_PARTICIPANT_EMAIL (DemoSeeder issuer and supplier A owners by default),
 * PW_CHANNEL=chrome to drive the installed Google Chrome instead of a downloaded Chromium.
 */
const ENABLED = process.env.E2E_SCREENSHOTS === '1'
const API_BASE = process.env.E2E_API_BASE ?? 'http://localhost:8000/api/app/v1'
const PASSWORD = process.env.E2E_PASSWORD ?? ''
const ISSUER_EMAIL = process.env.E2E_ISSUER_EMAIL ?? 'issuer.owner@demo.bafo.test'
const PARTICIPANT_EMAIL = process.env.E2E_PARTICIPANT_EMAIL ?? 'supplier-a.owner@demo.bafo.test'
const OUT_DIR = new URL('../../docs/screenshots/', import.meta.url).pathname

const VIEWPORTS = [
  { width: 360, height: 780 },
  { width: 768, height: 1024 },
  { width: 1280, height: 900 },
] as const

type Locale = 'ar' | 'en'

interface Shot {
  name: string
  path: string
  /** English is captured at 1280 px only; Arabic (the design reference) at every width. */
  locales?: readonly Locale[]
}

interface ListItem { id: string, status: string, format?: string }

async function signIn(request: APIRequestContext, email: string): Promise<string> {
  const response = await request.post(`${API_BASE}/auth/login`, {
    headers: { 'Accept': 'application/json', 'X-Platform': 'web' },
    data: { email, password: PASSWORD, device_name: 'Web · screenshots' },
  })
  expect(response.ok(), `sign-in for ${email}`).toBeTruthy()
  const body = await response.json() as { data: { token: string } }
  return body.data.token
}

async function competitions(request: APIRequestContext, token: string, role: 'issuer' | 'participant'): Promise<ListItem[]> {
  const response = await request.get(`${API_BASE}/competitions?role=${role}&status_group=all&per_page=50`, {
    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}`, 'X-Platform': 'web' },
  })
  expect(response.ok()).toBeTruthy()
  return (await response.json() as { data: ListItem[] }).data
}

function first(items: ListItem[], predicate: (item: ListItem) => boolean): string | null {
  return items.find(predicate)?.id ?? null
}

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle').catch(() => undefined)
  // Skeletons are replaced once the data arrives; give the client render a moment.
  await page.waitForTimeout(600)
}

async function startSession(page: Page, token: string): Promise<void> {
  await page.goto('/ar')
  await page.context().addCookies([{ name: 'bafo_token', value: encodeURIComponent(token), url: new URL(page.url()).origin }])
}

async function capture(page: Page, shot: Shot): Promise<string[]> {
  const problems: string[] = []
  const locales = shot.locales ?? ['ar', 'en']
  for (const viewport of VIEWPORTS) {
    for (const locale of locales) {
      if (locale === 'en' && viewport.width !== 1280) continue
      await page.setViewportSize(viewport)
      await page.goto(`/${locale}${shot.path}`)
      await settle(page)
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      if (overflow > 1) problems.push(`${locale} ${viewport.width}px ${shot.name}: scrolls sideways by ${overflow}px`)
      const dir = `${OUT_DIR}${viewport.width}`
      mkdirSync(dir, { recursive: true })
      await page.screenshot({ path: `${dir}/${locale}-${shot.name}.jpg`, fullPage: true, type: 'jpeg', quality: 80 })
    }
  }
  return problems
}

test.describe('screenshots', () => {
  test.skip(!ENABLED, 'Set E2E_SCREENSHOTS=1 (and a seeded API) to capture the visual review screenshots.')
  test.describe.configure({ mode: 'serial', timeout: 15 * 60_000 })

  test('public pages', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Viewports are set per shot.')
    const shots: Shot[] = [
      { name: 'landing', path: '' },
      { name: 'login', path: '/auth/login' },
      { name: 'register', path: '/auth/register' },
      { name: 'legal-terms', path: '/legal/terms' },
    ]
    const problems: string[] = []
    for (const shot of shots) problems.push(...await capture(page, shot))
    expect(problems).toEqual([])
  })

  test('issuer pages', async ({ page, request }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Viewports are set per shot.')
    const token = await signIn(request, ISSUER_EMAIL)
    const list = await competitions(request, token, 'issuer')
    const live = first(list, item => item.status === 'live' && item.format === 'live')
    const sealed = first(list, item => item.status === 'live' && item.format === 'sealed')
    const draft = first(list, item => item.status === 'draft')
    const awarded = first(list, item => item.status === 'awarded')
    const bafo = first(list, item => item.status === 'bafo_round')
    await startSession(page, token)
    const shots: Shot[] = [
      { name: 'home', path: '/dashboard' },
      { name: 'competitions', path: '/dashboard/competitions' },
      { name: 'competition-new', path: '/dashboard/competitions/new' },
      ...(draft ? [{ name: 'wizard-rules', path: `/dashboard/competitions/${draft}/setup/rules` }, { name: 'wizard-review', path: `/dashboard/competitions/${draft}/setup/review` }] : []),
      ...(live
        ? [
            { name: 'issuer-overview', path: `/dashboard/competitions/${live}` },
            { name: 'issuer-participants', path: `/dashboard/competitions/${live}/participants` },
            { name: 'issuer-live', path: `/dashboard/competitions/${live}/live` },
            { name: 'issuer-offers', path: `/dashboard/competitions/${live}/offers` },
            { name: 'issuer-qa', path: `/dashboard/competitions/${live}/qa` },
          ]
        : []),
      ...(sealed ? [{ name: 'issuer-live-sealed', path: `/dashboard/competitions/${sealed}/live` }] : []),
      ...(bafo ? [{ name: 'issuer-award-bafo', path: `/dashboard/competitions/${bafo}/award` }] : []),
      ...(awarded ? [{ name: 'issuer-award', path: `/dashboard/competitions/${awarded}/award` }] : []),
      { name: 'vendors', path: '/dashboard/vendors' },
      { name: 'team', path: '/dashboard/team' },
      { name: 'organization', path: '/dashboard/organization' },
      { name: 'account', path: '/dashboard/account' },
      { name: 'notifications', path: '/dashboard/notifications' },
      { name: 'billing', path: '/dashboard/billing' },
      { name: 'plans', path: '/dashboard/billing/plans' },
      { name: 'invoices', path: '/dashboard/billing/invoices' },
      { name: 'integrations', path: '/dashboard/integrations' },
      { name: 'api-clients', path: '/dashboard/integrations/api-clients' },
      { name: 'webhooks', path: '/dashboard/integrations/webhooks' },
      { name: 'exports', path: '/dashboard/integrations/exports' },
    ]
    const problems: string[] = []
    for (const shot of shots) problems.push(...await capture(page, shot))
    expect(problems).toEqual([])
  })

  test('participant pages', async ({ page, request }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Viewports are set per shot.')
    const token = await signIn(request, PARTICIPANT_EMAIL)
    const list = await competitions(request, token, 'participant') as (ListItem & { access?: { state: string } })[]
    const live = list.find(item => item.status === 'live' && item.format === 'live' && item.access?.state === 'full')?.id ?? null
    const sealed = first(list, item => item.status === 'live' && item.format === 'sealed')
    const invited = list.find(item => item.access?.state === 'join_required')?.id ?? null
    const awarded = first(list, item => item.status === 'awarded')
    await startSession(page, token)
    const shots: Shot[] = [
      { name: 'participating', path: '/dashboard/participating' },
      ...(invited ? [{ name: 'invitee-overview', path: `/dashboard/competitions/${invited}` }] : []),
      ...(live
        ? [
            { name: 'participant-overview', path: `/dashboard/competitions/${live}` },
            { name: 'participant-live', path: `/dashboard/competitions/${live}/live` },
            { name: 'participant-my-offers', path: `/dashboard/competitions/${live}/my-offers` },
            { name: 'participant-qa', path: `/dashboard/competitions/${live}/qa` },
          ]
        : []),
      ...(sealed ? [{ name: 'participant-live-sealed', path: `/dashboard/competitions/${sealed}/live` }] : []),
      ...(awarded ? [{ name: 'participant-result', path: `/dashboard/competitions/${awarded}` }] : []),
    ]
    const problems: string[] = []
    for (const shot of shots) problems.push(...await capture(page, shot))
    expect(problems).toEqual([])
  })
})
