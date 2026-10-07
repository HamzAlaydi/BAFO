<script setup lang="ts">
import { fetchPlans } from '~/services/billing'
import type { LandingFaqItem } from '~/components/landing/Faq.vue'
import { buildLandingJsonLd, LANDING_OG_IMAGE, landingOgImageUrl, landingPageUrl } from '~/utils/landing-seo'
// The self-hosted woff2 subsets the first screen uses (nuxt.config FONT_CSS). Preloaded below so the
// Arabic text paints in Plex before the layout settles (RELEASE_SCOPE §6.3 LCP rules: CLS from font swap).
import plexArabic400 from '@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-400-normal.woff2?url'
import plexArabic600 from '@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-600-normal.woff2?url'
import plexArabic700 from '@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-700-normal.woff2?url'
import interLatin from '@fontsource-variable/inter/files/inter-latin-wght-normal.woff2?url'

/**
 * W01 Landing · `/` (SSR; SCREENS §2.4, RELEASE_SCOPE §6). Sections in order: hero with the live
 * demo, how it works, tender vs auction, fairness, who it is for, [sponsored participation],
 * [ERP connectivity], plans (`GET /plans`, SSR, hidden on failure), FAQ, final CTA, contact
 * (`POST /contact` with the honeypot) and the footer. The bracketed sections, the sealed lines,
 * two FAQ items, the API terms link and the theme menu follow `features.flags` from
 * `GET /app-config` (RELEASE_SCOPE §1.3); in scope `core` they are not rendered.
 *
 * SEO (§6.3): per-locale title and description, Open Graph and Twitter with the static 1200×630
 * image, canonical and hreflang from `app.vue`, and Organization / WebSite / SoftwareApplication /
 * FAQPage JSON-LD built from the rendered FAQ items.
 */
definePageMeta({ layout: false })

const { t } = useI18n()
const locale = useAppLocale()
const config = useRuntimeConfig()
const appConfig = useAppConfigStore()

const siteUrl = computed(() => String(config.public.siteUrl).replace(/\/$/, ''))

/** Resolves with the value, or `undefined` once `ms` have passed (the timer is cleared either way). */
function withTimeout<T>(promise: Promise<T>, ms: number): Promise<T | undefined> {
  let timer: ReturnType<typeof setTimeout> | undefined
  const timeout = new Promise<undefined>((resolve) => {
    timer = setTimeout(() => resolve(undefined), ms)
  })
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer))
}

// ---------- Release-scope flags (RELEASE_SCOPE §1.3) ----------

// Loaded on the server so the flag-gated sections, the FAQ count and the JSON-LD are right in the
// first HTML; Pinia hydrates the same state on the client (the client plugin then finds it loaded).
if (import.meta.server) await withTimeout(appConfig.load(), 10_000)

const features = useFeatures()
const flags = computed(() => ({
  sealed: features.enabled('sealed_format'),
  sponsorship: features.enabled('sponsorship'),
  integrations: features.enabled('integrations_api'),
  darkMode: features.enabled('dark_mode'),
}))

// ---------- Plans teaser (SSR; hidden on failure or when empty) ----------

const { data: plans } = await useAsyncData(
  'landing:plans',
  () => withTimeout(fetchPlans(), 10_000).then(result => result ?? null).catch(() => null),
  { watch: [locale], default: () => null },
)

const teaserPlans = computed(() => (plans.value ?? []).filter(plan => !plan.is_custom && plan.monthly_price_minor !== null).slice(0, 3))
const hasCustomPlan = computed(() => (plans.value ?? []).some(plan => plan.is_custom))

// ---------- FAQ (RELEASE_SCOPE §6.2): 10 always, `sponsored` and `erp` by flag ----------

const FAQ_ALWAYS = ['what', 'tender_auction', 'who_can_join', 'identities', 'clock', 'anti_sniping', 'rules', 'award', 'plans', 'mobile'] as const

