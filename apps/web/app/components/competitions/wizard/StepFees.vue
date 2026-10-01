<script setup lang="ts">
import { fetchSponsorship, fetchSponsorshipQuote, updateSponsorship } from '~/services/billing'
import { listInvitations, updateInvitation } from '~/services/competitions'
import type { Sponsorship, SponsorshipMode, SponsorshipQuote } from '~/types/api/billing'
import type { Invitation, IssuerCompetition } from '~/types/api/competitions'
import type { TableColumn } from '~/types/ui'

/**
 * Wizard step 7 «رسوم المشاركة» (SCREENS W15 `fees`, R4, ARCHITECTURE §13.5), shown only when the
 * organisation and the platform allow covering fees (S10):
 *
 * - mode: none «يستخدم المتنافسون باقاتهم», all «تغطية الرسوم لجميع المدعوين», selected «تغطية الرسوم
 *   لمدعوين محددين» (saved at once with `PUT …/sponsorship`), and an optional cap (1–200);
 * - the server quote per invitation (coverage and reason), with a switch per row in `selected` mode
 *   (`PATCH …/invitations/{id} {sponsored}`);
 * - a coupon or voucher, and the order summary (passes to buy × unit price, discount, VAT, total).
 *
 * Money is only ever the server's quote; controls show the server state (no optimistic toggles).
 * Payment happens at publish (step 8, **Pay and publish**).
 */
const props = defineProps<{ competition: IssuerCompetition }>()
const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const editor = useCompetitionEditorStore()
const { message } = useErrorMessage()

