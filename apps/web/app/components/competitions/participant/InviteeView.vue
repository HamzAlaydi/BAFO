<script setup lang="ts">
import { declineInvitation } from '~/services/competitions'
import type { Competition, InviteeCompetition, ParticipationAccess } from '~/types/api/competitions'

/**
 * W14 overview for an **invitee** (SCREENS §2.4; `viewer_role = invitee`): the invitation details the
 * page header does not already show (opening time, fees covered when sponsored), the `AccessStateCard` with Join and
 * Decline from the server's `access` and `permissions`, the rules summary, and the invitation
 * documents. Mount it in the detail overview when `viewer_role` is `invitee`; it reads
 * `useCompetitionContext()`.
 *
 * Join → the response is the participant projection: the context switches to it (so the parent
 * re-renders as participant and subscribes to the participant channel) and a toast confirms it.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'invitee' ? ctx.competition.value as InviteeCompetition : null))
/** `details.access` from a `plan_required` join answer, until the next refetch. */
const accessOverride = ref<ParticipationAccess | null>(null)
const access = computed(() => accessOverride.value ?? competition.value?.access ?? null)

const joinOpen = ref(false)
const declineOpen = ref(false)
const declining = ref(false)
const declineError = ref<string | null>(null)

watch(() => competition.value?.access, () => {
  accessOverride.value = null
})

function onJoined(next: Competition): void {
  ctx.competition.value = next
  if (next.viewer_role !== 'invitee' && next.live) ctx.applyLive(next.live)
  toast.success(t('invitations.join.joined'))
}

function onPlanRequired(next: ParticipationAccess | null): void {
  if (next) accessOverride.value = next
}

async function decline(reason: string | null): Promise<void> {
  const invitation = competition.value?.invitation
  if (!invitation) return
  declining.value = true
  declineError.value = null
  try {
    await declineInvitation(invitation.id, reason)
    declineOpen.value = false
    toast.success(t('invitations.decline.done_title'))
    await ctx.refetch()
  }
  catch (error) {
    declineError.value = error instanceof ApiError && error.code === 'invalid_state_transition'
      ? t('invitations.decline.not_possible')
      : message(error)
    if (error instanceof ApiError && error.code === 'invalid_state_transition') void ctx.refetch()
  }
  finally {
    declining.value = false
  }
}
</script>

<template>
  <div
    v-if="competition && access"
    class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start"
    data-testid="invitee-view"
  >
    <div class="flex min-w-0 flex-col gap-4">
      <UiCard v-if="competition.schedule.bidding_opens_at || access.coverage === 'sponsored'">
        <InvitationsTeaser
          :competition="competition"
          :sponsored="access.coverage === 'sponsored'"
          :sponsor-name="access.sponsor_name"
          embedded
        />
      </UiCard>
      <UiCard
        v-if="competition.rules_summary.length > 0"
        :title="t('competitions.participant.overview.rules_title')"
      >
        <CompetitionsRulesSummary :lines="competition.rules_summary" />
      </UiCard>
      <CompetitionsParticipantAttachments
        v-if="competition.invitation_documents.length > 0"
        :attachments="competition.invitation_documents"
        :title="t('competitions.participant.attachments.invitation_title')"
      />
    </div>

    <div class="flex min-w-0 flex-col gap-4">
      <CompetitionsParticipantAccessStateCard
        :competition-id="competition.id"
        :state="access.state"
        :coverage="access.coverage"
        :sponsor-name="access.sponsor_name"
        :join-deadline="access.join_deadline"
        :invitation-status="competition.invitation.status"
        :competition-status="competition.status"
        :can-join="competition.permissions.can_join && access.state === 'join_required'"
        :can-decline="competition.permissions.can_decline"
        @join="joinOpen = true"
        @decline="declineOpen = true"
      />
    </div>

    <CompetitionsParticipantJoinDialog
      v-model:open="joinOpen"
      :invitation-id="competition.invitation.id"
      :competition-id="competition.id"
      :rules-summary="competition.rules_summary"
      @joined="onJoined"
      @plan-required="onPlanRequired"
      @stale="ctx.refetch"
    />
    <InvitationsDeclineDialog
      v-model:open="declineOpen"
      :busy="declining"
      :error="declineError"
      @confirm="decline"
    />
  </div>
</template>
