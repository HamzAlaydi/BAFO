<script setup lang="ts">
import { CreditCard, LogIn, XCircle } from '@lucide/vue'
import type { AccessState, CompetitionStatus, Coverage, InvitationStatus } from '~/types/api/competitions'

/**
 * `AccessStateCard` (SCREENS W14 invitee view), rendered from the server's `access` and
 * `permissions` only (ARCHITECTURE §8.4; the client never computes entitlement):
 *
 * | state / coverage             | content                                          | actions              |
 * |------------------------------|--------------------------------------------------|----------------------|
 * | join_required / sponsored    | fees-covered badge + «تغطي {sponsor} رسوم …»      | Join, Decline        |
 * | join_required / own_plan     | «تنضمون بباقتكم الحالية.»                          | Join, Decline        |
 * | plan_required                | «تحتاجون إلى باقة فعّالة …»                        | View plans, Decline  |
 * | unavailable                  | the reason (invitation or competition status)    | –                    |
 *
 * Join and Decline follow `permissions.can_join` / `can_decline` (S10); plan payment needs
 * `billing.purchase`, so users without it are told to ask the account owner.
 */
const props = defineProps<{
  competitionId: string
  state: AccessState
  coverage: Coverage
  sponsorName: string | null
  joinDeadline: string | null
  invitationStatus: InvitationStatus | null
  competitionStatus: CompetitionStatus
  canJoin: boolean
  canDecline: boolean
}>()

const emit = defineEmits<{ join: [], decline: [] }>()
const { t } = useI18n()
const auth = useAuthStore()

const body = computed(() => {
  switch (props.state) {
    case 'join_required':
      return props.coverage === 'sponsored' && props.sponsorName
        ? t('invitations.access_card.sponsored', { sponsor: props.sponsorName })
        : t('invitations.access_card.own_plan')
    case 'plan_required':
      return t('invitations.access_card.plan_required')
    case 'full':
      return t('invitations.access_card.full')
    case 'read_only':
      return t('invitations.access_card.read_only')
    default:
      return unavailableReason.value
  }
})

const unavailableReason = computed(() => {
  switch (props.invitationStatus) {
    case 'declined': return t('invitations.access_card.unavailable.declined')
    case 'expired': return t('invitations.access_card.unavailable.expired')
    case 'revoked': return t('invitations.access_card.unavailable.revoked')
    default:
      break
  }
  if (props.competitionStatus === 'cancelled') return t('invitations.access_card.unavailable.cancelled')
  if (!['scheduled', 'live'].includes(props.competitionStatus)) return t('invitations.access_card.unavailable.closed')
  return t('invitations.access_card.unavailable.generic')
})

const plansTo = computed(() => ({ path: '/dashboard/billing/plans', query: { return: `/dashboard/competitions/${props.competitionId}` } }))
const showDeadline = computed(() => (props.state === 'join_required' || props.state === 'plan_required') && Boolean(props.joinDeadline))
</script>

<template>
  <UiCard
    :title="t('invitations.access_card.title')"
    data-testid="access-state-card"
    :data-state="state"
  >
    <template #actions>
      <CompetitionsParticipantAccessChip
        :state="state"
        size="sm"
      />
    </template>
    <div class="flex flex-col gap-4">
      <InvitationsFeesCoveredBadge
        v-if="coverage === 'sponsored' && sponsorName && state !== 'unavailable'"
        :sponsor-name="sponsorName"
        class="self-start"
      />
      <p class="text-sm text-fg">
        {{ body }}
      </p>
      <div
        v-if="showDeadline"
        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
      >
        <span class="text-fg-muted">{{ t('invitations.landing.join_before') }}</span>
        <UiDateTime
          :value="joinDeadline"
          format="deadline"
          class="font-semibold text-fg"
        />
        <UiCountdown
          :ends-at="joinDeadline"
          :label="t('invitations.landing.join_before')"
          size="sm"
        />
      </div>
      <template v-if="state === 'plan_required'">
        <p
          v-if="!auth.can('billing.purchase')"
          class="text-sm text-fg-muted"
        >
          {{ t('invitations.access_card.purchase_permission') }}
        </p>
        <p
          v-else-if="!plansTo"
          class="text-sm text-fg-muted"
        >
          {{ t('invitations.access_card.plans_later') }}
        </p>
      </template>
      <div
        v-if="(state === 'join_required' && canJoin) || (state === 'plan_required' && plansTo) || canDecline"
        class="flex flex-col gap-2 sm:flex-row sm:flex-wrap"
      >
        <UiButton
          v-if="state === 'join_required' && canJoin"
          :icon="LogIn"
          :flip-icons="true"
          data-testid="join-open"
          @click="emit('join')"
        >
          {{ t('invitations.join.open') }}
        </UiButton>
        <UiButton
          v-if="state === 'plan_required' && plansTo"
          :to="plansTo"
          :icon="CreditCard"
        >
          {{ t('invitations.access_card.view_plans') }}
        </UiButton>
        <UiButton
          v-if="canDecline"
          variant="danger-ghost"
          :icon="XCircle"
          data-testid="decline-open"
          @click="emit('decline')"
        >
          {{ t('invitations.decline.open') }}
        </UiButton>
      </div>
    </div>
  </UiCard>
</template>
