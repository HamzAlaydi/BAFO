<script setup lang="ts">
/**
 * BAFO round status for the issuer (SCREENS W19/W22 `BafoRoundCard`): inverse (charcoal) panel with
 * the mark, the cutoff countdown on the server clock, the shortlist size and how many final offers
 * have arrived. Award and close stay disabled until the round ends (the server moves back to closed).
 */
defineProps<{
  cutoffAt: string | null
  shortlistCount: number
  submittedCount: number
  ended?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-lg bg-fg p-5 text-fg-inverse sm:flex-row sm:items-center sm:justify-between"
    :aria-label="t('bafo.round.title')"
  >
    <div class="flex items-start gap-3">
      <AppBrandMark size-class="size-8 shrink-0" />
      <div>
        <p class="font-bold">
          {{ ended ? t('bafo.round.ended_title') : t('bafo.round.title') }}
        </p>
        <p class="text-sm opacity-80">
          {{ t('bafo.round.progress', { submitted: submittedCount, total: shortlistCount }) }}
        </p>
      </div>
    </div>
    <div
      v-if="cutoffAt && !ended"
      class="flex flex-col items-start gap-0.5 sm:items-end"
    >
      <span class="text-sm opacity-80">{{ t('bafo.round.closes_in') }}</span>
      <!-- The countdown keeps its own text tokens (and warning tone), so it sits on a surface chip. -->
      <span class="rounded-md bg-surface px-3 py-1">
        <UiCountdown
          :ends-at="cutoffAt"
          :label="t('bafo.round.closes_in')"
          :ended-label="t('competitions.detail.countdown.closing')"
          size="lg"
          announce
        />
      </span>
    </div>
  </section>
</template>
