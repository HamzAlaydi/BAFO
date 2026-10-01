<script setup lang="ts">
import type { InviteeCompetition, ParticipantCompetition } from '~/types/api/competitions'

/**
 * Participant and invitee part of the W13 detail header (SCREENS §2.4): the fees-covered badge
 * «رسوم مغطّاة · تغطيها {sponsor}» for covered participants and invitees only, and the S3 countdown
 * (invitee: the join deadline; participant: opens, effective close or the BAFO cutoff from the live
 * snapshot, plus «أقصى موعد للإغلاق» under auto-extend). Place it in the header next to the chips;
 * it reads `useCompetitionContext()` and renders nothing for issuers. `hideCountdown` on the live
 * tab, whose countdown panel shows the same times.
 */
const props = defineProps<{ hideCountdown?: boolean }>()
const ctx = useCompetitionContext()
const { t } = useI18n()

const competition = computed(() => {
  const current = ctx.competition.value
  return current && current.viewer_role !== 'issuer' ? current as ParticipantCompetition | InviteeCompetition : null
})
const snapshot = computed(() => (isParticipantSnapshot(ctx.live.value) ? ctx.live.value : null))
const access = computed(() => competition.value?.access ?? null)
const sponsored = computed(() => access.value?.coverage === 'sponsored' && access.value.state !== 'unavailable')

const countdown = computed<{ target: string, label: string, ended?: string } | null>(() => {
  const current = competition.value
  if (!current || props.hideCountdown) return null
  if (current.viewer_role === 'invitee') {
    const waiting = current.access.state === 'join_required' || current.access.state === 'plan_required'
    const deadline = current.access.join_deadline ?? current.invitation.join_deadline
    return waiting && deadline ? { target: deadline, label: t('invitations.landing.join_before') } : null
  }
  const target = snapshot.value ? liveCountdownOf(snapshot.value) : null
  if (!target) return null
  if (target.kind === 'opens') return { target: target.target, label: t('live.countdown.opens'), ended: t('live.countdown.opening') }
  const label = target.kind === 'bafo_closes' ? t('live.countdown.bafo_closes') : t('live.countdown.closes')
  return { target: target.target, label, ended: t('live.countdown.closing') }
})

const hardStop = computed(() => (snapshot.value?.status === 'live' && !props.hideCountdown ? snapshot.value.hard_stop_at : null))
</script>

<template>
  <div
    v-if="competition && (sponsored || countdown)"
    class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm"
    data-testid="participant-header-meta"
  >
    <InvitationsFeesCoveredBadge
      v-if="sponsored"
      :sponsor-name="access?.sponsor_name"
    />
    <span
      v-if="countdown"
      class="inline-flex flex-wrap items-baseline gap-1.5"
    >
      <span class="text-fg-muted">{{ countdown.label }}</span>
      <UiCountdown
        :ends-at="countdown.target"
        :label="countdown.label"
        :ended-label="countdown.ended"
        size="sm"
      />
    </span>
    <span
      v-if="hardStop"
      class="inline-flex flex-wrap items-baseline gap-1.5"
    >
      <span class="text-fg-muted">{{ t('live.countdown.hard_stop') }}</span>
      <UiDateTime
        :value="hardStop"
        format="deadline"
        class="font-semibold text-fg"
      />
    </span>
  </div>
</template>
