<script setup lang="ts">
import { X } from '@lucide/vue'

/** Side sheet on the native `<dialog>` element; `side="end"` is the left edge in Arabic. */
const props = withDefaults(defineProps<{
  title: string
  description?: string
  side?: 'start' | 'end'
  size?: 'sm' | 'md' | 'lg'
  dismissible?: boolean
  /** Hide the visible header (e.g. a navigation drawer); the title still names the dialog. */
  hideHeader?: boolean
}>(), {
  side: 'end',
  size: 'md',
  dismissible: true,
})

const open = defineModel<boolean>('open', { default: false })
const { t } = useI18n()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const titleId = useId()
const { onClose, onCancel, onBackdropClick } = useDialogElement(dialog, open, { dismissible: () => props.dismissible })

const sizeClasses = { sm: 'max-w-xs', md: 'max-w-md', lg: 'max-w-xl' } as const
</script>

<template>
  <dialog
    ref="dialog"
    :aria-labelledby="titleId"
    class="fixed inset-y-0 m-0 h-dvh max-h-none w-[88vw] bg-surface p-0 text-fg shadow-lg open:animate-fade-in"
    :class="[sizeClasses[size], side === 'end' ? 'ms-auto me-0 border-s border-line' : 'ms-0 me-auto border-e border-line']"
    @close="onClose"
    @cancel="onCancel"
    @click="onBackdropClick"
  >
    <div class="flex h-full flex-col">
      <header
        class="flex items-start justify-between gap-4 border-b border-line px-5 py-4"
        :class="hideHeader && 'sr-only'"
      >
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
          v-if="dismissible && !hideHeader"
          :icon="X"
          size="sm"
          :label="t('common.actions.close')"
          class="-me-2"
          @click="open = false"
        />
      </header>
      <div class="flex-1 overflow-y-auto">
        <slot :close="() => (open = false)" />
      </div>
      <footer
        v-if="$slots.footer"
        class="flex gap-2 border-t border-line px-5 py-4"
      >
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>
