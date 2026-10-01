<script setup lang="ts">
import { BadgeCheck, ChevronRight, CreditCard, LogIn, XCircle } from '@lucide/vue'
import type { ParticipantCompetitionListItem } from '~/types/api/competitions'

/**
 * `CompetitionCard` for W23 Participating (SCREENS §2.4): issuer, title, direction and format,
 * status chip (+ overlay pills), access chip, fees covered (sponsored invitations only), my offer,
 * a standing chip when the server projected `is_leading`, a server-time countdown (the join
 * deadline while the invitation waits; the close while live) and the result. Cards needing action
 * show Join / Decline (or View plans), the same dialogs as W14.
 */
const props = defineProps<{
  item: ParticipantCompetitionListItem
}>()

const emit = defineEmits<{ join: [], decline: [] }>()
const { t } = useI18n()

const detailTo = computed(() => `/dashboard/competitions/${props.item.id}`)
const actionable = computed(() => needsAction(props.item))
const countdown = computed(() => participatingCountdownOf(props.item))
const joined = computed(() => props.item.access.state === 'full' || props.item.access.state === 'read_only')
const plansTo = computed(() => ({ path: '/dashboard/billing/plans', query: { return: `/dashboard/competitions/${props.item.id}` } }))
const canDecline = computed(() => actionable.value && (props.item.invitation.status === 'sent' || props.item.invitation.status === 'viewed'))

const countdownLabel = computed(() => {
  switch (countdown.value?.kind) {
    case 'join': return t('invitations.landing.join_before')
    case 'opens': return t('live.countdown.opens')
    default: return t('live.countdown.closes')
  }
})
</script>

<template>
  <article
    class="flex flex-col gap-4 rounded-lg border bg-surface p-4 shadow-xs sm:p-5"
    :class="actionable ? 'border-info/40' : 'border-line'"
    :data-testid="`participating-card-${item.id}`"
    :data-needs-action="actionable || undefined"
  >
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <UiOrgLogo
          :name="item.issuer.name"
          :src="item.issuer.logo_url"
          size="sm"
        />
        <div class="min-w-0">
          <p class="flex items-center gap-1 text-sm font-semibold text-fg">
            <span class="truncate">{{ item.issuer.name }}</span>
            <BadgeCheck
              v-if="item.issuer.verified"
              :size="14"
              class="shrink-0 text-brand"
              role="img"
              :aria-label="t('organization.verified')"
            />
          </p>
          <p
            v-if="item.reference_no"
            class="text-xs text-fg-muted"
          >
            <bdi>{{ item.reference_no }}</bdi>
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-1.5">
        <CompetitionsStatusChip
          :status="item.status"
          :phase="item.phase"
          :effective-close-at="item.schedule.effective_close_at"
          size="sm"
        />
        <CompetitionsParticipantAccessChip
          :state="item.access.state"
          size="sm"
        />
      </div>
    </header>

    <div class="flex flex-col gap-2">
      <h3 class="text-base font-bold text-balance text-fg sm:text-lg">
        <NuxtLinkLocale
          v-if="detailTo"
          :to="detailTo"
          class="rounded-sm hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
          {{ item.title }}
        </NuxtLinkLocale>
        <template v-else>
          {{ item.title }}
        </template>
      </h3>
      <div class="flex flex-wrap items-center gap-2">
        <CompetitionsDirectionChip
          :direction="item.direction"
          size="sm"
        />
        <CompetitionsFormatChip
          :format="item.format"
          size="sm"
        />
        <InvitationsFeesCoveredBadge
          v-if="item.access.coverage === 'sponsored' && item.access.state !== 'unavailable'"
          :sponsor-name="item.access.sponsor_name"
          size="sm"
        />
      </div>
    </div>

    <dl class="grid grid-cols-1 gap-3 border-t border-line pt-3 text-sm xs:grid-cols-2 md:grid-cols-3">
      <div v-if="joined">
        <dt class="text-fg-muted">
          {{ t('competitions.participating.card.my_offer') }}
        </dt>
        <dd class="mt-0.5 flex flex-wrap items-center gap-2 font-semibold text-fg">
          <UiAmount
            v-if="item.my_offer_amount_minor !== null"
            :minor="item.my_offer_amount_minor"
          />
          <span
            v-else
            class="font-normal text-fg-muted"
          >{{ t('competitions.participating.card.no_offer') }}</span>
          <CompetitionsParticipantStandingChip
            v-if="item.is_leading !== null"
            :leading="item.is_leading"
          />
        </dd>
      </div>
      <div v-if="countdown">
        <dt class="text-fg-muted">
          {{ countdownLabel }}
        </dt>
        <dd class="mt-0.5 flex flex-col">
          <UiCountdown
            :ends-at="countdown.target"
            :label="countdownLabel"
            size="sm"
          />
          <UiDateTime
            :value="countdown.target"
            format="deadline"
            class="text-xs text-fg-muted"
          />
        </dd>
      </div>
      <div v-if="item.result.outcome">
        <dt class="text-fg-muted">
          {{ t('competitions.participating.card.result') }}
        </dt>
        <dd class="mt-1">
          <CompetitionsParticipantOutcomeChip
            :outcome="item.result.outcome"
            size="sm"
          />
        </dd>
      </div>
    </dl>

    <footer
      v-if="actionable || detailTo"
      class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
    >
      <template v-if="actionable">
        <UiButton
          v-if="item.access.state === 'join_required'"
          size="sm"
          :icon="LogIn"
          :flip-icons="true"
          data-testid="card-join"
          @click="emit('join')"
        >
          {{ t('invitations.join.open') }}
        </UiButton>
        <UiButton
          v-else-if="plansTo"
          size="sm"
          :to="plansTo"
          :icon="CreditCard"
        >
          {{ t('invitations.access_card.view_plans') }}
        </UiButton>
        <p
          v-else
          class="text-sm text-fg-muted"
        >
          {{ t('invitations.access_card.plan_required') }}
        </p>
        <UiButton
          v-if="canDecline"
          size="sm"
          variant="danger-ghost"
          :icon="XCircle"
          data-testid="card-decline"
          @click="emit('decline')"
        >
          {{ t('invitations.decline.open') }}
        </UiButton>
      </template>
      <UiButton
        v-if="detailTo"
        :to="detailTo"
        size="sm"
        variant="ghost"
        :icon-end="ChevronRight"
        :flip-icons="true"
        class="sm:ms-auto"
      >
        {{ t('competitions.participating.card.open') }}
      </UiButton>
    </footer>
  </article>
</template>
