<script setup lang="ts">
import { CreditCard, FileText, Hourglass, ReceiptText, Ticket } from '@lucide/vue'
import { listVouchers, fetchSubscription, startTrial } from '~/services/billing'
import type { Subscription, SubscriptionOverview, Voucher } from '~/types/api/billing'
import type { TableColumn } from '~/types/ui'

/**
 * W30 Subscription and billing · `/dashboard/billing` (`billing.view`; SCREENS §2.4).
 * `GET /billing/subscription` + `GET /billing/vouchers`: the current and upcoming subscription with
 * the days-left and seats meters, Change plan / Renew, Start trial, history, vouchers and the
 * links to invoices. No optimistic state: the trial is shown once the server has created it.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const home = useHomeStore()
const toast = useToast()
const { message } = useErrorMessage()
const date = useDate()

useSeoMeta({ title: () => t('billing.title') })

const allowed = computed(() => auth.can('billing.view'))
// Vouchers come from unused covered passes and are redeemed in the coupon field: both belong to the
// sponsorship feature, hidden in release scope core (RELEASE_SCOPE.md §1.3).
const showVouchers = computed(() => features.enabled('sponsorship'))
const canPurchase = computed(() => auth.can('billing.purchase'))

const overview = ref<SubscriptionOverview | null>(null)
const vouchers = ref<Voucher[]>([])
const loading = ref(true)
const loadError = ref<unknown>(null)
const vouchersError = ref<unknown>(null)
const startingTrial = ref(false)
const trialError = ref<string | null>(null)

let seq = 0
async function load(): Promise<void> {
  if (!allowed.value) return
  const current = ++seq
  loading.value = true
  loadError.value = null
  vouchersError.value = null
  const [subscriptionResult, vouchersResult] = await Promise.allSettled([fetchSubscription(), showVouchers.value ? listVouchers() : Promise.resolve([])])
  if (current !== seq) return
  if (subscriptionResult.status === 'fulfilled') overview.value = subscriptionResult.value
  else loadError.value = subscriptionResult.reason
  if (vouchersResult.status === 'fulfilled') vouchers.value = vouchersResult.value
  else vouchersError.value = vouchersResult.reason
  loading.value = false
}

onMounted(load)

const current = computed(() => overview.value?.current ?? null)
const upcoming = computed(() => overview.value?.upcoming ?? null)
const history = computed(() => overview.value?.history ?? [])

/** A paid subscription is renewed from the plans page; a trial or grant moves to a paid plan. */
const planAction = computed(() => {
  if (!current.value) return t('billing.overview.actions.choose_plan')
  return current.value.source === 'paid' ? t('billing.overview.actions.change_or_renew') : t('billing.overview.actions.upgrade')
})

async function onStartTrial(): Promise<void> {
  startingTrial.value = true
  trialError.value = null
  try {
    await startTrial()
    toast.success(t('billing.overview.trial.started'))
    await Promise.allSettled([auth.fetchMe(), home.load(), load()])
  }
  catch (error) {
    trialError.value = message(error)
    if (error instanceof ApiError && error.code === 'trial_not_available') void load()
  }
  finally {
    startingTrial.value = false
  }
}

const historyColumns = computed<TableColumn[]>(() => [
  { key: 'plan', label: t('billing.overview.fields.plan'), primary: true },
  { key: 'source', label: t('billing.overview.fields.source') },
  { key: 'period', label: t('billing.overview.fields.period') },
  { key: 'seats', label: t('billing.overview.fields.seats'), numeric: true },
  { key: 'status', label: t('billing.overview.fields.status') },
  { key: 'total', label: t('billing.overview.fields.total_paid'), numeric: true },
])

