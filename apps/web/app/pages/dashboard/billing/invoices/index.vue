<script setup lang="ts">
import { ReceiptText } from '@lucide/vue'
import { listInvoices } from '~/services/billing'
import type { Invoice } from '~/types/api/billing'
import type { PagePagination } from '~/types/api/common'
import type { TableColumn } from '~/types/ui'

/**
 * W34 Invoices · `/dashboard/billing/invoices?page=` (`billing.view`; SCREENS §2.4). Newest first:
 * number, issue date, type, total incl. VAT, e-invoice status and the PDF (a blob download;
 * `invoice_pdf_not_ready` → «الفاتورة قيد الإصدار»). Subscription and sponsored-pass purchases both
 * appear here.
 */
definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const { t } = useI18n()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

useSeoMeta({ title: () => t('billing.invoices.title') })

const allowed = computed(() => auth.can('billing.view'))
const page = ref(parsePositiveInt(route.query.page) ?? 1)
const invoices = ref<Invoice[]>([])
const pagination = ref<PagePagination | null>(null)
const loading = ref(true)
const loadError = ref<unknown>(null)

let seq = 0
async function load(): Promise<void> {
  if (!allowed.value) return
  const current = ++seq
  loading.value = true
  loadError.value = null
  try {
    const result = await listInvoices({ page: page.value })
    if (current !== seq) return
    invoices.value = result.items
    pagination.value = result.pagination
  }
  catch (error) {
    if (current === seq) loadError.value = error
  }
  finally {
    if (current === seq) loading.value = false
  }
}

onMounted(load)

watch(page, (value) => {
  void router.replace({ query: { ...route.query, page: value > 1 ? String(value) : undefined } })
  void load()
})

const pageCount = computed(() => pagination.value?.last_page ?? (pagination.value?.has_more ? page.value + 1 : page.value))

const columns = computed<TableColumn[]>(() => [
  { key: 'number', label: t('billing.invoices.fields.number'), primary: true },
  { key: 'issue_date', label: t('billing.invoices.fields.issue_date') },
  { key: 'type', label: t('billing.invoices.fields.type') },
  { key: 'total', label: t('billing.invoices.fields.total'), numeric: true },
  { key: 'einvoice_status', label: t('billing.invoices.fields.einvoice_status') },
  { key: 'pdf', label: t('billing.invoices.fields.pdf'), align: 'end' },
])

const row = (value: unknown) => value as Invoice
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiPageHeader
      :title="t('billing.invoices.title')"
      :description="t('billing.invoices.subtitle')"
    >
      <template #eyebrow>
        <UiButton
          variant="link"
          size="sm"
          class="mb-1"
          to="/dashboard/billing"
        >
          {{ t('billing.title') }}
        </UiButton>
      </template>
    </UiPageHeader>

    <UiForbiddenState v-if="!allowed" />

    <template v-else>
      <UiCard
        v-if="loadError && invoices.length === 0"
        padding="none"
      >
        <UiErrorState
          :error="loadError"
          :retrying="loading"
          @retry="load"
        />
      </UiCard>

      <template v-else>
        <UiAlert
          v-if="loadError"
          tone="warning"
        >
          {{ t('billing.overview.stale') }}
        </UiAlert>
        <UiTable
          :columns="columns"
          :rows="invoices"
          row-key="id"
          :caption="t('billing.invoices.caption')"
          stack-below="xl"
          :loading="loading && invoices.length === 0"
        >
          <template #cell-number="{ row: r }">
            <NuxtLinkLocale
              :to="`/dashboard/billing/invoices/${row(r).id}`"
              class="link font-semibold"
            >
              <bdi>{{ row(r).number }}</bdi>
            </NuxtLinkLocale>
          </template>
          <template #cell-issue_date="{ row: r }">
            <UiDateTime
              :value="row(r).issue_date"
              format="date"
            />
          </template>
          <template #cell-type="{ row: r }">
            {{ t(`billing.invoices.types.${row(r).type}`) }}
          </template>
          <template #cell-total="{ row: r }">
            <UiAmount :minor="row(r).total_minor" />
          </template>
          <template #cell-einvoice_status="{ row: r }">
            <BillingEinvoiceStatusChip :status="row(r).einvoice_status" />
          </template>
          <template #cell-pdf="{ row: r }">
            <BillingInvoicePdfButton
              :invoice="row(r)"
              variant="ghost"
            />
          </template>
          <template #empty>
            <UiEmptyState
              :icon="ReceiptText"
              :title="t('billing.invoices.empty.title')"
              :description="t('billing.invoices.empty.body')"
            />
          </template>
        </UiTable>

        <UiPagination
          v-if="pageCount > 1"
          v-model:page="page"
          :page-count="pageCount"
        />
        <p class="text-sm text-fg-muted">
          {{ t('billing.invoices.total_note') }}
        </p>
      </template>
    </template>
  </div>
</template>
