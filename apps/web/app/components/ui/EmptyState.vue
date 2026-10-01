<script setup lang="ts">
import type { Component } from 'vue'
import { Inbox } from '@lucide/vue'

withDefaults(defineProps<{
  title: string
  description?: string
  icon?: Component
  compact?: boolean
}>(), {
  icon: () => Inbox,
})
</script>

<template>
  <div
    class="flex flex-col items-center justify-center text-center"
    :class="compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-12'"
  >
    <span
      class="flex items-center justify-center rounded-full bg-primary-soft text-primary-soft-fg"
      :class="compact ? 'size-10' : 'size-14'"
      aria-hidden="true"
    >
      <component
        :is="icon"
        :size="compact ? 20 : 26"
      />
    </span>
    <div class="max-w-sm">
      <p class="font-bold text-fg">
        {{ title }}
      </p>
      <p
        v-if="description"
        class="mt-1 text-sm text-fg-muted"
      >
        {{ description }}
      </p>
    </div>
    <div
      v-if="$slots.default"
      class="mt-2 flex flex-wrap justify-center gap-2"
    >
      <slot />
    </div>
  </div>
</template>
