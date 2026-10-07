/**
 * Structured data for the landing page (RELEASE_SCOPE §6.3): `Organization`, `WebSite`,
 * `SoftwareApplication` and `FAQPage` (from the rendered FAQ items only), emitted as one
 * `application/ld+json` array. Pure, so it is unit-tested and shared with the OG/sitemap tooling.
 */
import type { AppLocale } from '~/types/api/common'

export interface LandingSeoInput {
  /** Absolute origin without a trailing slash, e.g. `https://bafo-web-demo.vercel.app`. */
  siteUrl: string
  locale: AppLocale
  name: string
  tagline: string
  description: string
  faq: ReadonlyArray<{ question: string, answer: string }>
}

export const LANDING_OG_IMAGE = { width: 1200, height: 630 } as const

export function landingPageUrl(siteUrl: string, locale: AppLocale): string {
  return `${siteUrl.replace(/\/$/, '')}/${locale}`
}

export function landingOgImageUrl(siteUrl: string, locale: AppLocale): string {
  return `${siteUrl.replace(/\/$/, '')}/og/bafo-og-${locale}.png`
}

export function buildLandingJsonLd(input: LandingSeoInput): Record<string, unknown>[] {
  const siteUrl = input.siteUrl.replace(/\/$/, '')
  const url = landingPageUrl(siteUrl, input.locale)
  const inLanguage = input.locale === 'ar' ? 'ar-SA' : 'en-US'

  return [
    {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      'name': input.name,
      'alternateName': 'BAFO',
      'url': url,
      'logo': `${siteUrl}/brand/mark-color-1024.png`,
      'slogan': input.tagline,
    },
    {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      'name': input.name,
      'url': url,
      'inLanguage': inLanguage,
    },
    {
      '@context': 'https://schema.org',
      '@type': 'SoftwareApplication',
      'name': input.name,
      'applicationCategory': 'BusinessApplication',
      'operatingSystem': 'Web, iOS, Android',
      'description': input.description,
      'inLanguage': inLanguage,
      'url': url,
      'image': landingOgImageUrl(siteUrl, input.locale),
    },
    {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      'inLanguage': inLanguage,
      'mainEntity': input.faq.map(item => ({
        '@type': 'Question',
        'name': item.question,
        'acceptedAnswer': { '@type': 'Answer', 'text': item.answer },
      })),
    },
  ]
}
