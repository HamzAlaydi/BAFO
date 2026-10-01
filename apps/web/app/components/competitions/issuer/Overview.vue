<script setup lang="ts">
import { Ban, CircleSlash, Code2, Trophy, Wand2 } from '@lucide/vue'
import type { IssuerCompetition } from '~/types/api/competitions'
import { draftChecklist, draftProgressOf } from '~/stores/competition-editor-steps'

/**
 * W14 Overview for the **issuer** (SCREENS §2.4): the draft checklist with "Continue setup"; the
 * cancellation or not-awarded reason; the award summary; the description; the server's rules
 * summary (with `reserve_hidden`); documents (add while draft/scheduled/live, delete while
 * draft/scheduled); the schedule (estimated for drafts, real after publish); the counts; the fees
 * coverage summary; who created it (and an "API" badge when `source = api`).
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const auth = useAuthStore()
const appConfig = useAppConfigStore()
const clock = useServerTime()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const status = computed(() => ctx.live.value?.status ?? competition.value?.status ?? null)
const base = computed(() => (competition.value ? `/dashboard/competitions/${competition.value.id}` : ''))
const nowMs = ref(clock.now())
useIntervalFn(() => {
  nowMs.value = clock.now()
}, 30_000)

const feesEnabled = computed(() => auth.features?.sponsorship_enabled === true && appConfig.sponsorshipEnabled)
const checklist = computed(() => (competition.value ? draftChecklist(draftProgressOf(competition.value, { feesEnabled: feesEnabled.value, quotePassesToBuy: null }), competition.value.direction) : []))

const counts = computed(() => {
  const c = competition.value?.counts
  if (!c) return []
  return [
    { key: 'invitations', value: c.invitations },
    { key: 'joined', value: c.joined },
    { key: 'declined', value: c.declined },
    { key: 'participants_with_offers', value: c.participants_with_offers },
    { key: 'offers', value: c.offers },
    { key: 'comments', value: c.comments },
  ]
})

const leading = computed(() => {
  const snapshot = ctx.live.value
  if (snapshot && 'ranking' in snapshot) return snapshot.leader?.amount_minor ?? null
  return competition.value?.leading_amount_minor ?? null
})

function onAttachmentsChanged(): void {
  void ctx.refetch()
}
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-6"
  >
    <UiCard
      v-if="status === 'draft'"
      :title="t('competitions.issuer.overview.draft_title')"
      :description="t('competitions.issuer.overview.draft_body')"
    >
      <CompetitionsIssuerSetupChecklist
        :items="checklist"
        :competition-id="competition.id"
        :direction="competition.direction"
      />
      <template
        v-if="competition.permissions.can_edit"
        #footer
      >
        <UiButton
          :to="`${base}/setup`"
          :icon="Wand2"
        >
          {{ t('competitions.issuer.actions.continue_setup') }}
        </UiButton>
      </template>
    </UiCard>

    <UiAlert
      v-if="competition.cancellation"
      tone="danger"
      :icon="Ban"
      :title="t('competitions.issuer.overview.cancelled_title')"
    >
      <p>{{ competition.cancellation.reason.name }}</p>
      <p
        v-if="competition.cancellation.note"
        class="mt-1"
      >
        {{ competition.cancellation.note }}
      </p>
    </UiAlert>
    <UiAlert
      v-if="competition.not_awarded"
      tone="info"
      :icon="CircleSlash"
      :title="t('competitions.issuer.overview.not_awarded_title')"
    >
      <p>{{ competition.not_awarded.reason.name }}</p>
      <p
        v-if="competition.not_awarded.note"
        class="mt-1"
      >
        {{ competition.not_awarded.note }}
      </p>
    </UiAlert>

    <UiCard
      v-if="competition.award && competition.award.status === 'issued'"
      padding="sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <Trophy
            :size="22"
            class="shrink-0 text-brand"
            aria-hidden="true"
          />
          <div>
            <p class="text-sm text-fg-muted">
              {{ t('competitions.issuer.overview.awarded_to') }}
            </p>
            <p class="font-bold text-fg">
              {{ competition.award.participant.organization.name }}
              <span class="font-normal text-fg-muted">· {{ t('offers.participant_alias', { number: competition.award.participant.alias_no }) }}</span>
            </p>
          </div>
        </div>
        <UiAmount
          :minor="competition.award.amount_minor"
          size="lg"
        />
        <UiButton
          :to="`${base}/award`"
          variant="secondary"
          size="sm"
        >
          {{ t('competitions.issuer.overview.award_details') }}
        </UiButton>
      </div>
    </UiCard>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="flex min-w-0 flex-col gap-6">
        <UiCard :title="t('competitions.issuer.overview.description')">
          <p
            v-if="competition.description"
            class="text-sm leading-relaxed whitespace-pre-line text-fg"
          >
            {{ competition.description }}
          </p>
          <p
            v-else
            class="text-sm text-fg-muted"
          >
            {{ t('competitions.issuer.overview.no_description') }}
          </p>
          <p
            v-if="competition.category_other_text"
            class="mt-3 text-sm text-fg-muted"
          >
            {{ t('competitions.issuer.overview.other_category', { text: competition.category_other_text }) }}
          </p>
        </UiCard>

        <UiCard :title="t('competitions.issuer.overview.rules')">
          <CompetitionsRulesSummary :lines="competition.rules_summary" />
          <p
            v-if="competition.rules_summary.length === 0"
            class="text-sm text-fg-muted"
          >
            {{ t('competitions.issuer.overview.rules_pending') }}
          </p>
        </UiCard>

        <CompetitionsIssuerAttachmentManager
          :competition-id="competition.id"
          :status="competition.status"
          :can-manage="competition.permissions.can_edit || competition.permissions.can_invite"
          compact
          @changed="onAttachmentsChanged"
        />
      </div>

      <aside class="flex min-w-0 flex-col gap-6">
        <UiCard
          :title="t('competitions.issuer.overview.schedule')"
          padding="sm"
        >
          <CompetitionsWizardSchedulePreview
            v-if="status === 'draft'"
            :bidding-opens-at="competition.schedule.bidding_opens_at"
            :scheduled-close-at="competition.schedule.scheduled_close_at"
            :rules="competition.rules"
            :now-ms="nowMs"
          />
          <CompetitionsIssuerScheduleTimeline
            v-else
            :competition="competition"
            :effective-close-at="ctx.live.value?.effective_close_at"
            :server-now-ms="nowMs"
          />
        </UiCard>

        <UiCard
          :title="t('competitions.issuer.overview.counts')"
          padding="sm"
        >
          <dl class="grid grid-cols-2 gap-3">
            <div
              v-for="item in counts"
              :key="item.key"
              class="flex flex-col"
            >
              <dt class="text-xs text-fg-muted">
                {{ t(`competitions.issuer.counts.${item.key}`) }}
              </dt>
              <dd class="text-lg font-bold text-fg tabular-nums">
                {{ item.value }}
              </dd>
            </div>
          </dl>
          <p
            v-if="status !== 'draft'"
            class="mt-4 border-t border-line pt-3 text-sm text-fg-muted"
          >
            {{ t('glossary.leading_offer') }}
            <UiAmount
              :minor="leading"
              :empty="competition.format === 'sealed' && !competition.schedule.offers_opened_at ? t('offers.sealed_amount') : '—'"
              class="block font-bold text-fg"
            />
          </p>
        </UiCard>

        <UiCard
          v-if="competition.sponsorship"
          :title="t('sponsorship.summary.title')"
          padding="sm"
        >
          <dl class="flex flex-col gap-2 text-sm">
            <div class="flex justify-between gap-2">
              <dt class="text-fg-muted">
                {{ t('sponsorship.summary.mode') }}
              </dt>
              <dd class="text-fg">
                {{ t(`sponsorship.mode.${competition.sponsorship.mode}.title`) }}
              </dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-fg-muted">
                {{ t('sponsorship.summary.status') }}
              </dt>
              <dd class="text-fg">
                {{ t(`sponsorship.status.${competition.sponsorship.status}`) }}
              </dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-fg-muted">
                {{ t('sponsorship.summary.funded') }}
              </dt>
              <dd class="text-fg tabular-nums">
                {{ competition.sponsorship.funded_passes }}
              </dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt class="text-fg-muted">
                {{ t('sponsorship.summary.free_slots') }}
              </dt>
              <dd class="text-fg tabular-nums">
                {{ competition.sponsorship.free_slots }}
              </dd>
            </div>
          </dl>
          <NuxtLinkLocale
            :to="`${base}/participants`"
            class="link mt-3 inline-block text-sm"
          >
            {{ t('sponsorship.summary.manage') }}
          </NuxtLinkLocale>
        </UiCard>

        <p class="flex flex-wrap items-center gap-2 text-sm text-fg-muted">
          {{ t('competitions.issuer.overview.created_by', { name: competition.created_by.name }) }}
          <UiDateTime
            :value="competition.created_at"
            format="date"
          />
          <UiBadge
            v-if="competition.source === 'api'"
            tone="neutral"
            size="sm"
            :icon="Code2"
          >
            {{ t('competitions.issuer.overview.source_api') }}
          </UiBadge>
        </p>
      </aside>
    </div>
  </div>
</template>
