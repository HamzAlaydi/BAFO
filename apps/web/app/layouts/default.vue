<script setup lang="ts">
import type { LegalDocumentCode } from '~/types/api/platform'

/** Public pages (landing, legal, invitation landing): header with sign-in, footer with legal links. */
const { t } = useI18n()
const auth = useAuthStore()
const year = new Date().getFullYear()

const legalLinks: LegalDocumentCode[] = ['terms', 'privacy', 'refund', 'competition_rules']
</script>

<template>
  <div class="flex min-h-dvh flex-col bg-page">
    <header class="sticky top-0 z-30 border-b border-line bg-page/90 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
        <NuxtLinkLocale
          to="/"
          class="rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
        >
          <AppLogo size="sm" />
        </NuxtLinkLocale>
        <div class="flex items-center gap-1 sm:gap-2">
          <AppLanguageSwitch compact />
          <AppThemeMenu />
          <template v-if="auth.isAuthenticated">
            <UiButton
              to="/dashboard"
              size="sm"
            >
              {{ t('nav.dashboard') }}
            </UiButton>
          </template>
          <template v-else>
            <!-- Below 400 px only "Create account" fits next to the logo; the register page links to sign-in. -->
            <span class="hidden xs:contents">
              <UiButton
                to="/auth/login"
                variant="ghost"
                size="sm"
              >
                {{ t('nav.login') }}
              </UiButton>
            </span>
            <UiButton
              to="/auth/register"
              size="sm"
            >
              {{ t('nav.register') }}
            </UiButton>
          </template>
        </div>
      </div>
    </header>

    <main
      id="main"
      class="flex-1"
    >
      <slot />
    </main>

    <footer class="border-t border-line bg-surface-muted">
      <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="flex flex-col gap-2">
          <AppLogo size="sm" />
          <p class="text-sm text-fg-muted">
            {{ t('common.app.tagline') }}
          </p>
        </div>
        <nav :aria-label="t('common.footer.legal_links')">
          <ul class="flex flex-col gap-2 text-sm">
            <li
              v-for="code in legalLinks"
              :key="code"
            >
              <NuxtLinkLocale
                :to="`/legal/${code}`"
                class="text-fg-muted hover:text-fg hover:underline"
              >
                {{ t(`legal.codes.${code}`) }}
              </NuxtLinkLocale>
            </li>
          </ul>
        </nav>
        <div class="flex flex-col gap-2 sm:items-end">
          <AppLanguageSwitch />
          <p class="text-sm text-fg-muted">
            {{ t('common.footer.copyright', { year }) }}
          </p>
        </div>
      </div>
    </footer>
  </div>
</template>
