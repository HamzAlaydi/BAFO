<script setup lang="ts">
import type { Direction } from '~/types/api/competitions'

/**
 * Direction chip (SCREENS S2): glyph = direction, never colour. Tender: ↓ + «مناقصة · الأقل سعراً يفوز»;
 * auction: ↑ + «مزايدة · الأعلى سعراً يفوز». Both use the same neutral container with a charcoal
 * chevron derived from the mark, and the chevron is never mirrored in RTL.
 */
withDefaults(defineProps<{
  direction: Direction
  /** Show the winning rule after the noun (default true). */
  withRule?: boolean
  size?: 'sm' | 'md'
}>(), {
  withRule: true,
  size: 'md',
})

const { t } = useI18n()
</script>

<template>
  <span
    class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-neutral-soft font-semibold whitespace-nowrap text-neutral-soft-fg"
    :class="size === 'sm' ? 'h-6 px-2 text-xs' : 'h-7 px-2.5 text-[0.8125rem]'"
  >
    <svg
      viewBox="0 0 24 24"
      class="shrink-0 text-fg"
      :class="size === 'sm' ? 'size-3.5' : 'size-4'"
      fill="none"
      stroke="currentColor"
      stroke-width="3"
      stroke-linecap="round"
      stroke-linejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <polyline
        v-if="direction === 'tender'"
        points="5,8 12,17 19,8"
      />
      <polyline
        v-else
        points="5,16 12,7 19,16"
      />
    </svg>
    <span class="truncate">
      {{ t(`competitions.direction.${direction}`) }}<template v-if="withRule"> · {{ t(`competitions.direction.rule.${direction}`) }}</template>
    </span>
  </span>
</template>
