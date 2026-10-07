<script setup lang="ts">
/**
 * Who it is for (RELEASE_SCOPE §6.1 row 5): three audiences, each opened by its glossary term in
 * display type (brand green is allowed for display text ≥ 24 px, SCREENS S9).
 */
const { t } = useI18n()
const items = ['procurement', 'sellers', 'suppliers'] as const
</script>

<template>
  <section
    class="border-y border-line bg-surface-muted"
    aria-labelledby="audience-title"
  >
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
      <LandingSectionHeading
        id="audience-title"
        :title="t('landing.audience.title')"
        :subtitle="t('landing.audience.subtitle')"
      />
      <ul class="mt-10 grid gap-10 md:grid-cols-3 md:gap-8">
        <li
          v-for="item in items"
          :key="item"
          class="flex flex-col gap-3 border-t-2 border-line pt-6"
        >
          <p class="text-3xl leading-none font-bold text-brand">
            {{ t(`landing.audience.items.${item}.term`) }}
          </p>
          <h3 class="mt-2 text-xl font-bold text-fg">
            {{ t(`landing.audience.items.${item}.title`) }}
          </h3>
          <p class="text-fg-muted">
            {{ t(`landing.audience.items.${item}.body`) }}
          </p>
        </li>
      </ul>
    </div>
  </section>
</template>
