<script setup lang="ts">
import { CircleCheck } from '@lucide/vue'
import type { ParticipantBafoState } from '~/types/api/bidding'
import type { CompetitionStatus, Direction } from '~/types/api/competitions'

/**
 * `BafoBanner` (SCREENS W19, S2): the best-and-final-offer round on the inverse (charcoal) surface
 * with the brand mark. Shortlisted and not yet submitted: the invitation with the cutoff, the
 * reference amount (the participant's own last offer, shown only to them) and the rule. After the
 * one-shot submit: the receipt. Not shortlisted: the last offer stands.
 */
const props = defineProps<{
  bafo: ParticipantBafoState
  direction: Direction
  status: CompetitionStatus
}>()

const { t } = useI18n()
const { td } = useDirectionCopy(() => props.direction)
const running = computed(() => props.status === 'bafo_round')
</script>

<template>
  <section
    class="flex flex-col gap-3 rounded-lg bg-fg p-4 text-fg-inverse sm:p-5"
    data-testid="bafo-banner"
    :aria-label="t('glossary.bafo_round')"
  >
    <div class="flex items-center gap-2">
      <AppBrandMark size-class="size-6" />
      <h3 class="text-base font-bold">
        {{ t('glossary.bafo_round') }}
      </h3>
    </div>

    <template v-if="!running">
      <p class="text-sm">
        {{ t('bafo.ended') }}
      </p>
    </template>
    <template v-else-if="!bafo.shortlisted">
      <p class="text-sm">
        {{ t('bafo.not_shortlisted') }}
      </p>
    </template>
    <template v-else-if="bafo.submitted">
      <p class="flex items-center gap-2 text-sm font-semibold">
        <CircleCheck
          :size="18"
          aria-hidden="true"
        />
        {{ t('bafo.submitted') }}
      </p>
    </template>
    <template v-else>
      <p class="text-sm">
        <i18n-t
          keypath="bafo.invite"
          scope="global"
        >
          <template #cutoff>
            <UiDateTime
              :value="bafo.cutoff_at"
              format="deadline"
              class="font-semibold"
            />
          </template>
        </i18n-t>
      </p>
      <p
        v-if="bafo.reference_amount_minor !== null"
        class="text-sm"
      >
        <i18n-t
          keypath="bafo.reference"
          scope="global"
        >
          <template #amount>
            <UiAmount
              :minor="bafo.reference_amount_minor"
              class="font-bold"
            />
          </template>
        </i18n-t>
      </p>
      <p class="text-sm opacity-90">
        {{ td('bafo.rule') }}
      </p>
    </template>
  </section>
</template>
