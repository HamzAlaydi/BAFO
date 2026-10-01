import { expect, type Locator, type Page } from '@playwright/test'
import { labelRx, rx, tr, type Locale } from './i18n'
import { riyadhLocal, shotPath, type Actor } from './stack'

/**
 * UI flows shared by the live specs. Every step goes through the web app as a person would; the
 * selectors are the app's own messages (`tr`), so the same flow runs in Arabic and in English.
 */

type Direction = 'tender' | 'auction'
type Format = 'live' | 'sealed'

export interface CompetitionPlan {
  direction: Direction
  format: Format
  title: string
  description: string
  /** Category option label (its visible name in the locale of the issuer). */
  category: string
  /** Riyals, as typed (e.g. '1000'). */
  startPrice?: string
  reservePrice?: string
  /** Live only. */
  minStepAmount?: string
  mustBeat?: 'own' | 'best'
  rankVisibility?: 'full' | 'leading_flag' | 'none'
  showPrices?: boolean
  result?: 'none' | 'outcome_only' | 'outcome_and_amount'
  autoExtend?: { windowMinutes: number, byMinutes: number, max: number } | null
  minParticipants: number
  opensAt: Date
  closeAt: Date
  invite: string[]
  /** Invitees whose fees the issuer covers (`selected` mode); empty = no fees step changes. */
  sponsor: string[]
}

function td(locale: Locale, key: string, direction: Direction, params: Record<string, string | number> = {}): string {
  return tr(locale, `${key}.${direction}`, params)
}

/** A radio whose accessible name starts with the given title (choice cards append their description). */
function radioStartingWith(scope: Page | Locator, title: string): Locator {
  return scope.getByRole('radio', { name: new RegExp(`^${rx(title).source}`) })
}

async function setSwitch(page: Page, label: string, on: boolean): Promise<void> {
  const control = page.getByRole('switch', { name: label, exact: true })
  await expect(control).toBeVisible()
  if ((await control.getAttribute('aria-checked')) !== String(on)) await control.click()
  await expect(control).toHaveAttribute('aria-checked', String(on))
}

function textbox(page: Page | Locator, label: string): Locator {
  return page.getByRole('textbox', { name: labelRx(label) })
}

async function continueStep(page: Page, locale: Locale, nextPath: RegExp): Promise<void> {
  await page.getByRole('button', { name: tr(locale, 'common.actions.continue'), exact: true }).click()
  await expect(page).toHaveURL(nextPath, { timeout: 15_000 })
}

/**
 * Creates a competition through the whole setup wizard (W12 + W15), then publishes it: **Publish**,
 * or **Pay and publish** through the hosted fake checkout when covered fees need passes. Returns
 * the competition id.
 */
