<script setup lang="ts">
import { Download } from '@lucide/vue'
import { invoicePdfPath } from '~/services/billing'
import type { Invoice } from '~/types/api/billing'

/**
 * Invoice PDF download (SCREENS W34, W35): fetched as a blob with the bearer header. While the
 * e-invoice is not cleared the PDF is not available (`invoice_pdf_not_ready`): the button says so.
 */
const props = withDefaults(defineProps<{
  invoice: Pick<Invoice, 'id' | 'number' | 'pdf'>
  size?: 'sm' | 'md'
  variant?: 'secondary' | 'ghost'
}>(), {
  size: 'sm',
  variant: 'secondary',
})

const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()
const files = useFileDownload()
const busy = ref(false)

async function download(): Promise<void> {
  busy.value = true
  try {
    await files.download(invoicePdfPath(props.invoice.id), `${props.invoice.number}.pdf`)
  }
  catch (error) {
    if (error instanceof ApiError && error.code === 'invoice_pdf_not_ready') toast.info(t('billing.invoices.not_ready'))
    else toast.error(message(error))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <UiButton
    v-if="invoice.pdf.available"
    :variant="variant"
    :size="size"
    :icon="Download"
    :loading="busy"
    :aria-label="t('billing.invoices.download_named', { number: invoice.number })"
    @click="download"
  >
    {{ t('billing.invoices.download_pdf') }}
  </UiButton>
  <span
    v-else
    class="inline-flex items-center text-sm text-fg-muted"
  >
    {{ t('billing.invoices.not_ready') }}
  </span>
</template>
