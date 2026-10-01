<script setup lang="ts">
import { Pencil, Ticket } from '@lucide/vue'
import { updateSponsorship } from '~/services/billing'
import type { Sponsorship, SponsorshipMode } from '~/types/api/billing'

/**
 * Covered participation fees for a published competition (SCREENS W17 `SponsorshipCard`): mode and
 * cap, funded passes, the pass counters, status, unused passes and the voucher once issued. "Change"
 * updates the mode and cap while the server allows it (`sponsorship_locked` after funding).
 */
const props = defineProps<{ competitionId: string, sponsorship: Sponsorship, canManage: boolean }>()
const emit = defineEmits<{ updated: [sponsorship: Sponsorship] }>()

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const editing = ref(false)
const mode = ref<SponsorshipMode | null>(props.sponsorship.mode)
const cap = ref<number | null>(props.sponsorship.max_passes)
const busy = ref(false)
const error = ref<string | null>(null)

const counters = computed(() => (['pending', 'reserved', 'joined', 'released', 'unused', 'void', 'free_slots'] as const).map(key => ({
  key,
  value: props.sponsorship.counts[key],
})))

const modeOptions = computed(() => (['none', 'all', 'selected'] as const).map(value => ({
  value,
  label: t(`sponsorship.mode.${value}.title`),
  description: t(`sponsorship.mode.${value}.description`),
})))

function startEdit(): void {
  mode.value = props.sponsorship.mode
  cap.value = props.sponsorship.max_passes
  error.value = null
  editing.value = true
}

const capError = computed(() => (cap.value !== null && (cap.value < 1 || cap.value > 200) ? t('sponsorship.fees.cap_range', { min: 1, max: 200 }) : null))

async function save(): Promise<void> {
  if (!mode.value || capError.value) return
  busy.value = true
  error.value = null
  try {
    emit('updated', await updateSponsorship(props.competitionId, { mode: mode.value, max_passes: cap.value }))
    editing.value = false
    toast.success(t('sponsorship.fees.saved'))
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <UiCard :title="t('sponsorship.summary.title')">
    <template
      v-if="canManage && !editing && sponsorship.status !== 'settled'"
      #actions
    >
      <UiButton
        variant="secondary"
        size="sm"
        :icon="Pencil"
        @click="startEdit"
      >
        {{ t('sponsorship.summary.change') }}
      </UiButton>
    </template>

    <form
      v-if="editing"
      class="flex flex-col gap-4"
      novalidate
      @submit.prevent="save"
    >
      <UiRadioGroup
        v-model="mode"
        :options="modeOptions"
        :label="t('sponsorship.fees.mode_legend')"
      />
      <div
        v-if="mode !== 'none'"
        class="max-w-xs"
      >
        <CompetitionsWizardNumberField
          v-model="cap"
          :label="t('sponsorship.fees.cap_label')"
          :hint="t('sponsorship.fees.cap_hint')"
          :error="capError"
        />
      </div>
      <UiAlert
        v-if="error"
        tone="danger"
      >
        {{ error }}
      </UiAlert>
      <div class="flex justify-end gap-2">
        <UiButton
          variant="secondary"
          :disabled="busy"
          @click="editing = false"
        >
          {{ t('common.actions.cancel') }}
        </UiButton>
        <UiButton
          type="submit"
          :loading="busy"
        >
          {{ t('common.actions.save') }}
        </UiButton>
      </div>
    </form>

    <div
      v-else
      class="flex flex-col gap-4"
    >
      <div class="flex flex-wrap items-center gap-2">
        <UiBadge
          tone="info"
          :icon="Ticket"
        >
          {{ t(`sponsorship.mode.${sponsorship.mode}.title`) }}
        </UiBadge>
        <UiBadge tone="neutral">
          {{ t(`sponsorship.status.${sponsorship.status}`) }}
        </UiBadge>
        <span
          v-if="sponsorship.max_passes"
          class="text-sm text-fg-muted"
        >{{ t('sponsorship.summary.cap', { count: sponsorship.max_passes }) }}</span>
      </div>
      <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="flex flex-col">
          <dt class="text-xs text-fg-muted">
            {{ t('sponsorship.summary.funded') }}
          </dt>
          <dd class="text-lg font-bold text-fg tabular-nums">
            {{ sponsorship.funded_passes }}
          </dd>
        </div>
        <div
          v-for="counter in counters"
          :key="counter.key"
          class="flex flex-col"
        >
          <dt class="text-xs text-fg-muted">
            {{ counter.key === 'free_slots' ? t('sponsorship.summary.free_slots') : t(`sponsorship.pass_status.${counter.key}`) }}
          </dt>
          <dd class="text-lg font-bold text-fg tabular-nums">
            {{ counter.value }}
          </dd>
        </div>
      </dl>
      <p
        v-if="sponsorship.unused_count !== null"
        class="text-sm text-fg-muted"
      >
        {{ t('sponsorship.summary.unused', { count: sponsorship.unused_count }, sponsorship.unused_count) }}
      </p>
      <p
        v-if="sponsorship.voucher"
        class="flex flex-wrap items-center gap-2 text-sm text-fg"
      >
        {{ t('sponsorship.summary.voucher') }}
        <bdi class="font-semibold">{{ sponsorship.voucher.code }}</bdi>
        <UiCopyButton :value="sponsorship.voucher.code" />
      </p>
    </div>
  </UiCard>
</template>
