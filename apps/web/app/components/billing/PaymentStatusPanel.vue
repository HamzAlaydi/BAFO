<script setup lang="ts">
import type { Component } from 'vue'
import { CircleAlert, CircleCheck, Clock, LoaderCircle, TimerOff, Undo2 } from '@lucide/vue'
import type { PaymentOutcome } from '~/utils/billing-display'

/**
 * `PaymentStatusPanel` (SCREENS W33): the payment state with an icon and text (never colour alone),
 * announced politely as it changes. Copy and actions come from the page (they depend on `purpose`).
 * It never shows a card number or a gateway detail.
 */
const props = defineProps<{
  outcome: PaymentOutcome
  title: string
  description?: string | null
}>()

const VISUALS: Record<PaymentOutcome, { icon: Component, classes: string, spin?: boolean }> = {
  checking: { icon: LoaderCircle, classes: 'bg-neutral-soft text-neutral-soft-fg', spin: true },
  still_pending: { icon: Clock, classes: 'bg-info-soft text-info-soft-fg' },
  succeeded: { icon: CircleCheck, classes: 'bg-primary-soft text-primary-soft-fg' },
  failed: { icon: CircleAlert, classes: 'bg-warning-soft text-warning-soft-fg' },
  expired: { icon: TimerOff, classes: 'bg-warning-soft text-warning-soft-fg' },
  refunded: { icon: Undo2, classes: 'bg-neutral-soft text-neutral-soft-fg' },
}

const visual = computed(() => VISUALS[props.outcome])
</script>

<template>
  <div
    class="flex flex-col items-center gap-4 px-2 py-6 text-center"
    role="status"
    aria-live="polite"
    :aria-busy="outcome === 'checking' || undefined"
  >
    <span
      class="inline-flex size-16 items-center justify-center rounded-full"
      :class="visual.classes"
      aria-hidden="true"
    >
      <component
        :is="visual.icon"
        :size="32"
        :class="visual.spin && 'animate-spin motion-reduce:animate-none'"
      />
    </span>
    <div class="flex max-w-md flex-col gap-2">
      <h2 class="text-xl font-bold text-fg">
        {{ title }}
      </h2>
      <p
        v-if="description"
        class="text-fg-muted"
      >
        {{ description }}
      </p>
      <slot />
    </div>
    <div
      v-if="$slots.actions"
      class="flex flex-wrap justify-center gap-2"
    >
      <slot name="actions" />
    </div>
  </div>
</template>
