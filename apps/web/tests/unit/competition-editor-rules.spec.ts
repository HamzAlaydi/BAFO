import { describe, expect, it } from 'vitest'
import { formatBps } from '~/utils/money'
import type { Preset } from '~/types/api/catalog'
import {
  defaultEditorRules,
  editorRulesCustomised,
  editorRulesEqual,
  editorRulesFromPreset,
  editorRulesInput,
  editorRulesIssues,
  editorRulesPreview,
  minStepMode,
  normalizeEditorRules,
  percentTextToBps,
  presetsFor,
  showPricesForced,
} from '~/stores/competition-editor-rules'

/** The three seeded presets of ARCHITECTURE §5.2. */
const standardLiveTender: Preset = {
  id: 'p1',
  code: 'standard_live_tender',
  name: 'Standard live tender',
  description: '',
  direction: 'tender',
  format: 'live',
  rules: {
    must_beat: 'own',
    min_step_bps: 50,
    rank_visibility: 'leading_flag',
    show_prices: false,
    auto_extend: { enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 },
    final_window_minutes: 60,
    bafo_round: { enabled: false, duration_minutes: null },
  },
}
const sealedRfq: Preset = {
  id: 'p2',
  code: 'sealed_rfq',
  name: 'Sealed RFQ',
  description: '',
  direction: 'tender',
  format: 'sealed',
  rules: { rank_visibility: 'none', show_prices: false, bafo_round: { enabled: true, duration_minutes: 60 } },
}
const surplusAuction: Preset = {
  id: 'p3',
  code: 'surplus_sale_auction',
  name: 'Surplus sale',
  description: '',
  direction: 'auction',
  format: 'live',
  rules: {
    must_beat: 'best',
    min_step_minor: 50000,
    rank_visibility: 'leading_flag',
    show_prices: true,
    auto_extend: { enabled: true, window_seconds: 120, by_seconds: 120, max_extensions: 20 },
    final_window_minutes: null,
  },
}

describe('defaults and normalisation', () => {
  it('uses the column defaults for a live competition', () => {
    const rules = defaultEditorRules('live')
    expect(rules).toMatchObject({
      amount_granularity_minor: 100,
      must_beat: 'own',
      rank_visibility: 'leading_flag',
      show_prices: false,
      min_participants: 2,
      result_publication: 'outcome_only',
      auto_extend: { enabled: false, window_seconds: null, by_seconds: null, max_extensions: null },
      bafo_round: { enabled: false, duration_minutes: null },
    })
  })

  it('drops every live-only control for a sealed competition (R1)', () => {
    const live = editorRulesFromPreset(standardLiveTender, 'live')
    const sealed = normalizeEditorRules({ ...live, show_prices: true, result_publication: 'outcome_and_amount' }, 'sealed')
    expect(sealed.must_beat).toBeNull()
    expect(sealed.rank_visibility).toBe('none')
    expect(sealed.show_prices).toBe(false)
    expect(sealed.final_window_minutes).toBeNull()
    expect(sealed.auto_extend).toEqual({ enabled: false, window_seconds: null, by_seconds: null, max_extensions: null })
    expect(sealed.result_publication).toBe('outcome_only')
  })

  it('forces prices on when must_beat is best (R3) or the amount is published (R4)', () => {
    const base = defaultEditorRules('live')
    expect(normalizeEditorRules({ ...base, must_beat: 'best' }, 'live').show_prices).toBe(true)
    expect(normalizeEditorRules({ ...base, result_publication: 'outcome_and_amount' }, 'live').show_prices).toBe(true)
    expect(showPricesForced({ must_beat: 'own', result_publication: 'outcome_only' })).toBe(false)
  })

  it('fills defaults when auto-extend or the BAFO round is switched on, and nulls them when off (R10, R12)', () => {
    const base = defaultEditorRules('live')
    const on = normalizeEditorRules({ ...base, auto_extend: { enabled: true, window_seconds: null, by_seconds: null, max_extensions: null }, bafo_round: { enabled: true, duration_minutes: null } }, 'live')
    expect(on.auto_extend).toEqual({ enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 })
    expect(on.bafo_round).toEqual({ enabled: true, duration_minutes: 60 })
    const off = normalizeEditorRules({ ...on, auto_extend: { ...on.auto_extend, enabled: false }, bafo_round: { enabled: false, duration_minutes: 90 } }, 'live')
    expect(off.auto_extend.window_seconds).toBeNull()
    expect(off.bafo_round.duration_minutes).toBeNull()
  })

  it('keeps only one step kind (R7)', () => {
    const rules = normalizeEditorRules({ ...defaultEditorRules('live'), min_step_minor: 1000, min_step_bps: 50 }, 'live')
    expect(minStepMode(rules)).toBe('amount')
    expect(rules.min_step_bps).toBeNull()
  })
})

