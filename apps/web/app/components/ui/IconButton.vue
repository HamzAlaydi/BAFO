<script setup lang="ts">
import type { Component } from 'vue'
import type { RouteLocationNamedI18n } from 'vue-router'
import { NuxtLinkLocale } from '#components'
import type { ControlSize } from '~/types/ui'

type IconButtonVariant = 'ghost' | 'secondary' | 'primary' | 'danger-ghost'

const props = withDefaults(defineProps<{
  icon: Component
  /** Accessible name; also shown as the native tooltip. */
  label: string
  variant?: IconButtonVariant
  size?: ControlSize
  type?: 'button' | 'submit' | 'reset'
  to?: RouteLocationNamedI18n
  disabled?: boolean
  /** Mirror directional icons (arrows, chevrons) in RTL. */
  flipIcon?: boolean
}>(), {
  variant: 'ghost',
  size: 'md',
  type: 'button',
})

const variantClasses: Record<IconButtonVariant, string> = {
  'ghost': 'text-fg-muted hover:bg-surface-muted hover:text-fg',
  'secondary': 'border border-line bg-surface text-fg shadow-xs hover:bg-surface-muted',
  'primary': 'bg-primary text-primary-fg hover:bg-primary-hover',
  'danger-ghost': 'text-danger hover:bg-danger-soft',
}

const sizeClasses: Record<ControlSize, string> = { sm: 'size-8', md: 'size-10', lg: 'size-11' }
const iconSize: Record<ControlSize, number> = { sm: 16, md: 20, lg: 22 }

const classes = computed(() => [
  'relative inline-flex shrink-0 items-center justify-center rounded-md transition-colors duration-150',
  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.disabled && 'pointer-events-none opacity-55',
])
</script>

<template>
  <NuxtLinkLocale
    v-if="to && !disabled"
    :to="to"
    :aria-label="label"
    :title="label"
    :class="classes"
  >
    <component
      :is="icon"
      :size="iconSize[size]"
      :class="flipIcon && 'rtl:-scale-x-100'"
      aria-hidden="true"
    />
    <slot />
  </NuxtLinkLocale>
  <button
    v-else
    :type="type"
    :disabled="disabled"
    :aria-label="label"
    :title="label"
    :class="classes"
  >
    <component
      :is="icon"
      :size="iconSize[size]"
      :class="flipIcon && 'rtl:-scale-x-100'"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
