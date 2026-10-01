<script setup lang="ts">
import { Lock, ReceiptText } from '@lucide/vue'
import type { OwnOffer } from '~/types/api/bidding'

/**
 * `SealedLockPanel` + `SealedReceipt` (SCREENS W19, sealed format): the offer is sealed until the
 * close, nobody else sees it, and it can be revised until then (ARCHITECTURE §7.3: revisions may
 * move in either direction; the last one counts). After each accepted submit the receipt shows the
 * server sequence number and receipt time (Riyadh time, milliseconds).
 */
const props = defineProps<{
  offer: OwnOffer | null
}>()

const { t } = useI18n()
const receivedAt = computed(() => formatMachineTime(props.offer?.accepted_at))
</script>

<template>
  <section
    class="flex flex-col gap-4 rounded-lg border border-line bg-surface p-4 sm:p-5"
    data-testid="sealed-panel"
  >
    <div class="flex items-start gap-3">
      <span
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-neutral-soft text-neutral-soft-fg"
        aria-hidden="true"
      >
        <Lock :size="20" />
      </span>
      <div class="min-w-0">
        <h3 class="font-bold text-fg">
          {{ t('live.sealed.title') }}
        </h3>
        <p class="mt-0.5 text-sm text-fg-muted">
          {{ t('live.sealed.body') }}
        </p>
      </div>
    </div>

    <div
      v-if="offer"
      class="rounded-md border border-dashed border-line-strong/60 bg-surface-muted p-3"
      data-testid="sealed-receipt"
    >
      <p class="flex items-center gap-2 text-sm font-semibold text-fg">
        <ReceiptText
          :size="16"
          aria-hidden="true"
        />
        {{ t('offers.sealed.received') }}
      </p>
      <dl class="mt-2 grid grid-cols-1 gap-2 text-sm xs:grid-cols-3">
        <div>
          <dt class="text-fg-muted">
            {{ t('live.sealed.receipt_amount') }}
          </dt>
          <dd class="font-semibold text-fg">
            <UiAmount :minor="offer.amount_minor" />
          </dd>
        </div>
        <div>
          <dt class="text-fg-muted">
            {{ t('live.sealed.receipt_seq') }}
          </dt>
          <dd class="font-semibold text-fg tabular-nums">
            <bdi>{{ `#${offer.seq}` }}</bdi>
          </dd>
        </div>
        <div>
          <dt class="text-fg-muted">
            {{ t('live.sealed.receipt_time') }}
          </dt>
          <dd class="font-semibold text-fg tabular-nums">
            <bdi>{{ t('offers.machine_time', { time: receivedAt ?? '' }) }}</bdi>
          </dd>
        </div>
      </dl>
      <p class="mt-2 text-xs text-fg-muted">
        {{ t('live.sealed.revise_hint') }}
      </p>
    </div>
  </section>
</template>