export async function createCompetition(actor: Actor, plan: CompetitionPlan, shotPrefix: string): Promise<string> {
  const { page, locale } = actor
  const d = plan.direction

  // ---- Step 1: type ----
  await page.goto(`/${locale}/dashboard/competitions/new`)
  await expect(page.getByRole('heading', { level: 1, name: tr(locale, 'competitions.setup.new_title') })).toBeVisible()
  const directionGroup = page.getByRole('group', { name: tr(locale, 'competitions.setup.type.direction_legend') })
  const formatGroup = page.getByRole('group', { name: tr(locale, 'competitions.setup.type.format_legend') })
  await radioStartingWith(directionGroup, tr(locale, `competitions.direction.${d}`)).check()
  await radioStartingWith(formatGroup, tr(locale, `competitions.format.${plan.format}`)).check()
  await expect(radioStartingWith(directionGroup, tr(locale, `competitions.direction.${d}`))).toBeChecked()
  await page.getByRole('button', { name: tr(locale, 'common.actions.continue'), exact: true }).click()

  // ---- Step 2: basics → POST /competitions ----
  await textbox(page, tr(locale, 'competitions.setup.basics.title_label')).fill(plan.title)
  await textbox(page, tr(locale, 'competitions.setup.basics.description_label')).fill(plan.description)
  await page.getByRole('combobox', { name: labelRx(tr(locale, 'competitions.setup.basics.category_label')) }).selectOption({ label: plan.category })
  const region = page.getByRole('combobox', { name: labelRx(tr(locale, 'competitions.setup.basics.region_label')) })
  await region.selectOption({ index: 1 })
  await page.getByRole('button', { name: tr(locale, 'competitions.setup.create_draft') }).click()
  await expect(page).toHaveURL(/\/setup\/rules$/, { timeout: 15_000 })
  const id = /competitions\/([^/]+)\/setup/.exec(page.url())?.[1]
  if (!id) throw new Error(`No competition id in ${page.url()}`)

  // ---- Step 3: rules ----
  if (plan.startPrice) await textbox(page, td(locale, 'rules.start_price.label', d)).fill(plan.startPrice)
  if (plan.reservePrice) await textbox(page, td(locale, 'rules.reserve_price.label', d)).fill(plan.reservePrice)
  if (plan.format === 'live') {
    if (plan.mustBeat) {
      const group = page.getByRole('group', { name: tr(locale, 'rules.must_beat.label') })
      const label = plan.mustBeat === 'own' ? td(locale, 'rules.must_beat.own', d) : tr(locale, 'rules.must_beat.best')
      await radioStartingWith(group, label).check()
    }
    if (plan.minStepAmount) {
      // Segmented control: visually hidden radios inside their labels.
      const amountMode = page.getByRole('group', { name: td(locale, 'rules.min_step.label', d) }).getByRole('radio', { name: tr(locale, 'rules.min_step.amount') })
      await amountMode.locator('xpath=ancestor::label[1]').click()
      await expect(amountMode).toBeChecked()
      await textbox(page, tr(locale, 'rules.min_step.amount_label')).fill(plan.minStepAmount)
    }
    if (plan.rankVisibility) {
      const group = page.getByRole('group', { name: tr(locale, 'rules.visibility.label') })
      await radioStartingWith(group, tr(locale, `rules.visibility.${plan.rankVisibility}`)).check()
    }
  }
  if (plan.result) {
    const group = page.getByRole('group', { name: tr(locale, 'rules.result.label') })
    await radioStartingWith(group, tr(locale, `rules.result.${plan.result}`)).check()
  }
  if (plan.format === 'live') {
    if (plan.showPrices !== undefined) await setSwitch(page, tr(locale, 'rules.show_prices.label'), plan.showPrices)
    await setSwitch(page, tr(locale, 'rules.final_window.label'), false)
    if (plan.autoExtend) {
      await setSwitch(page, tr(locale, 'rules.auto_extend.label'), true)
      await textbox(page, tr(locale, 'rules.auto_extend.window_label')).fill(String(plan.autoExtend.windowMinutes))
      await textbox(page, tr(locale, 'rules.auto_extend.by_label')).fill(String(plan.autoExtend.byMinutes))
      await textbox(page, tr(locale, 'rules.auto_extend.max_label')).fill(String(plan.autoExtend.max))
    }
    else if (plan.autoExtend === null) {
      await setSwitch(page, tr(locale, 'rules.auto_extend.label'), false)
    }
  }
  await setSwitch(page, tr(locale, 'rules.bafo.label'), false)
  await textbox(page, tr(locale, 'rules.min_participants.label')).fill(String(plan.minParticipants))
  await page.screenshot({ path: shotPath(`${shotPrefix}-01-wizard-rules`), fullPage: true })
  await continueStep(page, locale, /\/setup\/schedule$/)

  // ---- Step 4: schedule ----
  const opensGroup = page.getByRole('group', { name: tr(locale, 'competitions.setup.schedule.opens_label') })
  await radioStartingWith(opensGroup, tr(locale, 'competitions.setup.schedule.opens_at')).check()
  await textbox(page, tr(locale, 'competitions.setup.schedule.opens_at')).fill(riyadhLocal(plan.opensAt))
  await textbox(page, tr(locale, 'competitions.setup.schedule.close_label')).fill(riyadhLocal(plan.closeAt))
  await continueStep(page, locale, /\/setup\/documents$/)

  // ---- Step 5: documents (none) ----
  await continueStep(page, locale, /\/setup\/participants$/)

  // ---- Step 6: participants (by e-mail) ----
  await page.getByRole('tab', { name: tr(locale, 'invitations.issuer.picker.tabs.email') }).click()
  const emails = textbox(page, tr(locale, 'invitations.issuer.picker.emails_label'))
  await emails.fill(`${plan.invite.join(' ')} `)
  await page.getByRole('button', { name: tr(locale, 'invitations.issuer.picker.add_emails', {}, plan.invite.length) }).click()
  await page.getByRole('button', { name: tr(locale, 'invitations.issuer.add_n', {}, plan.invite.length) }).click()
  for (const email of plan.invite) await expect(page.getByRole('main').getByText(email).first()).toBeVisible()
  await page.screenshot({ path: shotPath(`${shotPrefix}-02-wizard-participants`), fullPage: true })
  await continueStep(page, locale, /\/setup\/(fees|review)$/)

  // ---- Step 7: participation fees ----
  if (page.url().endsWith('/fees')) {
    const legend = page.getByRole('group', { name: tr(locale, 'sponsorship.fees.mode_legend') })
    if (plan.sponsor.length > 0) {
      // Server-controlled cards: the choice shows once `PUT …/sponsorship` answers.
      const selected = radioStartingWith(legend, tr(locale, 'sponsorship.mode.selected.title'))
      await selected.click()
      await expect(selected).toBeChecked({ timeout: 15_000 })
      for (const email of plan.sponsor) {
        const row = page.getByRole('row').filter({ hasText: email })
        const toggle = row.getByRole('switch')
        await expect(toggle).toBeVisible()
        if ((await toggle.getAttribute('aria-checked')) !== 'true') await toggle.click()
        await expect(toggle).toHaveAttribute('aria-checked', 'true')
      }
    }
    await page.screenshot({ path: shotPath(`${shotPrefix}-03-wizard-fees`), fullPage: true })
    await continueStep(page, locale, /\/setup\/review$/)
  }

  // ---- Step 8: review and publish ----
  const payButton = page.getByRole('button', { name: tr(locale, 'competitions.setup.review.pay_and_publish') })
  const publishButton = page.getByRole('button', { name: tr(locale, 'competitions.setup.review.publish'), exact: true })
  await expect(payButton.or(publishButton)).toBeVisible({ timeout: 15_000 })
  await page.screenshot({ path: shotPath(`${shotPrefix}-04-wizard-review`), fullPage: true })
  if (plan.sponsor.length > 0) {
    await expect(payButton).toBeVisible()
    await payButton.click()
    await page.getByRole('button', { name: new RegExp(`^${rx(tr(locale, 'sponsorship.fees.checkout.pay', { amount: '' }).trim()).source}`) }).click()
    await approveFakePayment(page)
    await expect(page.getByRole('heading', { name: tr(locale, 'billing.payment_return.sponsorship.published_title') })).toBeVisible({ timeout: 30_000 })
    await page.screenshot({ path: shotPath(`${shotPrefix}-05-paid-and-published`), fullPage: true })
  }
  else {
    await publishButton.click()
    await expect(page).toHaveURL(new RegExp(`/dashboard/competitions/${id}$`), { timeout: 15_000 })
  }
  return id
}

