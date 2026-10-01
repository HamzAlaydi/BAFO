<script setup lang="ts">
import { X } from '@lucide/vue'

/** Centred modal dialog on the native `<dialog>` element (top layer, focus containment, Esc). */
const props = withDefaults(defineProps<{
  title: string
  description?: string
  size?: 'sm' | 'md' | 'lg'
  /** Esc and backdrop clicks close the dialog. Turn off for flows that must be completed. */
  dismissible?: boolean
}>(), {
  size: 'md',
  dismissible: true,
})

const open = defineModel<boolean>('open', { default: false })
const { t } = useI18n()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const titleId = useId()
const { onClose, onCancel, onBackdropClick } = useDialogElement(dialog, open, { dismissible: () => props.dismissible })

const sizeClasses = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' } as const
</script>

<template>
  <dialog
    ref="dialog"
    :aria-labelledby="titleId"
    class="m-auto w-[calc(100%-2rem)] rounded-xl border border-line bg-surface p-0 text-fg shadow-lg open:animate-fade-in"
    :class="sizeClasses[size]"
    @close="onClose"
    @cancel="onCancel"
    @click="onBackdropClick"
  >
    <div class="flex max-h-[85dvh] flex-col">
      <header class="flex items-start justify-between gap-4 px-5 pt-5 sm:px-6">
        <div class="min-w-0">
          <h2
            :id="titleId"
            class="text-lg font-bold"
          >
            {{ title }}
          </h2>
          <p
            v-if="description"
            class="mt-1 text-sm text-fg-muted"
          >
            {{ description }}
          </p>
        </div>
        <UiIconButton
          v-if="dismissible"
          :icon="X"
          size="sm"
          :label="t('common.actions.close')"
          class="-me-2 -mt-1"
          @click="open = false"
        />
      </header>
      <div class="overflow-y-auto px-5 py-4 sm:px-6">
        <slot />
      </div>
      <footer
        v-if="$slots.footer"
        class="flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row sm:justify-end sm:px-6"
      >
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>
