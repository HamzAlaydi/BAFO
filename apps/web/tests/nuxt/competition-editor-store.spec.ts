import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { CATEGORY, makeIssuerCompetition, PRESETS, REGION } from '../fixtures/issuer'

const competitions = vi.hoisted(() => ({ createCompetition: vi.fn(), updateCompetition: vi.fn() }))
const catalog = vi.hoisted(() => ({ fetchLookups: vi.fn() }))
vi.mock('~/services/competitions', () => competitions)
vi.mock('~/services/catalog', () => catalog)

const OTHER = { id: '01j9cat0000000000000000other', code: 'other', name: 'أخرى', is_other: true, auction_allowed: true }
const NO_AUCTION = { id: '01j9cat00000000000000vehicles', code: 'vehicles', name: 'مركبات', is_other: false, auction_allowed: false }

async function editorWithLookups() {
  catalog.fetchLookups.mockResolvedValue({
    status: 'fresh',
    lookups: { regions: [REGION], categories: [CATEGORY, OTHER, NO_AUCTION], close_reasons: [], presets: PRESETS },
    etag: null,
  })
  await useLookupsStore().load()
  return useCompetitionEditorStore()
}

beforeEach(() => {
  competitions.createCompetition.mockReset()
  competitions.updateCompetition.mockReset()
  catalog.fetchLookups.mockReset()
  setActivePinia(createPinia())
})

