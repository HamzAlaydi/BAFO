<script setup lang="ts">
import { ArrowRight, CircleCheck } from '@lucide/vue'

/** Hero (RELEASE_SCOPE §6.1 row 1): the h1, the value proposition, both CTAs and the live demo card. */
const { t } = useI18n()
const points = ['trial', 'arabic_english', 'web_mobile'] as const
</script>

<template>
  <section
    class="relative overflow-hidden border-b border-line"
    aria-labelledby="hero-title"
  >
    <!-- The mark, faint and large, behind the demo card: one decorative moment, never mirrored. -->
    <LandingMarkOutline class="absolute -top-16 -end-24 size-[26rem] text-brand opacity-[0.07] sm:-top-24 sm:size-[34rem] lg:top-[-6rem] lg:size-[40rem]" />

    <div class="relative mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-16 lg:py-24">
      <div class="flex flex-col items-start gap-6">
        <UiBadge tone="primary">
          {{ t('landing.hero.eyebrow') }}
        </UiBadge>
        <h1
          id="hero-title"
          class="text-4xl leading-[1.15] font-bold text-balance text-fg sm:text-5xl lg:text-[3.25rem]"
        >
          {{ t('landing.hero.title') }}
        </h1>
        <p class="max-w-xl text-lg text-fg-muted">
          {{ t('landing.hero.subtitle') }}
        </p>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
          <UiButton
            to="/auth/register"
            size="lg"
            :icon-end="ArrowRight"
            flip-icons
          >
            {{ t('landing.hero.primary_cta') }}
          </UiButton>
          <UiButton
            to="/auth/login"
            size="lg"
            variant="secondary"
          >
            {{ t('landing.hero.secondary_cta') }}
          </UiButton>
        </div>
        <ul class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-fg-muted">
          <li
            v-for="point in points"
            :key="point"
            class="inline-flex items-center gap-1.5"
          >
            <CircleCheck
              :size="16"
              class="text-brand"
              aria-hidden="true"
            />
            {{ t(`landing.hero.points.${point}`) }}
          </li>
        </ul>
      </div>

      <LandingDemoCard />
    </div>
  </section>
</template>
