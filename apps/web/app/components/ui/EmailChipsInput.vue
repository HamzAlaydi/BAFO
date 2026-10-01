<script setup lang="ts">
import { CircleAlert, X } from '@lucide/vue'

/**
 * E-mail chips input (SCREENS §4.2; the invite picker): typing a
 * separator (comma, space, Enter, semicolon) or pasting a list splits it into chips; each chip is
 * validated on its own (invalid chips use the danger-soft tone with an icon and a text marker).
 */
const props = defineProps<{
  label: string
  hint?: string
  error?: string | null
  disabled?: boolean
}>()

const model = defineModel<string[]>({ default: () => [] })
const { t } = useI18n()
const id = `emails-${useId()}`
const text = ref('')
const invalidCount = computed(() => model.value.filter(email => !isEmail(email)).length)

function commit(raw: string): void {
  const next = splitEmailList(raw).filter(email => !model.value.includes(email))
  if (next.length > 0) model.value = [...model.value, ...next]
  text.value = ''
}

function onKeydown(event: KeyboardEvent): void {
  if (['Enter', ',', ';', ' ', 'Tab'].includes(event.key) && text.value.trim() !== '') {
    if (event.key !== 'Tab') event.preventDefault()
    commit(text.value)
  }
  else if (event.key === 'Backspace' && text.value === '' && model.value.length > 0) {
    model.value = model.value.slice(0, -1)
  }
}

const SEPARATOR = /[\s,;،]/

/**
 * Separators typed as text also split the chips: virtual keyboards (Android) and input methods do
 * not always send a usable `keydown` for a comma or a space.
 */
function onInput(): void {
  if (!SEPARATOR.test(text.value)) return
  const endsWithSeparator = SEPARATOR.test(text.value.slice(-1))
  const parts = text.value.split(/[\s,;،]+/)
  const rest = endsWithSeparator ? '' : parts.pop() ?? ''
  commit(parts.join(' '))
  text.value = rest
}

function onPaste(event: ClipboardEvent): void {
  const pasted = event.clipboardData?.getData('text') ?? ''
  if (!pasted) return
  event.preventDefault()
  commit(`${text.value} ${pasted}`)
}

function remove(email: string): void {
  model.value = model.value.filter(item => item !== email)
}

const describedby = computed(() => describedBy(props.error && `${id}-error`, props.hint && `${id}-hint`, invalidCount.value > 0 && `${id}-invalid`))
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label
      :for="id"
      class="text-sm font-semibold text-fg"
    >{{ label }}</label>
    <div
      class="flex min-h-11 flex-wrap items-center gap-1.5 rounded-md border bg-surface px-2 py-1.5 focus-within:outline-2 focus-within:outline-ring"
      :class="error ? 'border-danger' : 'border-line-strong'"
    >
      <span
        v-for="email in model"
        :key="email"
        class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-sm"
        :class="isEmail(email) ? 'bg-neutral-soft text-neutral-soft-fg' : 'bg-danger-soft text-danger-soft-fg'"
      >
        <CircleAlert
          v-if="!isEmail(email)"
          :size="14"
          aria-hidden="true"
        />
        <bdi class="truncate">{{ email }}</bdi>
        <span
          v-if="!isEmail(email)"
          class="sr-only"
        >{{ t('invitations.issuer.picker.invalid_email') }}</span>
        <button
          type="button"
          class="inline-flex size-5 items-center justify-center rounded-full hover:bg-surface-muted focus-visible:outline-2 focus-visible:outline-ring"
          :aria-label="t('common.combobox.remove', { label: email })"
          :disabled="disabled"
          @click="remove(email)"
        >
          <X
            :size="12"
            aria-hidden="true"
          />
        </button>
      </span>
      <input
        :id="id"
        v-model="text"
        type="text"
        dir="ltr"
        inputmode="email"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        class="h-8 min-w-40 flex-1 bg-transparent px-1 text-fg outline-none"
        :disabled="disabled"
        :aria-invalid="Boolean(error) || invalidCount > 0 || undefined"
        :aria-describedby="describedby"
        @keydown="onKeydown"
        @input="onInput"
        @paste="onPaste"
        @blur="text.trim() && commit(text)"
      >
    </div>
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="text-sm text-fg-muted"
    >
      {{ hint }}
    </p>
    <p
      v-if="invalidCount > 0"
      :id="`${id}-invalid`"
      class="text-sm text-danger"
    >
      {{ t('invitations.issuer.picker.invalid_count', { count: invalidCount }, invalidCount) }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="text-sm text-danger"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
