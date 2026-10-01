import { createCompetition, updateCompetition } from '~/services/competitions'
import type { Preset } from '~/types/api/catalog'
import type {
  CreateCompetitionRequest,
  Direction,
  Format,
  IssuerCompetition,
  Rules,
  UpdateCompetitionRequest,
} from '~/types/api/competitions'
import {
  cloneEditorRules,
  editorRulesCustomised,
  editorRulesEqual,
  editorRulesFromPreset,
  editorRulesInput,
  editorRulesIssues,
  normalizeEditorRules,
  presetsFor,
  type EditorIssue,
} from './competition-editor-rules'
import { editorScheduleIssues } from './competition-editor-schedule'

/** The parts of a draft each wizard step saves on its own (SCREENS W15). */
export type EditorSection = 'type' | 'basics' | 'rules' | 'schedule'

/** The editable draft, with the API's field names (CONVENTIONS §4.1). */
export interface EditorForm {
  direction: Direction
  format: Format
  preset_code: string | null
  title: string
  description: string
  category_id: string | null
  category_other_text: string
  region_id: string | null
  rules: Rules
  /** UTC ISO, or null for "when published". */
  bidding_opens_at: string | null
  scheduled_close_at: string | null
}

type FormField = keyof EditorForm

const SECTION_FIELDS: Record<EditorSection, readonly FormField[]> = {
  type: ['direction', 'format', 'preset_code', 'rules'],
  basics: ['title', 'description', 'category_id', 'category_other_text', 'region_id'],
  rules: ['preset_code', 'rules'],
  schedule: ['bidding_opens_at', 'scheduled_close_at'],
}

/** Top-level rule keys: server errors on `start_price_minor` belong to `rules.start_price_minor`. */
const RULE_KEYS = new Set([
  'start_price_minor', 'reserve_price_minor', 'min_step_minor', 'min_step_bps', 'amount_granularity_minor', 'must_beat',
  'rank_visibility', 'show_prices', 'auto_extend', 'final_window_minutes', 'bafo_round', 'min_participants', 'result_publication',
])

export const TITLE_MAX = 200
export const DESCRIPTION_MAX = 20_000
export const OTHER_TEXT_MAX = 150

export function emptyEditorForm(): EditorForm {
  return {
    direction: 'tender',
    format: 'live',
    preset_code: null,
    title: '',
    description: '',
    category_id: null,
    category_other_text: '',
    region_id: null,
    rules: editorRulesFromPreset(null, 'live'),
    bidding_opens_at: null,
    scheduled_close_at: null,
  }
}

export function editorFormFromCompetition(competition: IssuerCompetition): EditorForm {
  return {
    direction: competition.direction,
    format: competition.format,
    preset_code: competition.preset_code,
    title: competition.title,
    description: competition.description ?? '',
    category_id: competition.category?.id ?? null,
    category_other_text: competition.category_other_text ?? '',
    region_id: competition.region?.id ?? null,
    rules: cloneEditorRules(competition.rules),
    bidding_opens_at: competition.schedule.bidding_opens_at,
    scheduled_close_at: competition.schedule.scheduled_close_at,
  }
}

function cloneForm(form: EditorForm): EditorForm {
  return { ...form, rules: cloneEditorRules(form.rules) }
}

function sameValue(field: FormField, a: EditorForm, b: EditorForm): boolean {
  if (field === 'rules') return editorRulesEqual(a.rules, b.rules)
  return a[field] === b[field]
}

/** Normalises a server field path to the editor's (`start_price_minor` → `rules.start_price_minor`). */
export function editorFieldPath(path: string): string {
  const head = path.split('.')[0] ?? ''
  return RULE_KEYS.has(head) ? `rules.${path}` : path
}

/**
 * The issuer's creation wizard (SCREENS CD3, W12, W15): one draft form, saved step by step.
 *
 * - Steps 1–2 run client-side; `create()` sends `POST /competitions` with the basics, the type and the
 *   full preset rules. Later steps `save(section)` with `PATCH`, always sending the **whole** `rules`
 *   object with `preset_code` (SCREENS §6 G4).
 * - Changing direction or format resets the rules to the preset of the new combination, keeping the
 *   prices (W15 step 1).
 * - Dirty tracking per section feeds the unsaved-changes guard. No optimistic state: the form takes
 *   the server's copy after every save.
 */
