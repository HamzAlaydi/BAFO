/**
 * The 5-step creation wizard (RELEASE_SCOPE.md §2.1, SCREENS CD3, W12, W15) and the draft resume rule
 * (§2.5), as pure functions. The rules only pick where the wizard opens and which hints to show: publish
 * checks stay on the server.
 *
 * The wizard has the same five steps in both release scopes; the flags only add controls inside them.
 * The old 8-step keys (`type`, `documents`, `fees`) are kept as aliases so saved links keep working.
 */
import type { IssuerCompetition } from '~/types/api/competitions'
import type { SponsorshipMode } from '~/types/api/billing'

export type WizardStepKey = 'basics' | 'rules' | 'schedule' | 'participants' | 'review'

export const WIZARD_STEP_KEYS: readonly WizardStepKey[] = ['basics', 'rules', 'schedule', 'participants', 'review']

/** Step keys of the previous 8-step layout, mapped to the step that now holds their content. */
export type LegacyWizardStepKey = 'type' | 'documents' | 'fees'

export const LEGACY_STEP_ALIASES: Readonly<Record<LegacyWizardStepKey, WizardStepKey>> = {
  type: 'basics',
  documents: 'participants',
  fees: 'participants',
}

/**
 * Element ids of the wizard's form controls by editor field path (`rules.*` normalised), so the error
 * summary of a step can link to and focus the offending control (FQ8).
 */
export const WIZARD_FIELD_IDS: Readonly<Record<string, string>> = {
  'title': 'wizard-title',
  'description': 'wizard-description',
  'category_id': 'wizard-category',
  'region_id': 'wizard-region',
  'category_other_text': 'wizard-category-other',
  'rules.start_price_minor': 'wizard-start-price',
  'rules.reserve_price_minor': 'wizard-reserve-price',
  'rules.min_step_minor': 'wizard-min-step-amount',
  'rules.min_step_bps': 'wizard-min-step-percent',
  'rules.auto_extend.window_seconds': 'wizard-auto-window',
  'rules.auto_extend.by_seconds': 'wizard-auto-by',
  'rules.auto_extend.max_extensions': 'wizard-auto-max',
  'rules.final_window_minutes': 'wizard-final-window',
  'rules.bafo_round.duration_minutes': 'wizard-bafo-minutes',
  'rules.min_participants': 'wizard-min-participants',
  'bidding_opens_at': 'wizard-opens-at',
  'scheduled_close_at': 'wizard-close-at',
}

/** Step 1 runs client-side before the draft exists (CD3): finishing it creates the draft. */
export const WIZARD_CLIENT_STEPS: readonly WizardStepKey[] = ['basics']

/** The five steps, in order. Nothing is hidden per scope: gated controls live inside the steps. */
export function wizardStepKeys(): WizardStepKey[] {
  return [...WIZARD_STEP_KEYS]
}

export function isWizardStepKey(value: unknown): value is WizardStepKey {
  return typeof value === 'string' && (WIZARD_STEP_KEYS as readonly string[]).includes(value)
}

export function isLegacyStepKey(value: unknown): value is LegacyWizardStepKey {
  return typeof value === 'string' && value in LEGACY_STEP_ALIASES
}

/**
 * Resolves a `{step}` route parameter: a current key as is, a legacy key through its alias (the page
 * replaces the URL), anything else `null`.
 */
export function resolveWizardStep(value: unknown): { step: WizardStepKey, alias: boolean } | null {
  if (isWizardStepKey(value)) return { step: value, alias: false }
  if (isLegacyStepKey(value)) return { step: LEGACY_STEP_ALIASES[value], alias: true }
  return null
}

/** What the resume rule and the checklists need to know about a draft. */
export interface DraftProgress {
  hasTitle: boolean
  hasCategory: boolean
  hasRegion: boolean
  /** The category is "Other" and its text is missing. */
  otherTextMissing: boolean
  hasDescription: boolean
  auctionWithoutStartPrice: boolean
  hasClose: boolean
  invitations: number
  minParticipants: number
  feesEnabled: boolean
  sponsorshipMode: SponsorshipMode
  /** From `GET …/sponsorship/quote`; null when not loaded. */
  quotePassesToBuy: number | null
  attachments: number
}

