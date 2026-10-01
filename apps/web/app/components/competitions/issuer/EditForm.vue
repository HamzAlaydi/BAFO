<script setup lang="ts">
import { Lock } from '@lucide/vue'
import { updateCompetition } from '~/services/competitions'
import type { IssuerCompetition, UpdateCompetitionRequest } from '~/types/api/competitions'
import { DESCRIPTION_MAX, OTHER_TEXT_MAX, TITLE_MAX } from '~/stores/competition-editor'

/**
 * W16 Edit after publish (SCREENS §2.4, API.md §1.4 PATCH): `scheduled` → title, description,
 * category (+ other text), region, opens and closes; `live` → title and description. Only changed,
 * allowed fields are sent. The rules are shown as fixed; participants are notified of the update.
 * `competition_not_editable` lists the refused fields (`details.fields`).
 */
const props = defineProps<{ competition: IssuerCompetition }>()
const emit = defineEmits<{ saved: [competition: IssuerCompetition] }>()

const { t } = useI18n()
const toast = useToast()
const lookups = useLookupsStore()
const { message } = useErrorMessage()

const scheduled = computed(() => props.competition.status === 'scheduled')
const editable = computed(() => props.competition.permissions.can_edit && ['scheduled', 'live'].includes(props.competition.status))

const title = ref(props.competition.title)
const description = ref(props.competition.description ?? '')
const categoryId = ref<string | null>(props.competition.category?.id ?? null)
const otherText = ref(props.competition.category_other_text ?? '')
const regionId = ref<string | null>(props.competition.region?.id ?? null)
const opensAt = ref<string | null>(props.competition.schedule.bidding_opens_at)
const closeAt = ref<string | null>(props.competition.schedule.scheduled_close_at)
const busy = ref(false)
const formError = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const refused = ref<string[]>([])

onMounted(() => {
  void lookups.ensureLoaded().catch(() => {})
})

const category = computed(() => lookups.categoryById(categoryId.value))
const categoryOptions = computed(() => lookups.categories.map(item => ({
  value: item.id,
  label: item.name,
  disabled: props.competition.direction === 'auction' && !item.auction_allowed,
})))
const regionOptions = computed(() => lookups.regions.map(item => ({ value: item.id, label: item.name })))

const payload = computed<UpdateCompetitionRequest>(() => {
  const c = props.competition
  const body: UpdateCompetitionRequest = {}
  if (title.value.trim() !== c.title) body.title = title.value.trim()
  if (description.value.trim() !== (c.description ?? '').trim()) body.description = description.value.trim() || null
  if (scheduled.value) {
    if (categoryId.value && categoryId.value !== c.category?.id) body.category_id = categoryId.value
    const other = category.value?.is_other ? otherText.value.trim() || null : null
    if (other !== (c.category_other_text ?? null) || body.category_id) body.category_other_text = other
    if (regionId.value && regionId.value !== c.region?.id) body.region_id = regionId.value
    if (opensAt.value !== c.schedule.bidding_opens_at) body.bidding_opens_at = opensAt.value
    if (closeAt.value !== c.schedule.scheduled_close_at) body.scheduled_close_at = closeAt.value
  }
  return body
})
const dirty = computed(() => Object.keys(payload.value).length > 0)
useUnsavedChangesGuard(dirty)

const titleError = computed(() => fieldErrors.value.title ?? (!title.value.trim() ? t('competitions.setup.basics.issues.title_required') : null))
const descriptionError = computed(() => fieldErrors.value.description ?? (!description.value.trim() ? t('competitions.setup.basics.issues.description_required') : null))

async function save(): Promise<void> {
  if (!dirty.value || titleError.value || descriptionError.value) return
  busy.value = true
  formError.value = null
  fieldErrors.value = {}
  refused.value = []
  try {
    const updated = await updateCompetition(props.competition.id, payload.value)
    emit('saved', updated)
    toast.success(t('competitions.issuer.edit.saved'))
  }
  catch (error) {
    if (error instanceof ApiError && error.isValidation) {
      fieldErrors.value = Object.fromEntries(Object.entries(error.errors).map(([key, messages]) => [key, messages[0] ?? '']))
    }
    else if (error instanceof ApiError && error.code === 'competition_not_editable') {
      const fields = error.details.fields
      refused.value = Array.isArray(fields) ? fields.filter((field): field is string => typeof field === 'string') : []
      formError.value = message(error)
    }
    else {
      formError.value = message(error)
    }
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <UiAlert
      v-if="!editable"
      tone="info"
      :icon="Lock"
    >
      {{ t('competitions.issuer.edit.not_editable') }}
    </UiAlert>
    <template v-else>
      <UiAlert tone="info">
        {{ t('competitions.issuer.edit.notice') }}
      </UiAlert>
      <UiCard>
        <form
          class="flex flex-col gap-5"
          novalidate
          @submit.prevent="save"
        >
          <UiInput
            v-model="title"
            :label="t('competitions.setup.basics.title_label')"
            :error="titleError"
            :maxlength="TITLE_MAX"
            required
          />
          <UiTextarea
            v-model="description"
            :label="t('competitions.setup.basics.description_label')"
            :error="descriptionError"
            :maxlength="DESCRIPTION_MAX"
            :rows="8"
            required
          />
          <template v-if="scheduled">
            <div class="grid gap-5 md:grid-cols-2">
              <UiSelect
                v-model="categoryId"
                :options="categoryOptions"
                :label="t('competitions.setup.basics.category_label')"
                :error="fieldErrors.category_id"
                required
              />
              <UiSelect
                v-model="regionId"
                :options="regionOptions"
                :label="t('competitions.setup.basics.region_label')"
                :error="fieldErrors.region_id"
                required
              />
            </div>
            <UiInput
              v-if="category?.is_other"
              v-model="otherText"
              :label="t('competitions.setup.basics.other_text_label')"
              :error="fieldErrors.category_other_text"
              :maxlength="OTHER_TEXT_MAX"
              required
            />
            <div class="grid gap-5 md:grid-cols-2">
              <UiDateTimePicker
                v-model="opensAt"
                :label="t('competitions.setup.schedule.opens_at')"
                :hint="t('common.datetime.hint')"
                :error="fieldErrors.bidding_opens_at"
              />
              <UiDateTimePicker
                v-model="closeAt"
                :label="t('competitions.setup.schedule.close_label')"
                :hint="t('common.datetime.hint')"
                :error="fieldErrors.scheduled_close_at"
                required
              />
            </div>
          </template>
          <p
            v-else
            class="text-sm text-fg-muted"
          >
            {{ t('competitions.issuer.edit.live_fields') }}
          </p>
          <UiAlert
            v-if="formError"
            tone="danger"
          >
            <p>{{ formError }}</p>
            <p
              v-if="refused.length"
              class="mt-1"
            >
              {{ t('competitions.issuer.edit.refused', { fields: refused.join(', ') }) }}
            </p>
          </UiAlert>
          <div class="flex justify-end">
            <UiButton
              type="submit"
              :loading="busy"
              :disabled="!dirty"
            >
              {{ t('common.actions.save_changes') }}
            </UiButton>
          </div>
        </form>
      </UiCard>
    </template>

    <UiCard :title="t('competitions.issuer.edit.rules_fixed')">
      <CompetitionsRulesSummary :lines="competition.rules_summary" />
    </UiCard>
  </div>
</template>
