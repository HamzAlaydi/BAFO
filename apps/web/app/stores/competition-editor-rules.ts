/**
 * Competition rules for the issuer wizard (SCREENS W12/W15 step 3), as pure functions.
 *
 * - Defaults are the `competitions` column defaults (ARCHITECTURE §5.5).
 * - `normalizeEditorRules()` applies the rules the UI enforces by disabling controls: R1 (sealed),
 *   R2 (live needs `must_beat`), R3 and R4 (forced `show_prices`), R10 and R12 (disabled blocks are null).
 * - `editorRulesIssues()` mirrors R5–R13 of ARCHITECTURE §7.2 for **feedback only**; the server
 *   re-validates everything and its `rules.*` errors win.
 * - `editorRulesPreview()` describes unsaved rules in words (i18n keys + params). Once saved, the
 *   server's `rules_summary` is the authoritative text (ARCHITECTURE §7.16).
 */
import { PRESET_TIERS, type Preset, type PresetTier } from '~/types/api/catalog'
import type { AmountGranularity, Direction, Format, Rules, RulesInput } from '~/types/api/competitions'
import type { FeatureFlags } from '~/types/api/platform'
import { normalizeDigits } from '~/utils/digits'
import { formatBps } from '~/utils/money'

/** Bounds of ARCHITECTURE §7.2 and the §15.3 setting defaults. */
export const EDITOR_RULE_BOUNDS = {
  /** Setting `bidding.max_amount_minor` (SAR 10 bn). */
  maxAmountMinor: 1_000_000_000_000,
  minStepBps: { min: 1, max: 5000 },
  /** `bidding.auto_extend_bounds`: window and extension in seconds. */
  autoExtendSeconds: { min: 60, max: 1800 },
  autoExtendMax: { min: 1, max: 50 },
  /** `competitions.final_window_bounds`. */
  finalWindowMinutes: { min: 30, max: 600 },
  /** `bidding.bafo_duration_bounds`; 60 is the default when enabled. */
  bafoDurationMinutes: { min: 15, max: 4320, default: 60 },
  minParticipants: { min: 1, max: 50 },
} as const

export type MinStepMode = 'none' | 'amount' | 'percent'

/** A client-side rule problem: the field path (API `rules.*` naming) and an i18n key under `rules.validation`. */
export interface EditorIssue {
  field: string
  key: string
  params?: Record<string, string | number>
  /** Plural choice for the message (`t(key, params, count)`), when the message counts something. */
  count?: number
  /** `save`: the server rejects the save; `publish`: only publishing is blocked. */
  when: 'save' | 'publish'
}

/** Renders an issue with the app's `t`, passing the plural choice when the issue carries one. */
export function issueMessage(issue: EditorIssue, t: (key: string, params: Record<string, string | number>, count?: number) => string): string {
  return issue.count === undefined ? t(issue.key, issue.params ?? {}) : t(issue.key, issue.params ?? {}, issue.count)
}

/** `d` of ARCHITECTURE §7.1: tender −1, auction +1. */
export function editorDirectionSign(direction: Direction): -1 | 1 {
  return direction === 'tender' ? -1 : 1
}

/** Column defaults (ARCHITECTURE §5.5), normalised for the format. */
export function defaultEditorRules(format: Format): Rules {
  return normalizeEditorRules({
    start_price_minor: null,
    reserve_price_minor: null,
    min_step_minor: null,
    min_step_bps: null,
    amount_granularity_minor: 100,
    must_beat: 'own',
    rank_visibility: 'leading_flag',
    show_prices: false,
    auto_extend: { enabled: false, window_seconds: null, by_seconds: null, max_extensions: null },
    final_window_minutes: null,
    bafo_round: { enabled: false, duration_minutes: null },
    min_participants: 2,
    result_publication: 'outcome_only',
  }, format)
}

export function cloneEditorRules(rules: Rules): Rules {
  return {
    ...rules,
    auto_extend: { ...rules.auto_extend },
    bafo_round: { ...rules.bafo_round },
  }
}

