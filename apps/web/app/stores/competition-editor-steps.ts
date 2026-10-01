/**
 * The 8-step creation wizard (SCREENS CD3, W12, W15) and the draft resume rule (§2.5 F2), as pure
 * functions. The rules only pick where the wizard opens and which hints to show: publish checks
 * stay on the server.
 */
import type { IssuerCompetition } from '~/types/api/competitions'
import type { SponsorshipMode } from '~/types/api/billing'

export type WizardStepKey = 'type' | 'basics' | 'rules' | 'schedule' | 'documents' | 'participants' | 'fees' | 'review'

export const WIZARD_STEP_KEYS: readonly WizardStepKey[] = ['type', 'basics', 'rules', 'schedule', 'documents', 'participants', 'fees', 'review']

/** Steps 1–2 run client-side before the draft exists (CD3). */
export const WIZARD_CLIENT_STEPS: readonly WizardStepKey[] = ['type', 'basics']

/** Step 7 is hidden when participation fees cannot be covered (S10). */
export function wizardStepKeys(feesEnabled: boolean): WizardStepKey[] {
  return WIZARD_STEP_KEYS.filter(step => feesEnabled || step !== 'fees')
}

export function isWizardStepKey(value: unknown): value is WizardStepKey {
  return typeof value === 'string' && (WIZARD_STEP_KEYS as readonly string[]).includes(value)
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
 * SCREENS §2.5 F2: the first of basics missing (title, category, region) → 2; auction without a
 * start price → 3; no close time → 4; invitations below the minimum → 6; covered fees still to buy
 * → 7; otherwise the review.
 */
export function firstIncompleteStep(progress: DraftProgress): WizardStepKey {
  if (!progress.hasTitle || !progress.hasCategory || !progress.hasRegion) return 'basics'
  if (progress.auctionWithoutStartPrice) return 'rules'
  if (!progress.hasClose) return 'schedule'
  if (progress.invitations < progress.minParticipants) return 'participants'
  if (progress.feesEnabled && progress.sponsorshipMode !== 'none' && (progress.quotePassesToBuy ?? 0) > 0) return 'fees'
  return 'review'
}

export type ChecklistItemKey = 'basics' | 'description' | 'other_text' | 'start_price' | 'schedule' | 'invitations'

export interface ChecklistItem {
  key: ChecklistItemKey
  step: WizardStepKey
  complete: boolean
}

/**
 * The pre-publish checklist of the review step and the draft overview (SCREENS W14, W15 step 8):
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
 * The wizard step that owns a publish field error (SCREENS W15 "Publish errors"):
 * `description` / `category_other_text` → 2, `start_price_minor` / `rules.*` → 3,
 * `bidding_opens_at` / `scheduled_close_at` → 4, `invitations` → 6.
 */
export function publishFieldStep(path: string): WizardStepKey | null {
  if (['title', 'description', 'category_id', 'category_other_text', 'region_id'].includes(path)) return 'basics'
  if (path === 'direction' || path === 'format' || path === 'preset_code') return 'type'
  if (path === 'start_price_minor' || path === 'reserve_price_minor' || path.startsWith('rules')) return 'rules'
  if (path === 'bidding_opens_at' || path === 'scheduled_close_at') return 'schedule'
  if (path.startsWith('invitations')) return 'participants'
  return null
}
