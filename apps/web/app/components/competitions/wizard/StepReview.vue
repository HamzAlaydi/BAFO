<script setup lang="ts">
import { CreditCard, Pencil, Send } from '@lucide/vue'
import { checkoutReturnUrl, checkoutSponsorship, fetchSponsorshipQuote } from '~/services/billing'
import { publishCompetition } from '~/services/competitions'
import type { Payment, SponsorshipQuote } from '~/types/api/billing'
import type { IssuerCompetition } from '~/types/api/competitions'
import { draftChecklist, draftProgressOf, publishFieldStep, type WizardStepKey } from '~/stores/competition-editor-steps'

/**
 * Wizard step 8 «المراجعة والنشر» (SCREENS W15 `review`, §2.5 F3): a summary per step with Edit
 * links, the server's rules summary, the schedule preview, invitations against the minimum, the fees
 * summary and the pre-publish checklist (hints). **Publish**, or **Pay and publish** when the quote
 * has passes to buy:
 *
 * - `POST …/publish` → `scheduled`/`live` → overview with «نُشرت المنافسة وأُرسلت الدعوات»;
 * - `validation_failed` → each field linked to its step; `min_participants_not_met` → how many more,
 *   linked to step 6; `issuer_plan_required` → plans; `live_event_capacity_reached` → step 4;
 *   `sponsorship_payment_required` → the server quote and **Pay and publish**;
 *   `invalid_state_transition` → refetch;
 * - Pay and publish: `POST …/sponsorship/checkout {intent: publish}` with one `Idempotency-Key` per
 *   intent → the server's payment amounts → the hosted page; `sponsorship_already_funded` → publish.
 */
const props = defineProps<{ competition: IssuerCompetition, feesEnabled: boolean }>()
const emit = defineEmits<{ published: [competition: IssuerCompetition] }>()

const { t } = useI18n()
const auth = useAuthStore()
const editor = useCompetitionEditorStore()
const lookups = useLookupsStore()
const clock = useServerTime()
const locale = useAppLocale()
const { message } = useErrorMessage()

const quote = ref<SponsorshipQuote | null>(null)
const publishing = ref(false)
const paying = ref(false)
const payment = ref<Payment | null>(null)
const failure = ref<{ message: string, step?: WizardStepKey, fields?: Array<{ path: string, message: string, step: WizardStepKey | null }>, plans?: boolean, profile?: boolean } | null>(null)
let checkoutKey: string | null = null

const mode = computed(() => props.competition.sponsorship?.mode ?? 'none')
const base = computed(() => `/dashboard/competitions/${props.competition.id}`)
const nowMs = ref(clock.now())

async function loadQuote(): Promise<void> {
  if (!props.feesEnabled || mode.value === 'none') {
    quote.value = null
    return
  }
  try {
    quote.value = await fetchSponsorshipQuote(props.competition.id, editor.couponCode)
  }
  catch {
    quote.value = null
  }
}

onMounted(() => {
  void loadQuote()
  void lookups.ensureLoaded().catch(() => {})
})

const needsPayment = computed(() => (quote.value?.passes_to_buy ?? 0) > 0)
const canPurchase = computed(() => auth.can('billing.purchase'))
const progress = computed(() => draftProgressOf(props.competition, { feesEnabled: props.feesEnabled, quotePassesToBuy: quote.value?.passes_to_buy ?? null }))
const checklist = computed(() => draftChecklist(progress.value, props.competition.direction))
const presetName = computed(() => lookups.presets.find(preset => preset.code === props.competition.preset_code)?.name ?? t('competitions.setup.review.no_preset'))

function stepPath(step: WizardStepKey): string {
  return `${base.value}/setup/${step}`
}

function onError(error: unknown): void {
  const apiError = error instanceof ApiError ? error : null
  if (apiError?.isValidation) {
    const fields = Object.entries(apiError.errors).map(([path, messages]) => ({ path, message: messages[0] ?? '', step: publishFieldStep(path) }))
    failure.value = { message: t('competitions.setup.publish_errors.validation'), fields }
    return
  }
  switch (apiError?.code) {
    case 'min_participants_not_met': {
      const required = apiError.detailNumber('required') ?? props.competition.rules.min_participants
      const current = apiError.detailNumber('current') ?? progress.value.invitations
      const missing = Math.max(1, required - current)
      failure.value = { message: t('competitions.setup.publish_errors.min_participants', { count: missing }, missing), step: 'participants' }
      return
    }
    case 'issuer_plan_required':
      failure.value = { message: message(apiError), plans: true }
      return
    case 'live_event_capacity_reached':
      failure.value = { message: t('competitions.setup.publish_errors.capacity'), step: 'schedule' }
      return
    case 'sponsorship_payment_required': {
      const fromServer = apiError.details.quote as SponsorshipQuote | undefined
      if (fromServer) quote.value = fromServer
      failure.value = { message: t('competitions.setup.publish_errors.payment_required') }
      return
    }
    case 'billing_profile_incomplete':
      failure.value = { message: message(apiError), profile: true }
      return
    case 'invalid_state_transition':
      failure.value = { message: t('competitions.setup.publish_errors.already_published') }
      return
    default:
      failure.value = { message: message(error) }
  }
}

