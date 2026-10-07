<script setup lang="ts">
import { Ticket } from '@lucide/vue'

/**
 * Sponsored participation (R4), shown only with the `sponsorship` flag (RELEASE_SCOPE §1.3):
 * issuer-paid fees, the «رسوم مغطّاة» badge and unused passes.
 */
const { t } = useI18n()
const points = ['choose', 'badge', 'unused'] as const
</script>

<template>
  <section
    class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24"
    aria-labelledby="sponsored-title"
  >
    <div class="grid items-center gap-10 lg:grid-cols-2">
      <div class="flex flex-col gap-4">
        <InvitationsFeesCoveredBadge class="self-start" />
        <h2
          id="sponsored-title"
          class="text-3xl leading-tight font-bold text-balance text-fg sm:text-4xl"
        >
          {{ t('landing.sponsored.title') }}
        </h2>
        <p class="text-lg text-fg-muted">
          {{ t('landing.sponsored.body') }}
        </p>
      </div>
      <ul class="flex flex-col gap-4">
        <li
          v-for="point in points"
          :key="point"
          class="flex items-start gap-3 rounded-lg border border-line bg-surface p-5"
        >
          <span
            class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-info-soft text-info-soft-fg"
            aria-hidden="true"
          >
            <Ticket :size="20" />
          </span>
          <div>
            <h3 class="font-bold text-fg">
              {{ t(`landing.sponsored.points.${point}.title`) }}
            </h3>
            <p class="mt-1 text-sm text-fg-muted">
              {{ t(`landing.sponsored.points.${point}.body`) }}
            </p>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>
