<script setup lang="ts">
import { Lock, Radio } from '@lucide/vue'

/**
 * Tender vs auction (RELEASE_SCOPE §6.1 row 3): two panels split by one rule, each led by the
 * direction glyph of the mark (down = tender, the lowest offer wins; up = auction, the highest wins).
 * Colour never encodes direction (SCREENS S2). The sealed line appears only with `sealed_format`.
 */
defineProps<{ sealed: boolean }>()
const { t } = useI18n()
const directions = ['tender', 'auction'] as const
</script>

<template>
  <section
    id="modes"
    class="scroll-mt-20 border-y border-line bg-surface-muted"
    aria-labelledby="modes-title"
  >
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
      <LandingSectionHeading
        id="modes-title"
        :title="t('landing.modes.title')"
        :subtitle="t('landing.modes.subtitle')"
      />

      <div class="mt-10 grid overflow-hidden rounded-xl border border-line bg-surface md:grid-cols-2 md:divide-x divide-y divide-line md:divide-y-0">
        <article
          v-for="direction in directions"
          :key="direction"
          class="flex flex-col gap-5 p-6 sm:p-8"
          :aria-labelledby="`modes-${direction}`"
        >
          <svg
            viewBox="0 0 48 48"
            class="size-14 text-fg"
            fill="none"
            stroke="currentColor"
            stroke-width="5"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            focusable="false"
          >
            <polyline
              v-if="direction === 'tender'"
              points="10,16 24,34 38,16"
            />
            <polyline
              v-else
              points="10,32 24,14 38,32"
            />
          </svg>
          <div class="flex flex-col gap-2">
            <h3
              :id="`modes-${direction}`"
              class="text-2xl font-bold text-fg"
            >
              {{ t(`landing.modes.${direction}.title`) }}
            </h3>
            <p class="text-lg font-semibold text-fg">
              {{ t(`landing.modes.${direction}.lead`) }}
            </p>
          </div>
          <p class="text-fg-muted">
            {{ t(`landing.modes.${direction}.body`) }}
          </p>
          <p class="text-sm text-fg">
            <span class="font-semibold">{{ t('landing.modes.examples') }}</span>
            {{ t(`landing.modes.${direction}.examples`) }}
          </p>
          <CompetitionsDirectionChip
            :direction="direction"
            class="mt-auto self-start"
          />
        </article>
      </div>

      <ul class="mt-6 flex flex-col gap-3 text-fg-muted">
        <li class="flex items-start gap-3">
          <Radio
            :size="20"
            class="mt-0.5 shrink-0 text-brand"
            aria-hidden="true"
          />
          <span>{{ t('landing.modes.live_line') }}</span>
        </li>
        <li
          v-if="sealed"
          class="flex items-start gap-3"
        >
          <Lock
            :size="20"
            class="mt-0.5 shrink-0 text-brand"
            aria-hidden="true"
          />
          <span>{{ t('landing.modes.sealed_line') }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
