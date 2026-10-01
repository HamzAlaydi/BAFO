<script setup lang="ts" generic="T extends string">
/**
 * Radio cards on native radios (arrow keys, RTL order and form semantics come from the browser):
 * direction, format, presets and fee modes. Each card has a title, an optional description, optional
 * lines and an optional glyph slot. Disabled cards explain why in `disabledReason`.
 */
interface ChoiceCard<V extends string> {
  value: V
  title: string
  description?: string
  lines?: string[]
  disabled?: boolean
  disabledReason?: string
  badge?: string
}

const props = withDefaults(defineProps<{
  options: ChoiceCard<T>[]
  legend: string
  hint?: string
  error?: string | null
  columns?: 1 | 2 | 3
  disabled?: boolean
  /** Visually hide the legend (it stays the group's accessible name). */
  hideLegend?: boolean
}>(), {
  columns: 2,
})

const model = defineModel<T | null>({ default: null })
const name = `choice-${useId()}`
const hintId = computed(() => (props.hint ? `${name}-hint` : undefined))
const errorId = computed(() => (props.error ? `${name}-error` : undefined))

/**
 * Controlled radios: the parent may refuse a choice (for example until a confirmation), so the DOM
 * state is re-synced from the model after every change.
 */
function onChange(event: Event, value: T): void {
  model.value = value
  const input = event.target as HTMLInputElement
  void nextTick(() => {
    input.checked = model.value === value
    for (const radio of document.getElementsByName(name) as NodeListOf<HTMLInputElement>) {
      radio.checked = radio.value === model.value
    }
  })
}

const gridClass = computed(() => ({ 1: 'grid-cols-1', 2: 'grid-cols-1 sm:grid-cols-2', 3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' })[props.columns])
</script>

<template>
  <fieldset
    class="flex min-w-0 flex-col gap-3"
    :aria-describedby="describedBy(errorId, hintId)"
    :disabled="disabled"
  >
    <legend
      class="mb-1 text-sm font-bold text-fg"
      :class="hideLegend && 'sr-only'"
    >
      {{ legend }}
    </legend>
    <p
      v-if="hint"
      :id="hintId"
      class="-mt-2 text-sm text-fg-muted"
    >
      {{ hint }}
    </p>
    <div
      class="grid gap-3"
      :class="gridClass"
    >
      <label
        v-for="option in options"
        :key="option.value"
        class="relative flex cursor-pointer gap-3 rounded-lg border bg-surface p-4 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-ring"
        :class="[
          model === option.value ? 'border-primary bg-primary-soft/40 ring-1 ring-primary' : 'border-line hover:border-line-strong',
          (option.disabled || disabled) && 'cursor-not-allowed opacity-60',
        ]"
      >
        <input
          type="radio"
          class="mt-1 size-4 shrink-0 accent-primary"
          :name="name"
          :value="option.value"
          :checked="model === option.value"
          :disabled="option.disabled || disabled"
          @change="onChange($event, option.value)"
        >
        <span class="flex min-w-0 flex-1 flex-col gap-1">
          <span class="flex flex-wrap items-center gap-2">
            <slot
              name="glyph"
              :option="option"
            />
            <span class="font-bold text-fg">{{ option.title }}</span>
            <UiBadge
              v-if="option.badge"
              size="sm"
              tone="info"
            >
              {{ option.badge }}
            </UiBadge>
          </span>
          <span
            v-if="option.description"
            class="text-sm text-fg-muted"
          >{{ option.description }}</span>
          <ul
            v-if="option.lines?.length"
            class="mt-1 flex flex-wrap gap-1.5"
          >
            <li
              v-for="line in option.lines"
              :key="line"
              class="rounded-full bg-neutral-soft px-2 py-0.5 text-xs text-neutral-soft-fg"
            >
              {{ line }}
            </li>
          </ul>
          <span
            v-if="option.disabled && option.disabledReason"
            class="text-xs text-warning-soft-fg"
          >{{ option.disabledReason }}</span>
          <slot
            name="extra"
            :option="option"
          />
        </span>
      </label>
    </div>
    <p
      v-if="error"
      :id="errorId"
      class="text-sm text-danger"
      role="alert"
    >
      {{ error }}
    </p>
  </fieldset>
</template>