const faq = computed<LandingFaqItem[]>(() => [
  ...FAQ_ALWAYS,
  ...(flags.value.sponsorship ? ['sponsored' as const] : []),
  ...(flags.value.integrations ? ['erp' as const] : []),
].map(key => ({
  key,
  question: t(`landing.faq.items.${key}.question`),
  answer: t(`landing.faq.items.${key}.answer`),
})))

// ---------- SEO ----------

// The description mentions sealed offers only while the format can be chosen (`sealed_format`, §6.1).
const metaDescription = computed(() => t(flags.value.sealed ? 'landing.meta.description_sealed' : 'landing.meta.description'))

const pageUrl = computed(() => landingPageUrl(siteUrl.value, locale.value))
const ogImage = computed(() => landingOgImageUrl(siteUrl.value, locale.value))

useSeoMeta({
  title: () => t('landing.meta.title'),
  description: () => metaDescription.value,
  ogTitle: () => t('landing.meta.og_title'),
  ogDescription: () => metaDescription.value,
  ogType: 'website',
  ogUrl: () => pageUrl.value,
  ogImage: () => ogImage.value,
  ogImageWidth: LANDING_OG_IMAGE.width,
  ogImageHeight: LANDING_OG_IMAGE.height,
  ogImageType: 'image/png',
  ogImageAlt: () => t('landing.meta.og_image_alt'),
  ogLocale: () => (locale.value === 'ar' ? 'ar_SA' : 'en_US'),
  ogLocaleAlternate: () => [locale.value === 'ar' ? 'en_US' : 'ar_SA'],
  twitterCard: 'summary_large_image',
  twitterTitle: () => t('landing.meta.og_title'),
  twitterDescription: () => metaDescription.value,
  twitterImage: () => ogImage.value,
  twitterImageAlt: () => t('landing.meta.og_image_alt'),
})

const preloadFonts = computed(() => (locale.value === 'ar' ? [plexArabic700, plexArabic400, plexArabic600, interLatin] : [interLatin])
  .map(href => ({ key: `preload-${href}`, rel: 'preload' as const, as: 'font' as const, type: 'font/woff2', href, crossorigin: 'anonymous' as const })))

useHead(() => ({
  link: preloadFonts.value,
  meta: [{ key: 'keywords', name: 'keywords', content: t('landing.meta.keywords') }],
  script: [
    {
      key: 'landing-jsonld',
      type: 'application/ld+json',
      innerHTML: JSON.stringify(buildLandingJsonLd({
        siteUrl: siteUrl.value,
        locale: locale.value,
        name: t('common.app.name'),
        tagline: t('common.app.tagline'),
        description: metaDescription.value,
        faq: faq.value,
      })),
    },
  ],
}))
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-page">
    <LandingHeader
      :dark-mode="flags.darkMode"
      :show-plans="teaserPlans.length > 0"
    />

    <main
      id="main"
      class="flex-1"
    >
      <LandingHero />
      <LandingHow />
      <LandingModes :sealed="flags.sealed" />
      <LandingFairness :sealed="flags.sealed" />
      <LandingAudience />
      <LandingSponsored v-if="flags.sponsorship" />
      <LandingErp v-if="flags.integrations" />
      <LandingPlans
        v-if="teaserPlans.length > 0"
        :plans="teaserPlans"
        :has-custom-plan="hasCustomPlan"
      />

      <section
        id="faq"
        class="scroll-mt-20 border-t border-line"
        aria-labelledby="faq-title"
      >
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
          <LandingSectionHeading
            id="faq-title"
            :title="t('landing.faq.title')"
            :subtitle="t('landing.faq.subtitle')"
          />
          <LandingFaq :items="faq" />
        </div>
      </section>

      <LandingCta />
      <LandingContact />
    </main>

    <LandingFooter :api-terms="flags.integrations" />
  </div>
</template>