export function draftProgressOf(
  competition: IssuerCompetition,
  extra: { feesEnabled: boolean, quotePassesToBuy: number | null, invitations?: number },
): DraftProgress {
  return {
    hasTitle: competition.title.trim() !== '',
    hasCategory: Boolean(competition.category?.id),
    hasRegion: Boolean(competition.region?.id),
    otherTextMissing: Boolean(competition.category?.is_other) && !(competition.category_other_text ?? '').trim(),
    hasDescription: Boolean(competition.description?.trim()),
    auctionWithoutStartPrice: competition.direction === 'auction' && competition.rules.start_price_minor === null,
    hasClose: Boolean(competition.schedule.scheduled_close_at),
    invitations: extra.invitations ?? competition.counts.invitations,
    minParticipants: competition.rules.min_participants,
    feesEnabled: extra.feesEnabled,
    sponsorshipMode: competition.sponsorship?.mode ?? 'none',
    quotePassesToBuy: extra.quotePassesToBuy,
    attachments: competition.counts.attachments,
  }
}

/**
 * RELEASE_SCOPE.md §2.5: the first of basics missing (title, category, region) → basics; auction
 * without a start price → rules; no close time → schedule; invitations below the minimum, or covered
 * fees still to buy → participants; otherwise the review.
 */
export function firstIncompleteStep(progress: DraftProgress): WizardStepKey {
  if (!progress.hasTitle || !progress.hasCategory || !progress.hasRegion) return 'basics'
  if (progress.auctionWithoutStartPrice) return 'rules'
  if (!progress.hasClose) return 'schedule'
  if (progress.invitations < progress.minParticipants) return 'participants'
  if (progress.feesEnabled && progress.sponsorshipMode !== 'none' && (progress.quotePassesToBuy ?? 0) > 0) return 'participants'
  return 'review'
}

export type ChecklistItemKey = 'basics' | 'description' | 'other_text' | 'start_price' | 'schedule' | 'invitations'

export interface ChecklistItem {
  key: ChecklistItemKey
  step: WizardStepKey
  complete: boolean
}

/**
 * The pre-publish checklist of the review step and the draft overview (SCREENS W14, W15 step 5):
 * hints only; the server decides at publish.
 */
export function draftChecklist(progress: DraftProgress, direction: 'tender' | 'auction'): ChecklistItem[] {
  const items: ChecklistItem[] = [
    { key: 'basics', step: 'basics', complete: progress.hasTitle && progress.hasCategory && progress.hasRegion },
    { key: 'description', step: 'basics', complete: progress.hasDescription },
  ]
  if (progress.otherTextMissing) items.push({ key: 'other_text', step: 'basics', complete: false })
  if (direction === 'auction') items.push({ key: 'start_price', step: 'rules', complete: !progress.auctionWithoutStartPrice })
  items.push({ key: 'schedule', step: 'schedule', complete: progress.hasClose })
  items.push({ key: 'invitations', step: 'participants', complete: progress.invitations >= progress.minParticipants })
  return items
}

/**
 * The wizard step that owns a publish field error (SCREENS W15 "Publish errors", RELEASE_SCOPE.md §2.4):
 * the basics and the type → basics, prices and `rules.*` → rules, the two dates → schedule,
 * `invitations*` → participants.
 */
export function publishFieldStep(path: string): WizardStepKey | null {
  if (['title', 'description', 'category_id', 'category_other_text', 'region_id', 'direction', 'format', 'preset_code'].includes(path)) return 'basics'
  if (path === 'start_price_minor' || path === 'reserve_price_minor' || path.startsWith('rules')) return 'rules'
  if (path === 'bidding_opens_at' || path === 'scheduled_close_at') return 'schedule'
  if (path.startsWith('invitations')) return 'participants'
  return null
}
