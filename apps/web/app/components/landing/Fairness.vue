<script setup lang="ts">
import type { Component } from 'vue'
import { History, Lock, Timer, TimerReset } from '@lucide/vue'

/**
 * Fairness and integrity (RELEASE_SCOPE §6.1 row 4): server clock, anti-sniping, sealed offers
 * (only with `sealed_format`) and the audit log, as one divided grid rather than loose cards.
 */
const props = defineProps<{ sealed: boolean }>()
const { t } = useI18n()

const ICONS: Record<string, Component> = { clock: Timer, anti_sniping: TimerReset, sealed: Lock, audit: History }

const items = computed(() => ['clock', 'anti_sniping', 'sealed', 'audit']
  .filter(key => key !== 'sealed' || props.sealed)
  .map(key => ({
    key,
    icon: ICONS[key]!,
    title: t(`landing.fairness.items.${key}.title`),
    body: t(`landing.fairness.items.${key}.body`),
  })))
</script>

<template>
  <section
    id="fairness"
    class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-24"
    aria-labelledby="fairness-title"
  >
    <LandingSectionHeading
      id="fairness-title"
      :title="t('landing.fairness.title')"
      :subtitle="t('landing.fairness.subtitle')"
    />
    <ul
      class="mt-10 grid gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-2"
      :class="items.length === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'"
    >
      <li
        v-for="item in items"
        :key="item.key"
        class="flex flex-col gap-4 bg-surface p-6"
      >
        <span
          class="inline-flex size-11 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
          aria-hidden="true"
        >
          <component
            :is="item.icon"
            :size="22"
          />
        </span>
        <h3 class="text-lg font-bold text-fg">
          {{ item.title }}
        </h3>
        <p class="text-fg-muted">
          {{ item.body }}
        </p>
      </li>
    </ul>
  </section>
</template>