/** The API's hosted test checkout (`/pay/fake/{id}`): approve, then back to the web return page. */
export async function approveFakePayment(page: Page): Promise<void> {
  await expect(page).toHaveURL(/\/pay\/fake\//, { timeout: 15_000 })
  await page.getByRole('button', { name: /اعتماد الدفع|Approve payment/ }).click()
  await expect(page).toHaveURL(/\/dashboard\/billing\/checkout\/return/, { timeout: 30_000 })
}

/** A participant finds the invitation under "Participating" and joins it. Returns the access text seen. */
export async function joinFromParticipating(actor: Actor, title: string, expectAccess: string): Promise<void> {
  const { page, locale } = actor
  await page.goto(`/${locale}/dashboard/participating`)
  await page.getByRole('link', { name: title }).first().click()
  await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible()
  await expect(page.getByText(expectAccess, { exact: true })).toBeVisible()
  await page.getByRole('button', { name: tr(locale, 'invitations.join.open') }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('checkbox', { name: labelRx(tr(locale, 'invitations.join.accept')) }).check()
  await dialog.getByRole('button', { name: tr(locale, 'invitations.join.confirm') }).click()
  await expect(page.getByText(tr(locale, 'invitations.join.joined')).first()).toBeVisible({ timeout: 15_000 })
}

/** The live connection indicator of the detail header reads "Live" (Reverb subscribed and resynced). */
export async function expectSocketLive(actor: Actor): Promise<void> {
  const { page, locale } = actor
  const indicator = page.getByRole('status').and(page.getByText(tr(locale, 'common.connection.connected'), { exact: true }))
  await expect(indicator.first()).toBeVisible({ timeout: 20_000 })
  const reverb = actor.sockets.filter(socket => /\/app\//.test(socket.url()) && !socket.isClosed())
  expect(reverb.length, 'an open Reverb WebSocket').toBeGreaterThan(0)
}

/** Types an amount in the offer composer, confirms it, and waits for the server's acceptance. */
export async function submitOffer(actor: Actor, amount: string): Promise<void> {
  const { page, locale } = actor
  const input = page.getByTestId('offer-amount')
  await expect(input).toBeEnabled({ timeout: 30_000 })
  await input.fill(amount)
  const submit = page.getByTestId('submit-offer')
  await expect(submit).toBeEnabled()
  await submit.click()
  await expect(page.getByTestId('offer-confirm')).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: tr(locale, 'offers.confirm.submit'), exact: true }).click()
  await expect(page.getByRole('dialog')).toBeHidden({ timeout: 15_000 })
}

// ---------- Realtime and visibility assertions ----------

const marks = new WeakMap<Actor, number>()

export function markRequests(actor: Actor): void {
  marks.set(actor, actor.requests.length)
}

/** No `GET …/live` (the polling fallback) since the mark: the change came over the socket. */
export function expectNoPolling(actor: Actor): void {
  const since = actor.requests.slice(marks.get(actor) ?? 0)
  expect(since.filter(line => /^GET .*\/competitions\/[^/]+\/live$/.test(line)), 'polling requests').toEqual([])
}

export interface Forbidden {
  /** Organisation names (page text and every string in socket payloads). */
  names: string[]
  /** Formatted amounts that must not appear in the page. */
  texts: string[]
  /** Minor amounts that must not appear as any amount value in socket payloads. */
  minor: number[]
}

/** Neither the page nor any socket payload received by this viewer contains the given values. */
export async function expectNoLeak(actor: Actor, forbidden: Forbidden): Promise<void> {
  const text = await actor.page.locator('body').innerText()
  for (const value of [...forbidden.names, ...forbidden.texts]) {
    expect(text, `page of ${actor.email} shows "${value}"`).not.toMatch(rx(value))
  }
  for (const payload of socketPayloads(actor)) {
    const serialised = JSON.stringify(payload)
    for (const name of forbidden.names) expect(serialised, `socket payload to ${actor.email} names "${name}"`).not.toContain(name)
    for (const amount of forbidden.minor) expect(amountValues(payload), `socket payload to ${actor.email}`).not.toContain(amount)
  }
}

/** The parsed `data` of every Pusher event frame (the data is itself JSON text). */
function socketPayloads(actor: Actor): unknown[] {
  return actor.frames.flatMap((frame) => {
    try {
      const event = JSON.parse(frame) as { data?: unknown }
      return [typeof event.data === 'string' ? JSON.parse(event.data) : event.data ?? null]
    }
    catch {
      return []
    }
  })
}

/** Every numeric value under a key that names an amount (`*_minor`, `amount*`). */
function amountValues(node: unknown, key = ''): number[] {
  if (typeof node === 'number') return /minor|amount/.test(key) ? [node] : []
  if (Array.isArray(node)) return node.flatMap(item => amountValues(item, key))
  if (node && typeof node === 'object') return Object.entries(node).flatMap(([name, value]) => amountValues(value, name))
  return []
}
