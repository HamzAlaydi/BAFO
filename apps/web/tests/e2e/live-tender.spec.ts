import { expect, test, type APIRequestContext } from '@playwright/test'
import { labelRx, tr, trPattern, type Locale } from './support/i18n'
import { createCompetition, expectNoLeak, expectNoPolling, expectSocketLive, joinFromParticipating, markRequests, submitOffer } from './support/flows'
import { ACCOUNTS, apiGet, apiToken, attachDiagnostics, LIVE_ENABLED, ORGS, orgNames, shotPath, signIn, type Actor } from './support/stack'

/**
 * Tenders (lowest wins), end to end on a real stack with Reverb (opt-in: E2E_LIVE=1). The two tests
 * run in parallel; each takes about 15 minutes of wall time (opens 3 minutes after publishing and
 * runs the 10-minute minimum).
 *
 * - **Live tender, the English pass:** the issuer works in English; Supplier B joins on a covered
 *   pass (Arabic), Supplier C on its trial (English). Full ranking without prices: each participant
 *   sees its rank only. Updates arrive over the socket; a last-minute bid extends the close; the
 *   issuer awards the leader below target with the reserve confirmation and a justification.
 * - **Sealed tender:** nobody sees an amount until the close: the issuer console shows who submitted
 *   («مغلق»), each participant only its own sealed receipt, in the page and in every socket frame.
 *   After the close the issuer sees every amount and awards the lowest offer.
 */
test.use({ actionTimeout: 20_000, navigationTimeout: 30_000 })
// eslint-disable-next-line no-empty-pattern -- Playwright needs the fixtures argument destructured
test.afterEach(async ({}, info) => attachDiagnostics(info))

function opening(): { opensAt: Date, closeAt: Date } {
  const opensAt = new Date(Math.ceil((Date.now() + 3 * 60_000) / 60_000) * 60_000)
  return { opensAt, closeAt: new Date(opensAt.getTime() + 11 * 60_000) }
}

async function effectiveCloseAt(request: APIRequestContext, id: string): Promise<number> {
  const token = await apiToken(request, ACCOUNTS.issuerOwner)
  const live = await apiGet<{ effective_close_at: string }>(request, token, `/competitions/${id}/live`)
  return Date.parse(live.effective_close_at)
}

async function waitForOpen(actors: Actor[], opensAt: Date): Promise<void> {
  const timeout = Math.max(opensAt.getTime() - Date.now() + 20_000, 30_000)
  for (const actor of actors) {
    await expect(actor.page.getByTestId('offer-composer'), `offers open for ${actor.email}; socket: ${liveFrames(actor)}; requests: ${actor.requests.slice(-8).join(', ')}`).toBeVisible({ timeout })
  }
}

/** `event v status` of the live frames a page received (diagnostics). */
function liveFrames(actor: Actor): string {
  return actor.frames.map((frame) => {
    try {
      const event = JSON.parse(frame) as { event: string, data?: string }
      const data = typeof event.data === 'string' ? JSON.parse(event.data) as { v?: number, status?: string } : {}
      return `${event.event} ${data.v ?? ''} ${data.status ?? ''}`.trim()
    }
    catch {
      return '?'
    }
  }).join(' | ')
}

async function waitForClose(request: APIRequestContext, id: string, actors: Actor[]): Promise<void> {
  const timeout = Math.max(30_000, (await effectiveCloseAt(request, id)) - Date.now() + 45_000)
  for (const actor of actors) {
    await expect(actor.page.getByTestId('result-panel')).toContainText(tr(actor.locale, 'award.result.evaluation'), { timeout })
  }
}

