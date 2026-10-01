<script setup lang="ts">
import { FileDown, Search } from '@lucide/vue'
import { listIssuerCompetitions } from '~/services/competitions'
import type { IssuerCompetitionListItem } from '~/types/api/competitions'
import type { CreateExportRequest, ExportFormat, ExportType } from '~/types/api/integrations'
import type { ChoiceOption } from '~/types/ui'

/**
 * `ExportForm` (SCREENS W42; ARCHITECTURE §14.8): type (results, offer log, awards, vendors), format,
 * the issuer competition for results and offer log (`GET /competitions?role=issuer&q=`), and an
 * optional date range for awards.
 */
const props = withDefaults(defineProps<{
  busy?: boolean
  initialType?: ExportType
  formError?: string | null
  serverErrors?: Record<string, string>
}>(), {
  initialType: 'results',
  serverErrors: () => ({}),
})

const emit = defineEmits<{ submit: [request: CreateExportRequest, label: string | null] }>()
const { t } = useI18n()
const { message } = useErrorMessage()
const fromId = useId()
const toId = useId()

const type = ref<ExportType>(props.initialType)
const format = ref<ExportFormat>('xlsx')
const query = ref('')
const competitions = ref<IssuerCompetitionListItem[]>([])
const searching = ref(false)
const searchError = ref<string | null>(null)
const competition = ref<IssuerCompetitionListItem | null>(null)
const from = ref('')
const to = ref('')
const submitted = ref(false)

const needsCompetition = computed(() => type.value === 'results' || type.value === 'offer_log')

const typeOptions = computed<ChoiceOption<ExportType>[]>(() => (['results', 'offer_log', 'awards', 'vendors'] as const).map(value => ({
  value,
  label: t(`integrations.exports.types.${value}.label`),
  description: t(`integrations.exports.types.${value}.description`),
})))

const formatOptions = computed<ChoiceOption<ExportFormat>[]>(() => [
  { value: 'xlsx', label: t('integrations.exports.formats.xlsx') },
  { value: 'csv', label: t('integrations.exports.formats.csv') },
])

const typeModel = computed<ExportType | null>({ get: () => type.value, set: value => value && (type.value = value) })
const formatModel = computed<ExportFormat | null>({ get: () => format.value, set: value => value && (format.value = value) })

let seq = 0
async function search(): Promise<void> {
  const current = ++seq
  searching.value = true
  searchError.value = null
  try {
    const page = await listIssuerCompetitions({ q: query.value, per_page: 10 })
    if (current === seq) competitions.value = page.items
  }
  catch (error) {
    if (current === seq) searchError.value = message(error)
  }
  finally {
    if (current === seq) searching.value = false
  }
}

watch(query, () => void search())
watch(needsCompetition, (value) => {
  if (value && competitions.value.length === 0 && !searching.value) void search()
}, { immediate: true })

const competitionError = computed(() => props.serverErrors.competition_id ?? (submitted.value && needsCompetition.value && !competition.value ? t('integrations.exports.form.competition_required') : null))
const rangeError = computed(() => {
  if (props.serverErrors.from || props.serverErrors.to) return props.serverErrors.from ?? props.serverErrors.to ?? null
  return from.value && to.value && from.value > to.value ? t('integrations.exports.form.range_invalid') : null
})

function onSubmit(): void {
  submitted.value = true
  if (competitionError.value || rangeError.value || props.busy) return
  const request: CreateExportRequest = { type: type.value, format: format.value }
  if (needsCompetition.value && competition.value) request.competition_id = competition.value.id
  if (type.value === 'awards') {
    if (from.value) request.from = from.value
    if (to.value) request.to = to.value
  }
  emit('submit', request, needsCompetition.value ? (competition.value?.title ?? null) : null)
}
</script>

