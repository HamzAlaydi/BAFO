<script setup lang="ts">
import { Trophy, Undo2 } from '@lucide/vue'
import { revokeAward } from '~/services/bidding'
import type { Award } from '~/types/api/bidding'
import type { Direction } from '~/types/api/competitions'
import type { KeyValueItem } from '~/types/ui'

/**
 * The issued award (SCREENS W22 `AwardDetailCard`): winner (alias, organisation, CR and VAT), amount,
 * leading or not, rank at award, reserve met, justification, message to the winner, internal notes,
 * awarded by and at, ERP sync status and references, and the ledger hash. **Revoke award** asks for a
 * reason (5–1000) and returns the competition to evaluation.
 */
const props = defineProps<{ competitionId: string, direction: Direction, award: Award, canRevoke: boolean }>()
const emit = defineEmits<{ revoked: [award: Award] }>()

const { t } = useI18n()
const { td } = useDirectionCopy(() => props.direction)
const toast = useToast()
const { message } = useErrorMessage()
const date = useDate()
const money = useMoney()

const revokeOpen = ref(false)
const reason = ref('')
const busy = ref(false)
const error = ref<string | null>(null)
const attempted = ref(false)
const reasonError = computed(() => (attempted.value && reason.value.trim().length < 5 ? t('award.revoke.reason_short', { min: 5 }) : null))

const yesNo = (value: boolean | null) => (value === null ? '—' : value ? t('common.yes') : t('common.no'))

const items = computed<KeyValueItem[]>(() => {
  const a = props.award
  const list: KeyValueItem[] = [
    { key: 'winner', label: t('award.detail.winner'), value: `${t('offers.participant_alias', { number: a.participant.alias_no })} · ${a.participant.organization.name}` },
    { key: 'amount', label: t('award.detail.amount'), value: money.format(a.amount_minor), ltr: true },
    { key: 'leading', label: t('award.detail.leading'), value: yesNo(a.is_leading_offer) },
    { key: 'rank', label: t('award.detail.rank'), value: a.rank_at_award, ltr: true },
    { key: 'reserve', label: td('award.detail.reserve_met'), value: yesNo(a.reserve_met) },
  ]
  if (a.participant.organization.cr_number) list.push({ key: 'cr', label: t('award.detail.cr'), value: a.participant.organization.cr_number, ltr: true })
  if (a.participant.organization.vat_number) list.push({ key: 'vat', label: t('award.detail.vat'), value: a.participant.organization.vat_number, ltr: true })
  if (a.justification) list.push({ key: 'justification', label: t('award.detail.justification'), value: [a.justification.reason.name, a.justification.text].filter(Boolean).join(' — ') })
  if (a.message_to_winner) list.push({ key: 'message', label: t('award.detail.message'), value: a.message_to_winner })
  if (a.internal_notes) list.push({ key: 'notes', label: t('award.detail.notes'), value: a.internal_notes })
  list.push({ key: 'awarded', label: t('award.detail.awarded_by'), value: `${a.awarded_by?.name ?? '—'} · ${date.formatDateTime(a.awarded_at)}` })
  list.push({ key: 'erp', label: t('award.detail.erp'), value: t(`award.detail.erp_status.${a.erp_sync.status}`) })
  if (a.erp_sync.refs.length > 0) list.push({ key: 'refs', label: t('award.detail.erp_refs'), value: a.erp_sync.refs.map(ref => `${ref.system}:${ref.id}`).join(', '), ltr: true })
  if (a.ledger_head_hash) list.push({ key: 'hash', label: t('award.detail.ledger'), value: a.ledger_head_hash, ltr: true })
  return list
})

watch(revokeOpen, (value) => {
  if (!value) return
  reason.value = ''
  error.value = null
  attempted.value = false
}, { immediate: true })

async function revoke(): Promise<void> {
  attempted.value = true
  if (reasonError.value) return
  busy.value = true
  error.value = null
  try {
    const result = await revokeAward(props.competitionId, reason.value.trim())
    revokeOpen.value = false
    toast.success(t('award.revoke.done'))
    emit('revoked', result)
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
  <UiCard>
    <template #header>
      <div class="flex w-full flex-wrap items-center justify-between gap-3">
        <h3 class="flex items-center gap-2 text-base font-bold text-fg">
          <Trophy
            :size="20"
            class="text-brand"
            aria-hidden="true"
          />
          {{ t('award.detail.title') }}
        </h3>
        <UiButton
          v-if="canRevoke && award.status === 'issued'"
          variant="danger-ghost"
          size="sm"
          :icon="Undo2"
          flip-icons
          @click="revokeOpen = true"
        >
          {{ t('award.revoke.open') }}
        </UiButton>
      </div>
    </template>
    <UiKeyValueList :items="items" />

    <UiConfirmDialog
      v-model:open="revokeOpen"
      :title="t('award.revoke.title')"
      :description="t('award.revoke.description')"
      :confirm-label="t('award.revoke.confirm')"
      :cancel-label="t('award.revoke.keep')"
      :busy="busy"
      :error="error"
      danger
      @confirm="revoke"
    >
      <UiTextarea
        v-model="reason"
        :label="t('award.revoke.reason')"
        :error="reasonError"
        :maxlength="1000"
        :rows="3"
        required
      />
    </UiConfirmDialog>
  </UiCard>
</template>
