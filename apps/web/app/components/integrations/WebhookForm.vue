<script setup lang="ts">
import type { WebhookEndpointInput, WebhookEventType } from '~/types/api/integrations'

/**
 * Webhook endpoint fields (SCREENS W39 create, W40 edit): URL (https on a public address; the server's
 * SSRF guard decides, `webhook_url_invalid`), events or `*`, description.
 */
const props = withDefaults(defineProps<{
  eventTypes: WebhookEventType[]
  initial?: Partial<WebhookEndpointInput>
  submitLabel: string
  busy?: boolean
  serverErrors?: Record<string, string>
  formError?: string | null
}>(), {
  initial: () => ({}),
  serverErrors: () => ({}),
})

const emit = defineEmits<{ submit: [value: WebhookEndpointInput], dirty: [value: boolean] }>()
const { t } = useI18n()

const url = ref(props.initial.url ?? '')
const eventTypes = ref<string[]>([...(props.initial.event_types ?? ['*'])])
const description = ref(props.initial.description ?? '')
const submitted = ref(false)
/** Server field errors stop showing once the user edits that field. */
const edited = ref(new Set<string>())
watch(() => props.serverErrors, () => {
  edited.value = new Set()
})
watch(url, () => edited.value.add('url'))
watch(eventTypes, () => edited.value.add('event_types'))
const serverError = (field: string) => (edited.value.has(field) ? undefined : props.serverErrors[field])

const urlError = computed(() => {
  const server = serverError('url')
  if (server) return server
  if (!submitted.value) return null
  if (!url.value.trim()) return t('validation.required')
  return isWebhookUrlShape(url.value) ? null : t('validation.url_https')
})
const eventsError = computed(() => serverError('event_types') ?? (submitted.value && eventTypes.value.length === 0 ? t('integrations.webhooks.events.required') : null))

const dirty = computed(() => url.value !== (props.initial.url ?? '')
  || description.value !== (props.initial.description ?? '')
  || eventTypes.value.join(' ') !== [...(props.initial.event_types ?? ['*'])].join(' '))

watch(dirty, value => emit('dirty', value))

function onSubmit(): void {
  submitted.value = true
  if (urlError.value || eventsError.value || props.busy) return
  emit('submit', { url: url.value.trim(), event_types: eventTypes.value, description: description.value.trim() || null })
}
</script>

<template>
  <form
    class="flex flex-col gap-5"
    novalidate
    @submit.prevent="onSubmit"
  >
    <UiAlert
      v-if="formError"
      tone="danger"
      role="alert"
    >
      {{ formError }}
    </UiAlert>
    <UiInput
      v-model="url"
      type="url"
      inputmode="url"
      dir="ltr"
      :label="t('integrations.webhooks.fields.url')"
      :hint="t('integrations.webhooks.form.url_hint')"
      :error="urlError"
      :maxlength="1000"
      autocomplete="off"
      required
    />
    <IntegrationsEventTypePicker
      v-model="eventTypes"
      :event-types="props.eventTypes"
      :error="eventsError"
    />
    <UiTextarea
      v-model="description"
      :label="t('integrations.webhooks.fields.description')"
      :rows="2"
      :maxlength="500"
    />
    <div class="flex justify-end">
      <UiButton
        type="submit"
        :loading="busy"
      >
        {{ submitLabel }}
      </UiButton>
    </div>
  </form>
</template>
