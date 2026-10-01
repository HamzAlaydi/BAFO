<script setup lang="ts">
import { DESCRIPTION_MAX, OTHER_TEXT_MAX, TITLE_MAX } from '~/stores/competition-editor'

/**
 * Wizard step 2 «البيانات الأساسية» (SCREENS W12 step 2, W15 `basics`): title, description, category
 * (auction-disallowed categories are disabled for auctions; "Other" asks for its text) and region.
 * Client hints show after a blur or a submit attempt; server errors bind by path.
 */
const props = defineProps<{
  /** Show every hint (after a submit attempt). */
  showAll?: boolean
}>()

const { t } = useI18n()
const editor = useCompetitionEditorStore()
const lookups = useLookupsStore()
const touched = ref(new Set<string>())

onMounted(() => {
  void lookups.ensureLoaded().catch(() => {})
})

function touch(field: string): void {
  touched.value = new Set([...touched.value, field])
}

function errorFor(field: string): string | null {
  const server = editor.serverFieldErrors[field]
  if (server) return server
  if (!props.showAll && !touched.value.has(field)) return null
  const issue = editor.basicsIssues.find(item => item.field === field && item.when === 'save')
  return issue ? t(issue.key, issue.params ?? {}) : null
}

const categoryOptions = computed(() => lookups.categories.map(category => ({
  value: category.id,
  label: category.name,
  disabled: editor.form.direction === 'auction' && !category.auction_allowed,
})))

const regionOptions = computed(() => lookups.regions.map(region => ({ value: region.id, label: region.name })))

const title = computed({ get: () => editor.form.title, set: value => editor.update('title', value) })
const description = computed({ get: () => editor.form.description, set: value => editor.update('description', value) })
const categoryId = computed<string | null>({ get: () => editor.form.category_id, set: value => editor.update('category_id', value) })
const otherText = computed({ get: () => editor.form.category_other_text, set: value => editor.update('category_other_text', value) })
const regionId = computed<string | null>({ get: () => editor.form.region_id, set: value => editor.update('region_id', value) })
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiAlert
      v-if="lookups.error && lookups.categories.length === 0"
      tone="warning"
    >
      {{ t('common.lookups_failed') }}
    </UiAlert>

    <UiInput
      v-model="title"
      :label="t('competitions.setup.basics.title_label')"
      :hint="t('competitions.setup.basics.title_hint')"
      :error="errorFor('title')"
      :maxlength="TITLE_MAX"
      required
      @blur="touch('title')"
    />

    <UiTextarea
      v-model="description"
      :label="t('competitions.setup.basics.description_label')"
      :hint="t('competitions.setup.basics.description_hint')"
      :error="errorFor('description')"
      :maxlength="DESCRIPTION_MAX"
      :rows="8"
      @blur="touch('description')"
    />

    <div class="grid gap-6 md:grid-cols-2">
      <UiSelect
        v-model="categoryId"
        :options="categoryOptions"
        :label="t('competitions.setup.basics.category_label')"
        :hint="editor.form.direction === 'auction' ? t('competitions.setup.basics.category_auction_hint') : undefined"
        :placeholder="t('common.select_placeholder')"
        :error="errorFor('category_id')"
        required
        @blur="touch('category_id')"
      />
      <UiSelect
        v-model="regionId"
        :options="regionOptions"
        :label="t('competitions.setup.basics.region_label')"
        :placeholder="t('common.select_placeholder')"
        :error="errorFor('region_id')"
        required
        @blur="touch('region_id')"
      />
    </div>

    <UiInput
      v-if="editor.category?.is_other"
      v-model="otherText"
      :label="t('competitions.setup.basics.other_text_label')"
      :error="errorFor('category_other_text')"
      :maxlength="OTHER_TEXT_MAX"
      required
      @blur="touch('category_other_text')"
    />
  </div>
</template>
