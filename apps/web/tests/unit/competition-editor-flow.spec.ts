import { describe, expect, it } from 'vitest'
import type { OfferLogEntry } from '~/types/api/bidding'
import type { Invitation } from '~/types/api/competitions'
import { awardHints, lastOfferSeq, mergeOfferEntries, offerSeqGap } from '~/stores/competition-editor-console'
import { splitEmailList } from '~/utils/validation'
import { addStaged, stageEmail, stagedRowErrors, stagedToInput, stageOrganization, stageVendor, upsertInvitation } from '~/stores/competition-editor-invitations'
import { editorScheduleIssues, editorSchedulePreview } from '~/stores/competition-editor-schedule'
import { draftChecklist, firstIncompleteStep, LEGACY_STEP_ALIASES, publishFieldStep, resolveWizardStep, WIZARD_CLIENT_STEPS, wizardStepKeys, type DraftProgress } from '~/stores/competition-editor-steps'

const MIN = 60_000
const NOW = Date.parse('2026-11-01T09:00:00.000Z')
const iso = (ms: number) => new Date(ms).toISOString()

describe('schedule preview (SCREENS §6 G3)', () => {
  const rules = { final_window_minutes: 60, auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 } }

  it('derives the final window, invitation cutoff and latest close like ARCHITECTURE §7.2', () => {
    const close = NOW + 3 * 24 * 60 * MIN
    const preview = editorSchedulePreview({ biddingOpensAt: null, scheduledCloseAt: iso(close), rules, nowMs: NOW })
    expect(preview.opensOnPublish).toBe(true)
    expect(preview.finalWindowStartsAtMs).toBe(close - 60 * MIN)
    expect(preview.invitationCutoffAtMs).toBe(close - 60 * MIN)
    expect(preview.hardStopAtMs).toBe(close + 10 * 180 * 1000)
    expect(preview.durationMs).toBe(close - NOW)
  })

  it('uses the 60-minute cutoff without a final window, never before the opening', () => {
    const opens = NOW + 10 * MIN
    const close = opens + 30 * MIN
    const preview = editorSchedulePreview({ biddingOpensAt: iso(opens), scheduledCloseAt: iso(close), rules: { final_window_minutes: null, auto_extend: { enabled: false, window_seconds: null, by_seconds: null, max_extensions: null } }, nowMs: NOW })
    expect(preview.invitationCutoffAtMs).toBe(opens)
    expect(preview.hardStopAtMs).toBeNull()
  })

  it('flags R16 and the publish part of R11', () => {
    const noClose = editorScheduleIssues({ biddingOpensAt: null, scheduledCloseAt: null, rules, nowMs: NOW })
    expect(noClose.map(i => i.key)).toEqual(['competitions.setup.schedule.issues.close_required'])
    const inverted = editorScheduleIssues({ biddingOpensAt: iso(NOW + 60 * MIN), scheduledCloseAt: iso(NOW + 30 * MIN), rules, nowMs: NOW })
    expect(inverted).toEqual([expect.objectContaining({ key: 'competitions.setup.schedule.issues.close_after_open', when: 'save' })])
    const short = editorScheduleIssues({ biddingOpensAt: null, scheduledCloseAt: iso(NOW + 5 * MIN), rules, nowMs: NOW })
    expect(short).toContainEqual(expect.objectContaining({ key: 'competitions.setup.schedule.issues.min_duration', when: 'publish', params: { minutes: 5, min: 10 }, count: 5 }))
    const window = editorScheduleIssues({ biddingOpensAt: null, scheduledCloseAt: iso(NOW + 45 * MIN), rules, nowMs: NOW })
    expect(window.map(i => i.key)).toContain('competitions.setup.schedule.issues.final_window_too_long')
    const past = editorScheduleIssues({ biddingOpensAt: iso(NOW - 5 * MIN), scheduledCloseAt: iso(NOW + 5 * 24 * 60 * MIN), rules, nowMs: NOW })
    expect(past.map(i => i.key)).toContain('competitions.setup.schedule.issues.opens_in_past')
    const long = editorScheduleIssues({ biddingOpensAt: null, scheduledCloseAt: iso(NOW + 91 * 24 * 60 * MIN), rules, nowMs: NOW })
    expect(long).toContainEqual(expect.objectContaining({ key: 'competitions.setup.schedule.issues.max_duration', params: { days: 91, max: 90 }, count: 91 }))
  })

  it('rejects a closing time that has already passed, clearly and before saving (RELEASE_SCOPE §2.3)', () => {
    const past = editorScheduleIssues({ biddingOpensAt: null, scheduledCloseAt: iso(NOW - 5 * MIN), rules, nowMs: NOW })
    expect(past).toEqual([expect.objectContaining({ field: 'scheduled_close_at', key: 'competitions.setup.schedule.issues.close_in_past', when: 'save' })])
    // A past opening time with a past close: the close is the blocking problem, the opening a publish hint.
    const both = editorScheduleIssues({ biddingOpensAt: iso(NOW - 60 * MIN), scheduledCloseAt: iso(NOW - MIN), rules, nowMs: NOW })
    expect(both.map(i => i.key)).toEqual(['competitions.setup.schedule.issues.opens_in_past', 'competitions.setup.schedule.issues.close_in_past'])
    expect(both.find(i => i.key.endsWith('close_in_past'))?.when).toBe('save')
  })
})

