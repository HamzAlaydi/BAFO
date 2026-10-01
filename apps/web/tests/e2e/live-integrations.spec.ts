import { expect, test, type Locator } from '@playwright/test'
import { labelRx, tr, type Locale } from './support/i18n'
import { ACCOUNTS, API_ORIGIN, LIVE_ENABLED, shotPath, signIn } from './support/stack'

/**
 * Integrations on a real stack (opt-in: E2E_LIVE=1): Issuer Co (API access enabled) creates an API
 * client and a webhook endpoint from the dashboard. Each secret is shown exactly once, in a dialog
 * that cannot be dismissed before the owner confirms storing it; it is never shown again. The client
 * secret is proven real by a client-credentials token from the public API.
 */
const LOCALE: Locale = (process.env.E2E_LOCALE as Locale | undefined) ?? 'ar'

test.use({ actionTimeout: 20_000, navigationTimeout: 30_000 })

test.describe('integrations', () => {
  test.skip(!LIVE_ENABLED, 'E2E_LIVE=1 runs the live end-to-end specs against a running stack')

  test('API client and webhook endpoint: secrets shown once', async ({ browser, request }) => {
    test.skip(test.info().project.name !== 'chromium', 'desktop Chromium only')
    test.setTimeout(3 * 60_000)
    const L = LOCALE
    const stamp = Date.now().toString(36)
    const issuer = await signIn(browser, ACCOUNTS.issuerOwner, L)
    const page = issuer.page

    // ---- API client ----
    const clientName = `ERP e2e ${stamp}`
    await page.goto(`/${L}/dashboard/integrations/api-clients`)
    await expect(page.getByRole('heading', { level: 1, name: tr(L, 'integrations.clients.title') })).toBeVisible()
    await page.getByRole('button', { name: tr(L, 'integrations.clients.create') }).first().click()
    const form = page.getByRole('dialog', { name: tr(L, 'integrations.clients.form.title') })
    await form.getByRole('textbox', { name: labelRx(tr(L, 'integrations.clients.fields.name')) }).fill(clientName)
    await form.getByRole('textbox', { name: labelRx(tr(L, 'integrations.clients.fields.description')) }).fill('Playwright end-to-end')
    for (const scope of ['competitions_read', 'awards_read']) {
      await check(form.getByRole('checkbox', { name: new RegExp(`^${tr(L, `integrations.scopes.items.${scope}`)}`) }))
    }
    await form.getByRole('button', { name: tr(L, 'integrations.clients.form.submit') }).click()

    const created = page.getByRole('dialog', { name: tr(L, 'integrations.clients.created.title') })
    await expect(created).toBeVisible({ timeout: 15_000 })
    const codes = await created.locator('code').allInnerTexts()
    expect(codes).toHaveLength(2)
    const [clientId, secret] = codes.map(value => value.trim()) as [string, string]
    expect(secret.length).toBeGreaterThanOrEqual(32)
    await expect(created.getByText(tr(L, 'common.secret.once'))).toBeVisible()
    // The secret is masked in the saved screenshot (the dialog is what is being documented).
    await page.screenshot({ path: shotPath(`integrations-${L}-01-client-secret-once`), animations: 'disabled', mask: [created.locator('code')] })

    // The dialog cannot be closed before the owner confirms storing the secret.
    const done = created.getByRole('button', { name: tr(L, 'integrations.secret.done') })
    await expect(done).toBeDisabled()
    await page.keyboard.press('Escape')
    await expect(created).toBeVisible()

    // The secret is real: a client-credentials token from the public API.
    const token = await request.post(`${API_ORIGIN}/api/public/v1/oauth/token`, {
      headers: { Accept: 'application/json' },
      form: { grant_type: 'client_credentials', client_id: clientId, client_secret: secret, scope: 'competitions:read' },
    })
    expect(token.status(), 'client-credentials token with the shown secret').toBe(200)
    expect((await token.json() as { token_type: string }).token_type).toBe('Bearer')

    await check(created.getByRole('checkbox', { name: labelRx(tr(L, 'common.secret.acknowledge')) }))
    await done.click()
    await expect(created).toBeHidden()

    // Never shown again: not in the list, not on the client's page, not after a reload.
    await expect(page.getByRole('link', { name: clientName })).toBeVisible()
    expect(await page.locator('body').innerText()).not.toContain(secret)
    await page.getByRole('link', { name: clientName }).click()
    await expect(page.getByRole('heading', { level: 1, name: clientName })).toBeVisible()
    await expect(page.getByText(clientId).first()).toBeVisible()
    expect(await page.locator('body').innerText()).not.toContain(secret)
    await page.reload()
    await expect(page.getByRole('heading', { level: 1, name: clientName })).toBeVisible()
    expect(await page.content()).not.toContain(secret)
    await page.screenshot({ path: shotPath(`integrations-${L}-02-client-detail`), fullPage: true })

    // ---- Webhook endpoint ----
    const url = `http://localhost:9999/e2e/${stamp}`
    await page.goto(`/${L}/dashboard/integrations/webhooks`)
    await expect(page.getByRole('heading', { level: 1, name: tr(L, 'integrations.webhooks.title') })).toBeVisible()
    await page.getByRole('button', { name: tr(L, 'integrations.webhooks.create') }).first().click()
    const webhookForm = page.getByRole('dialog', { name: tr(L, 'integrations.webhooks.form.title_create') })
    await webhookForm.getByRole('textbox', { name: labelRx(tr(L, 'integrations.webhooks.fields.url')) }).fill(url)
    await webhookForm.getByRole('textbox', { name: labelRx(tr(L, 'integrations.webhooks.fields.description')) }).fill('Playwright receiver')
    await check(webhookForm.getByRole('checkbox', { name: new RegExp(`^${tr(L, 'integrations.webhooks.events.all_label')}`) }))
    await webhookForm.getByRole('button', { name: tr(L, 'integrations.webhooks.form.submit') }).click()

    const signing = page.getByRole('dialog', { name: tr(L, 'integrations.webhooks.created.title') })
    await expect(signing).toBeVisible({ timeout: 15_000 })
    const webhookSecret = (await signing.locator('code').last().innerText()).trim()
    expect(webhookSecret).toMatch(/^whsec_/)
    await page.screenshot({ path: shotPath(`integrations-${L}-03-webhook-secret-once`), animations: 'disabled', mask: [signing.locator('code')] })
    await check(signing.getByRole('checkbox', { name: labelRx(tr(L, 'common.secret.acknowledge')) }))
    await signing.getByRole('button', { name: tr(L, 'integrations.secret.done') }).click()
    await expect(signing).toBeHidden()
    await expect(page.getByRole('link', { name: url })).toBeVisible()
    expect(await page.locator('body').innerText()).not.toContain(webhookSecret)
    await page.getByRole('link', { name: url }).click()
    await expect(page.getByText(url).first()).toBeVisible()
    expect(await page.content()).not.toContain(webhookSecret)
    await page.screenshot({ path: shotPath(`integrations-${L}-04-webhook-detail`), fullPage: true })

    await issuer.context.close()
  })
})

async function check(box: Locator): Promise<void> {
  if (!(await box.isChecked())) await box.check()
  await expect(box).toBeChecked()
}
