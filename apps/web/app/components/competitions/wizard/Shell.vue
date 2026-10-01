<script setup lang="ts">
import { ArrowLeft, ArrowRight, Save } from '@lucide/vue'
import type { StepItem } from '~/types/ui'

/**
 * The creation wizard chrome (SCREENS W12/W15): the 8-step `UiStepper` (earlier steps are links), the
 * step heading, the content, an optional side column (rules summary), and a sticky footer with Back,
 * Save and Continue. Buttons show busy states and block double submits (S7).
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
  hideContinue?: boolean
  dirty?: boolean
}>(), {
  description: undefined,
  busy: false,
  canBack: true,
  showSave: false,
  saveDisabled: false,
  continueLabel: undefined,
  continueDisabled: false,
  hideContinue: false,
  dirty: false,
})

const emit = defineEmits<{ back: [], save: [], continue: [], select: [index: number] }>()
const { t } = useI18n()
const titleId = `wizard-step-${useId()}`
const stepperScroll = useTemplateRef<HTMLElement>('stepperScroll')

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
watch(() => props.current, () => void nextTick(revealCurrent))
const slots = defineSlots<{ default: () => unknown, aside?: () => unknown }>()
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiCard padding="sm">
      <!--
        Below `lg` the stepper shows "Step n of 8" with a progress bar. Between `lg` and `xl` (the
        sidebar takes its share) the eight steps still need more room than the card: the row scrolls.
      -->
      <div
        ref="stepperScroll"
        class="-m-1 overflow-x-auto p-1 [scrollbar-width:thin]"
      >
        <div class="lg:min-w-[48rem] xl:min-w-0">
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
            class="text-xl font-bold text-fg"
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
          v-if="dirty"
          class="text-sm text-fg-muted"
          role="status"
        >{{ t('common.unsaved_changes') }}</span>
        <div class="ms-auto flex flex-wrap items-center gap-2">
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