describe('wizard steps and the F2 resume rule', () => {
  const complete: DraftProgress = {
    hasTitle: true,
    hasCategory: true,
    hasRegion: true,
    otherTextMissing: false,
    hasDescription: true,
    auctionWithoutStartPrice: false,
    hasClose: true,
    invitations: 3,
    minParticipants: 2,
    feesEnabled: true,
    sponsorshipMode: 'none',
    quotePassesToBuy: null,
    attachments: 0,
  }

  it('has the same five steps in both release scopes (RELEASE_SCOPE §2.1)', () => {
    expect(wizardStepKeys()).toEqual(['basics', 'rules', 'schedule', 'participants', 'review'])
    expect(WIZARD_CLIENT_STEPS).toEqual(['basics'])
  })

  it('maps the legacy 8-step keys to the step that holds their content', () => {
    expect(LEGACY_STEP_ALIASES).toEqual({ type: 'basics', documents: 'participants', fees: 'participants' })
    expect(resolveWizardStep('type')).toEqual({ step: 'basics', alias: true })
    expect(resolveWizardStep('fees')).toEqual({ step: 'participants', alias: true })
    expect(resolveWizardStep('rules')).toEqual({ step: 'rules', alias: false })
    expect(resolveWizardStep('nope')).toBeNull()
    expect(resolveWizardStep(undefined)).toBeNull()
  })

  it('opens the first incomplete step (§2.5: covered fees still to buy open the participants step)', () => {
    expect(firstIncompleteStep({ ...complete, hasRegion: false })).toBe('basics')
    expect(firstIncompleteStep({ ...complete, auctionWithoutStartPrice: true })).toBe('rules')
    expect(firstIncompleteStep({ ...complete, hasClose: false })).toBe('schedule')
    expect(firstIncompleteStep({ ...complete, invitations: 1 })).toBe('participants')
    expect(firstIncompleteStep({ ...complete, sponsorshipMode: 'all', quotePassesToBuy: 3 })).toBe('participants')
    expect(firstIncompleteStep({ ...complete, sponsorshipMode: 'all', quotePassesToBuy: 3, feesEnabled: false })).toBe('review')
    expect(firstIncompleteStep(complete)).toBe('review')
  })

  it('builds the pre-publish checklist', () => {
    const items = draftChecklist({ ...complete, hasDescription: false, invitations: 1, otherTextMissing: true }, 'auction')
    expect(items.filter(item => !item.complete).map(item => item.key)).toEqual(['description', 'other_text', 'invitations'])
    expect(items.map(item => item.key)).toContain('start_price')
  })

  it('links publish field errors to their step', () => {
    expect(publishFieldStep('description')).toBe('basics')
    expect(publishFieldStep('category_other_text')).toBe('basics')
    expect(publishFieldStep('direction')).toBe('basics')
    expect(publishFieldStep('preset_code')).toBe('basics')
    expect(publishFieldStep('start_price_minor')).toBe('rules')
    expect(publishFieldStep('rules.final_window_minutes')).toBe('rules')
    expect(publishFieldStep('scheduled_close_at')).toBe('schedule')
    expect(publishFieldStep('invitations')).toBe('participants')
    expect(publishFieldStep('something_else')).toBeNull()
  })
})

