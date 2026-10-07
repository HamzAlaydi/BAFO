<script setup lang="ts">
import { ArrowRight } from '@lucide/vue'

/** Final call to action (RELEASE_SCOPE §6.1 row 8): the charcoal band with the register CTA and the contact link. */
const { t } = useI18n()
</script>

<template>
  <section
    class="relative overflow-hidden bg-charcoal text-white"
    aria-labelledby="cta-title"
  >
    <LandingMarkOutline class="absolute -bottom-24 -start-16 size-72 text-green-bright opacity-[0.12] sm:size-96" />
    <div class="relative mx-auto flex max-w-6xl flex-col items-start gap-8 px-4 py-16 sm:px-6 sm:py-20 md:flex-row md:items-center md:justify-between">
      <div class="flex max-w-2xl flex-col gap-3">
        <h2
          id="cta-title"
          class="text-3xl leading-tight font-bold text-balance sm:text-4xl"
        >
          {{ t('landing.cta.title') }}
        </h2>
        <p class="text-lg text-gray-300">
          {{ t('landing.cta.body') }}
        </p>
        <p class="text-2xl">
          <AppTagline inverse />
        </p>
      </div>
      <div class="flex flex-col items-start gap-3 md:items-end">
        <UiButton
          to="/auth/register"
          size="lg"
          :icon-end="ArrowRight"
          flip-icons
        >
          {{ t('landing.hero.primary_cta') }}
        </UiButton>
        <a
          href="#contact"
          class="rounded-md font-semibold text-green-bright underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-green-bright"
        >{{ t('landing.cta.contact') }}</a>
      </div>
    </div>
  </section>
</template>
