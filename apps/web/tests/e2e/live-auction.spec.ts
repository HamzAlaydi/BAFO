import { expect, test } from '@playwright/test'
import { labelRx, tr, trPattern, type Locale } from './support/i18n'
import { createCompetition, expectNoLeak, expectNoPolling, expectSocketLive, joinFromParticipating, markRequests, submitOffer } from './support/flows'
import { ACCOUNTS, apiGet, apiToken, attachDiagnostics, LIVE_ENABLED, ORGS, orgNames, shotPath, signIn, socketEvents } from './support/stack'

/**
 * Live auction, end to end on a real stack with Reverb (opt-in: E2E_LIVE=1, see tests/e2e/README.md).
 *
 * Issuer Co creates an auction through the wizard (auction, own minimum step, auto-extend,
 * «متصدر أو لا» standing, prices hidden), invites Supplier B (fees covered, paid on the hosted test
 * checkout) and Buyer D (own plan), and publishes. Both join; the three live views open on the same
 * Reverb server. Every live change below is asserted **without a reload**, while the connection
 * indicator reads «مباشر» and the page makes no polling request: the updates arrive over the socket.
 *
 * Supplier B never sees Buyer D's name or prices, in the page or in any socket frame (show_prices off).
 * A bid in the last minutes extends the close (banner on all three views); after the close the issuer
 * awards Buyer D, not the leader, with a justification, and both rooms show their result.
 *
 * Wall time: about 17 minutes (opens 3 minutes after publishing, runs the 10-minute minimum plus one
 * 2-minute extension).
 */
const LOCALE: Locale = (process.env.E2E_LOCALE as Locale | undefined) ?? 'ar'

test.use({ actionTimeout: 20_000, navigationTimeout: 30_000 })
// eslint-disable-next-line no-empty-pattern -- Playwright needs the fixtures argument destructured
test.afterEach(async ({}, info) => attachDiagnostics(info))