/** Whether R3 or R4 forces `show_prices` on (the control is then disabled and checked). */
export function showPricesForced(rules: Pick<Rules, 'must_beat' | 'result_publication'>): boolean {
  return rules.must_beat === 'best' || rules.result_publication === 'outcome_and_amount'
}

/**
 * Applies the rules the UI enforces structurally, so a saved draft never trips R1–R4, R10 or R12:
 * sealed competitions drop every live-only control; live ones always have `must_beat`; `best` and a
 * published amount force prices on; disabled auto-extend and BAFO blocks carry nulls.
 */
export function normalizeEditorRules(input: Rules, format: Format): Rules {
  const rules = cloneEditorRules(input)
  if (format === 'sealed') {
    rules.must_beat = null
    rules.rank_visibility = 'none'
    rules.show_prices = false
    rules.auto_extend = { enabled: false, window_seconds: null, by_seconds: null, max_extensions: null }
    rules.final_window_minutes = null
    // R4 would force prices on, which R1 forbids: a sealed result can publish the outcome only.
    if (rules.result_publication === 'outcome_and_amount') rules.result_publication = 'outcome_only'
  }
  else {
    if (rules.must_beat === null) rules.must_beat = 'own'
    if (showPricesForced(rules)) rules.show_prices = true
  }
  if (!rules.auto_extend.enabled) {
    rules.auto_extend = { enabled: false, window_seconds: null, by_seconds: null, max_extensions: null }
  }
  else {
    rules.auto_extend.window_seconds ??= 180
    rules.auto_extend.by_seconds ??= 180
    rules.auto_extend.max_extensions ??= 10
  }
  if (!rules.bafo_round.enabled) {
    rules.bafo_round = { enabled: false, duration_minutes: null }
  }
  else {
    rules.bafo_round.duration_minutes ??= EDITOR_RULE_BOUNDS.bafoDurationMinutes.default
  }
  if (rules.min_step_minor !== null && rules.min_step_bps !== null) rules.min_step_bps = null
  return rules
}

function mergeInput(base: Rules, input: RulesInput): Rules {
  const merged = cloneEditorRules(base)
  const { auto_extend: autoExtend, bafo_round: bafoRound, ...flat } = input
  for (const [key, value] of Object.entries(flat) as Array<[keyof typeof flat, unknown]>) {
    if (value !== undefined) (merged as unknown as Record<string, unknown>)[key] = value
  }
  if (autoExtend) merged.auto_extend = { ...merged.auto_extend, ...stripUndefined(autoExtend) }
  if (bafoRound) merged.bafo_round = { ...merged.bafo_round, ...stripUndefined(bafoRound) }
  return merged
}

function stripUndefined<T extends object>(value: T): Partial<T> {
  return Object.fromEntries(Object.entries(value).filter(([, v]) => v !== undefined)) as Partial<T>
}

/**
 * Keeps freshly chosen rules within the release scope (RELEASE_SCOPE.md §1.5): without
 * `final_pricing_window` a new draft has no final window, without `bafo_round` no BAFO round, so the
 * server's field refusals never trip on a preset value. Existing records are never touched: the
 * callers apply this to new drafts and preset choices only.
 */
export function editorRulesWithinScope(rules: Rules, flags: Pick<FeatureFlags, 'final_pricing_window' | 'bafo_round'>): Rules {
  const next = cloneEditorRules(rules)
  if (!flags.final_pricing_window) next.final_window_minutes = null
  if (!flags.bafo_round) next.bafo_round = { enabled: false, duration_minutes: null }
  return next
}

/**
 * Rules for a preset (or the defaults without one), keeping the issuer's prices. Presets never carry
 * prices (API.md §2.4), so the start and reserve prices survive a preset switch.
 */
export function editorRulesFromPreset(
  preset: Pick<Preset, 'rules'> | null,
  format: Format,
  keep: Pick<Rules, 'start_price_minor' | 'reserve_price_minor'> | null = null,
): Rules {
  const merged = preset ? mergeInput(defaultEditorRules(format), preset.rules) : defaultEditorRules(format)
  if (keep) {
    merged.start_price_minor = keep.start_price_minor
    merged.reserve_price_minor = keep.reserve_price_minor
  }
  return normalizeEditorRules(merged, format)
}

