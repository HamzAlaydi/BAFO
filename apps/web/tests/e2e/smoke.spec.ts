import { expect, test } from '@playwright/test'

test.describe('smoke', () => {
  test('root redirects to the Arabic landing page, rendered right-to-left', async ({ page }) => {
    await page.goto('/')
    await expect(page).toHaveURL(/\/ar\/?$/)
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(page.locator('html')).toHaveAttribute('lang', /^ar/)
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  })

  test('English landing page is left-to-right and links back to Arabic', async ({ page }) => {
    await page.goto('/en')
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr')
    await expect(page.getByRole('heading', { level: 1 })).toContainText('best and final offer')
    await page.getByRole('link', { name: /العربية/ }).first().click()
    await expect(page).toHaveURL(/\/ar\/?$/)
  })

  test('dashboard requires a session', async ({ page }) => {
    await page.goto('/ar/dashboard')
    await expect(page).toHaveURL(/\/ar\/auth\/login\?redirect=/)
  })

  test('the scaffold sign-in path redirects to /auth/login (SCREENS CD2)', async ({ page }) => {
    await page.goto('/en/login')
    await expect(page).toHaveURL(/\/en\/auth\/login$/)
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  })
})
