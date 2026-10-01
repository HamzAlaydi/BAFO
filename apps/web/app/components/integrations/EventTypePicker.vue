<script setup lang="ts">
import type { WebhookEventType } from '~/types/api/integrations'

/**
 * `EventTypePicker` (SCREENS W39, W40): every catalogue event (`GET …/webhook-event-types`, localised
 * descriptions), or `*` for all events including those added later.
 */
const props = defineProps<{
  eventTypes: WebhookEventType[]
  error?: string | null
  disabled?: boolean
}>()

const model = defineModel<string[]>({ required: true })
const { t } = useI18n()
const errorId = useId()

const all = computed(() => model.value.includes('*'))
const selected = computed(() => new Set(model.value))

function setAll(value: boolean): void {
  model.value = value ? ['*'] : []
}

function toggle(type: string, value: boolean): void {
  const next = new Set(model.value.filter(item => item !== '*'))
  if (value) next.add(type)
  else next.delete(type)
  model.value = props.eventTypes.map(item => item.type).filter(item => next.has(item))
}
</script>

<template>
  <fieldset
    class="flex min-w-0 flex-col gap-3"
    :aria-describedby="error ? errorId : undefined"
  >
    <legend class="mb-1 text-sm font-semibold text-fg">
      {{ t('integrations.webhooks.events.label') }}
      <span
        class="text-danger"
        aria-hidden="true"
      >*</span>
    </legend>
    <UiCheckbox
      :model-value="all"
      :label="t('integrations.webhooks.events.all_label')"
      :description="t('integrations.webhooks.events.all_hint')"
      :disabled="disabled"
      @update:model-value="setAll"
    />
    <div
      v-if="!all"
      class="flex flex-col gap-3 rounded-md border border-line p-4"
    >
      <p class="text-sm text-fg-muted">
        {{ t('integrations.webhooks.events.pick_hint') }}
      </p>
      <UiCheckbox
        v-for="eventType in eventTypes"
        :key="eventType.type"
        :model-value="selected.has(eventType.type)"
        :label="eventType.description"
        :description="eventType.type"
        :disabled="disabled"
        @update:model-value="toggle(eventType.type, $event)"
      />
    </div>
    <p
      v-if="error"
      :id="errorId"
      class="text-sm font-medium text-danger"
      role="alert"
    >
      {{ error }}
    </p>
  </fieldset>
</template>
