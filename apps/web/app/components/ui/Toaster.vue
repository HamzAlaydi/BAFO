<script setup lang="ts">
import type { Component } from 'vue'
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'
import type { ToastVariant } from '~/composables/useToast'

/** Renders the `useToast()` queue. Mount once, in app.vue. */
const { toasts, dismiss } = useToast()
const { t } = useI18n()

const icons: Record<ToastVariant, Component> = {
  success: CircleCheck,
  error: CircleAlert,
  warning: TriangleAlert,
  info: Info,
}

const iconClasses: Record<ToastVariant, string> = {
  success: 'text-success',
  error: 'text-danger',
  warning: 'text-warning',
  info: 'text-info',
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:end-0 sm:items-end"
    aria-live="polite"
    aria-relevant="additions"
  >
    <TransitionGroup
      enter-from-class="opacity-0 translate-y-2"
      enter-active-class="transition duration-200 ease-out"
      leave-to-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :role="toast.variant === 'error' ? 'alert' : 'status'"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-line bg-surface p-4 text-fg shadow-lg"
      >
        <component
          :is="icons[toast.variant]"
          :size="20"
          class="mt-0.5 shrink-0"
          :class="iconClasses[toast.variant]"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p
            v-if="toast.title"
            class="text-sm font-bold"
          >
            {{ toast.title }}
          </p>
          <p class="text-sm text-fg-muted">
            {{ toast.message }}
          </p>
        </div>
        <UiIconButton
          :icon="X"
          size="sm"
          :label="t('common.actions.dismiss')"
          class="-me-1.5 -mt-1.5"
          @click="dismiss(toast.id)"
        />
      </div>
    </TransitionGroup>
  </div>
</template>
