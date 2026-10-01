<script setup lang="ts">
import type { Component } from 'vue'
import type { RouteLocationNamedI18n } from 'vue-router'
import { NuxtLinkLocale } from '#components'

/** A number with its label, optionally linking to the filtered list it counts (SCREENS W10). */
const props = defineProps<{
  label: string
  value: number | null | undefined
  icon?: Component
  to?: RouteLocationNamedI18n
  loading?: boolean
}>()

const tag = computed(() => (props.to ? NuxtLinkLocale : 'div'))
</script>

<template>
  <component
    :is="tag"
    :to="to"
    class="flex h-full flex-col gap-3 rounded-lg border border-line bg-surface p-4 shadow-xs transition-colors"
    :class="to && 'hover:border-line-strong/50 hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring'"
  >
    <span class="flex items-start justify-between gap-3">
      <span class="min-w-0 text-sm break-words hyphens-auto text-fg-muted">{{ label }}</span>
      <span
        v-if="icon"
        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary-soft text-primary-soft-fg"
        aria-hidden="true"
      >
        <component
          :is="icon"
          :size="18"
        />
      </span>
    </span>
    <UiSkeleton
      v-if="loading"
      class="mt-auto h-8 w-12"
    />
    <span
      v-else
      class="mt-auto text-3xl font-bold text-fg tabular-nums"
    >{{ value ?? '—' }}</span>
  </component>
</template>
