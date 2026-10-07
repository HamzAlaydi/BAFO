<script setup lang="ts">
import { CreditCard, Send } from '@lucide/vue'
import { checkoutReturnUrl, checkoutSponsorship } from '~/services/billing'
import { createInvitations } from '~/services/competitions'
import type { Payment, SponsorshipMode, SponsorshipQuote } from '~/types/api/billing'
import type { Invitation, IssuerCompetition } from '~/types/api/competitions'
import { stagedRowErrors, stagedToInput, type StagedInvitation } from '~/stores/competition-editor-invitations'

/**
 * "Invite more" after publishing (SCREENS W17 `InviteDrawer`): the step-6 picker (with a "cover fees"
 * switch per row in `selected` mode), then `POST …/invitations`:
 *
 * - 201 → the rows are sent;
 * - 409 `sponsorship_payment_required` → the server quote with **Pay and send**
 *   (`POST …/sponsorship/checkout {intent: invite}` → hosted payment) and, in `selected` mode,
 *   **Send without covering fees** (re-post with `sponsored: false`);
 * - `invitation_cutoff_passed`, `max_participants_exceeded` and per-row errors as in step 6.
 */
const props = defineProps<{
  competition: IssuerCompetition
  existing: Invitation[]
  sponsorshipMode: SponsorshipMode
}>()
const emit = defineEmits<{ created: [invitations: Invitation[]] }>()
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const features = useFeatures()
const locale = useAppLocale()
const { message } = useErrorMessage()

const staged = ref<StagedInvitation[]>([])
const sending = ref(false)
const formError = ref<string | null>(null)
const quote = ref<SponsorshipQuote | null>(null)
const couponCode = ref<string | null>(null)
const paying = ref(false)
const payment = ref<Payment | null>(null)
let checkoutKey: string | null = null

const selectedMode = computed(() => props.sponsorshipMode === 'selected')
const canPurchase = computed(() => auth.can('billing.purchase'))
const sendable = computed(() => staged.value.filter(row => row.kind !== 'email' || isEmail(row.email ?? '')))

watch(open, (value) => {
  if (value) return
  staged.value = []
  formError.value = null
  quote.value = null
  payment.value = null
  checkoutKey = null
})

// A different set of rows is a different payment intent (new idempotency key).
watch(staged, () => {
  quote.value = null
  payment.value = null
  checkoutKey = null
}, { deep: true })

function applyRowErrors(error: ApiError, rows: StagedInvitation[]): void {
  const mapped = stagedRowErrors(error, rows.length)
  const byKey = new Map(rows.map((row, index) => [row.key, mapped.rows[index] ?? null]))
  staged.value = staged.value.map((row) => {
    const problem = byKey.get(row.key)
    return problem ? { ...row, error: problem.message, errorCode: problem.code } : row
  })
  formError.value = mapped.unmatched[0] ?? t('invitations.issuer.rows_rejected')
}

async function send(coverFees = true): Promise<void> {
  const rows = sendable.value
  if (rows.length === 0) return
  sending.value = true
  formError.value = null
  try {
    const body = coverFees ? stagedToInput(rows, selectedMode.value) : stagedToInput(rows.map(row => ({ ...row, sponsored: false })), true)
    const created = await createInvitations(props.competition.id, body)
    emit('created', created)
    toast.success(t('invitations.issuer.toasts.sent', { count: created.length }, created.length))
    open.value = false
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'sponsorship_payment_required') {
      quote.value = (error.details.quote as SponsorshipQuote | undefined) ?? null
      if (!quote.value) formError.value = message(error)
    }
    else if (error instanceof ApiError && error.isValidation) {
      applyRowErrors(error, rows)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    sending.value = false
  }
}

async function payAndSend(): Promise<void> {
  const rows = sendable.value
  if (rows.length === 0) return
  paying.value = true
  formError.value = null
  checkoutKey ??= uuidv4()
  try {
    payment.value = await checkoutSponsorship(props.competition.id, {
      intent: 'invite',
      invitations: stagedToInput(rows, false),
      coupon_code: couponCode.value,
      return_url: checkoutReturnUrl(window.location.origin, locale.value),
    }, checkoutKey)
    if (!payment.value.redirect_url && payment.value.status === 'succeeded') {
      toast.success(t('invitations.issuer.toasts.paid_and_sent'))
      emit('created', [])
      open.value = false
    }
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'sponsorship_already_funded') {
      await send(true)
    }
    else if (error instanceof ApiError && error.isValidation && Object.keys(error.errors).some(path => path.startsWith('invitations.'))) {
      applyRowErrors(error, rows)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    paying.value = false
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    :title="t('invitations.issuer.drawer.title')"
    :description="t('invitations.issuer.drawer.description')"
    size="lg"
  >
    <div class="flex flex-col gap-5 p-5 sm:p-6">
      <template v-if="!payment">
        <CompetitionsIssuerInvitePicker
          v-model="staged"
          :competition-id="competition.id"
          :existing="existing"
          :sponsored-selectable="selectedMode && features.enabled('sponsorship')"
          :category-id="competition.category?.id ?? null"
          :region-id="competition.region?.id ?? null"
        />
        <UiAlert
          v-if="formError"
          tone="danger"
        >
          {{ formError }}
        </UiAlert>

        <UiCard
          v-if="quote"
          :title="t('invitations.issuer.drawer.payment_title')"
          :description="t('invitations.issuer.drawer.payment_body')"
        >
          <div class="flex flex-col gap-4">
            <CompetitionsIssuerQuoteSummary :quote="quote" />
            <BillingCouponField
              v-model:code="couponCode"
              :context="{ purpose: 'sponsorship', competition_id: competition.id }"
            />
            <UiAlert
              v-if="!canPurchase"
              tone="warning"
            >
              {{ t('sponsorship.fees.purchase_permission') }}
            </UiAlert>
          </div>
          <template #footer>
            <UiButton
              v-if="selectedMode"
              variant="secondary"
              :loading="sending"
              :disabled="paying"
              @click="send(false)"
            >
              {{ t('invitations.issuer.drawer.send_without_fees') }}
            </UiButton>
            <UiButton
              v-if="canPurchase"
              :icon="CreditCard"
              :loading="paying"
              :disabled="sending"
              @click="payAndSend"
            >
              {{ t('invitations.issuer.drawer.pay_and_send') }}
            </UiButton>
          </template>
        </UiCard>
      </template>

      <CompetitionsIssuerCheckoutPanel
        v-else
        :payment="payment"
        @cancel="payment = null"
      />
    </div>
    <template
      v-if="!payment && !quote"
      #footer
    >
      <UiButton
        variant="secondary"
        :disabled="sending"
        @click="open = false"
      >
        {{ t('common.actions.cancel') }}
      </UiButton>
      <UiButton
        :icon="Send"
        flip-icons
        :loading="sending"
        :disabled="sendable.length === 0"
        @click="send(true)"
      >
        {{ t('invitations.issuer.drawer.send', { count: sendable.length }, sendable.length) }}
      </UiButton>
    </template>
  </UiDrawer>
</template>