describe('staged invitations', () => {
  it('splits a pasted list on commas, spaces, semicolons and new lines', () => {
    expect(splitEmailList('a@x.sa, B@x.sa;c@x.sa\n a@x.sa  d@x.sa،e@x.sa')).toEqual(['a@x.sa', 'b@x.sa', 'c@x.sa', 'd@x.sa', 'e@x.sa'])
  })

  it('skips rows already staged or already invited', () => {
    const invited = [{ email: 'old@x.sa', organization: null, vendor: null, status: 'sent' }] as unknown as Invitation[]
    const first = addStaged([], [stageEmail('a@x.sa'), stageEmail('old@x.sa')], invited)
    expect(first.rows.map(row => row.key)).toEqual(['email:a@x.sa'])
    expect(first.duplicates).toBe(1)
    const second = addStaged(first.rows, [stageEmail('A@x.sa'), stageOrganization('org1', 'Delta')], invited)
    expect(second.rows).toHaveLength(2)
    expect(second.duplicates).toBe(1)
  })

  it('builds the request body with the sponsored flag only when asked', () => {
    const rows = [
      { ...stageEmail('a@x.sa', ' Ahmed '), sponsored: true },
      stageOrganization('org1', 'Delta'),
      stageVendor('ven1', 'Riyada', 'sales@riyada.sa'),
    ]
    expect(stagedToInput(rows, true)).toEqual([
      { email: 'a@x.sa', name: 'Ahmed', sponsored: true },
      { organization_id: 'org1', sponsored: false },
      { vendor_id: 'ven1', sponsored: false },
    ])
    expect(stagedToInput(rows, false)[0]).toEqual({ email: 'a@x.sa', name: 'Ahmed' })
  })

  it('maps all-or-nothing row errors and item codes by index', () => {
    const { rows, unmatched } = stagedRowErrors({
      errors: { 'invitations.1.email': ['Already invited.'], 'invitations': ['Too many.'] },
      details: { item_codes: { 'invitations.1.email': 'invitation_duplicate', 'invitations.2.vendor_id': 'vendor_blocked' } },
    }, 3)
    expect(rows[0]).toBeNull()
    expect(rows[1]).toEqual({ message: 'Already invited.', code: 'invitation_duplicate' })
    expect(rows[2]).toEqual({ message: null, code: 'vendor_blocked' })
    expect(unmatched).toEqual(['Too many.'])
  })

  it('upserts invitations by id', () => {
    const a = { id: 'a', status: 'sent' } as Invitation
    const b = { id: 'b', status: 'sent' } as Invitation
    expect(upsertInvitation([a, b], { ...a, status: 'joined' }).map(item => item.status)).toEqual(['joined', 'sent'])
    expect(upsertInvitation([a], b)).toHaveLength(2)
  })
})

describe('issuer console helpers', () => {
  const entry = (seq: number, id = `o${seq}`): OfferLogEntry => ({
    id,
    seq,
    participant: { id: 'p', alias_no: 1, organization: { id: 'org', name: 'Org' } },
    amount_minor: 1000 * seq,
    stage: 'live',
    accepted_at: '2026-11-09T11:59:58.412Z',
    channel: 'web',
    voided: false,
  })

  it('merges the offer log by id in seq order and detects gaps (S4)', () => {
    const merged = mergeOfferEntries([entry(1), entry(3)], [entry(2), entry(3)])
    expect(merged.map(item => item.seq)).toEqual([1, 2, 3])
    expect(lastOfferSeq(merged)).toBe(3)
    expect(offerSeqGap(3, entry(4))).toBe(false)
    expect(offerSeqGap(3, entry(6))).toBe(true)
  })

  it('shows justification and reserve fields from the selection', () => {
    const leader = { rank: 1, is_leader: true, current_amount_minor: 900000 }
    const second = { rank: 2, is_leader: false, current_amount_minor: 950000 }
    expect(awardHints(leader, null, 'tender')).toEqual({ notLeading: false, reserveNotMet: false })
    expect(awardHints(second, 920000, 'tender')).toEqual({ notLeading: true, reserveNotMet: true })
    expect(awardHints(leader, 920000, 'tender').reserveNotMet).toBe(false)
    expect(awardHints({ rank: 1, is_leader: true, current_amount_minor: 900000 }, 950000, 'auction').reserveNotMet).toBe(true)
    expect(awardHints(null, 1, 'tender')).toEqual({ notLeading: false, reserveNotMet: false })
  })
})
