<script setup lang="ts">
import { Check, Circle, Eye, EyeOff } from '@lucide/vue'

/**
 * Password with show/hide and, for new passwords, the live rule checklist of ARCHITECTURE §13.9
 * (≥ 8 characters, lowercase, uppercase, digit, symbol; SCREENS S7).
 */
defineOptions({ inheritAttrs: false })

const { rootAttrs, controlAttrs } = useFieldAttrs()

const props = withDefaults(defineProps<{
  label: string
  hint?: string
  error?: string | null
  id?: string
  required?: boolean
  disabled?: boolean
  autocomplete?: 'current-password' | 'new-password'
  /** Show the rule checklist under the field. */
  checklist?: boolean
}>(), {
  autocomplete: 'current-password',
})

const model = defineModel<string>({ default: '' })
const { t } = useI18n()
const visible = ref(false)
const autoId = useId()
const inputId = computed(() => props.id ?? `password-${autoId}`)
const checklistId = computed(() => `${inputId.value}-rules`)

const rules = computed(() => {
  const checks = passwordChecks(model.value)
  return PASSWORD_RULES.map(rule => ({
    rule,
    met: checks[rule],
    label: t(`common.password.rules.${rule}`, { min: PASSWORD_MIN_LENGTH }),
  }))
})
</script>

<template>
  <UiField
    v-bind="rootAttrs()"
    :id="inputId"
    v-slot="{ describedby, invalid }"
    :label="label"
    :hint="hint"
    :error="error"
    :required="required"
  >
    <div
      class="flex h-11 items-center gap-2 rounded-md border bg-surface ps-3 pe-1 transition-colors focus-within:outline-2 focus-within:outline-offset-0 focus-within:outline-ring"
      :class="[invalid ? 'border-danger' : 'border-line-strong', disabled && 'cursor-not-allowed bg-surface-muted opacity-70']"
    >
      <input
        :id="inputId"
        v-model="model"
        v-bind="controlAttrs()"
        :type="visible ? 'text' : 'password'"
        :autocomplete="autocomplete"
        :required="required"
        :disabled="disabled"
        dir="ltr"
        spellcheck="false"
        autocapitalize="off"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy(describedby, checklist ? checklistId : undefined)"
        class="h-full min-w-0 flex-1 bg-transparent text-start text-fg outline-none focus-visible:outline-none disabled:cursor-not-allowed"
      >
      <UiIconButton
        :icon="visible ? EyeOff : Eye"
        size="sm"
        :label="visible ? t('common.password.hide') : t('common.password.show')"
        :disabled="disabled"
        @click="visible = !visible"
      />
    </div>
    <ul
      v-if="checklist"
      :id="checklistId"
      class="grid grid-cols-1 gap-x-4 gap-y-1 text-sm xs:grid-cols-2"
      :aria-label="t('common.password.checklist_label')"
    >
      <li
        v-for="item in rules"
        :key="item.rule"
        class="flex items-center gap-2"
        :class="item.met ? 'text-success-soft-fg' : 'text-fg-muted'"
      >
        <component
          :is="item.met ? Check : Circle"
          :size="14"
          class="shrink-0"
          aria-hidden="true"
        />
        <span>{{ item.label }}</span>
        <span class="sr-only">{{ item.met ? t('common.password.rule_met') : t('common.password.rule_not_met') }}</span>
      </li>
    </ul>
  </UiField>
</template>
