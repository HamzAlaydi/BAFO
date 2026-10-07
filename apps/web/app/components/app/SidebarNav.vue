<script setup lang="ts">
import { Plus } from '@lucide/vue'
import { CREATE_COMPETITION_ACTION, DASHBOARD_NAV } from '~/config/navigation'

/**
 * Dashboard side navigation (SCREENS §2.3): entries the user cannot use are hidden (CD8), and so are
 * entries whose release-scope feature is off (RELEASE_SCOPE.md §5). The primary "Create competition"
 * action sits above (S10): hidden without `competitions.create`, disabled with a plan explanation
 * without `can_issue`. The current entry follows the URL, or `useNavHighlight()` where one URL serves
 * several entries (the competition detail).
 */
const emit = defineEmits<{ navigate: [] }>()
const { t } = useI18n()
const route = useRoute()
const localePath = useLocalePath()
const auth = useAuthStore()
const features = useFeatures()
const createHintId = useId()

const groups = computed(() => DASHBOARD_NAV
  .map(group => ({ ...group, items: group.items.filter(item => (!item.permission || auth.can(item.permission)) && features.anyEnabled(item.feature)) }))
  .filter(group => group.items.length > 0))

const canCreate = computed(() => auth.can(CREATE_COMPETITION_ACTION.permission))
const canIssue = computed(() => auth.entitlements?.can_issue === true)
const highlight = useNavHighlight()

function isActive(to: string): boolean {
  if (highlight.value) return to === highlight.value
  const target = localePath(to)
  return to === '/dashboard' ? route.path === target : route.path === target || route.path.startsWith(`${target}/`)
}
</script>

<template>
  <nav
    :aria-label="t('nav.main')"
    class="flex flex-col gap-5"
  >
    <div
      v-if="canCreate"
      class="flex flex-col gap-1.5 px-1"
    >
      <UiButton
        :to="canIssue ? CREATE_COMPETITION_ACTION.to : undefined"
        :icon="Plus"
        :disabled="!canIssue"
        :aria-describedby="canIssue ? undefined : createHintId"
        block
        @click="emit('navigate')"
      >
        {{ t('nav.create_competition') }}
      </UiButton>
      <p
        v-if="!canIssue"
        :id="createHintId"
        class="px-1 text-xs text-fg-muted"
      >
        {{ t('nav.create_competition_requires_plan') }}
        <NuxtLinkLocale
          to="/dashboard/billing/plans"
          class="link"
          @click="emit('navigate')"
        >
          {{ t('nav.view_plans') }}
        </NuxtLinkLocale>
      </p>
    </div>

    <div
      v-for="group in groups"
      :key="group.key ?? 'root'"
      class="flex flex-col gap-1"
    >
      <p
        v-if="group.key"
        class="px-3 text-xs font-semibold text-fg-muted"
      >
        {{ t(`nav.groups.${group.key}`) }}
      </p>
      <ul class="flex flex-col gap-0.5">
        <li
          v-for="item in group.items"
          :key="item.key"
        >
          <NuxtLinkLocale
            :to="item.to"
            class="flex h-10 items-center gap-3 rounded-md px-3 text-[0.9375rem] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            :class="isActive(item.to) ? 'bg-primary-soft text-primary-soft-fg' : 'text-fg-muted hover:bg-surface-muted hover:text-fg'"
            :aria-current="isActive(item.to) ? 'page' : undefined"
            @click="emit('navigate')"
          >
            <component
              :is="item.icon"
              :size="18"
              aria-hidden="true"
            />
            {{ t(`nav.${item.key}`) }}
          </NuxtLinkLocale>
        </li>
      </ul>
    </div>
  </nav>
</template>
