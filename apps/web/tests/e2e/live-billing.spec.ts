import { readFileSync } from 'node:fs'
import { expect, test } from '@playwright/test'
import { labelRx, tr, type Locale } from './support/i18n'
import { approveFakePayment } from './support/flows'
import { LIVE_ENABLED, OTP_CODE, shotPath, waitForHydration } from './support/stack'

/**
 * Billing on a real stack (opt-in: E2E_LIVE=1). A new company registers on the web (e-mail OTP from
 * OTP_FAKE_CODE), subscribes to the single plan through the hosted test checkout, finds the tax
 * invoice in its invoices, and downloads the invoice PDF.
 */
const LOCALE: Locale = (process.env.E2E_LOCALE as Locale | undefined) ?? 'ar'

test.use({ actionTimeout: 20_000, navigationTimeout: 30_000 })

test.describe('billing', () => {
  test.skip(!LIVE_ENABLED, 'E2E_LIVE=1 runs the live end-to-end specs against a running stack')

  test('register, subscribe through the test checkout, download the invoice PDF', async ({ browser }) => {
    test.skip(test.info().project.name !== 'chromium', 'desktop Chromium only')
    test.setTimeout(5 * 60_000)
    const L = LOCALE
    const stamp = Date.now()
    const email = `e2e.billing.${stamp}@example.com`
    const company = L === 'ar' ? `منشأة اختبار ${stamp.toString(36)}` : `E2E Trading ${stamp.toString(36)}`
    const cr = `70${String(stamp).slice(-8)}`
    const password = `E2e-${stamp.toString(36)}-Pass!`

    const context = await browser.newContext({ locale: L === 'ar' ? 'ar-SA' : 'en-US', timezoneId: 'Asia/Riyadh' })
    const page = await context.newPage()

    // ---- Register the company with a complete billing profile ----
    await page.goto(`/${L}/auth/register`)
    await waitForHydration(page)
    const field = (key: string) => page.getByRole('textbox', { name: labelRx(tr(L, key)) })
    await field('auth.fields.name').fill(L === 'ar' ? 'منى الاختبار' : 'Mona Tester')
    await field('auth.fields.email').fill(email)
    await field('auth.fields.phone').fill(`5${String(stamp).slice(-8)}`)
    await field('auth.fields.password').fill(password)
    await field('auth.fields.password_confirmation').fill(password)
    await field('organization.fields.name').fill(company)
    await field('organization.fields.cr_number').fill(cr)
    await page.getByRole('combobox', { name: labelRx(tr(L, 'organization.fields.region')) }).selectOption({ index: 1 })
    await field('organization.fields.city').fill(L === 'ar' ? 'الرياض' : 'Riyadh')
    await field('organization.fields.legal_name_ar').fill(`شركة ${company}`)
    await field('organization.fields.legal_name_en').fill(`E2E ${stamp.toString(36)} Co.`)
    const address = page.locator('details').filter({ hasText: tr(L, 'organization.sections.national_address') })
    if (!(await address.evaluate(node => (node as HTMLDetailsElement).open))) await address.locator('summary').click()
    await field('organization.fields.building_number').fill('1234')
    await field('organization.fields.street').fill(L === 'ar' ? 'طريق الملك فهد' : 'King Fahd Road')
    await field('organization.fields.district').fill(L === 'ar' ? 'العليا' : 'Al Olaya')
    await field('organization.fields.postal_code').fill('12211')
    await page.getByRole('checkbox', { name: labelRx(tr(L, 'auth.register.accept_terms')) }).check()
    await page.getByRole('checkbox', { name: labelRx(tr(L, 'auth.register.accept_privacy')) }).check()
    await page.screenshot({ path: shotPath(`billing-${L}-01-register`), fullPage: true })
    await page.getByRole('button', { name: tr(L, 'auth.register.submit') }).click()

    // ---- E-mail OTP ----
    await expect(page).toHaveURL(/\/auth\/verify/, { timeout: 20_000 })
    await page.getByRole('textbox', { name: labelRx(tr(L, 'auth.fields.otp')) }).fill(OTP_CODE)
    await expect(page).toHaveURL(new RegExp(`/${L}/dashboard`), { timeout: 20_000 })

    // ---- Subscribe to the single plan ----
    await page.goto(`/${L}/dashboard/billing/plans`)
    const plan = page.getByRole('link', { name: new RegExp(`^${tr(L, 'billing.plans.choose_named', { plan: '' })}`) }).first()
    const planName = (await plan.getAttribute('aria-label') ?? await plan.innerText()).split(':').pop()?.trim() ?? ''
    await plan.click()
    await expect(page).toHaveURL(/\/dashboard\/billing\/checkout\?/)
    await page.getByRole('button', { name: tr(L, 'billing.checkout.continue'), exact: true }).click()
    await expect(page.getByRole('heading', { name: tr(L, 'billing.checkout.confirm.title') })).toBeVisible({ timeout: 15_000 })
    await page.screenshot({ path: shotPath(`billing-${L}-02-checkout-confirm`), fullPage: true })
    await page.getByRole('button', { name: new RegExp(`^${tr(L, 'billing.checkout.confirm.pay', { total: '' }).trim()}`) }).click()
    await approveFakePayment(page)
    await expect(page.getByRole('heading', { name: tr(L, 'billing.payment_return.subscription.title') })).toBeVisible({ timeout: 30_000 })
    await page.screenshot({ path: shotPath(`billing-${L}-03-subscription-active`), fullPage: true })
    expect(planName.length).toBeGreaterThan(0)

    // ---- The invoice appears; its PDF downloads ----
    await page.goto(`/${L}/dashboard/billing/invoices`)
    const row = page.getByRole('row').filter({ hasText: tr(L, 'billing.invoices.types.tax_invoice') }).first()
    const pdfButton = row.getByRole('button', { name: new RegExp(tr(L, 'billing.invoices.download_named', { number: '' }).trim()) })
    // The invoice is issued and the e-invoice cleared on the queue after the payment: it appears,
    // then offers its PDF, within seconds (the page shows what exists when it loads).
    await expect(async () => {
      await page.reload()
      await expect(pdfButton).toBeVisible({ timeout: 3_000 })
    }).toPass({ timeout: 60_000, intervals: [2_000] })
    await page.screenshot({ path: shotPath(`billing-${L}-04-invoices`), fullPage: true })
    const [download] = await Promise.all([page.waitForEvent('download'), pdfButton.click()])
    expect(download.suggestedFilename()).toMatch(/\.pdf$/)
    const saved = test.info().outputPath(download.suggestedFilename())
    await download.saveAs(saved)
    const bytes = readFileSync(saved)
    expect(bytes.subarray(0, 5).toString('latin1')).toBe('%PDF-')
    expect(bytes.length).toBeGreaterThan(1_000)

    await context.close()
  })
})
