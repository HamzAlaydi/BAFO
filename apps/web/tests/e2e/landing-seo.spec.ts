import { expect, test } from '@playwright/test'

/** Landing SEO surface (RELEASE_SCOPE §6.3): head tags, JSON-LD, the sitemap and robots routes. */
test.describe('landing SEO', () => {
  for (const locale of ['ar', 'en'] as const) {
    test(`/${locale} carries canonical, hreflang, Open Graph and a FAQPage graph`, async ({ page }) => {
      await page.goto(`/${locale}`)
      await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`/${locale}$`))
      for (const hreflang of ['ar', 'en', 'x-default']) {
        await expect(page.locator(`link[rel="alternate"][hreflang="${hreflang}"]`)).toHaveCount(1)
      }
      await expect(page.locator('link[rel="alternate"][hreflang="x-default"]')).toHaveAttribute('href', /\/ar$/)
      await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', new RegExp(`/og/bafo-og-${locale}\\.png$`))
      await expect(page.locator('meta[name="twitter:card"]')).toHaveAttribute('content', 'summary_large_image')
      const graph = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent() ?? '[]') as Array<{ '@type': string }>
      expect(graph.map(node => node['@type'])).toEqual(['Organization', 'WebSite', 'SoftwareApplication', 'FAQPage'])
      expect(await page.locator('#faq details').count()).toBeGreaterThanOrEqual(10)
      expect(await page.locator('h1').count()).toBe(1)
    })
  }

  test('serves robots.txt with the private areas blocked and the sitemap announced', async ({ request }) => {
    const response = await request.get('/robots.txt')
    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('text/plain')
    const text = await response.text()
    expect(text).toContain('Disallow: /ar/dashboard')
    expect(text).toContain('Disallow: /en/auth')
    expect(text).toMatch(/Sitemap: https?:\/\/\S+\/sitemap\.xml/)
  })

  test('serves a sitemap with both locale roots and hreflang alternates', async ({ request }) => {
    const response = await request.get('/sitemap.xml')
    expect(response.status()).toBe(200)
    expect(response.headers()['content-type']).toContain('xml')
    const xml = await response.text()
    expect(xml).toContain('<loc>')
    expect(xml).toMatch(/<loc>https?:\/\/[^<]+\/ar<\/loc>/)
    expect(xml).toMatch(/<loc>https?:\/\/[^<]+\/en<\/loc>/)
    expect(xml).toContain('hreflang="x-default"')
  })

  test('an unknown page answers 404 with the not-found page and noindex', async ({ page }) => {
    const response = await page.goto('/ar/this-page-does-not-exist')
    expect(response?.status()).toBe(404)
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/)
    await expect(page.getByText('الصفحة غير موجودة')).toBeVisible()
  })
})
