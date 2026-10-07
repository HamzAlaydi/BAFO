import tailwindcss from '@tailwindcss/vite'

/**
 * Self-hosted fonts (R2R3 D2, SCREENS R-W6): the woff2 files ship with the app from the OFL
 * `@fontsource` packages, so there is no third-party font request. Each face is split by
 * `unicode-range`, so the browser downloads only the scripts a page uses.
 */
const FONT_CSS = [
  '@fontsource-variable/inter/wght.css',
  '@fontsource/ibm-plex-sans-arabic/400.css',
  '@fontsource/ibm-plex-sans-arabic/500.css',
  '@fontsource/ibm-plex-sans-arabic/600.css',
  '@fontsource/ibm-plex-sans-arabic/700.css',
]

/**
 * Absolute site origin for canonical, hreflang, Open Graph, the sitemap and robots.txt
 * (RELEASE_SCOPE §6.3). `NUXT_PUBLIC_SITE_URL` overrides it at build time; set
 * `NUXT_PUBLIC_I18N_BASE_URL` to the same value when overriding at runtime only.
 */
const SITE_URL = (process.env.NUXT_PUBLIC_SITE_URL ?? 'https://bafo-web-demo.vercel.app').replace(/\/$/, '')

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: ['@nuxtjs/i18n', '@pinia/nuxt', '@vueuse/nuxt', '@nuxt/eslint'],

  devtools: { enabled: true },

  app: {
    head: {
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'color-scheme', content: 'light dark' },
        { name: 'theme-color', content: '#FFFFFF', media: '(prefers-color-scheme: light)' },
        { name: 'theme-color', content: '#14161B', media: '(prefers-color-scheme: dark)' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico', sizes: '48x48' },
        { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
        { rel: 'manifest', href: '/site.webmanifest' },
      ],
    },
  },

  css: [...FONT_CSS, '~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      // Override with NUXT_PUBLIC_API_BASE etc. (see .env.example)
      apiBase: 'http://localhost:8000/api/app/v1',
      // Canonical origin of the public site (no trailing slash).
      siteUrl: SITE_URL,
      broadcastAuthEndpoint: 'http://localhost:8000/broadcasting/auth',
      // Development-only override of `AppConfig.realtime` (GET /app-config is the contract source).
      reverb: {
        key: '',
        host: 'localhost',
        port: 8085,
        scheme: 'http',
      },
    },
  },

  // SSR for public pages (landing, auth); the dashboard is a client-rendered SPA.
  // Every page is served with anti-framing and no-sniff headers (SECURITY_REVIEW S-05): the
  // dashboard's award, publish and payment buttons cannot be clickjacked from another site.
  routeRules: {
    '/**': {
      headers: {
        // SAMEORIGIN, not DENY: Nuxt DevTools frames the app from the same origin in development.
        'X-Frame-Options': 'SAMEORIGIN',
        'Content-Security-Policy': 'frame-ancestors \'self\'',
        'X-Content-Type-Options': 'nosniff',
        'Referrer-Policy': 'same-origin',
        'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
      },
    },
    '/ar/dashboard': { ssr: false },
    '/ar/dashboard/**': { ssr: false },
    '/en/dashboard': { ssr: false },
    '/en/dashboard/**': { ssr: false },
  },

  compatibilityDate: '2025-07-15',

  // Pre-compressed static assets (gzip and brotli) for the node server used by the hosted demo image;
  // platforms with their own edge compression ignore the files (RELEASE_SCOPE §6.3 LCP rules).
  nitro: {
    compressPublicAssets: { gzip: true, brotli: true },
  },

  vite: {
    plugins: [tailwindcss()],
  },

  typescript: {
    strict: true,
  },

  eslint: {
    config: {
      stylistic: {
        indent: 2,
        quotes: 'single',
        semi: false,
      },
    },
  },

  i18n: {
    baseUrl: SITE_URL,
    defaultLocale: 'ar',
    strategy: 'prefix',
    langDir: 'locales',
    locales: [
      { code: 'ar', language: 'ar-SA', dir: 'rtl', name: 'العربية', file: 'ar.json' },
      { code: 'en', language: 'en-US', dir: 'ltr', name: 'English', file: 'en.json' },
    ],
    detectBrowserLanguage: false,
  },
})
