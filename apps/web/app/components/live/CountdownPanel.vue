<script setup lang="ts">
import { Clock3, Gauge, Server } from '@lucide/vue'
import type { ParticipantLiveSnapshot } from '~/types/api/bidding'
import type { LiveConnectionState } from '~/utils/connection-state'

/**
 * `CountdownPanel` (SCREENS S3, W19 header): status and phase, the server-time countdown to the S3
 * target (opens, effective close, BAFO cutoff), the latest possible close under auto-extend, the
 * final-window countdown during the initial phase, the extension count, and the two timing hints
 * (slow connection; «يُعتمد وقت استلام العرض على خادم بافو» in the last 10 seconds).
 *
 * At zero it reads «جارٍ الإغلاق…»: only the server says "closed" (S3 step 9). Threshold
 * announcements (10 and 5 minutes polite, 1 minute assertive) come from `UiCountdown`.
 */
const props = defineProps<{
  snapshot: ParticipantLiveSnapshot
  connection: LiveConnectionState
  /** From the competition schedule: shown as a second countdown during the initial phase. */
  finalWindowStartsAt?: string | null
  closedAt?: string | null
  /** Remaining time to the main target on the server clock (the room owns the clock). */
  remainingMs: number | null
  slow?: boolean
}>()

const { t } = useI18n()
const countdown = computed(() => liveCountdownOf(props.snapshot))
const lastSeconds = computed(() => props.remainingMs !== null && props.remainingMs > 0 && props.remainingMs <= 10_000)

const label = computed(() => {
  switch (countdown.value?.kind) {
    case 'opens': return t('live.countdown.opens')
    case 'bafo_closes': return t('live.countdown.bafo_closes')
    default: return t('live.countdown.closes')
  }
})

const deadlineKey = computed(() => {
  switch (countdown.value?.kind) {
    case 'opens': return 'live.countdown.opens_at'
    case 'bafo_closes': return 'live.countdown.bafo_cutoff_at'
    default: return 'live.countdown.closes_at'
  }
})

const showFinalWindow = computed(() => props.snapshot.status === 'live' && props.snapshot.phase === 'initial' && Boolean(props.finalWindowStartsAt))
const showHardStop = computed(() => props.snapshot.status === 'live' && Boolean(props.snapshot.hard_stop_at))
</script>

<template>
  <UiCard padding="none">
    <div class="flex flex-col gap-4 p-4 sm:p-5">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <CompetitionsStatusChip
          :status="snapshot.status"
          :phase="snapshot.phase"
          :effective-close-at="snapshot.effective_close_at"
          :extension-count="snapshot.extension_count"
        />
        <UiConnectionIndicator :state="connection" />
      </div>

      <div
        v-if="countdown"
        class="flex flex-col gap-1"
      >
        <p class="text-sm font-semibold text-fg-muted">
          {{ label }}
        </p>
        <UiCountdown
          :ends-at="countdown.target"
          :label="label"
          :ended-label="countdown.kind === 'opens' ? t('live.countdown.opening') : t('live.countdown.closing')"
          size="lg"
          announce
          data-testid="live-countdown"
        />
        <p class="text-sm text-fg-muted">
          <i18n-t
            :keypath="deadlineKey"
            scope="global"
          >
            <template #time>
              <UiDateTime
                :value="countdown.target"
                format="deadline"
              />
            </template>
          </i18n-t>
        </p>
      </div>
      <p
        v-else-if="closedAt"
        class="text-sm text-fg-muted"
      >
        <i18n-t
          keypath="live.countdown.closed_at"
          scope="global"
        >
          <template #time>
            <UiDateTime
              :value="closedAt"
              format="deadline"
            />
          </template>
        </i18n-t>
      </p>

      <dl
        v-if="showFinalWindow || showHardStop || snapshot.extension_count > 0"
        class="grid grid-cols-1 gap-3 border-t border-line pt-3 text-sm sm:grid-cols-2"
      >
        <div
          v-if="showFinalWindow"
          class="flex flex-col gap-0.5"
        >
          <dt class="text-fg-muted">
            {{ t('live.countdown.final_window_in') }}
          </dt>
          <dd class="font-semibold text-fg">
            <UiCountdown
              :ends-at="finalWindowStartsAt"
              :label="t('live.countdown.final_window_in')"
              size="sm"
            />
          </dd>
        </div>
        <div
          v-if="showHardStop"
          class="flex flex-col gap-0.5"
        >
          <dt class="text-fg-muted">
            {{ t('live.countdown.hard_stop') }}
          </dt>
          <dd class="font-semibold text-fg">
            <UiDateTime
              :value="snapshot.hard_stop_at"
              format="deadline"
            />
          </dd>
        </div>
        <div
          v-if="snapshot.extension_count > 0"
          class="flex flex-col gap-0.5"
        >
          <dt class="text-fg-muted">
            {{ t('live.countdown.extensions_label') }}
          </dt>
          <dd
            class="font-semibold text-fg"
            data-testid="extension-count"
          >
            {{ t('live.countdown.extensions', { count: snapshot.extension_count }, snapshot.extension_count) }}
          </dd>
        </div>
      </dl>

      <p
        v-if="lastSeconds"
        class="flex items-start gap-2 rounded-md bg-info-soft p-3 text-sm text-info-soft-fg"
        data-testid="server-timing-hint"
      >
        <Server
          :size="16"
          class="mt-0.5 shrink-0"
          aria-hidden="true"
        />
        {{ t('live.hint.server_timing') }}
      </p>
      <p
        v-if="slow && countdown"
        class="flex items-start gap-2 rounded-md bg-warning-soft p-3 text-sm text-warning-soft-fg"
        data-testid="slow-hint"
      >
        <Gauge
          :size="16"
          class="mt-0.5 shrink-0"
          aria-hidden="true"
        />
        {{ t('live.hint.slow_connection') }}
      </p>
      <p
        v-if="!countdown && !closedAt"
        class="flex items-center gap-2 text-sm text-fg-muted"
      >
        <Clock3
          :size="16"
          aria-hidden="true"
        />
        {{ t('live.countdown.none') }}
      </p>
    </div>
  </UiCard>
</template>
