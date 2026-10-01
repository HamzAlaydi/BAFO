<script setup lang="ts">
import type { Component } from 'vue'
import { CircleAlert, CircleCheck, Clock } from '@lucide/vue'
import type { EInvoiceStatus } from '~/types/api/billing'

/** E-invoice (ZATCA) status of an invoice (SCREENS W34): icon and text, never colour alone. */
const props = defineProps<{ status: EInvoiceStatus }>()
const { t } = useI18n()

const ICONS: Record<EInvoiceStatus, Component> = {
  cleared: CircleCheck,
  reported: CircleCheck,
  pending: Clock,
  rejected: CircleAlert,
  failed: CircleAlert,
}

const icon = computed(() => ICONS[props.status])
</script>

<template>
  <UiBadge
    :tone="EINVOICE_STATUS_TONES[status]"
    size="sm"
    :icon="icon"
  >
    {{ t(`billing.invoices.einvoice.${status}`) }}
  </UiBadge>
</template>
