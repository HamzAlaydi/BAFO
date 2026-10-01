<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue'
import { OPT_IN_API_SCOPES, type ApiScope } from '~/types/api/integrations'

/**
 * `ScopeChecklist` (SCREENS W37, W38; ARCHITECTURE §14.4): every public-API scope grouped by
 * resource, with its code as an LTR island. `competitions:publish`, `competitions:manage` and
 * `webhooks:manage` are opt-in and carry a warning.
 */
defineProps<{
  error?: string | null
  disabled?: boolean
}>()

const model = defineModel<ApiScope[]>({ required: true })
const { t } = useI18n()
const errorId = useId()

const selected = computed(() => new Set(model.value))

function toggle(scope: ApiScope, value: boolean): void {
  const next = new Set(model.value)
  if (value) next.add(scope)
  else next.delete(scope)
  // Keep catalogue order so the request is stable.
  model.value = API_SCOPE_GROUPS.flatMap(group => group.scopes).filter(item => next.has(item))
}

const isOptIn = (scope: ApiScope) => OPT_IN_API_SCOPES.includes(scope)
</script>

<template>
  <fieldset
    class="flex min-w-0 flex-col gap-4"
    :aria-describedby="error ? errorId : undefined"
  >
    <legend class="mb-1 text-sm font-semibold text-fg">
      {{ t('integrations.scopes.label') }}
      <span
        class="text-danger"
        aria-hidden="true"
      >*</span>
    </legend>
    <p class="-mt-2 text-sm text-fg-muted">
      {{ t('integrations.scopes.hint') }}
    </p>
    <div
      v-for="group in API_SCOPE_GROUPS"
      :key="group.key"
      class="flex flex-col gap-3 rounded-md border border-line p-4"
    >
      <p class="text-sm font-bold text-fg">
        {{ t(`integrations.scopes.groups.${group.key}`) }}
      </p>
      <div
        v-for="scope in group.scopes"
        :key="scope"
        class="flex flex-col gap-1"
      >
        <UiCheckbox
          :model-value="selected.has(scope)"
          :label="t(`integrations.scopes.items.${scopeKey(scope)}`)"
          :disabled="disabled"
          @update:model-value="toggle(scope, $event)"
        />
        <p class="flex flex-wrap items-center gap-2 ps-8 text-xs text-fg-muted">
          <code
            dir="ltr"
            class="rounded-xs bg-surface-muted px-1.5 py-0.5 font-mono"
          >{{ scope }}</code>
          <span
            v-if="isOptIn(scope)"
            class="inline-flex items-center gap-1 font-semibold text-warning-soft-fg"
          >
            <TriangleAlert
              :size="14"
              aria-hidden="true"
            />
            {{ t('integrations.scopes.opt_in_warning') }}
          </span>
        </p>
      </div>
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
