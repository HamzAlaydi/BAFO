/**
 * Renders the static Open Graph images (RELEASE_SCOPE §6.3): `public/og/bafo-og-ar.png` and
 * `public/og/bafo-og-en.png`, 1200×630, from `scripts/og-template.html` with the brand colours,
 * the mark, the supplied wordmark artwork and the landing title of each locale.
 *
 *   node scripts/og-image.ts            (Playwright Chromium; `pnpm exec playwright install chromium`)
 *   PW_CHANNEL=chrome node scripts/og-image.ts   to use the installed Google Chrome instead
 *
 * Run it again whenever `landing.meta.og_title` or `common.app.tagline` changes.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { chromium } from '@playwright/test'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const WIDTH = 1200
const HEIGHT = 630

type Locale = 'ar' | 'en'
interface Messages { [key: string]: string | Messages }

function message(tree: Messages, path: string): string {
  const value = path.split('.').reduce<string | Messages | undefined>((node, key) => (typeof node === 'object' ? node[key] : undefined), tree)
  if (typeof value !== 'string') throw new Error(`Missing i18n key ${path}`)
  return value
}

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function taglineHtml(locale: Locale, tree: Messages): string {
  // Guide emphasis (05_brand §2.4): «أفضل عرض» / "Best" and "Offer" bold and green.
  const template = message(tree, 'common.brand.tagline.template')
  const best = `<strong>${escapeHtml(message(tree, 'common.brand.tagline.best'))}</strong>`
  const offer = `<strong>${escapeHtml(message(tree, 'common.brand.tagline.offer'))}</strong>`
  return escapeHtml(template).replace('{best}', best).replace('{offer}', offer)
}

async function main(): Promise<void> {
  const template = readFileSync(resolve(root, 'scripts/og-template.html'), 'utf8')
  const siteUrl = (process.env.NUXT_PUBLIC_SITE_URL ?? 'https://bafo-web-demo.vercel.app').replace(/\/+$/, '')
  const host = new URL(siteUrl).host
  const outDir = resolve(root, 'public/og')
  mkdirSync(outDir, { recursive: true })

  const browser = await chromium.launch(process.env.PW_CHANNEL ? { channel: process.env.PW_CHANNEL } : {})
  try {
    for (const locale of ['ar', 'en'] as const) {
      const tree = JSON.parse(readFileSync(resolve(root, `i18n/locales/${locale}.json`), 'utf8')) as Messages
      const html = template
        .replaceAll('{{lang}}', locale)
        .replaceAll('{{dir}}', locale === 'ar' ? 'rtl' : 'ltr')
        .replaceAll('{{locale}}', locale)
        .replaceAll('{{title}}', escapeHtml(message(tree, 'landing.meta.title')))
        .replaceAll('{{tagline}}', taglineHtml(locale, tree))
        .replaceAll('{{host}}', escapeHtml(host))
      // Written next to the template so its relative font and artwork URLs resolve.
      const rendered = resolve(root, `scripts/.og-${locale}.html`)
      writeFileSync(rendered, html)
      const page = await browser.newPage({ viewport: { width: WIDTH, height: HEIGHT }, deviceScaleFactor: 1 })
      await page.goto(pathToFileURL(rendered).href)
      await page.evaluate(() => document.fonts.ready)
      const out = resolve(outDir, `bafo-og-${locale}.png`)
      await page.screenshot({ path: out, fullPage: false })
      await page.close()
      console.log(`wrote ${out}`)
    }
  }
  finally {
    await browser.close()
  }
}

main().catch((error) => {
  console.error(error)
  process.exit(1)
})
