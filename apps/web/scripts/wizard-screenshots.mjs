import { mkdirSync, readFileSync, rmSync } from 'node:fs'
import { chromium } from '@playwright/test'

/**
 * Visual check of the 5-step creation wizard against a running stack (RELEASE_SCOPE.md §2, §8):
 * signs in as the DemoSeeder issuer through the app API, opens the new-competition page and every
 * step of the first draft in Arabic and English at 1280 px and 390 px, verifies the legacy step
 * aliases redirect, checks that a hidden page (Team) renders the 404 state in place, and saves
 * full-page screenshots under `docs/screenshots/core/<width>/`.
 * A page that scrolls sideways fails the run (SCREENS S1).
 *
 *   node scripts/wizard-screenshots.mjs
 *   WEB_BASE=http://localhost:3000 API_BASE=http://localhost:8000/api/app/v1 ISSUER_EMAIL=… ISSUER_PASSWORD=…
 */
const WEB_BASE = process.env.WEB_BASE ?? 'http://localhost:3000'
const API_BASE = process.env.API_BASE ?? 'http://localhost:8000/api/app/v1'
const EMAIL = process.env.ISSUER_EMAIL ?? 'issuer.owner@demo.bafo.test'
const PASSWORD = process.env.ISSUER_PASSWORD ?? 'Bafo-Demo-2026'
const OUT = new URL('../docs/screenshots/core/', import.meta.url).pathname
const VIEWPORTS = [{ width: 1280, height: 900 }, { width: 390, height: 844 }]
const LOCALES = ['ar', 'en']

async function api(path, init = {}, token = null) {
  const response = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Platform': 'web', ...(token ? { Authorization: `Bearer ${token}` } : {}), ...(init.headers ?? {}) },
  })
  if (!response.ok) throw new Error(`${init.method ?? 'GET'} ${path} → ${response.status}`)
  return (await response.json()).data
}

const session = await api('/auth/login', { method: 'POST', body: JSON.stringify({ email: EMAIL, password: PASSWORD, device_name: 'Web · wizard screenshots' }) })
const token = session.token
const drafts = await api('/competitions?role=issuer&status_group=draft&per_page=10', {}, token)
const draft = drafts.find(item => item.status === 'draft')
if (!draft) throw new Error('No draft competition for the issuer: load the demo data first (docs/build/DEMO.md).')
const config = await api('/app-config')
console.log(`release scope: ${config.features.release_scope}; draft: ${draft.id} «${draft.title}»`)

const browser = await chromium.launch()
const problems = []
const settle = async (page) => {
  await page.waitForLoadState('networkidle').catch(() => undefined)
  await page.waitForTimeout(800)
}

for (const viewport of VIEWPORTS) {
  const context = await browser.newContext({ viewport, timezoneId: 'Asia/Riyadh' })
  await context.addCookies([{ name: 'bafo_token', value: encodeURIComponent(token), url: WEB_BASE }])
  const page = await context.newPage()
  const dir = `${OUT}${viewport.width}`
  mkdirSync(dir, { recursive: true })
  for (const locale of LOCALES) {
    const shots = [
      ['dashboard', `/${locale}/dashboard`],
      ['wizard-new', `/${locale}/dashboard/competitions/new`],
      ['wizard-basics', `/${locale}/dashboard/competitions/${draft.id}/setup/basics`],
      ['wizard-rules', `/${locale}/dashboard/competitions/${draft.id}/setup/rules`],
      ['wizard-schedule', `/${locale}/dashboard/competitions/${draft.id}/setup/schedule`],
      ['wizard-participants', `/${locale}/dashboard/competitions/${draft.id}/setup/participants`],
      ['wizard-review', `/${locale}/dashboard/competitions/${draft.id}/setup/review`],
    ]
    for (const [name, path] of shots) {
      await page.goto(`${WEB_BASE}${path}`)
      await settle(page)
      if (name === 'wizard-rules') {
        // Open the advanced disclosure once so the screenshot shows what is inside it in this scope.
        const toggle = page.locator('main button[aria-expanded="false"][aria-controls]').first()
        if (await toggle.count()) {
          await toggle.click()
          await page.waitForTimeout(300)
        }
      }
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)
      if (overflow > 1) problems.push(`${locale} ${viewport.width}px ${name}: scrolls sideways by ${overflow}px`)
      await page.screenshot({ path: `${dir}/${locale}-${name}.jpg`, fullPage: true, type: 'jpeg', quality: 80 })
      console.log(`saved ${viewport.width}/${locale}-${name}.jpg`)
    }
    // Legacy step keys redirect to the step that holds their content (RELEASE_SCOPE §2.1).
    for (const [legacy, target] of [['type', 'basics'], ['documents', 'participants'], ['fees', 'participants']]) {
      await page.goto(`${WEB_BASE}/${locale}/dashboard/competitions/${draft.id}/setup/${legacy}`)
      await page.waitForURL(new RegExp(`/setup/${target}$`), { timeout: 15_000 }).catch(() => problems.push(`${locale}: /setup/${legacy} did not redirect to /setup/${target} (${page.url()})`))
    }
    // A hidden page renders the 404 state in place when its flag is off: same URL, no redirect (§5).
    if (!config.features.flags.team_management) {
      const notFound = JSON.parse(readFileSync(new URL(`../i18n/locales/${locale}.json`, import.meta.url), 'utf8')).errors.not_found
      await page.goto(`${WEB_BASE}/${locale}/dashboard/team`)
      await page.getByText(notFound).first().waitFor({ timeout: 15_000 }).catch(() => problems.push(`${locale}: /dashboard/team did not show the 404 state (${page.url()})`))
      if (!page.url().endsWith(`/${locale}/dashboard/team`)) problems.push(`${locale}: /dashboard/team redirected to ${page.url()}`)
      rmSync(`${dir}/${locale}-team-redirect.jpg`, { force: true })
      await page.screenshot({ path: `${dir}/${locale}-team-hidden.jpg`, type: 'jpeg', quality: 80 })
    }
  }
  await context.close()
}
await browser.close()
if (problems.length > 0) {
  console.error('Problems:\n' + problems.map(line => ` - ${line}`).join('\n'))
  process.exit(1)
}
console.log('done')
