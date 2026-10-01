<script setup lang="ts">
import type { Component } from 'vue'
import { Info, TriangleAlert, WifiOff, X } from '@lucide/vue'

/**
 * Full-width strip under the top bar (home alerts, offline, reconnecting). Never red: warnings use
 * the amber tone, information the blue tone (SCREENS §2.3, S2). Actions go in the `action` slot.
 */
const props = withDefaults(defineProps<{
  tone?: 'info' | 'warning' | 'neutral'
  icon?: Component
  dismissible?: boolean
  /** `alert` for connection problems; `status` for informational banners. */
  role?: 'status' | 'alert'
  offline?: boolean
}>(), {
  tone: 'info',
  role: 'status',
})

const emit = defineEmits<{ dismiss: [] }>()
const { t } = useI18n()

const toneClasses = {
  info: 'border-info/25 bg-info-soft text-info-soft-fg',
  warning: 'border-warning/40 bg-warning-soft text-warning-soft-fg',
  neutral: 'border-line bg-neutral-soft text-neutral-soft-fg',
} as const

const iconComponent = computed(() => props.icon ?? (props.offline ? WifiOff : props.tone === 'warning' ? TriangleAlert : Info))
</script>

<template>
  <div
    :role="role"
    class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b px-4 py-2.5 text-sm sm:px-6"
    :class="toneClasses[tone]"
  >
    <component
      :is="iconComponent"
      :size="18"
      class="shrink-0"
      aria-hidden="true"
    />
    <div class="min-w-0 flex-1 font-medium">
      <slot />
    </div>
    <div
      v-if="$slots.action"
      class="flex shrink-0 items-center gap-2"
    >
      <slot name="action" />
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="-me-1 inline-flex size-8 shrink-0 items-center justify-center rounded-sm opacity-80 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-ring"
      :aria-label="t('common.actions.dismiss')"
      @click="emit('dismiss')"
    >
      <X
        :size="16"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
