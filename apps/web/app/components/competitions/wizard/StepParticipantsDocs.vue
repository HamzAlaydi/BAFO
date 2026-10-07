<script setup lang="ts">
import { ChevronDown, Paperclip } from '@lucide/vue'
import type { IssuerCompetition } from '~/types/api/competitions'

/**
 * Wizard step 4 «المتنافسون والمستندات» (RELEASE_SCOPE.md §2.1): the invitations (`StepParticipants`),
 * the optional documents behind a collapsed disclosure «مستندات اختيارية» (`StepDocuments`, with the
 * `attachments` flag), and the covered-fees panel (`StepFees`) at the end, only when the `sponsorship`
 * flag and the organisation's feature are both on. Every part saves through its own endpoint as the
 * issuer works; Continue only navigates.
 */
const props = defineProps<{ competition: IssuerCompetition }>()
const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const auth = useAuthStore()
const features = useFeatures()
const documentsId = useId()

const showDocuments = computed(() => features.enabled('attachments'))
const showFees = computed(() => features.enabled('sponsorship') && auth.features?.sponsorship_enabled === true)
// Open by default when documents already exist, so they are not hidden from the issuer.
const documentsOpen = ref(props.competition.counts.attachments > 0)
</script>

<template>
  <div class="flex flex-col gap-8">
    <CompetitionsWizardStepParticipants
      :competition="competition"
      @changed="emit('changed')"
    />

    <UiCard
      v-if="showDocuments"
      padding="none"
    >
      <button
        type="button"
        class="flex min-h-14 w-full items-center justify-between gap-3 px-5 py-4 text-start focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring sm:px-6"
        :aria-expanded="documentsOpen"
        :aria-controls="documentsId"
        @click="documentsOpen = !documentsOpen"
      >
        <span class="flex min-w-0 items-start gap-3">
          <Paperclip
            :size="20"
            class="mt-0.5 shrink-0 text-fg-muted"
            aria-hidden="true"
          />
          <span class="flex min-w-0 flex-col gap-0.5">
            <span class="flex flex-wrap items-center gap-2">
              <span class="text-base font-bold text-fg">{{ t('competitions.setup.participants.documents_title') }}</span>
              <UiBadge
                v-if="competition.counts.attachments > 0"
                tone="neutral"
                size="sm"
              >
                {{ t('competitions.setup.review.documents', { count: competition.counts.attachments }, competition.counts.attachments) }}
              </UiBadge>
            </span>
            <span class="text-sm text-fg-muted">{{ t('competitions.setup.participants.documents_hint') }}</span>
          </span>
        </span>
        <ChevronDown
          :size="20"
          class="shrink-0 text-fg-muted transition-transform"
          :class="documentsOpen && 'rotate-180'"
          aria-hidden="true"
        />
      </button>
      <div
        v-show="documentsOpen"
        :id="documentsId"
        class="border-t border-line px-5 py-5 sm:px-6"
      >
        <CompetitionsWizardStepDocuments
          :competition="competition"
          @changed="emit('changed')"
        />
      </div>
    </UiCard>

    <section
      v-if="showFees"
      class="flex flex-col gap-4"
      :aria-label="t('competitions.setup.participants.fees_title')"
    >
      <div>
        <h3 class="text-base font-bold text-fg">
          {{ t('competitions.setup.participants.fees_title') }}
        </h3>
        <p class="mt-0.5 text-sm text-fg-muted">
          {{ t('competitions.setup.descriptions.fees') }}
        </p>
      </div>
      <CompetitionsWizardStepFees
        :competition="competition"
        @changed="emit('changed')"
      />
    </section>
  </div>
</template>
