<script setup lang="ts">
import { Gavel, Users } from '@lucide/vue'
import type { TabItem } from '~/types/ui'

/**
 * How it works (RELEASE_SCOPE §6.1 row 2): a tab per role, three steps each. The steps are a real
 * sequence (create → invite → award; invited → offer → result), so they are numbered and connected.
 */
type Audience = 'issuer' | 'participant'

const { t } = useI18n()
const audience = ref<Audience>('issuer')

const tabs = computed<TabItem[]>(() => [
  { key: 'issuer', label: t('landing.how.tabs.issuer'), icon: Gavel },
  { key: 'participant', label: t('landing.how.tabs.participant'), icon: Users },
])

const STEP_KEYS: Record<Audience, readonly string[]> = {
  issuer: ['create', 'invite', 'award'],
  participant: ['invited', 'offer', 'result'],
}

function steps(kind: Audience) {
  return STEP_KEYS[kind].map((key, index) => ({
    key,
    number: index + 1,
    title: t(`landing.how.steps.${kind}.${key}.title`),
    body: t(`landing.how.steps.${kind}.${key}.body`),
  }))
}
</script>

<template>
  <section
    id="how-it-works"
    class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-24"
    aria-labelledby="how-title"
  >
    <LandingSectionHeading
      id="how-title"
      :title="t('landing.how.title')"
      :subtitle="t('landing.how.subtitle')"
    />
    <div class="mt-10">
      <UiTabs
        v-model="audience"
        :items="tabs"
        :label="t('landing.how.tabs.label')"
      >
        <template #default="{ active }">
          <ol class="mt-6 grid gap-10 md:grid-cols-3 md:gap-8">
            <li
              v-for="step in steps(active as Audience)"
              :key="step.key"
              class="relative flex flex-col gap-4 md:before:absolute md:before:top-5 md:before:start-14 md:before:end-0 md:before:h-px md:before:bg-line md:last:before:hidden"
            >
              <span
                class="inline-flex size-10 items-center justify-center rounded-full bg-primary text-base font-bold text-primary-fg tabular-nums"
                aria-hidden="true"
              >{{ step.number }}</span>
              <div class="flex flex-col gap-2">
                <h3 class="text-xl font-bold text-fg">
                  {{ step.title }}
                </h3>
                <p class="max-w-prose text-fg-muted">
                  {{ step.body }}
                </p>
              </div>
            </li>
          </ol>
        </template>
      </UiTabs>
    </div>
  </section>
</template>