async function publish(): Promise<void> {
  publishing.value = true
  failure.value = null
  try {
    emit('published', await publishCompetition(props.competition.id))
  }
  catch (error) {
    onError(error)
  }
  finally {
    publishing.value = false
  }
}

async function payAndPublish(): Promise<void> {
  if (!canPurchase.value) return
  paying.value = true
  failure.value = null
  checkoutKey ??= uuidv4()
  try {
    const created = await checkoutSponsorship(props.competition.id, {
      intent: 'publish',
      coupon_code: editor.couponCode,
      return_url: checkoutReturnUrl(window.location.origin, locale.value),
    }, checkoutKey)
    if (!created.redirect_url && created.status === 'succeeded') {
      checkoutKey = null
      await publish()
      return
    }
    payment.value = created
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'sponsorship_already_funded') {
      checkoutKey = null
      await publish()
      return
    }
    onError(error)
  }
  finally {
    paying.value = false
  }
}

function cancelPayment(): void {
  payment.value = null
  checkoutKey = null
}

const summaryRows = computed(() => {
  const c = props.competition
  return {
    type: [
      { label: t('competitions.setup.review.preset'), value: presetName.value },
      { label: t('rules.bafo.label'), value: c.rules.bafo_round.enabled ? t('common.yes') : t('common.no') },
    ],
    basics: [
      { label: t('competitions.setup.basics.title_label'), value: c.title },
      { label: t('competitions.setup.basics.category_label'), value: c.category_other_text ? `${c.category.name} · ${c.category_other_text}` : c.category.name },
      { label: t('competitions.setup.basics.region_label'), value: c.region.name },
    ],
  }
})

watch(() => editor.couponCode, () => void loadQuote())
</script>

