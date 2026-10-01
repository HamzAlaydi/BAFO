<script setup lang="ts">
import type { Component } from 'vue'
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'

type AlertTone = 'info' | 'success' | 'warning' | 'danger'

const props = withDefaults(defineProps<{
  tone?: AlertTone
  title?: string
  dismissible?: boolean
  icon?: Component
}>(), {
  tone: 'info',
})

const emit = defineEmits<{ dismiss: [] }>()
const { t } = useI18n()

const tones: Record<AlertTone, { classes: string, icon: Component }> = {
  info: { classes: 'border-info/30 bg-info-soft text-info-soft-fg', icon: Info },
  success: { classes: 'border-success/30 bg-success-soft text-success-soft-fg', icon: CircleCheck },
  warning: { classes: 'border-warning/40 bg-warning-soft text-warning-soft-fg', icon: TriangleAlert },
  danger: { classes: 'border-danger/30 bg-danger-soft text-danger-soft-fg', icon: CircleAlert },
}

const current = computed(() => tones[props.tone])
</script>

<template>
  <div
    :role="tone === 'danger' || tone === 'warning' ? 'alert' : 'status'"
    class="flex items-start gap-3 rounded-md border p-4"
    :class="current.classes"
  >
    <component
      :is="icon ?? current.icon"
      :size="20"
      class="mt-0.5 shrink-0"
      aria-hidden="true"
    />
    <div class="min-w-0 flex-1 text-sm">
      <p
        v-if="title"
        class="font-bold"
      >
        {{ title }}
      </p>
      <div :class="title && 'mt-0.5'">
        <slot />
      </div>
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="-me-1 -mt-1 inline-flex size-7 shrink-0 items-center justify-center rounded-sm opacity-80 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-ring"
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
