<script setup lang="ts">
import type { Component } from 'vue'
import type { RouteLocationNamedI18n } from 'vue-router'
import { LoaderCircle } from '@lucide/vue'
import { NuxtLinkLocale } from '#components'
import type { ButtonVariant, ControlSize } from '~/types/ui'

const props = withDefaults(defineProps<{
  variant?: ButtonVariant
  size?: ControlSize
  type?: 'button' | 'submit' | 'reset'
  /** Internal route (localised automatically). */
  to?: RouteLocationNamedI18n
  /** External URL. */
  href?: string
  loading?: boolean
  disabled?: boolean
  block?: boolean
  icon?: Component
  iconEnd?: Component
  /** Mirror directional icons (arrows, chevrons) in RTL. */
  flipIcons?: boolean
}>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
})

const { t } = useI18n()
const isDisabled = computed(() => props.disabled || props.loading)

const variantClasses: Record<ButtonVariant, string> = {
  'primary': 'bg-primary text-primary-fg shadow-xs hover:bg-primary-hover',
  'secondary': 'border border-line bg-surface text-fg shadow-xs hover:bg-surface-muted',
  'ghost': 'text-fg hover:bg-surface-muted',
  'danger': 'bg-danger text-danger-fg shadow-xs hover:bg-danger-hover',
  'danger-ghost': 'text-danger hover:bg-danger-soft',
  'link': 'text-link underline-offset-4 hover:underline',
}

const sizeClasses: Record<ControlSize, string> = {
  sm: 'h-9 gap-1.5 px-3 text-sm',
  md: 'h-11 gap-2 px-4 text-[0.9375rem]',
  lg: 'h-12 gap-2 px-5 text-base',
}

const iconSize: Record<ControlSize, number> = { sm: 16, md: 18, lg: 20 }

const classes = computed(() => [
  'inline-flex shrink-0 select-none items-center justify-center rounded-md font-bold whitespace-nowrap transition-colors duration-150',
  'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
  variantClasses[props.variant],
  props.variant === 'link' ? 'h-auto px-0' : sizeClasses[props.size],
  props.block && 'w-full',
  isDisabled.value && 'pointer-events-none opacity-55',
])

const tag = computed(() => {
  if (props.to && !isDisabled.value) return NuxtLinkLocale
  if (props.href && !isDisabled.value) return 'a'
  return 'button'
})

const bindings = computed(() => {
  if (tag.value === NuxtLinkLocale) return { to: props.to }
  if (tag.value === 'a') return { href: props.href, rel: 'noopener noreferrer', target: '_blank' }
  return {
    'type': props.type,
    'disabled': isDisabled.value,
    'aria-busy': props.loading || undefined,
  }
})
</script>

<template>
  <component
    :is="tag"
    v-bind="bindings"
    :class="classes"
  >
    <LoaderCircle
      v-if="loading"
      :size="iconSize[size]"
      class="animate-spin"
      aria-hidden="true"
    />
    <component
      :is="icon"
      v-else-if="icon"
      :size="iconSize[size]"
      :class="flipIcons && 'rtl:-scale-x-100'"
      aria-hidden="true"
    />
    <slot />
    <span
      v-if="loading"
      class="sr-only"
    >{{ t('common.loading') }}</span>
    <component
      :is="iconEnd"
      v-if="iconEnd"
      :size="iconSize[size]"
      :class="flipIcons && 'rtl:-scale-x-100'"
      aria-hidden="true"
    />
  </component>
</template>
