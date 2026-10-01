<script setup lang="ts">
import type { LadderEntry } from '~/types/api/bidding'

/**
 * `Ladder` and the leading amount (SCREENS W19): rendered **only** from what the participant
 * projection carries (`leading_amount_minor` when `show_prices`; `ladder` when full rank and prices
 * are visible). Other participants appear by alias only; the viewer's row reads «أنتم». Amounts are
 * neutral: nothing is coloured by whether a price is higher or lower (S2). A new leading amount is
 * announced politely, at most once every 10 s (S9).
 */
const props = defineProps<{
  leadingAmountMinor: number | null
  ladder: LadderEntry[] | null
}>()

const { t } = useI18n()
const money = useMoney()
const hasContent = computed(() => props.leadingAmountMinor !== null || (props.ladder?.length ?? 0) > 0)

const announcer = useThrottledAnnouncer()
watch(() => props.leadingAmountMinor, (amount, previous) => {
  if (amount === null || previous === null || amount === previous) return
  announcer.announce(() => (props.leadingAmountMinor === null ? '' : t('live.leading_amount', { amount: money.format(props.leadingAmountMinor) })))
})
</script>

<template>
  <UiCard
    v-if="hasContent"
    :title="t('live.market.title')"
    padding="sm"
  >
    <p
      class="sr-only"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ announcer.message.value }}
    </p>
    <div class="flex flex-col gap-4">
      <p
        v-if="leadingAmountMinor !== null"
        class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-fg-muted"
        data-testid="leading-amount"
      >
        <i18n-t
          keypath="live.leading_amount"
          scope="global"
          tag="span"
        >
          <template #amount>
            <UiAmount
              :minor="leadingAmountMinor"
              size="lg"
              class="text-fg"
            />
          </template>
        </i18n-t>
      </p>

      <div v-if="ladder && ladder.length > 0">
        <h4 class="mb-2 text-sm font-semibold text-fg">
          {{ t('live.market.ladder_title') }}
        </h4>
        <ol
          class="flex flex-col divide-y divide-line rounded-md border border-line"
          :aria-label="t('live.market.ladder_caption')"
          data-testid="ladder"
        >
          <li
            v-for="(entry, index) in ladder"
            :key="`${entry.alias_no}-${index}`"
            class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
            :class="entry.is_me && 'bg-surface-muted font-semibold'"
            :aria-current="entry.is_me ? 'true' : undefined"
          >
            <span class="flex min-w-0 items-center gap-2">
              <span class="w-6 shrink-0 text-center text-fg-muted tabular-nums">
                <bdi>{{ index + 1 }}</bdi>
              </span>
              <span class="truncate text-fg">
                {{ entry.is_me ? t('live.market.you') : t('live.market.alias', { alias: entry.alias_no }) }}
              </span>
            </span>
            <UiAmount
              :minor="entry.amount_minor"
              size="sm"
            />
          </li>
        </ol>
      </div>
    </div>
  </UiCard>
</template>
