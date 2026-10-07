<script setup lang="ts">
import { CircleCheck, Timer, TimerReset, Users } from '@lucide/vue'

/**
 * The hero's live demo (RELEASE_SCOPE §6.1 row 1): a sample tender as a participant sees it in the
 * live room (SCREENS W19): direction and status chips, the leading offer, a countdown ticking from
 * an end fixed once on the server, alias standings and the standing banner. Static content, no API.
 */
const { t } = useI18n()
const money = useMoney()

// Fixed once per server render and reused on the client, so the hydrated clock agrees.
const endsAt = useState('landing:preview-ends-at', () => new Date(Date.now() + (2 * 3600 + 14 * 60) * 1000).toISOString())

// A tender: the lowest offer leads. "You" are participant 3, in the lead.
const standings = computed(() => [
  { rank: 1, alias: 3, amountMinor: 1_248_000_00, time: t('landing.preview.times.first'), you: true },
  { rank: 2, alias: 1, amountMinor: 1_262_500_00, time: t('landing.preview.times.second'), you: false },
  { rank: 3, alias: 5, amountMinor: 1_275_000_00, time: t('landing.preview.times.third'), you: false },
])
</script>

<template>
  <article
    class="relative flex flex-col overflow-hidden rounded-xl border border-line bg-surface shadow-lg"
    :aria-label="t('landing.preview.label')"
  >
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
      <CompetitionsDirectionChip
        direction="tender"
        size="sm"
      />
      <CompetitionsStatusChip
        status="live"
        phase="open"
        :overlays="false"
        size="sm"
      />
    </div>

    <div class="px-5 pt-4 pb-3">
      <p class="text-lg leading-snug font-bold text-fg">
        {{ t('landing.preview.title') }}
      </p>
      <p class="mt-1 text-sm text-fg-muted">
        {{ t('landing.preview.issuer') }}
      </p>
    </div>

    <dl class="grid grid-cols-2 gap-px border-y border-line bg-line">
      <div class="bg-surface px-5 py-4">
        <dt class="text-xs text-fg-muted">
          {{ t('glossary.leading_offer') }}
        </dt>
        <dd class="mt-1 text-xl font-bold whitespace-nowrap text-primary-soft-fg tabular-nums">
          <bdi>{{ money.format(standings[0]!.amountMinor) }}</bdi>
        </dd>
      </div>
      <div class="bg-surface px-5 py-4">
        <dt class="text-xs text-fg-muted">
          {{ t('landing.preview.closes_in') }}
        </dt>
        <dd class="mt-1 flex flex-col">
          <UiCountdown
            :ends-at="endsAt"
            :label="t('landing.preview.closes_in')"
            size="lg"
            class="leading-none"
          />
          <span class="mt-1 inline-flex items-center gap-1 text-xs text-fg-muted">
            <Timer
              :size="12"
              aria-hidden="true"
            />
            {{ t('landing.preview.server_clock') }}
          </span>
        </dd>
      </div>
    </dl>

    <ol
      class="divide-y divide-line"
      :aria-label="t('landing.preview.standings')"
    >
      <li
        v-for="row in standings"
        :key="row.rank"
        class="flex items-center gap-3 px-5 py-2.5 text-sm"
        :class="row.you ? 'bg-primary-soft' : ''"
      >
        <span
          class="w-5 shrink-0 font-semibold text-fg-muted tabular-nums"
          :aria-label="t('landing.preview.rank', { rank: row.rank })"
        >{{ row.rank }}</span>
        <span class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-0.5">
          <span
            class="font-semibold"
            :class="row.you ? 'text-primary-soft-fg' : 'text-fg'"
          >{{ t('landing.preview.alias', { number: row.alias }) }}</span>
          <UiBadge
            v-if="row.you"
            tone="primary"
            size="sm"
            solid
          >
            {{ t('landing.preview.you') }}
          </UiBadge>
          <span
            class="text-xs"
            :class="row.you ? 'text-neutral-soft-fg' : 'text-fg-muted'"
          >{{ row.time }}</span>
        </span>
        <bdi
          class="shrink-0 font-bold whitespace-nowrap tabular-nums"
          :class="row.you ? 'text-primary-soft-fg' : 'text-fg'"
        >{{ money.format(row.amountMinor) }}</bdi>
      </li>
    </ol>

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-line bg-surface-muted px-5 py-3">
      <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-2.5 py-1 text-sm font-semibold text-primary-soft-fg">
        <CircleCheck
          :size="14"
          aria-hidden="true"
        />
        {{ t('landing.preview.leading') }}
      </span>
      <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-fg-muted">
        <span class="inline-flex items-center gap-1.5">
          <Users
            :size="14"
            aria-hidden="true"
          />
          {{ t('landing.preview.participants', { count: 7 }, 7) }}
        </span>
        <span class="inline-flex items-center gap-1.5">
          <TimerReset
            :size="14"
            aria-hidden="true"
          />
          {{ t('landing.preview.auto_extend') }}
        </span>
      </span>
    </div>
  </article>
</template>
