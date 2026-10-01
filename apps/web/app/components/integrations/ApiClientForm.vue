<script setup lang="ts">
import type { ApiClientInput, ApiScope } from '~/types/api/integrations'

/**
 * API client fields (SCREENS W37 create, W38 edit): name, description and scopes. Validation mirrors
 * the API for feedback only (name required, ≤ 120; at least one scope); server field errors come in
 * through `serverErrors` by path.
 */
const props = withDefaults(defineProps<{
  initial?: Partial<ApiClientInput>
  submitLabel: string
  busy?: boolean
  disabled?: boolean
  serverErrors?: Record<string, string>
  formError?: string | null
}>(), {
  initial: () => ({}),
  serverErrors: () => ({}),
})

const emit = defineEmits<{ submit: [value: ApiClientInput], dirty: [value: boolean] }>()
const { t } = useI18n()

const name = ref(props.initial.name ?? '')
const description = ref(props.initial.description ?? '')
const scopes = ref<ApiScope[]>([...(props.initial.scopes ?? DEFAULT_API_SCOPES)])
const submitted = ref(false)
/** Server field errors stop showing once the user edits that field. */
const edited = ref(new Set<string>())
watch(() => props.serverErrors, () => {
  edited.value = new Set()
})
watch(name, () => edited.value.add('name'))
watch(scopes, () => edited.value.add('scopes'))
const serverError = (field: string) => (edited.value.has(field) ? undefined : props.serverErrors[field])

const nameError = computed(() => {
  const server = serverError('name')
  if (server) return server
  if (!submitted.value) return null
  if (!name.value.trim()) return t('validation.required')
  return name.value.trim().length > 120 ? t('validation.max_length', { max: 120 }) : null
})
const scopesError = computed(() => serverError('scopes') ?? (submitted.value && scopes.value.length === 0 ? t('integrations.scopes.required') : null))

const dirty = computed(() => name.value !== (props.initial.name ?? '')
  || description.value !== (props.initial.description ?? '')
  || scopes.value.join(' ') !== [...(props.initial.scopes ?? DEFAULT_API_SCOPES)].join(' '))

watch(dirty, value => emit('dirty', value))

function onSubmit(): void {
  submitted.value = true
  if (nameError.value || scopesError.value || props.busy) return
  emit('submit', { name: name.value.trim(), description: description.value.trim() || null, scopes: scopes.value })
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
      v-model="name"
      :label="t('integrations.clients.fields.name')"
      :hint="t('integrations.clients.form.name_hint')"
      :error="nameError"
      :maxlength="120"
      :disabled="disabled"
      required
    />
    <UiTextarea
      v-model="description"
      :label="t('integrations.clients.fields.description')"
      :rows="2"
      :maxlength="500"
      :disabled="disabled"
    />
    <IntegrationsScopeChecklist
      v-model="scopes"
      :error="scopesError"
      :disabled="disabled"
    />
    <div class="flex justify-end">
      <UiButton
        type="submit"
        :loading="busy"
        :disabled="disabled"
      >
        {{ submitLabel }}
      </UiButton>
    </div>
  </form>
</template>