describe('competition editor store', () => {
  it('starts a new live tender with the first matching preset', async () => {
    const editor = await editorWithLookups()
    editor.startNew()
    expect(editor.isNew).toBe(true)
    expect(editor.form.preset_code).toBe('standard_live_tender')
    expect(editor.form.rules.min_step_bps).toBe(50)
    expect(editor.form.rules.auto_extend).toEqual({ enabled: true, window_seconds: 180, by_seconds: 180, max_extensions: 10 })
    expect(editor.dirty).toBe(false)
  })

  it('creates the draft with the basics, the type and the full preset rules (CD3)', async () => {
    const editor = await editorWithLookups()
    editor.startNew()
    editor.update('title', '  توريد طابعات  ')
    editor.update('description', 'وصف')
    editor.update('category_id', CATEGORY.id)
    editor.update('region_id', REGION.id)
    competitions.createCompetition.mockResolvedValue(makeIssuerCompetition({ title: 'توريد طابعات' }))

    const created = await editor.create()

    expect(created.id).toBeTruthy()
    const body = competitions.createCompetition.mock.calls[0]![0]
    expect(body).toMatchObject({
      title: 'توريد طابعات',
      description: 'وصف',
      category_id: CATEGORY.id,
      category_other_text: null,
      region_id: REGION.id,
      direction: 'tender',
      format: 'live',
      preset_code: 'standard_live_tender',
    })
    expect(Object.keys(body.rules).sort()).toEqual(Object.keys(editor.form.rules).sort())
    expect(editor.isNew).toBe(false)
    expect(editor.dirty).toBe(false)
  })

  it('saves one section with PATCH, sending the whole rules object with the preset (SCREENS §6 G4)', async () => {
    const editor = await editorWithLookups()
    editor.load(makeIssuerCompetition())
    expect(await editor.save('rules')).toBeNull()
    expect(competitions.updateCompetition).not.toHaveBeenCalled()

    editor.patchRules({ min_participants: 4 })
    expect(editor.isDirty('rules')).toBe(true)
    expect(editor.isDirty('basics')).toBe(false)
    competitions.updateCompetition.mockImplementation(async (_id: string, body: { rules: Record<string, unknown> }) =>
      makeIssuerCompetition({ rules: { ...makeIssuerCompetition().rules, ...body.rules } as never }))

    await editor.save('rules')

    const [id, body] = competitions.updateCompetition.mock.calls[0]!
    expect(id).toBe(makeIssuerCompetition().id)
    expect(body).toEqual({ preset_code: 'standard_live_tender', rules: expect.objectContaining({ min_participants: 4, auto_extend: expect.any(Object), bafo_round: expect.any(Object) }) })
    expect(Object.keys(body)).toEqual(['preset_code', 'rules'])
    expect(editor.dirty).toBe(false)
  })

  it('keeps unsaved edits of other sections when one section is saved', async () => {
    const editor = await editorWithLookups()
    editor.load(makeIssuerCompetition())
    editor.update('title', 'عنوان لم يُحفظ')
    editor.update('scheduled_close_at', '2026-11-10T12:00:00.000Z')
    competitions.updateCompetition.mockResolvedValue(makeIssuerCompetition({ schedule: { ...makeIssuerCompetition().schedule, scheduled_close_at: '2026-11-10T12:00:00.000Z' } }))

    await editor.save('schedule')

    expect(competitions.updateCompetition.mock.calls[0]![1]).toEqual({ bidding_opens_at: null, scheduled_close_at: '2026-11-10T12:00:00.000Z' })
    expect(editor.form.title).toBe('عنوان لم يُحفظ')
    expect(editor.isDirty('basics')).toBe(true)
    expect(editor.isDirty('schedule')).toBe(false)
  })

  it('resets the rules to the preset of a new direction or format and keeps the prices', async () => {
    const editor = await editorWithLookups()
    editor.load(makeIssuerCompetition())
    editor.patchRules({ min_participants: 9 })
    editor.setType('tender', 'sealed')
    expect(editor.form.preset_code).toBe('sealed_rfq')
    expect(editor.form.rules.must_beat).toBeNull()
    expect(editor.form.rules.rank_visibility).toBe('none')
    expect(editor.form.rules.bafo_round).toEqual({ enabled: true, duration_minutes: 60 })
    expect(editor.form.rules.min_participants).toBe(2)
    expect(editor.form.rules.start_price_minor).toBe(25_000_000)
    expect(editor.form.rules.reserve_price_minor).toBe(21_000_000)
    expect(editor.payloadFor('type')).toMatchObject({ direction: 'tender', format: 'sealed', preset_code: 'sealed_rfq' })
  })

  it('reports basics problems that block saving, and auction-only categories', async () => {
    const editor = await editorWithLookups()
    editor.startNew()
    const keys = () => editor.blockingIssues('basics', Date.now()).map(issue => issue.key)
    expect(keys()).toEqual(expect.arrayContaining([
      'competitions.setup.basics.issues.title_required',
      'competitions.setup.basics.issues.category_required',
      'competitions.setup.basics.issues.region_required',
    ]))
    editor.update('title', 'بيع مركبات')
    editor.update('region_id', REGION.id)
    editor.update('category_id', OTHER.id)
    expect(keys()).toEqual(['competitions.setup.basics.issues.other_text_required'])
    editor.update('category_other_text', 'خدمات خاصة')
    expect(keys()).toEqual([])
    editor.setType('auction', 'live')
    editor.update('category_id', NO_AUCTION.id)
    expect(keys()).toEqual(['competitions.setup.basics.issues.category_auction'])
    // Description is required only at publish: not a save blocker.
    expect(editor.basicsIssues.some(issue => issue.key === 'competitions.setup.basics.issues.description_required')).toBe(true)
  })

  it('binds server rule errors to the editor paths', async () => {
    const editor = await editorWithLookups()
    editor.load(makeIssuerCompetition())
    editor.patchRules({ min_participants: 3 })
    competitions.updateCompetition.mockRejectedValue(new ApiError({
      status: 422,
      code: 'validation_failed',
      message: 'invalid',
      errors: { 'start_price_minor': ['السعر غير صالح'], 'rules.min_participants': ['خارج الحدود'], 'title': ['مطلوب'] },
    }))

    await expect(editor.save('rules')).rejects.toBeInstanceOf(ApiError)

    expect(editor.serverFieldErrors).toEqual({
      'rules.start_price_minor': 'السعر غير صالح',
      'rules.min_participants': 'خارج الحدود',
      'title': 'مطلوب',
    })
    expect(editor.isDirty('rules')).toBe(true)
  })
})
