<script setup lang="ts">
import { BadgeCheck } from '@lucide/vue'
import type { CompetitionTeaser } from '~/types/api/competitions'

/**
 * `InvitationTeaser` (SCREENS W03, W14 invitee view): issuer with the verified badge, title,
 * direction and format chips, category, region, join deadline with a server-time countdown, the
 * masked invited e-mail, and the fees-covered badge for covered invitations. Shows only what the
 * teaser projection carries (no participant data, no prices).
 *
 * `embedded`: inside the competition detail (W14 invitee view), whose header already shows the
 * issuer, reference, title, chips, category, region, the fees-covered badge and the join countdown,
 * only the rest is shown.
 */
defineProps<{
  competition: CompetitionTeaser
  joinDeadline?: string | null
  maskedEmail?: string | null
  sponsored?: boolean
  sponsorName?: string | null
  embedded?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-5">
    <div
      v-if="!embedded"
      class="flex items-center gap-3"
    >
      <UiOrgLogo
        :name="competition.issuer.name"
        :src="competition.issuer.logo_url"
        size="md"
      />
      <div class="min-w-0">
        <p class="text-xs text-fg-muted">
          {{ t('glossary.issuer') }}
        </p>
        <p class="flex items-center gap-1 font-bold text-fg">
          <span class="truncate">{{ competition.issuer.name }}</span>
          <BadgeCheck
            v-if="competition.issuer.verified"
            :size="16"
            class="shrink-0 text-brand"
            role="img"
            :aria-label="t('organization.verified')"
          />
        </p>
      </div>
    </div>

    <div
      v-if="!embedded"
      class="flex flex-col gap-3"
    >
      <p
        v-if="competition.reference_no"
        class="text-sm text-fg-muted"
      >
        <bdi>{{ competition.reference_no }}</bdi>
      </p>
      <h2 class="text-xl font-bold text-balance text-fg sm:text-2xl">
        {{ competition.title }}
      </h2>
      <div class="flex flex-wrap items-center gap-2">
        <CompetitionsDirectionChip :direction="competition.direction" />
        <CompetitionsFormatChip :format="competition.format" />
        <InvitationsFeesCoveredBadge v-if="sponsored" />
      </div>
    </div>

    <dl
      v-if="!embedded || competition.schedule.bidding_opens_at || maskedEmail"
      class="grid grid-cols-1 gap-4 rounded-lg bg-surface-muted p-4 text-sm sm:grid-cols-2"
    >
      <div v-if="!embedded">
        <dt class="text-fg-muted">
          {{ t('glossary.category') }}
        </dt>
        <dd class="mt-0.5 font-semibold text-fg">
          {{ competition.category.name }}
        </dd>
      </div>
      <div v-if="!embedded">
        <dt class="text-fg-muted">
          {{ t('invitations.landing.region') }}
        </dt>
        <dd class="mt-0.5 font-semibold text-fg">
          {{ competition.region.name }}
        </dd>
      </div>
      <div v-if="joinDeadline && !embedded">
        <dt class="text-fg-muted">
          {{ t('invitations.landing.join_before') }}
        </dt>
        <dd class="mt-0.5 flex flex-col gap-0.5 font-semibold text-fg">
          <UiDateTime
            :value="joinDeadline"
            format="deadline"
          />
          <UiCountdown
            :ends-at="joinDeadline"
            size="sm"
            :label="t('invitations.landing.join_before')"
          />
        </dd>
      </div>
      <div v-if="competition.schedule.bidding_opens_at">
        <dt class="text-fg-muted">
          {{ t('invitations.landing.opens_at') }}
        </dt>
        <dd class="mt-0.5 font-semibold text-fg">
          <UiDateTime
            :value="competition.schedule.bidding_opens_at"
            format="deadline"
          />
        </dd>
      </div>
      <div v-if="maskedEmail">
        <dt class="text-fg-muted">
          {{ t('invitations.landing.invited_email') }}
        </dt>
        <dd class="mt-0.5 font-semibold text-fg">
          <bdi>{{ maskedEmail }}</bdi>
        </dd>
      </div>
    </dl>

    <p
      v-if="sponsored"
      class="rounded-md border border-info/25 bg-info-soft p-3 text-sm text-info-soft-fg"
    >
      {{ t('invitations.landing.sponsored_line', { issuer: sponsorName ?? competition.issuer.name }) }}
    </p>
  </div>
</template>
