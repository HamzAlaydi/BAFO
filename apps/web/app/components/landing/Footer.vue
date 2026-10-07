<script setup lang="ts">
import type { LegalDocumentCode } from '~/types/api/platform'

/**
 * Landing footer (RELEASE_SCOPE §6.1 row 9): legal links (W02; the API terms only with
 * `integrations_api`), support contacts, the language switch and the copyright line without a
 * hard-coded year.
 */
const props = defineProps<{ apiTerms: boolean }>()
const { t } = useI18n()

const legalLinks = computed<LegalDocumentCode[]>(() => [
  'terms',
  'privacy',
  'refund',
  'competition_rules',
  ...(props.apiTerms ? ['api_terms' as const] : []),
])
</script>

<template>
  <footer class="border-t border-line bg-page">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.3fr_1fr_1fr]">
      <div class="flex flex-col items-start gap-3">
        <AppLogo size="sm" />
        <!-- Display size: the guide's brand-green emphasis needs ≥ 24 px for AA contrast (SCREENS S9). -->
        <p class="text-2xl leading-tight">
          <AppTagline />
        </p>
      </div>
      <nav :aria-labelledby="'footer-legal'">
        <p
          id="footer-legal"
          class="text-sm font-bold text-fg"
        >
          {{ t('common.footer.legal_links') }}
        </p>
        <ul class="mt-3 flex flex-col gap-2 text-sm">
          <li
            v-for="code in legalLinks"
            :key="code"
          >
            <NuxtLinkLocale
              :to="`/legal/${code}`"
              class="inline-flex min-h-8 items-center text-fg-muted hover:text-fg hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
              {{ t(`legal.codes.${code}`) }}
            </NuxtLinkLocale>
          </li>
        </ul>
      </nav>
      <div class="flex flex-col gap-6">
        <div>
          <p class="text-sm font-bold text-fg">
            {{ t('landing.footer.support') }}
          </p>
          <div class="mt-3">
            <AppSupportContacts />
          </div>
        </div>
        <div>
          <p class="text-sm font-bold text-fg">
            {{ t('landing.footer.language') }}
          </p>
          <div class="mt-1 -ms-2.5">
            <AppLanguageSwitch />
          </div>
        </div>
      </div>
    </div>
    <div class="border-t border-line">
      <div class="mx-auto max-w-6xl px-4 py-4 text-sm text-fg-muted sm:px-6">
        <p>{{ t('landing.footer.copyright') }}</p>
      </div>
    </div>
  </footer>
</template>
