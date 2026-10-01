<script setup lang="ts">
import { Check } from '@lucide/vue'
import type { StepItem } from '~/types/ui'

/**
 * Wizard progress. Completed steps can be revisited when `navigable`; the current step is announced
 * with `aria-current="step"`. Collapses to "Step 2 of 4" with a progress bar below `collapseBelow`
 * (`sm` by default; long wizards such as the 8-step competition setup collapse below `lg`). The root
 * is positioned, so the visually hidden status texts are clipped with it inside scrolling containers.
 */
const props = withDefaults(defineProps<{
  steps: StepItem[]
  /** Zero-based index of the current step. */
  current: number
  navigable?: boolean
  collapseBelow?: 'sm' | 'md' | 'lg'
}>(), {
  navigable: false,
  collapseBelow: 'sm',
})

const emit = defineEmits<{ select: [index: number] }>()
const { t } = useI18n()

const currentStep = computed(() => props.steps[props.current])
const progress = computed(() => (props.steps.length > 0 ? ((props.current + 1) / props.steps.length) * 100 : 0))

// Static class names, so Tailwind generates them.
const COMPACT_VISIBLE = { sm: 'sm:hidden', md: 'md:hidden', lg: 'lg:hidden' } as const
const FULL_VISIBLE = { sm: 'hidden sm:flex', md: 'hidden md:flex', lg: 'hidden lg:flex' } as const

function state(index: number): 'complete' | 'current' | 'upcoming' {
  if (index < props.current) return 'complete'
  return index === props.current ? 'current' : 'upcoming'
}
</script>

<template>
  <nav
    class="relative"
    :aria-label="t('common.stepper.label')"
  >
    <!-- Compact: small screens -->
    <div
      class="flex flex-col gap-2"
      :class="COMPACT_VISIBLE[collapseBelow]"
    >
      <p class="text-sm text-fg-muted">
        {{ t('common.stepper.progress', { current: current + 1, total: steps.length }) }}
        <span class="font-bold text-fg">{{ currentStep?.label }}</span>
      </p>
      <div
        class="h-1.5 overflow-hidden rounded-full bg-surface-muted"
        aria-hidden="true"
      >
        <div
          class="h-full rounded-full bg-brand transition-[width] duration-300"
          :style="{ width: `${progress}%` }"
        />
      </div>
    </div>

    <!-- Full row -->
    <ol
      class="items-start"
      :class="FULL_VISIBLE[collapseBelow]"
    >
      <li
        v-for="(step, index) in steps"
        :key="step.key"
        class="flex flex-1 items-start gap-3 last:flex-none"
        :aria-current="state(index) === 'current' ? 'step' : undefined"
      >
        <component
          :is="navigable && state(index) === 'complete' ? 'button' : 'div'"
          :type="navigable && state(index) === 'complete' ? 'button' : undefined"
          class="flex min-w-0 items-start gap-3 rounded-md text-start focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          @click="navigable && state(index) === 'complete' && emit('select', index)"
        >
          <span
            class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold tabular-nums"
            :class="{
              'border-primary bg-primary text-primary-fg': state(index) === 'complete',
              'border-brand bg-surface text-fg': state(index) === 'current',
              'border-line bg-surface text-fg-muted': state(index) === 'upcoming',
            }"
          >
            <Check
              v-if="state(index) === 'complete'"
              :size="16"
              aria-hidden="true"
            />
            <span v-else>{{ index + 1 }}</span>
          </span>
          <span class="min-w-0 pt-1">
            <span
              class="block text-sm font-semibold"
              :class="state(index) === 'upcoming' ? 'text-fg-muted' : 'text-fg'"
            >{{ step.label }}</span>
            <span
              v-if="step.description"
              class="block text-xs text-fg-muted"
            >{{ step.description }}</span>
            <span
              v-if="state(index) === 'complete'"
              class="sr-only"
            >({{ t('common.stepper.completed') }})</span>
          </span>
        </component>
        <span
          v-if="index < steps.length - 1"
          class="mx-3 mt-4 h-0.5 flex-1 rounded-full"
          :class="state(index) === 'complete' ? 'bg-primary' : 'bg-line'"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>