<template>
  <form
    class="flex flex-col gap-5"
    novalidate
    @submit.prevent="onSubmit"
  >
    <UiAlert
      v-if="formError"
      tone="danger"
      role="alert"
    >
      {{ formError }}
    </UiAlert>

    <UiRadioGroup
      v-model="typeModel"
      :options="typeOptions"
      :label="t('integrations.exports.form.type_label')"
      required
    />

    <UiSegmented
      v-model="formatModel"
      :options="formatOptions"
      :label="t('integrations.exports.form.format_label')"
    />

    <!-- Competition (results, offer log) -->
    <fieldset
      v-if="needsCompetition"
      class="flex min-w-0 flex-col gap-3"
    >
      <legend class="mb-1 text-sm font-semibold text-fg">
        {{ t('integrations.exports.form.competition_label') }}
        <span
          class="text-danger"
          aria-hidden="true"
        >*</span>
      </legend>
      <UiSearchInput
        v-model="query"
        :label="t('integrations.exports.form.competition_search')"
        :placeholder="t('integrations.exports.form.competition_search')"
      />
      <p class="text-sm text-fg-muted">
        {{ t('integrations.exports.form.competition_hint') }}
      </p>
      <div
        class="max-h-72 overflow-y-auto rounded-md border border-line"
        :aria-busy="searching || undefined"
      >
        <p
          v-if="searchError"
          class="p-4 text-sm text-danger"
          role="alert"
        >
          {{ searchError }}
        </p>
        <div
          v-else-if="searching && competitions.length === 0"
          class="flex flex-col gap-2 p-4"
        >
          <UiSkeleton class="h-4 w-2/3" />
          <UiSkeleton class="h-4 w-1/2" />
        </div>
        <p
          v-else-if="competitions.length === 0"
          class="flex items-center gap-2 p-4 text-sm text-fg-muted"
        >
          <Search
            :size="16"
            aria-hidden="true"
          />
          {{ t('integrations.exports.form.no_competitions') }}
        </p>
        <ul
          v-else
          class="divide-y divide-line"
        >
          <li
            v-for="item in competitions"
            :key="item.id"
          >
            <label class="flex min-h-11 cursor-pointer items-start gap-3 px-4 py-3 hover:bg-surface-muted">
              <input
                type="radio"
                name="export-competition"
                class="mt-1 size-4 shrink-0 accent-primary"
                :value="item.id"
                :checked="competition?.id === item.id"
                @change="competition = item"
              >
              <span class="flex min-w-0 flex-col gap-0.5">
                <span class="font-semibold text-fg">{{ item.title }}</span>
                <span class="flex flex-wrap items-center gap-2 text-xs text-fg-muted">
                  <bdi v-if="item.reference_no">{{ item.reference_no }}</bdi>
                  <span>{{ t(`competitions.direction.${item.direction}`) }}</span>
                  <span>{{ t(`competitions.status.${item.status}`) }}</span>
                </span>
              </span>
            </label>
          </li>
        </ul>
      </div>
      <p
        v-if="competitionError"
        class="text-sm font-medium text-danger"
        role="alert"
      >
        {{ competitionError }}
      </p>
    </fieldset>

    <!-- Date range (awards) -->
    <fieldset
      v-if="type === 'awards'"
      class="flex min-w-0 flex-col gap-3"
    >
      <legend class="mb-1 text-sm font-semibold text-fg">
        {{ t('integrations.exports.form.range_label') }}
      </legend>
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField
          :id="fromId"
          v-slot="{ describedby }"
          :label="t('integrations.exports.form.from_label')"
        >
          <input
            :id="fromId"
            v-model="from"
            type="date"
            dir="ltr"
            class="h-11 rounded-md border border-line-strong bg-surface px-3 text-start text-fg focus-visible:outline-2 focus-visible:outline-ring"
            :aria-describedby="describedby"
          >
        </UiField>
        <UiField
          :id="toId"
          v-slot="{ describedby }"
          :label="t('integrations.exports.form.to_label')"
          :error="rangeError"
        >
          <input
            :id="toId"
            v-model="to"
            type="date"
            dir="ltr"
            class="h-11 rounded-md border border-line-strong bg-surface px-3 text-start text-fg focus-visible:outline-2 focus-visible:outline-ring"
            :aria-describedby="describedby"
            :aria-invalid="Boolean(rangeError) || undefined"
          >
        </UiField>
      </div>
      <p class="text-sm text-fg-muted">
        {{ t('integrations.exports.form.range_hint') }}
      </p>
    </fieldset>

    <p class="rounded-md bg-surface-muted p-3 text-sm text-fg-muted">
      {{ t('integrations.exports.note') }}
    </p>

    <div class="flex justify-end">
      <UiButton
        type="submit"
        :icon="FileDown"
        :loading="busy"
      >
        {{ t('integrations.exports.form.submit') }}
      </UiButton>
    </div>
  </form>
</template>