<template>
  <div class="flex flex-col gap-6">
    <template v-if="!payment">
      <div class="grid gap-6 lg:grid-cols-2">
        <UiCard :title="t('competitions.setup.steps.type')">
          <template #actions>
            <UiButton
              :to="stepPath('type')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2">
              <CompetitionsDirectionChip :direction="competition.direction" />
              <CompetitionsFormatChip :format="competition.format" />
            </div>
            <dl class="flex flex-col gap-1 text-sm">
              <div
                v-for="row in summaryRows.type"
                :key="row.label"
                class="flex justify-between gap-3"
              >
                <dt class="text-fg-muted">
                  {{ row.label }}
                </dt>
                <dd class="text-end text-fg">
                  {{ row.value }}
                </dd>
              </div>
            </dl>
          </div>
        </UiCard>

        <UiCard :title="t('competitions.setup.steps.basics')">
          <template #actions>
            <UiButton
              :to="stepPath('basics')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <dl class="flex flex-col gap-1 text-sm">
            <div
              v-for="row in summaryRows.basics"
              :key="row.label"
              class="flex justify-between gap-3"
            >
              <dt class="shrink-0 text-fg-muted">
                {{ row.label }}
              </dt>
              <dd class="min-w-0 text-end break-words text-fg">
                {{ row.value }}
              </dd>
            </div>
          </dl>
          <p class="mt-3 line-clamp-3 text-sm whitespace-pre-line text-fg-muted">
            {{ competition.description || t('competitions.issuer.overview.no_description') }}
          </p>
        </UiCard>

        <UiCard :title="t('competitions.setup.steps.rules')">
          <template #actions>
            <UiButton
              :to="stepPath('rules')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <CompetitionsRulesSummary :lines="competition.rules_summary" />
        </UiCard>

        <UiCard :title="t('competitions.setup.steps.schedule')">
          <template #actions>
            <UiButton
              :to="stepPath('schedule')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <CompetitionsWizardSchedulePreview
            :bidding-opens-at="competition.schedule.bidding_opens_at"
            :scheduled-close-at="competition.schedule.scheduled_close_at"
            :rules="competition.rules"
            :now-ms="nowMs"
          />
        </UiCard>

        <UiCard :title="t('competitions.setup.steps.participants')">
          <template #actions>
            <UiButton
              :to="stepPath('participants')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <p class="text-sm text-fg">
            {{ t('invitations.issuer.counter', { count: progress.invitations, min: progress.minParticipants }) }}
          </p>
          <p class="mt-1 text-sm text-fg-muted">
            {{ t('competitions.setup.review.documents', { count: competition.counts.attachments }, competition.counts.attachments) }}
            <NuxtLinkLocale
              :to="stepPath('documents')"
              class="link ms-1"
            >
              {{ t('competitions.setup.review.edit') }}
            </NuxtLinkLocale>
          </p>
        </UiCard>

        <UiCard
          v-if="feesEnabled"
          :title="t('competitions.setup.steps.fees')"
        >
          <template #actions>
            <UiButton
              :to="stepPath('fees')"
              variant="ghost"
              size="sm"
              :icon="Pencil"
            >
              {{ t('competitions.setup.review.edit') }}
            </UiButton>
          </template>
          <p class="text-sm text-fg">
            {{ t(`sponsorship.mode.${mode}.title`) }}
          </p>
          <CompetitionsIssuerQuoteSummary
            v-if="quote && mode !== 'none'"
            :quote="quote"
            class="mt-3"
          />
        </UiCard>
      </div>

      <UiCard :title="t('competitions.setup.review.checklist')">
        <CompetitionsIssuerSetupChecklist
          :items="checklist"
          :competition-id="competition.id"
          :direction="competition.direction"
        />
      </UiCard>

      <UiAlert
        v-if="failure"
        tone="danger"
        :title="t('competitions.setup.publish_errors.title')"
      >
        <p>{{ failure.message }}</p>
        <ul
          v-if="failure.fields?.length"
          class="mt-2 flex flex-col gap-1"
        >
          <li
            v-for="field in failure.fields"
            :key="field.path"
          >
            {{ field.message }}
            <NuxtLinkLocale
              v-if="field.step"
              :to="stepPath(field.step)"
              class="link ms-1"
            >
              {{ t(`competitions.setup.steps.${field.step}`) }}
            </NuxtLinkLocale>
          </li>
        </ul>
        <NuxtLinkLocale
          v-if="failure.step"
          :to="stepPath(failure.step)"
          class="link mt-2 inline-block"
        >
          {{ t('competitions.setup.publish_errors.go_to', { step: t(`competitions.setup.steps.${failure.step}`) }) }}
        </NuxtLinkLocale>
        <NuxtLinkLocale
          v-if="failure.plans"
          :to="{ path: '/dashboard/billing/plans', query: { return: `${base}/setup/review` } }"
          class="link mt-2 inline-block"
        >
          {{ t('competitions.list.view_plans') }}
        </NuxtLinkLocale>
        <NuxtLinkLocale
          v-if="failure.profile"
          :to="{ path: '/dashboard/organization', query: { return: `${base}/setup/review` } }"
          class="link mt-2 inline-block"
        >
          {{ t('competitions.setup.publish_errors.complete_profile') }}
        </NuxtLinkLocale>
      </UiAlert>

      <UiCard padding="sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p class="max-w-xl text-sm text-fg-muted">
            {{ needsPayment ? t('competitions.setup.review.pay_note') : t('competitions.setup.review.publish_note') }}
          </p>
          <template v-if="competition.permissions.can_publish">
            <template v-if="needsPayment">
              <UiButton
                v-if="canPurchase"
                :icon="CreditCard"
                :loading="paying || publishing"
                @click="payAndPublish"
              >
                {{ t('competitions.setup.review.pay_and_publish') }}
              </UiButton>
              <p
                v-else
                class="text-sm text-warning-soft-fg"
              >
                {{ t('sponsorship.fees.purchase_permission') }}
              </p>
            </template>
            <UiButton
              v-else
              :icon="Send"
              flip-icons
              :loading="publishing"
              @click="publish"
            >
              {{ t('competitions.setup.review.publish') }}
            </UiButton>
          </template>
          <p
            v-else
            class="text-sm text-fg-muted"
          >
            {{ t('competitions.setup.review.no_permission') }}
          </p>
        </div>
      </UiCard>
    </template>

    <CompetitionsIssuerCheckoutPanel
      v-else
      :payment="payment"
      @cancel="cancelPayment"
    />
  </div>
</template>
