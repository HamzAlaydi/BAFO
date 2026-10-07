<script setup lang="ts">
import { ArrowLeft, ArrowRight, Check, LoaderCircle, Save } from '@lucide/vue'
import type { StepItem } from '~/types/ui'

export type WizardAutosaveState = 'idle' | 'saving' | 'saved' | 'failed'

/**
 * The creation wizard chrome (SCREENS W12/W15; RELEASE_SCOPE.md §2.1): the 5-step `UiStepper` (earlier
 * steps are links), the step heading (focused on every step change, FQ9), the content, an optional
 * side column (rules summary), and a sticky footer with Back, Save and Continue, the autosave status
 * («حفظ تلقائي…» / «تم الحفظ 10:42», FQ6) and the reason whenever Continue is disabled (FQ7). Buttons
 * show busy states and block double submits (S7).
 */
const props = withDefaults(defineProps<{
  steps: StepItem[]
  current: number
  title: string
  description?: string
  busy?: boolean
  canBack?: boolean
  showSave?: boolean
  saveDisabled?: boolean
  continueLabel?: string
  continueDisabled?: boolean
  /** Why Continue is disabled, shown next to it (never a silent disabled button). */
  continueReason?: string
  hideContinue?: boolean
  dirty?: boolean
  autosave?: WizardAutosaveState
  /** UTC ISO of the last successful autosave, for «تم الحفظ {time}». */
  savedAt?: string | null
}>(), {
  description: undefined,
  busy: false,
  canBack: true,
  showSave: false,
  saveDisabled: false,
  continueLabel: undefined,
  continueDisabled: false,
  continueReason: undefined,
  hideContinue: false,
  dirty: false,
  autosave: 'idle',
  savedAt: null,
})

const emit = defineEmits<{ back: [], save: [], continue: [], select: [index: number] }>()
const { t } = useI18n()
const date = useDate()
const titleId = `wizard-step-${useId()}`
const stepperScroll = useTemplateRef<HTMLElement>('stepperScroll')
const heading = useTemplateRef<HTMLElement>('heading')

const autosaveText = computed(() => {
  if (props.autosave === 'saving') return t('common.autosave.saving')
  if (props.autosave === 'failed') return t('common.autosave.failed')
  if (props.autosave === 'saved' && props.savedAt) return t('common.autosave.saved_at', { time: date.formatTime(props.savedAt) })
  if (props.dirty) return t('common.unsaved_changes')
  return null
})

/**
 * Keeps the current step visible in the scrolling stepper row. Only the row scrolls (`scrollBy`);
 * `scrollIntoView` would also scroll every ancestor, the page included.
 */
function revealCurrent(): void {
  const scroller = stepperScroll.value
  const target = scroller?.querySelector<HTMLElement>('[aria-current="step"]')
  if (!scroller || !target) return
  const box = scroller.getBoundingClientRect()
  const item = target.getBoundingClientRect()
  if (item.left < box.left) scroller.scrollBy({ left: item.left - box.left - 16 })
  else if (item.right > box.right) scroller.scrollBy({ left: item.right - box.right + 16 })
}

onMounted(revealCurrent)
// A step change moves focus to the new heading (FQ9), so keyboard and screen-reader users land on it.
watch(() => props.current, () => void nextTick(() => {
  revealCurrent()
  heading.value?.focus({ preventScroll: false })
}))
const slots = defineSlots<{ default: () => unknown, aside?: () => unknown }>()
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiCard padding="sm">
      <!--
        Below `lg` the stepper shows "Step n of 5" with a progress bar. Five steps fit the card from
        `lg` up; the row still scrolls as a safety net for long step labels.
      -->
      <div
        ref="stepperScroll"
        class="-m-1 overflow-x-auto p-1 [scrollbar-width:thin]"
      >
        <div class="lg:min-w-[40rem] xl:min-w-0">
          <UiStepper
            :steps="steps"
            :current="current"
            navigable
            collapse-below="lg"
            @select="index => emit('select', index)"
          />
        </div>
      </div>
    </UiCard>

    <div
      class="grid gap-6"
      :class="slots.aside ? 'xl:grid-cols-[minmax(0,1fr)_20rem]' : ''"
    >
      <section
        class="flex min-w-0 flex-col gap-6"
        :aria-labelledby="titleId"
      >
        <header>
          <h2
            :id="titleId"
            ref="heading"
            class="rounded-md text-xl font-bold text-fg outline-none focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring"
            tabindex="-1"
          >
            {{ title }}
          </h2>
          <p
            v-if="description"
            class="mt-1 max-w-2xl text-fg-muted"
          >
            {{ description }}
          </p>
        </header>
        <slot />
      </section>
      <aside
        v-if="slots.aside"
        class="min-w-0"
      >
        <div class="xl:sticky xl:top-20">
          <UiCard padding="sm">
            <slot name="aside" />
          </UiCard>
        </div>
      </aside>
    </div>

    <div class="sticky bottom-2 z-20 rounded-lg border border-line bg-surface/95 px-4 py-3 shadow-md backdrop-blur">
      <div class="flex flex-wrap items-center gap-2">
        <UiButton
          v-if="canBack"
          variant="ghost"
          :icon="ArrowLeft"
          :disabled="busy"
          flip-icons
          @click="emit('back')"
        >
          {{ t('common.actions.back') }}
        </UiButton>
        <span
          v-if="autosaveText"
          class="inline-flex items-center gap-1.5 text-sm"
          :class="autosave === 'failed' ? 'text-danger' : 'text-fg-muted'"
          role="status"
          aria-live="polite"
        >
          <LoaderCircle
            v-if="autosave === 'saving'"
            :size="14"
            class="animate-spin"
            aria-hidden="true"
          />
          <Check
            v-else-if="autosave === 'saved'"
            :size="14"
            class="text-brand"
            aria-hidden="true"
          />
          {{ autosaveText }}
        </span>
        <div class="ms-auto flex flex-wrap items-center justify-end gap-2">
          <span
            v-if="!hideContinue && continueDisabled && continueReason"
            class="text-sm text-fg-muted"
            role="status"
          >{{ continueReason }}</span>
          <UiButton
            v-if="showSave"
            variant="secondary"
            :icon="Save"
            :disabled="busy || saveDisabled"
            @click="emit('save')"
          >
            {{ t('common.actions.save') }}
          </UiButton>
          <UiButton
            v-if="!hideContinue"
            :icon-end="ArrowRight"
            :loading="busy"
            :disabled="continueDisabled"
            flip-icons
            @click="emit('continue')"
          >
            {{ continueLabel ?? t('common.actions.continue') }}
          </UiButton>
        </div>
      </div>
    </div>
  </div>
</template>