export const useCompetitionEditorStore = defineStore('competition-editor', () => {
  const lookups = useLookupsStore()

  const competitionId = ref<string | null>(null)
  const competition = ref<IssuerCompetition | null>(null)
  const form = ref<EditorForm>(emptyEditorForm())
  const saved = ref<EditorForm>(emptyEditorForm())
  const saving = ref(false)
  const error = ref<ApiError | null>(null)
  /** Coupon applied to the covered-fees quote (step 7), reused by "Pay and publish" (step 8). */
  const couponCode = ref<string | null>(null)

  const isNew = computed(() => competitionId.value === null)

  const preset = computed<Preset | null>(() => lookups.presets.find(item => item.code === form.value.preset_code) ?? null)
  const availablePresets = computed(() => presetsFor(lookups.presets, form.value.direction, form.value.format))
  const customised = computed(() => editorRulesCustomised(form.value.rules, preset.value, form.value.format))
  const category = computed(() => lookups.categoryById(form.value.category_id))

  function isDirty(section?: EditorSection): boolean {
    const fields = section ? SECTION_FIELDS[section] : (Object.keys(SECTION_FIELDS) as EditorSection[]).flatMap(key => SECTION_FIELDS[key])
    return fields.some(field => !sameValue(field, form.value, saved.value))
  }

  const dirty = computed(() => isDirty())

  // ---------- Loading ----------

  /** A new competition (W12): defaults to a live tender with its first preset. */
  function startNew(): void {
    competitionId.value = null
    competition.value = null
    error.value = null
    couponCode.value = null
    const next = emptyEditorForm()
    const first = presetsFor(lookups.presets, next.direction, next.format)[0] ?? null
    next.preset_code = first?.code ?? null
    next.rules = editorRulesFromPreset(first, next.format)
    form.value = next
    saved.value = cloneForm(next)
  }

  /** An existing draft (W15). */
  function load(source: IssuerCompetition): void {
    if (competitionId.value !== source.id) couponCode.value = null
    competitionId.value = source.id
    competition.value = source
    error.value = null
    form.value = editorFormFromCompetition(source)
    saved.value = cloneForm(form.value)
  }

  /**
   * Takes the server's copy after a save. Fields of the saved section, and fields without unsaved
   * edits, follow the server; unsaved edits in other sections are kept.
   */
  function absorb(source: IssuerCompetition, section: EditorSection | null): void {
    const fresh = editorFormFromCompetition(source)
    const keep = cloneForm(form.value)
    const next = cloneForm(fresh)
    for (const field of Object.keys(fresh) as FormField[]) {
      const inSection = section === null || SECTION_FIELDS[section].includes(field)
      if (!inSection && !sameValue(field, form.value, saved.value)) {
        (next as unknown as Record<string, unknown>)[field] = field === 'rules' ? keep.rules : keep[field]
      }
    }
    competitionId.value = source.id
    competition.value = source
    form.value = next
    saved.value = fresh
  }

  // ---------- Editing ----------

  /**
   * Direction or format changed (W15 step 1): the rules return to the preset of the new combination
   * (the current preset when it still fits), keeping the start and reserve prices.
   */
  function setType(direction: Direction, format: Format): void {
    const current = form.value
    if (current.direction === direction && current.format === format) return
    const options = presetsFor(lookups.presets, direction, format)
    const nextPreset = options.find(item => item.code === current.preset_code) ?? options[0] ?? null
    form.value = {
      ...current,
      direction,
      format,
      preset_code: nextPreset?.code ?? null,
      rules: editorRulesFromPreset(nextPreset, format, current.rules),
    }
  }

  /** A preset card was chosen: its rules, the current prices. */
  function choosePreset(code: string | null): void {
    const chosen = code ? lookups.presets.find(item => item.code === code) ?? null : null
    form.value = {
      ...form.value,
      preset_code: chosen?.code ?? null,
      rules: editorRulesFromPreset(chosen, form.value.format, form.value.rules),
    }
  }

  /** Replaces the rules (normalised for the format: R1–R4, R10, R12). */
  function setRules(next: Rules): void {
    form.value = { ...form.value, rules: normalizeEditorRules(next, form.value.format) }
  }

  function patchRules(patch: Partial<Rules>): void {
    setRules({ ...form.value.rules, ...patch })
  }

  function update<K extends Exclude<FormField, 'rules' | 'direction' | 'format' | 'preset_code'>>(field: K, value: EditorForm[K]): void {
    form.value = { ...form.value, [field]: value }
  }

  /** Discards unsaved edits (one section, or everything). */
  function reset(section?: EditorSection): void {
    if (!section) {
      form.value = cloneForm(saved.value)
      return
    }
    const next = cloneForm(form.value)
    for (const field of SECTION_FIELDS[section]) {
      (next as unknown as Record<string, unknown>)[field] = field === 'rules' ? cloneEditorRules(saved.value.rules) : saved.value[field]
    }
    form.value = next
  }

  // ---------- Client checks (feedback only; the server decides) ----------

  const basicsIssues = computed<EditorIssue[]>(() => {
    const issues: EditorIssue[] = []
    const value = form.value
    const base = 'competitions.setup.basics.issues'
    if (!value.title.trim()) issues.push({ field: 'title', key: `${base}.title_required`, when: 'save' })
    else if (value.title.trim().length > TITLE_MAX) issues.push({ field: 'title', key: `${base}.title_too_long`, when: 'save', params: { max: TITLE_MAX } })
    if (!value.category_id) issues.push({ field: 'category_id', key: `${base}.category_required`, when: 'save' })
    else if (value.direction === 'auction' && category.value && !category.value.auction_allowed) {
      issues.push({ field: 'category_id', key: `${base}.category_auction`, when: 'save' })
    }
    if (category.value?.is_other && !value.category_other_text.trim()) {
      issues.push({ field: 'category_other_text', key: `${base}.other_text_required`, when: 'save' })
    }
    if (!value.region_id) issues.push({ field: 'region_id', key: `${base}.region_required`, when: 'save' })
    if (value.description.length > DESCRIPTION_MAX) issues.push({ field: 'description', key: `${base}.description_too_long`, when: 'save', params: { max: DESCRIPTION_MAX } })
    if (!value.description.trim()) issues.push({ field: 'description', key: `${base}.description_required`, when: 'publish' })
    return issues
  })

  const rulesIssues = computed(() => editorRulesIssues(form.value.rules, form.value.direction, form.value.format))

  function scheduleIssuesAt(nowMs: number): EditorIssue[] {
    return editorScheduleIssues({
      biddingOpensAt: form.value.bidding_opens_at,
      scheduledCloseAt: form.value.scheduled_close_at,
      rules: form.value.rules,
      nowMs,
    })
  }

  /** Issues that stop a section from saving (the server would reject it). */
  function blockingIssues(section: EditorSection, nowMs: number): EditorIssue[] {
    const pick = (issues: EditorIssue[]) => issues.filter(issue => issue.when === 'save')
    if (section === 'basics') return pick(basicsIssues.value)
    if (section === 'rules' || section === 'type') return pick(rulesIssues.value)
    return pick(scheduleIssuesAt(nowMs))
  }

  /** First server message per editor field path (`rules.*` normalised). */
  const serverFieldErrors = computed<Record<string, string>>(() => {
    const result: Record<string, string> = {}
    if (!error.value?.isValidation) return result
    for (const [path, messages] of Object.entries(error.value.errors)) {
      const message = messages[0]
      if (message) result[editorFieldPath(path)] ??= message
    }
    return result
  })

  // ---------- Saving ----------

  function payloadFor(section: EditorSection): UpdateCompetitionRequest {
    const value = form.value
    switch (section) {
      case 'type':
        return { direction: value.direction, format: value.format, preset_code: value.preset_code, rules: editorRulesInput(value.rules, value.format) }
      case 'rules':
        return { preset_code: value.preset_code, rules: editorRulesInput(value.rules, value.format) }
      case 'basics':
        return {
          title: value.title.trim(),
          description: value.description.trim() || null,
          category_id: value.category_id ?? undefined,
          category_other_text: category.value?.is_other ? value.category_other_text.trim() || null : null,
          region_id: value.region_id ?? undefined,
        }
      case 'schedule':
        return { bidding_opens_at: value.bidding_opens_at, scheduled_close_at: value.scheduled_close_at }
    }
  }

  function createPayload(): CreateCompetitionRequest {
    const value = form.value
    const basics = payloadFor('basics')
    return {
      title: basics.title ?? '',
      description: basics.description ?? null,
      category_id: value.category_id ?? '',
      category_other_text: basics.category_other_text ?? null,
      region_id: value.region_id ?? '',
      direction: value.direction,
      format: value.format,
      preset_code: value.preset_code,
      rules: editorRulesInput(value.rules, value.format),
    }
  }

  /** `POST /competitions` after step 2 (CD3). Throws `ApiError` (`issuer_plan_required`, `auction_not_enabled`, 422). */
  async function create(): Promise<IssuerCompetition> {
    saving.value = true
    error.value = null
    try {
      const created = await createCompetition(createPayload())
      absorb(created, null)
      return created
    }
    catch (cause) {
      error.value = cause instanceof ApiError ? cause : null
      throw cause
    }
    finally {
      saving.value = false
    }
  }

  /**
   * `PATCH /competitions/{id}` with the section's fields. Returns null when nothing changed.
   * Throws `ApiError` (422 bound by path, `competition_not_editable`).
   */
  async function save(section: EditorSection): Promise<IssuerCompetition | null> {
    const id = competitionId.value
    if (!id) throw new Error('save() needs a created draft')
    if (!isDirty(section)) return null
    saving.value = true
    error.value = null
    try {
      const updated = await updateCompetition(id, payloadFor(section))
      absorb(updated, section)
      return updated
    }
    catch (cause) {
      error.value = cause instanceof ApiError ? cause : null
      throw cause
    }
    finally {
      saving.value = false
    }
  }

  function clearError(): void {
    error.value = null
  }

  return {
    competitionId,
    competition,
    form,
    saved,
    saving,
    error,
    couponCode,
    isNew,
    preset,
    availablePresets,
    customised,
    category,
    dirty,
    basicsIssues,
    rulesIssues,
    serverFieldErrors,
    isDirty,
    startNew,
    load,
    absorb,
    setType,
    choosePreset,
    setRules,
    patchRules,
    update,
    reset,
    scheduleIssuesAt,
    blockingIssues,
    payloadFor,
    createPayload,
    create,
    save,
    clearError,
  }
})
