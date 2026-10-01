<script setup lang="ts">
import type { RouteLocationNamedI18n } from 'vue-router'
import { History } from '@lucide/vue'
import type { OwnOffer } from '~/types/api/bidding'

/**
 * `MyOfferCard` (SCREENS W19): the participant's current offer from the snapshot, with the server
 * receipt time in Riyadh time and milliseconds, the stage, and the number of offers; a link to the
 * offer history (W21) when the page exists.
 */
const props = defineProps<{
  offer: OwnOffer | null
  offersCount: number
  historyTo?: RouteLocationNamedI18n | null
}>()

const { t } = useI18n()
const receivedAt = computed(() => formatMachineTime(props.offer?.accepted_at, { date: false }))
</script>

<template>
  <UiCard
    :title="t('live.my_offer.title')"
    padding="sm"
  >
    <div
      class="flex flex-col gap-3"
      data-testid="my-offer-card"
    >
      <template v-if="offer">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <UiAmount
            :minor="offer.amount_minor"
            size="xl"
            class="text-fg"
          />
          <UiBadge
            tone="neutral"
            size="sm"
          >
            {{ t(`offers.stage.${offer.stage}`) }}
          </UiBadge>
        </div>
        <p
          v-if="receivedAt"
          class="text-sm text-fg-muted"
        >
          {{ t('live.my_offer.received_at', { time: t('offers.machine_time', { time: receivedAt }) }) }}
        </p>
      </template>
      <p
        v-else
        class="text-sm text-fg-muted"
      >
        {{ t('live.my_offer.none') }}
      </p>
      <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3 text-sm">
        <span class="text-fg-muted">{{ t('live.my_offer.count', { count: offersCount }, offersCount) }}</span>
        <UiButton
          v-if="historyTo && offersCount > 0"
          :to="historyTo"
          variant="link"
          size="sm"
          :icon="History"
        >
          {{ t('live.my_offer.history_link') }}
        </UiButton>
      </div>
    </div>
  </UiCard>
</template>