test.describe('live auction over Reverb', () => {
  test.skip(!LIVE_ENABLED, 'E2E_LIVE=1 runs the live end-to-end specs against a running stack')

  test('auction: wizard, sponsored join, live bidding, extension, award', async ({ browser, request }) => {
    test.skip(test.info().project.name !== 'chromium', 'desktop Chromium only')
    test.setTimeout(30 * 60_000)
    const L = LOCALE
    const stamp = Date.now().toString(36)
    const title = L === 'ar' ? `مزاد معدات فائضة ${stamp}` : `Surplus equipment auction ${stamp}`

    const issuer = await signIn(browser, ACCOUNTS.issuerOwner, L)
    const supplierB = await signIn(browser, ACCOUNTS.supplierB, L)
    const buyerD = await signIn(browser, ACCOUNTS.buyerD, L)

    // Opens a few minutes after publishing (joining closes when offers open in a short competition).
    const opensAt = new Date(Math.ceil((Date.now() + 3 * 60_000) / 60_000) * 60_000)
    const closeAt = new Date(opensAt.getTime() + 10 * 60_000 + 60_000)

    const id = await test.step('issuer creates, pays for the covered pass and publishes', async () => createCompetition(issuer, {
      direction: 'auction',
      format: 'live',
      title,
      description: L === 'ar' ? 'مزايدة اختبار شامل: معدات مستعملة بحالة جيدة.' : 'End-to-end test auction: used equipment in good condition.',
      category: L === 'ar' ? 'فائض ومخلفات' : 'Surplus and scrap',
      startPrice: '1000',
      minStepAmount: '10',
      mustBeat: 'own',
      rankVisibility: 'leading_flag',
      showPrices: false,
      result: 'outcome_only',
      autoExtend: { windowMinutes: 2, byMinutes: 2, max: 3 },
      minParticipants: 2,
      opensAt,
      closeAt,
      invite: [ACCOUNTS.supplierB, ACCOUNTS.buyerD],
      sponsor: [ACCOUNTS.supplierB],
    }, `auction-${L}`))

    await test.step('Supplier B joins on the covered pass, Buyer D on its plan', async () => {
      await joinFromParticipating(supplierB, title, tr(L, 'invitations.access_card.sponsored', { sponsor: ORGS.issuer.name }))
      await supplierB.page.screenshot({ path: shotPath(`auction-${L}-06-joined-sponsored`), fullPage: true })
      await joinFromParticipating(buyerD, title, tr(L, 'invitations.access_card.own_plan'))
      const token = await apiToken(request, ACCOUNTS.supplierB)
      const view = await apiGet<{ viewer_role: string, access: { state: string, coverage: string } }>(request, token, `/competitions/${id}`)
      expect(view.viewer_role).toBe('participant')
      expect(view.access).toMatchObject({ state: 'full', coverage: 'sponsored' })
    })

    const livePath = `/${L}/dashboard/competitions/${id}/live`
    await test.step('all three live views connect to Reverb', async () => {
      for (const actor of [issuer, supplierB, buyerD]) {
        await actor.page.goto(livePath)
        await expectSocketLive(actor)
      }
    })

    await test.step('offers open on the server clock, without a reload', async () => {
      const wait = opensAt.getTime() - Date.now() + 15_000
      for (const actor of [supplierB, buyerD]) {
        await expect(actor.page.getByTestId('offer-composer')).toBeVisible({ timeout: Math.max(wait, 30_000) })
      }
    })

    // ---- First bid: the issuer console updates over the socket ----
    await test.step('Supplier B bids; the issuer console updates live', async () => {
      markRequests(issuer)
      const framesBefore = issuer.frames.length
      await submitOffer(supplierB, '1000')
      await expect(supplierB.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'leading')
      const leader = issuer.page.getByRole('heading', { name: tr(L, 'glossary.leading_offer') }).locator('..')
      await expect(leader).toContainText(ORGS.supplierB.name, { timeout: 10_000 })
      await expect(leader).toContainText(/1,000/)
      await expect.poll(() => socketEvents({ ...issuer, frames: issuer.frames.slice(framesBefore) }), {
        message: `socket events on the issuer page (frames ${issuer.frames.length}, sockets ${issuer.sockets.map(socket => `${socket.url()} closed=${socket.isClosed()}`).join(', ')}, requests ${issuer.requests.slice(-12).join(', ')})`,
        timeout: 5_000,
      }).toEqual(expect.arrayContaining(['live.updated', 'offer.accepted']))
      expectNoPolling(issuer)
      await expectSocketLive(issuer)
      await issuer.page.screenshot({ path: shotPath(`auction-${L}-07-issuer-console-first-bid`), fullPage: true })
    })

    // ---- Outbid: Supplier B's room flips live ----
    await test.step('Buyer D outbids; Supplier B sees «not leading» live', async () => {
      markRequests(supplierB)
      await submitOffer(buyerD, '1050')
      await expect(buyerD.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'leading')
      await expect(supplierB.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'outbid', { timeout: 10_000 })
      await expect(supplierB.page.getByTestId('standing-banner')).toContainText(tr(L, 'live.status.not_leading.auction'))
      expectNoPolling(supplierB)
      await expectSocketLive(supplierB)
      const leader = issuer.page.getByRole('heading', { name: tr(L, 'glossary.leading_offer') }).locator('..')
      await expect(leader).toContainText(ORGS.buyerD.name)
      await supplierB.page.screenshot({ path: shotPath(`auction-${L}-08-participant-outbid`), fullPage: true })
    })

    await test.step('no participant sees another participant\'s name or price', async () => {
      // Supplier B's 1,000 equals the public opening price, so only names are checked for Buyer D here.
      await expectNoLeak(supplierB, { names: orgNames(ORGS.buyerD), texts: ['1,050'], minor: [105_000] })
      await expectNoLeak(buyerD, { names: orgNames(ORGS.supplierB), texts: [], minor: [] })
    })

    // ---- Last-minute bid: auto-extension ----
    await test.step('a bid in the last two minutes extends the close for everyone', async () => {
      const token = await apiToken(request, ACCOUNTS.issuerOwner)
      const before = await apiGet<{ effective_close_at: string, extension_count: number }>(request, token, `/competitions/${id}/live`)
      const inWindowAt = Date.parse(before.effective_close_at) - 75_000
      await supplierB.page.waitForTimeout(Math.max(0, inWindowAt - Date.now()))
      await submitOffer(supplierB, '1100')
      // The soft close does not stack: the new close is max(close, now + 2 minutes) (ARCHITECTURE §7.7).
      const after = await apiGet<{ effective_close_at: string, extension_count: number }>(request, token, `/competitions/${id}/live`)
      expect(after.extension_count).toBe(before.extension_count + 1)
      expect(Date.parse(after.effective_close_at)).toBeGreaterThan(Date.parse(before.effective_close_at))
      const extended = trPattern(L, 'live.extended')
      await expect(supplierB.page.getByTestId('extension-notice')).toContainText(extended, { timeout: 10_000 })
      await expect(buyerD.page.getByTestId('extension-notice')).toContainText(extended, { timeout: 10_000 })
      await expect(buyerD.page.getByTestId('standing-banner')).toHaveAttribute('data-tone', 'outbid')
      await expect(issuer.page.getByText(tr(L, 'live.console.extended.auto'))).toBeVisible({ timeout: 10_000 })
      await supplierB.page.screenshot({ path: shotPath(`auction-${L}-09-extension-banner`), fullPage: true })
      await issuer.page.screenshot({ path: shotPath(`auction-${L}-10-issuer-extended`), fullPage: true })
    })

    // ---- Close ----
    await test.step('the competition closes on the server clock', async () => {
      const token = await apiToken(request, ACCOUNTS.issuerOwner)
      const live = await apiGet<{ effective_close_at: string }>(request, token, `/competitions/${id}/live`)
      const timeout = Math.max(30_000, Date.parse(live.effective_close_at) - Date.now() + 45_000)
      for (const actor of [supplierB, buyerD]) {
        await expect(actor.page.getByTestId('result-panel')).toContainText(tr(L, 'award.result.evaluation'), { timeout })
      }
      await supplierB.page.screenshot({ path: shotPath(`auction-${L}-11-closed`), fullPage: true })
    })

    // ---- Award the non-leading bidder, with a justification ----
    await test.step('issuer awards Buyer D with a justification', async () => {
      const page = issuer.page
      await page.goto(`/${L}/dashboard/competitions/${id}/award`)
      const row = page.getByRole('row').filter({ hasText: ORGS.buyerD.name })
      await row.getByRole('radio').check()
      await expect(page.getByText(tr(L, 'award.workspace.why_not_leading'))).toBeVisible()
      await page.getByRole('combobox', { name: labelRx(tr(L, 'award.workspace.reason')) }).selectOption({ index: 1 })
      await page.getByRole('textbox', { name: labelRx(tr(L, 'award.workspace.justification_text')) }).fill(L === 'ar' ? 'أفضل شروط تسليم ودفع.' : 'Better delivery and payment terms.')
      await page.getByRole('button', { name: tr(L, 'award.workspace.award') }).click()
      await page.getByRole('dialog').getByRole('button', { name: tr(L, 'award.confirm.confirm') }).click()
      await expect(page.getByText(tr(L, 'award.workspace.done')).first()).toBeVisible({ timeout: 15_000 })
      await page.screenshot({ path: shotPath(`auction-${L}-12-awarded`), fullPage: true })
    })

    await test.step('both rooms show their result live', async () => {
      await expect(buyerD.page.getByTestId('result-panel')).toContainText(tr(L, 'award.result.won_body'), { timeout: 15_000 })
      await expect(supplierB.page.getByTestId('result-panel')).toContainText(tr(L, 'award.result.not_selected_body'), { timeout: 15_000 })
      await buyerD.page.screenshot({ path: shotPath(`auction-${L}-13-winner-result`), fullPage: true })
      await supplierB.page.screenshot({ path: shotPath(`auction-${L}-14-participant-result`), fullPage: true })
      await expectNoLeak(supplierB, { names: orgNames(ORGS.buyerD), texts: ['1,050'], minor: [105_000] })
      await expectNoLeak(buyerD, { names: orgNames(ORGS.supplierB), texts: ['1,100'], minor: [110_000] })
    })

    for (const actor of [issuer, supplierB, buyerD]) await actor.context.close()
  })
})