const sponsorship = ref<Sponsorship | null>(null)
const quote = ref<SponsorshipQuote | null>(null)
const invitations = ref<Invitation[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const busy = ref(false)
const error = ref<string | null>(null)
const cap = ref<number | null>(null)
const rowBusy = ref<string | null>(null)

async function loadQuote(): Promise<void> {
  quote.value = await fetchSponsorshipQuote(props.competition.id, editor.couponCode)
}

async function load(): Promise<void> {
  loadError.value = null
  try {
    const [current, list] = await Promise.all([fetchSponsorship(props.competition.id), listInvitations(props.competition.id)])
    sponsorship.value = current
    invitations.value = list.invitations
    cap.value = current.max_passes
    await loadQuote()
  }
  catch (cause) {
    loadError.value = cause
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => editor.couponCode, async () => {
  try {
    await loadQuote()
  }
  catch (cause) {
    error.value = message(cause)
  }
})

const modeOptions = computed(() => (['none', 'all', 'selected'] as const).map(value => ({
  value,
  title: t(`sponsorship.mode.${value}.title`),
  description: t(`sponsorship.mode.${value}.description`),
})))

async function save(mode: SponsorshipMode, maxPasses: number | null): Promise<void> {
  busy.value = true
  error.value = null
  try {
    sponsorship.value = await updateSponsorship(props.competition.id, { mode, max_passes: mode === 'none' ? null : maxPasses })
    cap.value = sponsorship.value.max_passes
    await loadQuote()
    emit('changed')
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    busy.value = false
  }
}

/** Controlled by the server state: the cards change only after the `PUT` succeeds. */
const mode = computed<SponsorshipMode | null>({
  get: () => sponsorship.value?.mode ?? null,
  set: (value) => {
    if (value && value !== sponsorship.value?.mode && !busy.value) void save(value, cap.value)
  },
})

const capError = computed(() => (cap.value !== null && (cap.value < 1 || cap.value > 200) ? t('sponsorship.fees.cap_range', { min: 1, max: 200 }) : null))
const capDirty = computed(() => (sponsorship.value?.max_passes ?? null) !== cap.value)

async function toggleRow(invitationId: string, sponsored: boolean): Promise<void> {
  rowBusy.value = invitationId
  error.value = null
  try {
    const updated = await updateInvitation(props.competition.id, invitationId, { sponsored })
    invitations.value = invitations.value.map(item => (item.id === updated.id ? updated : item))
    await loadQuote()
    emit('changed')
  }
  catch (cause) {
    error.value = message(cause)
  }
  finally {
    rowBusy.value = null
  }
}

const requested = (invitationId: string | null) => invitations.value.find(item => item.id === invitationId)?.sponsored_requested ?? false

const columns = computed<TableColumn[]>(() => {
  const list: TableColumn[] = [
    { key: 'invitee', label: t('invitations.issuer.table.invitee'), primary: true },
    { key: 'coverage', label: t('invitations.issuer.table.coverage') },
    { key: 'reason', label: t('sponsorship.fees.reason_label') },
  ]
  if (sponsorship.value?.mode === 'selected') list.push({ key: 'cover', label: t('invitations.issuer.picker.cover_fees'), align: 'end' })
  return list
})

type QuoteLine = SponsorshipQuote['lines'][number]
const asLine = (row: unknown) => row as QuoteLine
</script>

<template>
  <div class="flex flex-col gap-6">
    <div
      v-if="loading"
      class="flex flex-col gap-3"
    >
      <UiSkeleton class="h-24 w-full" />
      <UiSkeleton class="h-40 w-full" />
    </div>
    <UiCard
      v-else-if="loadError"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        @retry="load"
      />
    </UiCard>
    <template v-else-if="sponsorship">
      <UiAlert
        v-if="!sponsorship.enabled"
        tone="warning"
      >
        {{ t('errors.sponsorship_not_enabled') }}
      </UiAlert>

      <CompetitionsWizardChoiceCards
        v-model="mode"
        :options="modeOptions"
        :legend="t('sponsorship.fees.mode_legend')"
        :columns="3"
        :disabled="busy || !sponsorship.enabled"
      />

      <p class="-mt-4 text-sm text-fg-muted">
        {{ t('sponsorship.fees.unit_price') }}
        <UiAmount
          :minor="sponsorship.unit_price_minor"
          class="font-semibold text-fg"
        />
        · {{ t('common.prices_exclude_vat') }}
      </p>

      <div
        v-if="sponsorship.mode !== 'none'"
        class="flex flex-wrap items-end gap-3"
      >
        <div class="w-full max-w-xs">
          <CompetitionsWizardNumberField
            v-model="cap"
            :label="t('sponsorship.fees.cap_label')"
            :hint="t('sponsorship.fees.cap_hint')"
            :error="capError"
            :disabled="busy"
          />
        </div>
        <UiButton
          variant="secondary"
          :loading="busy"
          :disabled="!capDirty || Boolean(capError)"
          @click="save(sponsorship.mode, cap)"
        >
          {{ t('sponsorship.fees.cap_apply') }}
        </UiButton>
      </div>

      <UiAlert
        v-if="error"
        tone="danger"
      >
        {{ error }}
      </UiAlert>

      <template v-if="sponsorship.mode !== 'none' && quote">
        <UiTable
          :columns="columns"
          :rows="quote.lines"
          :row-key="row => row.invitation_id ?? row.email"
          :caption="t('sponsorship.fees.table_caption')"
          :empty-title="t('sponsorship.fees.no_invitations')"
        >
          <template #cell-invitee="{ row }">
            <span class="flex min-w-0 flex-col">
              <bdi class="truncate font-semibold text-fg">{{ asLine(row).email }}</bdi>
              <span
                v-if="asLine(row).organization_name"
                class="text-xs text-fg-muted"
              >{{ asLine(row).organization_name }}</span>
            </span>
          </template>
          <template #cell-coverage="{ row }">
            <CompetitionsIssuerCoverageChip :coverage="asLine(row).coverage" />
          </template>
          <template #cell-reason="{ row }">
            {{ asLine(row).reason ? t(`sponsorship.fees.reasons.${asLine(row).reason}`) : '—' }}
          </template>
          <template #cell-cover="{ row }">
            <UiSwitch
              v-if="asLine(row).invitation_id"
              :model-value="requested(asLine(row).invitation_id)"
              :label="t('invitations.issuer.picker.cover_fees')"
              :disabled="rowBusy !== null"
              @update:model-value="value => toggleRow(asLine(row).invitation_id ?? '', value)"
            />
          </template>
        </UiTable>

        <div class="grid gap-6 md:grid-cols-2">
          <UiCard
            :title="t('sponsorship.fees.coupon.title')"
            padding="sm"
          >
            <BillingCouponField
              v-model:code="editor.couponCode"
              :context="{ purpose: 'sponsorship', competition_id: competition.id }"
            />
          </UiCard>
          <UiCard
            :title="t('sponsorship.fees.summary.title')"
            padding="sm"
          >
            <CompetitionsIssuerQuoteSummary :quote="quote" />
            <p
              v-if="quote.passes_to_buy === 0"
              class="mt-3 text-sm text-fg-muted"
            >
              {{ t('sponsorship.fees.nothing_to_buy') }}
            </p>
          </UiCard>
        </div>
      </template>

      <p class="rounded-md bg-surface-muted p-4 text-sm text-fg-muted">
        {{ t('sponsorship.fees.policy') }}
      </p>
    </template>
  </div>
</template>
