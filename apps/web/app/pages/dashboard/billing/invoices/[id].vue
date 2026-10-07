<script setup lang="ts">
import { fetchInvoice } from '~/services/billing'
import type { Invoice } from '~/types/api/billing'
import type { KeyValueItem } from '~/types/ui'

/**
 * W35 Invoice detail · `/dashboard/billing/invoices/{id}` (`billing.view`; SCREENS §2.4): number,
 * dates, lines, subtotal, discount, VAT, total, ZATCA UUID, e-invoice status, the payment it settles
 * and the PDF. 404 never reveals whether the invoice exists.
 */
definePageMeta({ layout: 'dashboard', middleware: ['auth', 'feature'], feature: 'billing_invoices', featureFallback: '/dashboard/billing' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()

const allowed = computed(() => auth.can('billing.view'))
const invoiceId = computed(() => String(route.params.id ?? ''))
const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)

useSeoMeta({ title: () => (invoice.value ? t('billing.invoice.title', { number: invoice.value.number }) : t('billing.invoices.title')) })

async function load(): Promise<void> {
  if (!allowed.value) return
  loading.value = true
  loadError.value = null
  try {
    invoice.value = await fetchInvoice(invoiceId.value)
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
  }
}

onMounted(load)

const notFound = computed(() => loadError.value instanceof ApiError && loadError.value.isNotFound)
const forbidden = computed(() => loadError.value instanceof ApiError && loadError.value.isForbidden)

const items = computed<KeyValueItem[]>(() => {
  const inv = invoice.value
  if (!inv) return []
  return [
    { key: 'number', label: t('billing.invoices.fields.number'), value: inv.number, ltr: true },
    { key: 'type', label: t('billing.invoices.fields.type'), value: t(`billing.invoices.types.${inv.type}`) },
    { key: 'issue_date', label: t('billing.invoices.fields.issue_date'), value: null },
    { key: 'issued_at', label: t('billing.invoice.fields.issued_at'), value: null },
    { key: 'einvoice_status', label: t('billing.invoices.fields.einvoice_status'), value: null },
    { key: 'zatca_uuid', label: t('billing.invoice.fields.zatca_uuid'), value: inv.zatca_uuid, ltr: true },
    { key: 'payment', label: t('billing.invoice.fields.payment'), value: inv.payment_id ? null : '' },
  ]
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader :title="invoice ? t('billing.invoice.title', { number: invoice.number }) : t('billing.invoices.title')">
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/billing/invoices"
        >
          {{ t('billing.invoices.title') }}
        </UiButton>
      </template>
      <template
        v-if="invoice"
        #actions
      >
        <BillingInvoicePdfButton
          :invoice="invoice"
          size="md"
        />
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed || forbidden" />

    <UiCard v-else-if="notFound">
      <UiNotFoundState />
    </UiCard>

    <UiCard
      v-else-if="loadError"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        :retrying="loading"
        @retry="load"
      />
    </UiCard>

    <div
      v-else-if="loading || !invoice"
      class="grid gap-6 lg:grid-cols-2"
      :aria-label="t('common.loading')"
    >
      <UiCard>
        <UiSkeleton :lines="7" />
      </UiCard>
      <UiCard>
        <UiSkeleton :lines="5" />
      </UiCard>
    </div>

    <div
      v-else
      class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]"
    >
      <UiCard
        as="section"
        :title="t('billing.invoice.details_title')"
      >
        <UiKeyValueList :items="items">
          <template #value-issue_date>
            <UiDateTime
              :value="invoice.issue_date"
              format="date"
            />
          </template>
          <template #value-issued_at>
            <UiDateTime
              :value="invoice.issued_at"
              format="deadline"
            />
          </template>
          <template #value-einvoice_status>
            <BillingEinvoiceStatusChip :status="invoice.einvoice_status" />
          </template>
          <template #value-zatca_uuid>
            <code
              v-if="invoice.zatca_uuid"
              dir="ltr"
              class="font-mono text-xs break-all"
            >{{ invoice.zatca_uuid }}</code>
            <span v-else>—</span>
          </template>
          <template #value-payment>
            <UiButton
              v-if="invoice.payment_id"
              variant="link"
              size="sm"
              :to="{ path: '/dashboard/billing/checkout/return', query: { payment: invoice.payment_id } }"
            >
              {{ t('billing.invoice.view_payment') }}
            </UiButton>
            <span v-else>—</span>
          </template>
        </UiKeyValueList>
      </UiCard>

      <UiCard
        as="section"
        :title="t('billing.invoice.lines_title')"
      >
        <BillingAmountsBreakdown
          :lines="invoice.lines"
          :subtotal-minor="invoice.subtotal_minor"
          :discount-minor="invoice.discount_minor"
          :vat-rate-bp="invoice.vat_rate_bp"
          :vat-minor="invoice.vat_minor"
          :total-minor="invoice.total_minor"
          :caption="t('billing.invoice.lines_title')"
        />
      </UiCard>
    </div>
  </div>
</template>
