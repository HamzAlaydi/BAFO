<script setup lang="ts">
import type { Component } from 'vue'
import type { Tone } from '~/types/ui'

const props = withDefaults(defineProps<{
  tone?: Tone
  /** Filled high-emphasis variant (e.g. "Awarded"). */
  solid?: boolean
  size?: 'sm' | 'md'
  icon?: Component
  dot?: boolean
  /** A pulsing dot after the icon (static under reduced motion), e.g. the final pricing window. */
  pulse?: boolean
}>(), {
  tone: 'neutral',
  size: 'md',
})

const softClasses: Record<Tone, string> = {
  neutral: 'bg-neutral-soft text-neutral-soft-fg',
  primary: 'bg-primary-soft text-primary-soft-fg',
  success: 'bg-success-soft text-success-soft-fg',
  warning: 'bg-warning-soft text-warning-soft-fg',
  danger: 'bg-danger-soft text-danger-soft-fg',
  info: 'bg-info-soft text-info-soft-fg',
}

const solidClasses: Record<Tone, string> = {
  neutral: 'bg-fg text-fg-inverse',
  primary: 'bg-primary text-primary-fg',
  success: 'bg-primary text-primary-fg',
  warning: 'bg-warning text-fg-inverse',
  danger: 'bg-danger text-danger-fg',
  info: 'bg-info text-fg-inverse',
}

const classes = computed(() => [
  'inline-flex max-w-full items-center gap-1.5 rounded-full font-semibold whitespace-nowrap',
  props.size === 'sm' ? 'h-6 px-2 text-xs' : 'h-7 px-2.5 text-[0.8125rem]',
  props.solid ? solidClasses[props.tone] : softClasses[props.tone],
])
</script>

<template>
  <span :class="classes">
    <span
      v-if="dot"
      class="size-1.5 shrink-0 rounded-full bg-current"
      aria-hidden="true"
    />
    <component
      :is="icon"
      v-else-if="icon"
      :size="size === 'sm' ? 12 : 14"
      class="shrink-0"
      aria-hidden="true"
    />
    <span
      v-if="pulse"
      class="size-1.5 shrink-0 animate-pulse rounded-full bg-current motion-reduce:animate-none"
      aria-hidden="true"
    />
    <span class="truncate"><slot /></span>
  </span>
</template>
