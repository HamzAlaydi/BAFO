<script setup lang="ts">
import { Lock, Tag } from '@lucide/vue'
import type { OfferLogEntry } from '~/types/api/bidding'
import { OFFER_FEED_SIZE } from '~/stores/competition-editor-console'

/**
 * The console's latest accepted offers (SCREENS W19 `OfferFeed`): newest first, the server receipt
 * time with milliseconds (Riyadh), the participant alias, the amount (or «مغلق» while sealed) and
 * the stage. Voided offers carry a badge. The full history is the offers log tab.
 */
const props = defineProps<{ entries: OfferLogEntry[], loading?: boolean }>()
const { t } = useI18n()

const latest = computed(() => [...props.entries].sort((a, b) => b.seq - a.seq).slice(0, OFFER_FEED_SIZE))
</script>

<template>
  <div>
    <div
      v-if="loading && latest.length === 0"
      class="flex flex-col gap-2"
    >
      <UiSkeleton class="h-8 w-full" />
      <UiSkeleton class="h-8 w-full" />
      <UiSkeleton class="h-8 w-full" />
    </div>
    <UiEmptyState
      v-else-if="latest.length === 0"
      :title="t('offers.log.empty_title')"
      :icon="Tag"
      compact
    />
    <ol
      v-else
      class="flex flex-col divide-y divide-line"
    >
      <li
        v-for="entry in latest"
        :key="entry.id"
        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm"
        :class="entry.voided && 'opacity-60'"
      >
        <span class="flex min-w-0 flex-col">
          <span class="font-semibold text-fg">{{ t('offers.participant_alias', { number: entry.participant.alias_no }) }}</span>
          <bdi class="text-xs text-fg-muted tabular-nums">{{ t('offers.machine_time', { time: formatMachineTime(entry.accepted_at) ?? '—' }) }}</bdi>
        </span>
        <span class="flex items-center gap-2">
          <UiBadge
            size="sm"
            tone="neutral"
          >
            {{ t(`offers.stage.${entry.stage}`) }}
          </UiBadge>
          <UiBadge
            v-if="entry.voided"
            size="sm"
            tone="neutral"
          >
            {{ t('offers.log.voided') }}
          </UiBadge>
          <UiAmount
            v-if="entry.amount_minor !== null"
            :minor="entry.amount_minor"
            class="font-semibold"
          />
          <span
            v-else
            class="inline-flex items-center gap-1 text-fg-muted"
          ><Lock
            :size="14"
            aria-hidden="true"
          />{{ t('offers.sealed_amount') }}</span>
        </span>
      </li>
    </ol>
  </div>
</template>
