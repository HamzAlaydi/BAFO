<script setup lang="ts">
import { EyeOff, ShieldCheck, Timer } from '@lucide/vue'

const { t } = useI18n()
const route = useRoute()
const year = new Date().getFullYear()
// Long forms (registration) opt into a wider column with `definePageMeta({ authWide: true })`.
const wide = computed(() => route.meta.authWide === true)

const points = computed(() => [
  { key: 'clock', icon: Timer, text: t('auth.aside.points.clock') },
  { key: 'privacy', icon: EyeOff, text: t('auth.aside.points.privacy') },
  { key: 'security', icon: ShieldCheck, text: t('auth.aside.points.security') },
])
</script>

<template>
  <div
    class="grid min-h-dvh bg-page"
    :class="wide ? 'lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]' : 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]'"
  >
    <!-- Form side (start: right in Arabic) -->
    <div class="flex flex-col">
      <header class="flex h-16 items-center justify-between gap-3 px-4 sm:px-8">
        <NuxtLinkLocale
          to="/"
          class="rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
        >
          <AppLogo size="sm" />
        </NuxtLinkLocale>
        <div class="flex items-center gap-1">
          <AppLanguageSwitch compact />
          <AppThemeMenu />
        </div>
      </header>
      <main
        id="main"
        class="flex flex-1 items-start justify-center px-4 py-8 sm:items-center sm:px-8"
      >
        <div
          class="w-full"
          :class="wide ? 'max-w-2xl' : 'max-w-md'"
        >
          <slot />
        </div>
      </main>
    </div>

    <!-- Brand side (end: left in Arabic) -->
    <aside class="relative hidden overflow-hidden bg-charcoal text-white lg:sticky lg:top-0 lg:flex lg:h-dvh lg:flex-col lg:justify-between lg:p-12">
      <AppLogo
        size="md"
        inverse
      />
      <div class="relative max-w-md">
        <p class="text-3xl leading-snug">
          <AppTagline inverse />
        </p>
        <p class="mt-4 text-gray-300">
          {{ t('auth.aside.body') }}
        </p>
        <ul class="mt-8 flex flex-col gap-4">
          <li
            v-for="point in points"
            :key="point.key"
            class="flex items-start gap-3"
          >
            <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-white/10 text-green-bright">
              <component
                :is="point.icon"
                :size="18"
                aria-hidden="true"
              />
            </span>
            <span class="pt-1.5 text-gray-200">{{ point.text }}</span>
          </li>
        </ul>
      </div>
      <p class="text-sm text-gray-400">
        {{ t('common.footer.copyright', { year }) }}
      </p>
      <!-- Decorative mark; never recoloured or mirrored. -->
      <div class="pointer-events-none absolute -end-24 -bottom-24 opacity-[0.07]">
        <AppBrandMark size-class="size-96" />
      </div>
    </aside>
  </div>
</template>
