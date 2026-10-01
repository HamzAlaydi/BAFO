<script setup lang="ts">
import { Activity } from '@lucide/vue'
import type { HomeActivity } from '~/types/api/competitions'

/**
 * Recent organisation activity (`Home.activities`, API.md §2.13): rendered with
 * `home.activity.<action with . → _>`, relative time, and a link to the subject when its page exists.
 */
defineProps<{ activities: HomeActivity[] }>()

const { t, te } = useI18n()

const KNOWN = new Set([
  'competition.created',
  'competition.published',
  'competition.cancelled',
  'competition.closed',
  'award.issued',
  'invitation.joined',
  'member.added',
  'subscription.activated',
])

function text(activity: HomeActivity): string {
  const key = `home.activity.${activity.action.replace(/\./g, '_')}`
  const params = { actor: activity.actor?.name ?? t('home.activity.someone'), title: activity.subject?.title ?? '' }
  return KNOWN.has(activity.action) && te(key) ? t(key, params) : t('home.activity.other', params)
}

function link(activity: HomeActivity): string | null {
  if (activity.subject?.type === 'competition') return `/dashboard/competitions/${activity.subject.id}`
  if (activity.action === 'member.added') return '/dashboard/team'
  if (activity.action === 'subscription.activated') return '/dashboard/billing'
  return null
}
</script>

<template>
  <UiEmptyState
    v-if="activities.length === 0"
    :icon="Activity"
    :title="t('home.activity.empty.title')"
    :description="t('home.activity.empty.body')"
    compact
  />
  <ol
    v-else
    class="flex flex-col"
  >
    <li
      v-for="activity in activities"
      :key="activity.id"
      class="flex gap-3 border-b border-line py-3 last:border-b-0"
    >
      <span
        class="mt-2 size-2 shrink-0 rounded-full bg-brand"
        aria-hidden="true"
      />
      <div class="min-w-0 flex-1">
        <NuxtLinkLocale
          v-if="link(activity)"
          :to="link(activity)!"
          class="text-sm text-fg hover:underline focus-visible:outline-2 focus-visible:outline-ring"
        >
          {{ text(activity) }}
        </NuxtLinkLocale>
        <p
          v-else
          class="text-sm text-fg"
        >
          {{ text(activity) }}
        </p>
        <UiRelativeTime
          :value="activity.occurred_at"
          class="mt-0.5 block text-xs text-fg-muted"
        />
      </div>
    </li>
  </ol>
</template>