const COMPARED_KEYS = [
  'min_step_minor',
  'min_step_bps',
  'amount_granularity_minor',
  'must_beat',
  'rank_visibility',
  'show_prices',
  'final_window_minutes',
  'min_participants',
  'result_publication',
] as const

/** "Customised" badge: the rules differ from what the preset gives (prices excluded). */
export function editorRulesCustomised(rules: Rules, preset: Pick<Preset, 'rules'> | null, format: Format): boolean {
  if (!preset) return false
  const reference = editorRulesFromPreset(preset, format)
  if (COMPARED_KEYS.some(key => rules[key] !== reference[key])) return true
  const ae = rules.auto_extend
  const ref = reference.auto_extend
  if (ae.enabled !== ref.enabled || ae.window_seconds !== ref.window_seconds || ae.by_seconds !== ref.by_seconds || ae.max_extensions !== ref.max_extensions) return true
  return rules.bafo_round.enabled !== reference.bafo_round.enabled || rules.bafo_round.duration_minutes !== reference.bafo_round.duration_minutes
}

/** Presets for a direction and format, in lookup order. */
export function presetsFor<T extends Pick<Preset, 'direction' | 'format'>>(presets: readonly T[], direction: Direction, format: Format): T[] {
  return presets.filter(preset => preset.direction === direction && preset.format === format)
}

type TieredPreset = Pick<Preset, 'direction' | 'format' | 'tier'>

/** The tier cards of RELEASE_SCOPE.md §2.2 for a direction and format, ordered simple → standard → protected. */
export function tieredPresetsFor<T extends TieredPreset>(presets: readonly T[], direction: Direction, format: Format): T[] {
  return presetsFor(presets, direction, format)
    .filter(preset => preset.tier !== null && preset.tier !== undefined)
    .sort((a, b) => PRESET_TIERS.indexOf(a.tier as PresetTier) - PRESET_TIERS.indexOf(b.tier as PresetTier))
}

/** The untiered reference presets («قوالب أخرى»), shown only with `advanced_rules`. */
export function untieredPresetsFor<T extends TieredPreset>(presets: readonly T[], direction: Direction, format: Format): T[] {
  return presetsFor(presets, direction, format).filter(preset => preset.tier === null || preset.tier === undefined)
}

/**
 * The preset a new draft (or a changed direction) starts from: the `standard` tier, else the first
 * tiered preset, else the first preset of the combination (older catalogues without tiers).
 */
export function defaultPresetFor<T extends TieredPreset>(presets: readonly T[], direction: Direction, format: Format): T | null {
  const tiered = tieredPresetsFor(presets, direction, format)
  return tiered.find(preset => preset.tier === 'standard') ?? tiered[0] ?? presetsFor(presets, direction, format)[0] ?? null
}

export function minStepMode(rules: Pick<Rules, 'min_step_minor' | 'min_step_bps'>): MinStepMode {
  if (rules.min_step_minor !== null) return 'amount'
  if (rules.min_step_bps !== null) return 'percent'
  return 'none'
}

/** "0.5" / "0,5" / "٠٫٥" → 50 bps; null when not a number with at most 2 decimals. */
export function percentTextToBps(text: string): number | null {
  const cleaned = normalizeDigits(text).replace(/\s|%/g, '').replace(',', '.')
  const match = /^(\d{1,3})(?:\.(\d{1,2}))?$/.exec(cleaned)
  if (!match) return null
  return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'))
}

function isMultiple(amount: number, granularity: AmountGranularity): boolean {
  return amount % granularity === 0
}

function amountIssues(field: string, amount: number | null, rules: Rules, issues: EditorIssue[]): void {
  if (amount === null) return
  if (!Number.isSafeInteger(amount) || amount <= 0) {
    issues.push({ field, key: 'rules.validation.positive', when: 'save' })
    return
  }
  if (amount > EDITOR_RULE_BOUNDS.maxAmountMinor) {
    issues.push({ field, key: 'rules.validation.too_large', when: 'save' })
    return
  }
  if (!isMultiple(amount, rules.amount_granularity_minor)) {
    issues.push({ field, key: 'rules.validation.granularity', when: 'save' })
  }
}

