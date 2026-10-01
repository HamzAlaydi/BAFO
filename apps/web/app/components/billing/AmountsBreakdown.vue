<script setup lang="ts">
/**
 * Server amounts of a payment or an invoice (SCREENS S6, W32, W33, W35): optional lines, then
 * subtotal, upgrade credit, discount, VAT and the total incl. VAT. Credits and discounts show as a
 * minus inside the LTR island. Zero credit and discount rows are omitted.
 */
interface BreakdownLine {
  description: string
  quantity: number
  unit_price_minor: number
  net_minor: number
}

const props = withDefaults(defineProps<{
  lines?: BreakdownLine[]
  subtotalMinor: number
  creditMinor?: number
  discountMinor?: number
  vatRateBp: number
  vatMinor: number
  totalMinor: number
  couponCode?: string | null
  /** Caption of the lines table. */
  caption?: string
}>(), {
  lines: () => [],
  creditMinor: 0,
  discountMinor: 0,
})

const { t } = useI18n()

const vatLabel = computed(() => t('billing.amounts.vat', { rate: t('billing.common.percent', { value: formatBps(props.vatRateBp) }) }))
const discountLabel = computed(() => (props.couponCode
  ? t('billing.amounts.discount_code', { code: props.couponCode })
  : t('billing.amounts.discount')))
</script>

<template>
  <div class="flex flex-col gap-4">
    <div
      v-if="lines.length > 0"
      class="overflow-x-auto"
    >
      <table class="w-full min-w-[16rem] border-collapse text-sm">
        <caption class="sr-only">
          {{ caption ?? t('billing.amounts.lines_caption') }}
        </caption>
        <thead>
          <tr class="border-b border-line text-fg-muted">
            <th
              scope="col"
              class="py-2 pe-3 text-start font-semibold"
            >
              {{ t('billing.amounts.description') }}
            </th>
            <th
              scope="col"
              class="px-3 py-2 text-end font-semibold"
            >
              {{ t('billing.amounts.quantity') }}
            </th>
            <th
              scope="col"
              class="hidden px-3 py-2 text-end font-semibold sm:table-cell"
            >
              {{ t('billing.amounts.unit_price') }}
            </th>
            <th
              scope="col"
              class="py-2 ps-3 text-end font-semibold"
            >
              {{ t('billing.amounts.net') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(line, index) in lines"
            :key="index"
            class="border-b border-line align-top"
          >
            <td class="py-2.5 pe-3 text-fg">
              <BillingIsolatedText :text="line.description" />
            </td>
            <td class="px-3 py-2.5 text-end text-fg tabular-nums">
              <bdi>{{ line.quantity }}</bdi>
            </td>
            <td class="hidden px-3 py-2.5 text-end sm:table-cell">
              <UiAmount :minor="line.unit_price_minor" />
            </td>
            <td class="py-2.5 ps-3 text-end">
              <UiAmount :minor="line.net_minor" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <dl class="flex flex-col gap-2 text-sm">
      <div class="flex items-center justify-between gap-3">
        <dt class="text-fg-muted">
          {{ t('billing.amounts.subtotal') }}
        </dt>
        <dd><UiAmount :minor="subtotalMinor" /></dd>
      </div>
      <div
        v-if="creditMinor > 0"
        class="flex items-center justify-between gap-3"
      >
        <dt class="text-fg-muted">
          {{ t('billing.amounts.credit') }}
        </dt>
        <dd>
          <UiAmount
            :minor="creditMinor"
            sign="minus"
          />
        </dd>
      </div>
      <div
        v-if="discountMinor > 0"
        class="flex items-center justify-between gap-3"
      >
        <dt class="text-fg-muted">
          {{ discountLabel }}
        </dt>
        <dd>
          <UiAmount
            :minor="discountMinor"
            sign="minus"
          />
        </dd>
      </div>
      <div class="flex items-center justify-between gap-3">
        <dt class="text-fg-muted">
          {{ vatLabel }}
        </dt>
        <dd><UiAmount :minor="vatMinor" /></dd>
      </div>
      <div class="flex items-center justify-between gap-3 border-t border-line pt-3">
        <dt class="font-bold text-fg">
          {{ t('billing.amounts.total') }}
        </dt>
        <dd>
          <UiAmount
            :minor="totalMinor"
            size="lg"
          />
        </dd>
      </div>
    </dl>
  </div>
</template>
