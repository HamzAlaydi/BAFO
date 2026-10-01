<script setup lang="ts">
import { CircleCheck } from '@lucide/vue'

/**
 * Reveal-once dialog (SCREENS W37–W40): a client secret, an API key or a webhook signing secret,
 * shown exactly once after create or rotate. The dialog cannot be dismissed until the user confirms
 * they stored the value; the parent drops the value when `done` fires. Optional non-secret
 * identifiers (the `client_id`) are shown with a copy button above it.
 */
const props = defineProps<{
  title: string
  description?: string
  secretLabel: string
  secret: string | null
  identifiers?: Array<{ label: string, value: string }>
}>()

const emit = defineEmits<{ done: [] }>()
const open = defineModel<boolean>('open', { default: false })
const { t } = useI18n()
const acknowledged = ref(false)

watch(() => props.secret, () => {
  acknowledged.value = false
})

function finish(): void {
  if (!acknowledged.value) return
  open.value = false
  acknowledged.value = false
  emit('done')
}
</script>

<template>
  <UiModal
    v-model:open="open"
    :title="title"
    :description="description"
    :dismissible="false"
    size="lg"
  >
    <div class="flex flex-col gap-5">
      <dl
        v-if="identifiers && identifiers.length > 0"
        class="flex flex-col gap-3"
      >
        <div
          v-for="item in identifiers"
          :key="item.label"
          class="flex flex-col gap-1.5"
        >
          <dt class="text-sm font-semibold text-fg">
            {{ item.label }}
          </dt>
          <dd class="flex items-center gap-2 rounded-md border border-line bg-surface-muted px-3 py-2">
            <code
              dir="ltr"
              class="min-w-0 flex-1 font-mono text-sm break-all text-fg"
            >{{ item.value }}</code>
            <UiCopyButton
              :value="item.value"
              :label="t('common.secret.copy', { label: item.label })"
            />
          </dd>
        </div>
      </dl>
      <UiSecretReveal
        v-if="secret"
        v-model:acknowledged="acknowledged"
        :label="secretLabel"
        :value="secret"
      />
    </div>
    <template #footer>
      <UiButton
        :icon="CircleCheck"
        :disabled="!acknowledged"
        @click="finish"
      >
        {{ t('integrations.secret.done') }}
      </UiButton>
    </template>
  </UiModal>
</template>