function inRange(value: number | null, bounds: { min: number, max: number }): boolean {
  return value !== null && Number.isInteger(value) && value >= bounds.min && value <= bounds.max
}

/**
 * Client mirror of ARCHITECTURE §7.2 R5–R13 (feedback only). Field paths follow the API's `rules.*`
 * naming so server errors and client hints bind to the same controls.
 */
export function editorRulesIssues(rules: Rules, direction: Direction, format: Format): EditorIssue[] {
  const issues: EditorIssue[] = []
  const B = EDITOR_RULE_BOUNDS

  // R5: an auction needs an opening price before publishing.
  if (direction === 'auction' && rules.start_price_minor === null) {
    issues.push({ field: 'rules.start_price_minor', key: 'rules.validation.start_price_required', when: 'publish' })
  }
  // R8: prices and the step are positive multiples of the granularity, under the platform maximum.
  amountIssues('rules.start_price_minor', rules.start_price_minor, rules, issues)
  amountIssues('rules.reserve_price_minor', rules.reserve_price_minor, rules, issues)
  amountIssues('rules.min_step_minor', rules.min_step_minor, rules, issues)

  // R6: tender reserve ≤ start; auction reserve ≥ start.
  const start = rules.start_price_minor
  const reserve = rules.reserve_price_minor
  if (start !== null && reserve !== null && editorDirectionSign(direction) * (reserve - start) < 0) {
    issues.push({ field: 'rules.reserve_price_minor', key: `rules.validation.reserve_vs_start.${direction}`, when: 'save' })
  }

  // R7: one step kind; bps within 1–5000; an absolute step below the start price.
  if (rules.min_step_bps !== null && !inRange(rules.min_step_bps, B.minStepBps)) {
    issues.push({ field: 'rules.min_step_bps', key: 'rules.validation.min_step_percent_range', when: 'save' })
  }
  if (rules.min_step_minor !== null && start !== null && rules.min_step_minor >= start) {
    issues.push({ field: 'rules.min_step_minor', key: 'rules.validation.min_step_below_start', when: 'save' })
  }

  if (format === 'live') {
    // R10: auto-extend bounds.
    if (rules.auto_extend.enabled) {
      if (!inRange(rules.auto_extend.window_seconds, B.autoExtendSeconds)) {
        issues.push({ field: 'rules.auto_extend.window_seconds', key: 'rules.validation.auto_extend_minutes', when: 'save', params: { min: 1, max: 30 } })
      }
      if (!inRange(rules.auto_extend.by_seconds, B.autoExtendSeconds)) {
        issues.push({ field: 'rules.auto_extend.by_seconds', key: 'rules.validation.auto_extend_minutes', when: 'save', params: { min: 1, max: 30 } })
      }
      if (!inRange(rules.auto_extend.max_extensions, B.autoExtendMax)) {
        issues.push({ field: 'rules.auto_extend.max_extensions', key: 'rules.validation.auto_extend_max', when: 'save', params: B.autoExtendMax })
      }
    }
    // R11 (save part): off, or 30–600 minutes.
    if (rules.final_window_minutes !== null && !inRange(rules.final_window_minutes, B.finalWindowMinutes)) {
      issues.push({ field: 'rules.final_window_minutes', key: 'rules.validation.final_window_range', when: 'save', params: B.finalWindowMinutes })
    }
  }

  // R12: BAFO duration.
  if (rules.bafo_round.enabled && !inRange(rules.bafo_round.duration_minutes, B.bafoDurationMinutes)) {
    issues.push({ field: 'rules.bafo_round.duration_minutes', key: 'rules.validation.bafo_duration_range', when: 'save', params: { min: B.bafoDurationMinutes.min, max: B.bafoDurationMinutes.max } })
  }

  // R13: minimum participants.
  if (!inRange(rules.min_participants, B.minParticipants)) {
    issues.push({ field: 'rules.min_participants', key: 'rules.validation.min_participants_range', when: 'save', params: B.minParticipants })
  }

  return issues
}