describe('presets', () => {
  it('applies a preset and keeps the issuer prices', () => {
    const rules = editorRulesFromPreset(surplusAuction, 'live', { start_price_minor: 500000, reserve_price_minor: 800000 })
    expect(rules.must_beat).toBe('best')
    expect(rules.show_prices).toBe(true)
    expect(rules.min_step_minor).toBe(50000)
    expect(rules.start_price_minor).toBe(500000)
    expect(rules.reserve_price_minor).toBe(800000)
  })

  it('filters presets by direction and format', () => {
    const all = [standardLiveTender, sealedRfq, surplusAuction]
    expect(presetsFor(all, 'tender', 'live').map(p => p.code)).toEqual(['standard_live_tender'])
    expect(presetsFor(all, 'tender', 'sealed').map(p => p.code)).toEqual(['sealed_rfq'])
    expect(presetsFor(all, 'auction', 'sealed')).toEqual([])
  })

  it('detects rules customised away from the preset (prices do not count)', () => {
    const rules = editorRulesFromPreset(standardLiveTender, 'live', { start_price_minor: 100000, reserve_price_minor: null })
    expect(editorRulesCustomised(rules, standardLiveTender, 'live')).toBe(false)
    expect(editorRulesCustomised({ ...rules, min_participants: 5 }, standardLiveTender, 'live')).toBe(true)
    expect(editorRulesCustomised({ ...rules, auto_extend: { ...rules.auto_extend, max_extensions: 3 } }, standardLiveTender, 'live')).toBe(true)
    expect(editorRulesCustomised(rules, null, 'live')).toBe(false)
  })

  it('sends the whole rules object, normalised', () => {
    const input = editorRulesInput({ ...editorRulesFromPreset(sealedRfq, 'sealed'), must_beat: 'own' }, 'sealed')
    expect(input.must_beat).toBeNull()
    expect(Object.keys(input).sort()).toEqual(Object.keys(defaultEditorRules('live')).sort())
  })

  it('compares rules deeply', () => {
    const a = editorRulesFromPreset(standardLiveTender, 'live')
    const b = editorRulesFromPreset(standardLiveTender, 'live')
    expect(editorRulesEqual(a, b)).toBe(true)
    expect(editorRulesEqual(a, { ...b, auto_extend: { ...b.auto_extend, by_seconds: 60 } })).toBe(false)
  })
})

describe('percentages', () => {
  it('formats basis points (SCREENS S6)', () => {
    expect(formatBps(125)).toBe('1.25')
    expect(formatBps(5000)).toBe('50')
  })

  it('parses percentages, Arabic digits included', () => {
    expect(percentTextToBps('0.5')).toBe(50)
    expect(percentTextToBps('٠٫٥')).toBe(50)
    expect(percentTextToBps('12,25')).toBe(1225)
    expect(percentTextToBps('1.234')).toBeNull()
    expect(percentTextToBps('abc')).toBeNull()
  })
})

