<script setup lang="ts">
import type { BillingInterval } from '~/types/api/billing'
import type { ChoiceOption } from '~/types/ui'

/** Monthly / annual switch (SCREENS W31, W32), on native radios. */
withDefaults(defineProps<{ size?: 'sm' | 'md', disabled?: boolean }>(), { size: 'md' })

const model = defineModel<BillingInterval>({ required: true })
const { t } = useI18n()

const options = computed<ChoiceOption<BillingInterval>[]>(() => BILLING_INTERVALS.map(value => ({
  value,
  label: t(`billing.intervals.${value}`),
})))

const selected = computed<BillingInterval | null>({
  get: () => model.value,
  set: (value) => {
    if (value) model.value = value
  },
})
</script>

<template>
  <UiSegmented
    v-model="selected"
    :options="options"
    :label="t('billing.intervals.label')"
    :size="size"
    :disabled="disabled"
  />
</template>