/** One line of the unsaved-rules preview: an i18n key under `rules.preview` with its parameters. */
export interface RulesPreviewLine {
  key: string
  params?: Record<string, string | number>
  /** Amount parameters to format with the money formatter before insertion (CONVENTIONS §6.2). */
  amounts?: Record<string, number>
  /** Counted parameters, inserted with their noun in the locale's plural form (`rules.preview.units.<unit>`). */
  counts?: Record<string, { unit: 'minutes' | 'times', count: number }>
}

/**
 * The rules in words, before they are saved (ARCHITECTURE §7.16 topics, issuer variant). Only
 * rendering: the saved `rules_summary` from the server stays the reference once the step is saved.
 */
export function editorRulesPreview(rules: Rules, direction: Direction, format: Format): RulesPreviewLine[] {
  const lines: RulesPreviewLine[] = [{ key: `rules.preview.type.${direction}` }]
  lines.push({ key: format === 'sealed' ? 'rules.preview.format_sealed' : 'rules.preview.format_live' })
  if (rules.start_price_minor !== null) {
    lines.push({ key: `rules.preview.start_price.${direction}`, amounts: { amount: rules.start_price_minor } })
  }
  if (rules.reserve_price_minor !== null) {
    lines.push({ key: `rules.preview.reserve.${direction}`, amounts: { amount: rules.reserve_price_minor } })
  }
  if (format === 'live') {
    lines.push({ key: rules.must_beat === 'best' ? 'rules.preview.must_beat_best' : `rules.preview.must_beat_own.${direction}` })
    if (rules.min_step_minor !== null) lines.push({ key: `rules.preview.min_step_amount.${direction}`, amounts: { amount: rules.min_step_minor } })
    else if (rules.min_step_bps !== null) lines.push({ key: `rules.preview.min_step_percent.${direction}`, params: { percent: formatBps(rules.min_step_bps) } })
    const visibility = rules.rank_visibility
    lines.push({ key: `rules.preview.visibility_${visibility}${rules.show_prices ? '_prices' : ''}` })
    if (rules.final_window_minutes !== null) lines.push({ key: 'rules.preview.final_window', counts: { minutes: { unit: 'minutes', count: rules.final_window_minutes } } })
    if (rules.auto_extend.enabled) {
      lines.push({
        key: 'rules.preview.auto_extend',
        counts: {
          window: { unit: 'minutes', count: Math.round((rules.auto_extend.window_seconds ?? 0) / 60) },
          by: { unit: 'minutes', count: Math.round((rules.auto_extend.by_seconds ?? 0) / 60) },
          max: { unit: 'times', count: rules.auto_extend.max_extensions ?? 0 },
        },
      })
    }
  }
  else {
    lines.push({ key: 'rules.preview.visibility_sealed' })
  }
  if (rules.bafo_round.enabled) lines.push({ key: 'rules.preview.bafo', counts: { minutes: { unit: 'minutes', count: rules.bafo_round.duration_minutes ?? 0 } } })
  lines.push({ key: 'rules.preview.min_participants', params: { count: rules.min_participants } })
  lines.push({ key: `rules.preview.result_${rules.result_publication}` })
  lines.push({ key: rules.amount_granularity_minor === 100 ? 'rules.preview.granularity_riyals' : 'rules.preview.granularity_halalas' })
  lines.push({ key: 'rules.preview.server_time' })
  return lines
}

/**
 * The full `rules` object for `POST`/`PATCH` (SCREENS §6 G4: always the whole object, so the result
 * never depends on server-side merge rules).
 */
export function editorRulesInput(rules: Rules, format: Format): RulesInput {
  return cloneEditorRules(normalizeEditorRules(rules, format))
}

/** Deep equality for two rules objects (dirty tracking). */
export function editorRulesEqual(a: Rules, b: Rules): boolean {
  return JSON.stringify(sortKeys(a)) === JSON.stringify(sortKeys(b))
}

function sortKeys(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(sortKeys)
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map(key => [key, sortKeys((value as Record<string, unknown>)[key])]))
  }
  return value
}
