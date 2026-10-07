<script setup lang="ts">
/**
 * Wizard step 1 «النوع والأساسيات» (RELEASE_SCOPE.md §2.1): the direction cards (and the format cards
 * when `sealed_format` is on) from `StepType`, then the basics from `StepBasics`. The BAFO switch and
 * the preset cards live in the rules step now. `StepType` owns the direction-change confirmation on an
 * existing draft.
 */
defineProps<{
  /** Show every hint of the basics (after a submit attempt). */
  showAll?: boolean
}>()

const features = useFeatures()
</script>

<template>
  <div class="flex flex-col gap-8">
    <CompetitionsWizardStepType
      :show-format="features.enabled('sealed_format')"
      :show-bafo="false"
      :show-presets="false"
    />
    <CompetitionsWizardStepBasics :show-all="showAll" />
  </div>
</template>