test.describe('tenders over Reverb', () => {
  test.skip(!LIVE_ENABLED, 'E2E_LIVE=1 runs the live end-to-end specs against a running stack')
  test.describe.configure({ mode: 'parallel' })

  test('live tender (English issuer): sponsored join, ranks without prices, extension, award below target', async ({ browser, request }) => {
    test.skip(test.info().project.name !== 'chromium', 'desktop Chromium only')
    test.setTimeout(30 * 60_000)
    const stamp = Date.now().toString(36)
    const title = `Office supplies tender ${stamp}`
    const issuer = await signIn(browser, ACCOUNTS.issuerOwner, 'en')
    const supplierB = await signIn(browser, ACCOUNTS.supplierB, 'ar')
    const supplierC = await signIn(browser, ACCOUNTS.supplierC, 'en')
    const { opensAt, closeAt } = opening()

    const id = await test.step('issuer creates and pays for the covered pass', async () => createCompetition(issuer, {
      direction: 'tender',
      format: 'live',
      title,
      description: 'End-to-end test tender: office supplies for 2027.',
      category: 'Office supplies',
      startPrice: '50000',
      reservePrice: '45000',
      minStepAmount: '100',
      mustBeat: 'own',
      rankVisibility: 'full',
      showPrices: false,
      result: 'outcome_only',
      autoExtend: { windowMinutes: 2, byMinutes: 2, max: 2 },
      minParticipants: 2,
      opensAt,
      closeAt,
      invite: [ACCOUNTS.supplierB, ACCOUNTS.supplierC],
      sponsor: [ACCOUNTS.supplierB],
    }, 'tender-en'))

    await test.step('participants join (B on the covered pass, C on its trial)', async () => {
      await joinFromParticipating(supplierB, title, tr('ar', 'invitations.access_card.sponsored', { sponsor: ORGS.issuer.name }))
      await joinFromParticipating(supplierC, title, tr('en', 'invitations.access_card.own_plan'))
    })

    for (const actor of [issuer, supplierB, supplierC]) {
      await actor.page.goto(`/${actor.locale}/dashboard/competitions/${id}/live`)
      await expectSocketLive(actor)
    }
    await waitForOpen([supplierB, supplierC], opensAt)

    await test.step('B bids; the issuer console ranks it live', async () => {
      markRequests(issuer)
      await submitOffer(supplierB, '48000')
      await expect(supplierB.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'leading')
      await expect(supplierB.page.getByTestId('standing-banner')).toContainText(tr('ar', 'live.status.rank', { rank: 1, count: 1 }))
      const leader = issuer.page.getByRole('heading', { name: tr('en', 'glossary.leading_offer') }).locator('..')
      await expect(leader).toContainText(ORGS.supplierB.name, { timeout: 10_000 })
      await expect(leader).toContainText('48,000')
      await expect(issuer.page.getByText(tr('en', 'live.console.reserve.not_met.tender'))).toBeVisible()
      expectNoPolling(issuer)
    })

    await test.step('C bids lower; B drops to rank 2 of 2 live', async () => {
      markRequests(supplierB)
      markRequests(issuer)
      await submitOffer(supplierC, '47500')
      // The issuer console follows every offer over the socket: leader, metrics and the offer feed.
      const leader = issuer.page.getByRole('heading', { name: tr('en', 'glossary.leading_offer') }).locator('..')
      await expect(leader).toContainText(ORGS.supplierC.name, { timeout: 10_000 })
      await expect(leader).toContainText('47,500')
      const feed = issuer.page.getByRole('heading', { name: tr('en', 'live.console.feed.title') }).locator('xpath=ancestor::header/..')
      await expect(feed.getByText(tr('en', 'offers.participant_alias', { number: '' }).trim(), { exact: false })).toHaveCount(2, { timeout: 10_000 })
      expectNoPolling(issuer)
      await expect(supplierB.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'outbid', { timeout: 10_000 })
      await expect(supplierB.page.getByTestId('standing-banner')).toContainText(tr('ar', 'live.status.rank', { rank: 2, count: 2 }))
      await expect(supplierC.page.getByTestId('standing-banner')).toContainText(tr('en', 'live.status.rank', { rank: 1, count: 2 }))
      expectNoPolling(supplierB)
      await expectSocketLive(supplierB)
      await supplierB.page.screenshot({ path: shotPath('tender-en-06-participant-rank-2'), fullPage: true })
      await issuer.page.screenshot({ path: shotPath('tender-en-07-issuer-console'), fullPage: true })
      await expectNoLeak(supplierB, { names: orgNames(ORGS.supplierC), texts: ['47,500'], minor: [4_750_000] })
      await expectNoLeak(supplierC, { names: orgNames(ORGS.supplierB), texts: ['48,000'], minor: [4_800_000] })
    })

    await test.step('a last-minute bid by B extends the close', async () => {
      const inWindowAt = (await effectiveCloseAt(request, id)) - 75_000
      await supplierB.page.waitForTimeout(Math.max(0, inWindowAt - Date.now()))
      await submitOffer(supplierB, '47000')
      await expect(supplierB.page.getByTestId('extension-notice')).toBeVisible({ timeout: 10_000 })
      await expect(supplierC.page.getByTestId('extension-notice')).toContainText(trPattern('en', 'live.extended'), { timeout: 10_000 })
      await expect(issuer.page.getByText(tr('en', 'live.console.extended.auto'))).toBeVisible({ timeout: 10_000 })
      await supplierC.page.screenshot({ path: shotPath('tender-en-08-extension-banner'), fullPage: true })
    })

    await test.step('close, then award the leader below target with the confirmation', async () => {
      await waitForClose(request, id, [supplierB, supplierC])
      const page = issuer.page
      await page.goto(`/en/dashboard/competitions/${id}/award`)
      await page.getByRole('row').filter({ hasText: ORGS.supplierB.name }).getByRole('radio').check()
      await page.getByRole('button', { name: tr('en', 'award.workspace.award') }).click()
      // Below target: the server asks for the justification and the confirmation.
      await expect(page.getByText(tr('en', 'award.workspace.why_reserve'))).toBeVisible({ timeout: 10_000 })
      await page.getByRole('combobox', { name: labelRx(tr('en', 'award.workspace.reason')) }).selectOption({ index: 1 })
      await page.getByRole('textbox', { name: labelRx(tr('en', 'award.workspace.justification_text')) }).fill('Best price received; the target was ambitious.')
      await page.getByRole('checkbox', { name: labelRx(tr('en', 'award.workspace.confirm_reserve.tender')) }).check()
      await page.getByRole('button', { name: tr('en', 'award.workspace.award') }).click()
      await page.getByRole('dialog').getByRole('button', { name: tr('en', 'award.confirm.confirm') }).click()
      await expect(page.getByText(tr('en', 'award.workspace.done')).first()).toBeVisible({ timeout: 15_000 })
      await page.screenshot({ path: shotPath('tender-en-09-awarded'), fullPage: true })
      await expect(supplierB.page.getByTestId('result-panel')).toContainText(tr('ar', 'award.result.won_body'), { timeout: 15_000 })
      await expect(supplierC.page.getByTestId('result-panel')).toContainText(tr('en', 'award.result.not_selected_body'), { timeout: 15_000 })
      await supplierC.page.screenshot({ path: shotPath('tender-en-10-participant-result'), fullPage: true })
    })

    for (const actor of [issuer, supplierB, supplierC]) await actor.context.close()
  })

  test('sealed tender: no amount visible to anyone until the close', async ({ browser, request }) => {
    test.skip(test.info().project.name !== 'chromium', 'desktop Chromium only')
    test.setTimeout(30 * 60_000)
    const L: Locale = 'ar'
    const stamp = Date.now().toString(36)
    const title = `توريد أجهزة شبكات بظرف مغلق ${stamp}`
    const issuer = await signIn(browser, ACCOUNTS.issuerOwner, L)
    const supplierA = await signIn(browser, ACCOUNTS.supplierA, L)
    const supplierC = await signIn(browser, ACCOUNTS.supplierC, L)
    const { opensAt, closeAt } = opening()

    const id = await test.step('issuer creates and publishes a sealed tender', async () => createCompetition(issuer, {
      direction: 'tender',
      format: 'sealed',
      title,
      description: 'منافسة اختبار شامل بعروض مغلقة.',
      category: 'أجهزة ومعدات تقنية',
      startPrice: '32000',
      result: 'outcome_only',
      minParticipants: 2,
      opensAt,
      closeAt,
      invite: [ACCOUNTS.supplierA, ACCOUNTS.supplierC],
      sponsor: [],
    }, 'sealed-ar'))

    await joinFromParticipating(supplierA, title, tr(L, 'invitations.access_card.own_plan'))
    await joinFromParticipating(supplierC, title, tr(L, 'invitations.access_card.own_plan'))
    for (const actor of [issuer, supplierA, supplierC]) {
      await actor.page.goto(`/${L}/dashboard/competitions/${id}/live`)
      await expectSocketLive(actor)
    }
    await waitForOpen([supplierA, supplierC], opensAt)

    await test.step('sealed offers: the issuer sees who submitted, never an amount', async () => {
      await submitOffer(supplierA, '30000')
      await submitOffer(supplierC, '29000')
      // One offer per participant every 2 s (bidding.offer_min_interval_seconds).
      await supplierA.page.waitForTimeout(2_500)
      await submitOffer(supplierA, '28500')
      await expect(supplierA.page.getByTestId('sealed-panel')).toContainText('28,500')
      await expect(supplierC.page.getByTestId('sealed-panel')).toContainText('29,000')
      await expect(issuer.page.getByText(tr(L, 'live.console.sealed_locked'))).toBeVisible()
      await expect(issuer.page.getByRole('table', { name: tr(L, 'live.console.ranking.caption') }).getByRole('row')).toHaveCount(3, { timeout: 10_000 })
      // The console follows every sealed offer (who and when, never how much): three offers so far.
      const offersMetric = issuer.page.locator('dl > div').filter({ has: issuer.page.getByText(tr(L, 'live.console.metrics.offers'), { exact: true }) }).locator('dd')
      await expect(offersMetric).toHaveText('3', { timeout: 10_000 })
      await issuer.page.screenshot({ path: shotPath('sealed-ar-06-issuer-locked'), fullPage: true })
      await supplierA.page.screenshot({ path: shotPath('sealed-ar-07-participant-sealed'), fullPage: true })
      const amounts = { texts: ['30,000', '29,000', '28,500'], minor: [3_000_000, 2_900_000, 2_850_000] }
      await expectNoLeak(issuer, { names: [], ...amounts })
      await expectNoLeak(supplierA, { names: orgNames(ORGS.supplierC), texts: ['29,000'], minor: [2_900_000] })
      await expectNoLeak(supplierC, { names: orgNames(ORGS.supplierA), texts: ['30,000', '28,500'], minor: [3_000_000, 2_850_000] })
    })

    await test.step('after the close the issuer sees every amount and awards the lowest', async () => {
      await waitForClose(request, id, [supplierA, supplierC])
      const ranking = issuer.page.getByRole('table', { name: tr(L, 'live.console.ranking.caption') })
      await expect(ranking).toContainText('28,500', { timeout: 15_000 })
      await expect(ranking).toContainText('29,000')
      await issuer.page.screenshot({ path: shotPath('sealed-ar-08-issuer-unsealed'), fullPage: true })
      const page = issuer.page
      await page.goto(`/${L}/dashboard/competitions/${id}/award`)
      await page.getByRole('row').filter({ hasText: ORGS.supplierA.name }).getByRole('radio').check()
      await page.getByRole('button', { name: tr(L, 'award.workspace.award') }).click()
      await page.getByRole('dialog').getByRole('button', { name: tr(L, 'award.confirm.confirm') }).click()
      await expect(page.getByText(tr(L, 'award.workspace.done')).first()).toBeVisible({ timeout: 15_000 })
      await expect(supplierA.page.getByTestId('result-panel')).toContainText(tr(L, 'award.result.won_body'), { timeout: 15_000 })
      await expect(supplierC.page.getByTestId('result-panel')).toContainText(tr(L, 'award.result.not_selected_body'), { timeout: 15_000 })
      await expectNoLeak(supplierC, { names: orgNames(ORGS.supplierA), texts: ['28,500'], minor: [2_850_000] })
      await supplierC.page.screenshot({ path: shotPath('sealed-ar-09-participant-result'), fullPage: true })
    })

    for (const actor of [issuer, supplierA, supplierC]) await actor.context.close()
  })
})