describe('client checks (R5–R13)', () => {
  const tender = () => editorRulesFromPreset(standardLiveTender, 'live')

  it('requires an opening price for an auction only at publish (R5)', () => {
    const issues = editorRulesIssues(editorRulesFromPreset(surplusAuction, 'live'), 'auction', 'live')
    expect(issues).toContainEqual(expect.objectContaining({ field: 'rules.start_price_minor', key: 'rules.validation.start_price_required', when: 'publish' }))
    expect(editorRulesIssues(tender(), 'tender', 'live')).toEqual([])
  })

  it('checks the reserve against the start price by direction (R6)', () => {
    const tenderIssues = editorRulesIssues({ ...tender(), start_price_minor: 100000, reserve_price_minor: 120000 }, 'tender', 'live')
    expect(tenderIssues.map(i => i.key)).toContain('rules.validation.reserve_vs_start.tender')
    expect(editorRulesIssues({ ...tender(), start_price_minor: 100000, reserve_price_minor: 90000 }, 'tender', 'live')).toEqual([])
    const auction = editorRulesFromPreset(surplusAuction, 'live')
    const auctionIssues = editorRulesIssues({ ...auction, start_price_minor: 100000, reserve_price_minor: 90000 }, 'auction', 'live')
    expect(auctionIssues.map(i => i.key)).toContain('rules.validation.reserve_vs_start.auction')
  })

  it('checks the step (R7) and the granularity (R8)', () => {
    const rules = { ...tender(), min_step_bps: null, min_step_minor: 200000, start_price_minor: 100050 }
    const keys = editorRulesIssues(rules, 'tender', 'live').map(i => `${i.field}:${i.key}`)
    expect(keys).toContain('rules.min_step_minor:rules.validation.min_step_below_start')
    expect(keys).toContain('rules.start_price_minor:rules.validation.granularity')
    expect(editorRulesIssues({ ...tender(), min_step_bps: 6000 }, 'tender', 'live').map(i => i.key)).toContain('rules.validation.min_step_percent_range')
    expect(editorRulesIssues({ ...tender(), amount_granularity_minor: 1, start_price_minor: 100050 }, 'tender', 'live')).toEqual([])
  })

  it('checks auto-extend, final window, BAFO and minimum participants bounds (R10–R13)', () => {
    const rules = {
      ...tender(),
      auto_extend: { enabled: true, window_seconds: 30, by_seconds: 3600, max_extensions: 60 },
      final_window_minutes: 20,
      bafo_round: { enabled: true, duration_minutes: 5 },
      min_participants: 0,
    }
    const fields = editorRulesIssues(rules, 'tender', 'live').map(i => i.field)
    expect(fields).toEqual(expect.arrayContaining([
      'rules.auto_extend.window_seconds',
      'rules.auto_extend.by_seconds',
      'rules.auto_extend.max_extensions',
      'rules.final_window_minutes',
      'rules.bafo_round.duration_minutes',
      'rules.min_participants',
    ]))
  })
})

describe('rules preview', () => {
  it('describes a live tender with direction variants and formatted amounts', () => {
    const rules = { ...editorRulesFromPreset(standardLiveTender, 'live'), start_price_minor: 25000000 }
    const lines = editorRulesPreview(rules, 'tender', 'live')
    const keys = lines.map(line => line.key)
    expect(keys[0]).toBe('rules.preview.type.tender')
    expect(keys).toContain('rules.preview.must_beat_own.tender')
    expect(keys).toContain('rules.preview.min_step_percent.tender')
    expect(keys).toContain('rules.preview.visibility_leading_flag')
    expect(keys).toContain('rules.preview.auto_extend')
    expect(lines.find(line => line.key === 'rules.preview.start_price.tender')?.amounts).toEqual({ amount: 25000000 })
    expect(lines.find(line => line.key === 'rules.preview.min_step_percent.tender')?.params).toEqual({ percent: '0.5' })
  })

  it('describes a sealed competition without live-only lines', () => {
    const keys = editorRulesPreview(editorRulesFromPreset(sealedRfq, 'sealed'), 'tender', 'sealed').map(line => line.key)
    expect(keys).toContain('rules.preview.format_sealed')
    expect(keys).toContain('rules.preview.visibility_sealed')
    expect(keys).toContain('rules.preview.bafo')
    expect(keys.some(key => key.startsWith('rules.preview.must_beat'))).toBe(false)
  })
})