const row = (value: unknown) => value as Subscription
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('billing.title')"
      :description="t(showVouchers ? 'billing.subtitle' : 'billing.subtitle_core')"
    >
      <template
        v-if="allowed"
        #actions
      >
        <UiButton
          v-if="features.enabled('billing_invoices')"
          variant="secondary"
          :icon="FileText"
          to="/dashboard/billing/invoices"
        >
          {{ t('billing.overview.actions.invoices') }}
        </UiButton>
        <UiButton
          variant="secondary"
          :icon="CreditCard"
          to="/dashboard/billing/plans"
        >
          {{ t('billing.overview.actions.plans') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />

    <template v-else>
      <!-- L -->
      <div
        v-if="loading && !overview"
        class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
        :aria-label="t('common.loading')"
      >
        <UiCard>
          <UiSkeleton :lines="6" />
        </UiCard>
        <UiCard>
          <UiSkeleton :lines="3" />
        </UiCard>
      </div>

      <!-- X -->
      <UiCard
        v-else-if="loadError && !overview"
        padding="none"
      >
        <UiErrorState
          :error="loadError"
          :retrying="loading"
          @retry="load"
        />
      </UiCard>

      <template v-else-if="overview">
        <UiAlert
          v-if="loadError"
          tone="warning"
        >
          {{ t('billing.overview.stale') }}
        </UiAlert>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
          <div class="flex flex-col gap-6">
            <BillingSubscriptionCard
              v-if="current"
              :subscription="current"
            >
              <template
                v-if="canPurchase"
                #actions
              >
                <UiButton
                  :icon="CreditCard"
                  to="/dashboard/billing/plans"
                >
                  {{ planAction }}
                </UiButton>
              </template>
            </BillingSubscriptionCard>

            <!-- No subscription -->
            <UiCard v-else>
              <UiEmptyState
                :icon="CreditCard"
                :title="t('billing.overview.none.title')"
                :description="t('billing.overview.none.body')"
              >
                <UiButton
                  to="/dashboard/billing/plans"
                  :variant="overview.trial_available && canPurchase ? 'secondary' : 'primary'"
                >
                  {{ t('billing.overview.actions.plans') }}
                </UiButton>
              </UiEmptyState>
            </UiCard>

            <!-- Trial -->
            <UiCard
              v-if="overview.trial_available"
              as="section"
              :aria-label="t('billing.overview.trial.title')"
            >
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                  <span
                    class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-info-soft text-info-soft-fg"
                    aria-hidden="true"
                  >
                    <Hourglass :size="20" />
                  </span>
                  <div>
                    <h2 class="font-bold text-fg">
                      {{ t('billing.overview.trial.title') }}
                    </h2>
                    <p class="mt-1 text-sm text-fg-muted">
                      {{ t('billing.overview.trial.body') }}
                    </p>
                  </div>
                </div>
                <UiButton
                  v-if="canPurchase"
                  class="shrink-0"
                  :loading="startingTrial"
                  @click="onStartTrial"
                >
                  {{ t('billing.overview.trial.start') }}
                </UiButton>
                <p
                  v-else
                  class="text-sm text-fg-muted sm:max-w-56"
                >
                  {{ t('billing.common.purchase_permission') }}
                </p>
              </div>
              <UiAlert
                v-if="trialError"
                class="mt-4"
                tone="danger"
                role="alert"
              >
                {{ trialError }}
              </UiAlert>
            </UiCard>

            <BillingSubscriptionCard
              v-if="upcoming"
              :subscription="upcoming"
              variant="upcoming"
            />
          </div>

          <div class="flex flex-col gap-6">
            <UiCard :title="t('billing.overview.seats_title')">
              <TeamSeatsMeter :seats="{ used: overview.seats_used, total: overview.seats_total }" />
              <UiButton
                v-if="auth.can('team.manage') && features.enabled('team_management')"
                class="mt-4"
                variant="link"
                size="sm"
                to="/dashboard/team"
              >
                {{ t('billing.overview.manage_team') }}
              </UiButton>
            </UiCard>

            <UiCard
              v-if="features.enabled('billing_invoices')"
              as="section"
              :title="t('billing.overview.invoices.title')"
            >
              <div class="flex items-start gap-3">
                <ReceiptText
                  :size="20"
                  class="mt-0.5 shrink-0 text-brand"
                  aria-hidden="true"
                />
                <p class="text-sm text-fg-muted">
                  {{ t('billing.overview.invoices.body') }}
                </p>
              </div>
              <UiButton
                class="mt-4"
                variant="secondary"
                size="sm"
                to="/dashboard/billing/invoices"
              >
                {{ t('billing.overview.actions.invoices') }}
              </UiButton>
            </UiCard>

            <UiCard
              v-if="showVouchers && auth.features?.sponsorship_enabled"
              as="section"
              :title="t('billing.overview.sponsorship.title')"
            >
              <div class="flex items-start gap-3">
                <Ticket
                  :size="20"
                  class="mt-0.5 shrink-0 text-brand"
                  aria-hidden="true"
                />
                <p class="text-sm text-fg-muted">
                  {{ t('billing.overview.sponsorship.body') }}
                </p>
              </div>
            </UiCard>
          </div>
        </div>

        <!-- Vouchers -->
        <UiCard
          v-if="showVouchers"
          as="section"
          :title="t('billing.vouchers.title')"
          :description="t('billing.vouchers.hint')"
        >
          <UiErrorState
            v-if="vouchersError"
            compact
            :error="vouchersError"
            :retrying="loading"
            @retry="load"
          />
          <UiEmptyState
            v-else-if="vouchers.length === 0"
            compact
            :icon="Ticket"
            :title="t('billing.vouchers.empty.title')"
            :description="t('billing.vouchers.empty.body')"
          />
          <BillingVoucherList
            v-else
            :vouchers="vouchers"
          />
        </UiCard>

        <!-- History -->
        <section
          class="flex flex-col gap-3"
          aria-labelledby="billing-history-title"
        >
          <h2
            id="billing-history-title"
            class="text-lg font-bold text-fg"
          >
            {{ t('billing.overview.history.title') }}
          </h2>
          <UiTable
            :columns="historyColumns"
            :rows="history"
            row-key="id"
            :caption="t('billing.overview.history.title')"
            :empty-title="t('billing.overview.history.empty')"
          >
            <template #cell-plan="{ row: r }">
              <span class="flex flex-col">
                <span class="font-semibold text-fg">{{ row(r).plan.name }}</span>
                <span
                  v-if="row(r).interval"
                  class="text-xs text-fg-muted"
                >{{ t(`billing.intervals.${row(r).interval}`) }}</span>
              </span>
            </template>
            <template #cell-source="{ row: r }">
              {{ t(`billing.sources.${row(r).source}`) }}
            </template>
            <template #cell-period="{ row: r }">
              <span class="text-sm whitespace-nowrap text-fg-muted">
                {{ t('billing.overview.period_value', { start: date.formatDate(row(r).starts_at), end: date.formatDate(row(r).ends_at) }) }}
              </span>
            </template>
            <template #cell-seats="{ row: r }">
              <bdi>{{ row(r).seats }}</bdi>
            </template>
            <template #cell-status="{ row: r }">
              <UiBadge
                :tone="SUBSCRIPTION_STATUS_TONES[row(r).status]"
                size="sm"
                dot
              >
                {{ t(`billing.statuses.${row(r).status}`) }}
              </UiBadge>
            </template>
            <template #cell-total="{ row: r }">
              <UiAmount
                v-if="row(r).amounts"
                :minor="row(r).amounts!.total_minor"
              />
              <span v-else>—</span>
            </template>
          </UiTable>
        </section>
      </template>
    </template>
  </div>
</template>
