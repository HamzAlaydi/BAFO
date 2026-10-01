import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import {
  CompetitionsDirectionChip,
  CompetitionsFormatChip,
  CompetitionsStatusChip,
  UiAmount,
  UiButton,
  UiCombobox,
  UiCountdown,
  UiDateTimePicker,
  UiFileDrop,
  UiMoneyInput,
  UiOtpInput,
  UiPagination,
  UiPasswordInput,
  UiPhoneInput,
  UiSwitch,
  UiTabs,
} from '#components'

const t = (key: string, params?: Record<string, unknown>) => useNuxtApp().$i18n.t(key, params ?? {})

describe('UiButton', () => {
  it('renders a button with its label', async () => {
    const wrapper = await mountSuspended(UiButton, { slots: { default: () => 'Save' } })
    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.text()).toContain('Save')
  })

  it('is busy and disabled while loading', async () => {
    const wrapper = await mountSuspended(UiButton, { props: { loading: true }, slots: { default: () => 'Save' } })
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain(t('common.loading'))
  })

  it('renders a localised link when given a route', async () => {
    const wrapper = await mountSuspended(UiButton, { props: { to: '/login' }, slots: { default: () => 'Go' } })
    expect(wrapper.element.tagName).toBe('A')
    expect(wrapper.attributes('href')).toMatch(/^\/(ar|en)\/login$/)
  })
})

describe('UiMoneyInput', () => {
  it('emits integer halalas for typed riyals, including Arabic digits', async () => {
    const wrapper = await mountSuspended(UiMoneyInput, { props: { label: 'Price', modelValue: null } })
    const input = wrapper.get('input')
    await input.setValue('1,250.5')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([125_050])
    await input.setValue('٢٠٠')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([20_000])
  })

  it('flags invalid amounts without changing the model', async () => {
    const wrapper = await mountSuspended(UiMoneyInput, { props: { label: 'Price', modelValue: 100 } })
    await wrapper.get('input').setValue('12.345')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.text()).toContain(t('common.money.invalid'))
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('shows a value set from outside even while focused, but keeps its own typing as typed', async () => {
    const wrapper = await mountSuspended(UiMoneyInput, { props: { label: 'Price', modelValue: null } })
    const input = wrapper.get('input')
    await input.trigger('focus')
    await input.setValue('12.')
    await wrapper.setProps({ modelValue: 1_200 })
    expect(input.element.value).toBe('12.')
    await wrapper.setProps({ modelValue: 9_950_000 })
    expect(input.element.value).toBe('99500.00')
    await input.trigger('blur')
    expect(input.element.value).toBe('99,500.00')
  })

  it('puts layout classes on the field and other attributes on the input', async () => {
    const wrapper = await mountSuspended(UiMoneyInput, { props: { label: 'Price', modelValue: null }, attrs: { 'class': 'col-span-2', 'data-testid': 'price' } })
    expect(wrapper.classes()).toContain('col-span-2')
    expect(wrapper.get('input').classes()).not.toContain('col-span-2')
    expect(wrapper.get('input').attributes('data-testid')).toBe('price')
  })

  it('shows the VAT breakdown on request', async () => {
    const wrapper = await mountSuspended(UiMoneyInput, { props: { label: 'Price', modelValue: 100_000, showVat: true } })
    expect(wrapper.get('input').element.value).toBe('1,000.00')
    expect(wrapper.text()).toContain('150.00')
    expect(wrapper.text()).toContain('1,150.00')
  })
})

describe('UiDateTimePicker', () => {
  it('edits Riyadh wall-clock time and emits UTC ISO', async () => {
    const wrapper = await mountSuspended(UiDateTimePicker, { props: { label: 'Closes at', modelValue: '2026-09-29T12:05:00.000Z' } })
    const input = wrapper.get('input')
    expect(input.element.value).toBe('2026-09-29T15:05')
    expect(wrapper.text()).toContain(t('common.time.riyadh_suffix'))
    await input.setValue('2026-10-01T09:30')
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['2026-10-01T06:30:00.000Z'])
  })
})

describe('competition chips (SCREENS S2)', () => {
  it('shows the status from competitionStatusVisual, with overlays while live', async () => {
    const closeAt = new Date(useServerTime().now() + 5 * 60_000).toISOString()
    const wrapper = await mountSuspended(CompetitionsStatusChip, { props: { status: 'live', phase: 'final_window', effectiveCloseAt: closeAt, extensionCount: 1 } })
    expect(wrapper.text()).toContain(t('competitions.status.final_window'))
    expect(wrapper.text()).toContain(t('competitions.status.closing_soon'))
    expect(wrapper.text()).toContain(t('competitions.status.extended'))
    const noOverlays = await mountSuspended(CompetitionsStatusChip, { props: { status: 'live', phase: 'open', effectiveCloseAt: closeAt, overlays: false } })
    expect(noOverlays.text()).toBe(t('competitions.status.live'))
  })

  it('maps closed to «قيد التقييم» / Evaluation and marks the BAFO round with the brand mark', async () => {
    expect((await mountSuspended(CompetitionsStatusChip, { props: { status: 'closed' } })).text()).toBe(t('competitions.status.closed'))
    const bafo = await mountSuspended(CompetitionsStatusChip, { props: { status: 'bafo_round' } })
    expect(bafo.find('svg polyline.stroke-green-500').exists()).toBe(true)
  })

  it('shows the direction with its glyph and rule, never coloured by direction', async () => {
    const tender = await mountSuspended(CompetitionsDirectionChip, { props: { direction: 'tender' } })
    const auction = await mountSuspended(CompetitionsDirectionChip, { props: { direction: 'auction' } })
    expect(tender.text()).toContain(t('competitions.direction.tender'))
    expect(tender.text()).toContain(t('competitions.direction.rule.tender'))
    expect(auction.text()).toContain(t('competitions.direction.rule.auction'))
    expect(tender.get('polyline').attributes('points')).toBe('5,8 12,17 19,8')
    expect(auction.get('polyline').attributes('points')).toBe('5,16 12,7 19,16')
    expect(tender.classes()).toEqual(auction.classes())
    expect(tender.html()).not.toContain('rtl:')
  })

  it('shows the format', async () => {
    expect((await mountSuspended(CompetitionsFormatChip, { props: { format: 'sealed' } })).text()).toBe(t('competitions.format.sealed'))
  })
})

describe('UiAmount', () => {
  it('renders an LTR money island with a minus for discounts', async () => {
    const wrapper = await mountSuspended(UiAmount, { props: { minor: 30_000, sign: 'minus' } })
    expect(wrapper.element.tagName).toBe('BDI')
    expect(wrapper.text()).toMatch(/^− (300\.00 ر\.س|SAR 300\.00)$/)
    expect((await mountSuspended(UiAmount, { props: { minor: null } })).text()).toBe('—')
  })
})

describe('UiOtpInput', () => {
  it('normalises Arabic-Indic digits and emits complete at six digits', async () => {
    const wrapper = await mountSuspended(UiOtpInput, { props: { label: 'Code', modelValue: '' } })
    const input = wrapper.get('input')
    expect(input.attributes('autocomplete')).toBe('one-time-code')
    expect(input.attributes('inputmode')).toBe('numeric')
    await input.setValue('١٢٣٤٥٦٧')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['123456'])
    expect(wrapper.emitted('complete')?.at(-1)).toEqual(['123456'])
  })
})

describe('UiPasswordInput', () => {
  it('shows the live rule checklist and toggles visibility', async () => {
    const wrapper = await mountSuspended(UiPasswordInput, { props: { label: 'Password', modelValue: 'Bafo2026', checklist: true, autocomplete: 'new-password' } })
    const rules = wrapper.findAll('li')
    expect(rules).toHaveLength(5)
    expect(rules.filter(rule => rule.classes().includes('text-success-soft-fg'))).toHaveLength(4)
    expect(wrapper.get('input').attributes('type')).toBe('password')
    await wrapper.get(`button[aria-label="${t('common.password.show')}"]`).trigger('click')
    expect(wrapper.get('input').attributes('type')).toBe('text')
  })
})

describe('UiPhoneInput', () => {
  it('emits E.164 only for a complete Saudi mobile and accepts pasted local formats', async () => {
    const wrapper = await mountSuspended(UiPhoneInput, { props: { label: 'Mobile', modelValue: null } })
    const input = wrapper.get('input')
    await input.setValue('5012')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0] ?? null).toBeNull()
    await input.setValue('0501234567')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['+966501234567'])
    expect(input.element.value).toBe('501234567')
  })
})

describe('UiCombobox', () => {
  const options = [{ value: 'a', label: 'Alpha' }, { value: 'b', label: 'Beta' }, { value: 'c', label: 'Gamma' }]

  it('filters, toggles with the keyboard and respects the maximum', async () => {
    const wrapper = await mountSuspended(UiCombobox, { props: { label: 'Categories', options, modelValue: ['a'], max: 2 } })
    const input = wrapper.get('input[role="combobox"]')
    await input.setValue('bet')
    expect(wrapper.findAll('[role="option"]')).toHaveLength(1)
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['a', 'b']])
    await wrapper.setProps({ modelValue: ['a', 'b'] })
    await input.setValue('')
    const gamma = wrapper.findAll('[role="option"]').find(option => option.text() === 'Gamma')!
    expect(gamma.attributes('aria-disabled')).toBe('true')
  })
})

describe('UiPagination', () => {
  it('moves to the next page and marks the current one', async () => {
    const wrapper = await mountSuspended(UiPagination, { props: { page: 2, pageCount: 5 } })
    expect(wrapper.get('[aria-current="page"]').text()).toBe('2')
    await wrapper.get(`button[aria-label="${t('common.pagination.next')}"]`).trigger('click')
    expect(wrapper.emitted('update:page')?.at(-1)).toEqual([3])
  })

  it('renders nothing for a single page', async () => {
    const wrapper = await mountSuspended(UiPagination, { props: { page: 1, pageCount: 1 } })
    expect(wrapper.find('nav').exists()).toBe(false)
  })
})

describe('UiSwitch', () => {
  it('toggles and exposes the switch role', async () => {
    const wrapper = await mountSuspended(UiSwitch, { props: { label: 'Sealed offers', modelValue: false } })
    const button = wrapper.get('[role="switch"]')
    expect(button.attributes('aria-checked')).toBe('false')
    await button.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([true])
  })
})

describe('UiTabs', () => {
  it('moves with the arrow key that points forward in the reading direction', async () => {
    const items = [{ key: 'details', label: 'Details' }, { key: 'offers', label: 'Offers' }, { key: 'log', label: 'Log' }]
    const wrapper = await mountSuspended(UiTabs, { props: { items, label: 'Competition', modelValue: 'details' } })
    const forward = useNuxtApp().$i18n.locale.value === 'ar' ? 'ArrowLeft' : 'ArrowRight'
    await wrapper.get('[role="tablist"]').trigger('keydown', { key: forward })
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['offers'])
    const tabs = wrapper.findAll('[role="tab"]')
    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(tabs[1]!.attributes('tabindex')).toBe('0')
    expect(tabs[0]!.attributes('tabindex')).toBe('-1')
  })
})

describe('UiCountdown (R-W5)', () => {
  it('reports expiry of a past deadline on the server clock', async () => {
    const wrapper = await mountSuspended(UiCountdown, { props: { endsAt: new Date(Date.now() - 1000).toISOString() } })
    await nextTick()
    expect(wrapper.text()).toContain(t('common.countdown.ended'))
    expect(wrapper.emitted('expire')).toHaveLength(1)
  })

  it('counts down from the server time, not the device time, as HH:MM:SS under 24 h', async () => {
    const clock = useServerTime()
    clock.reset()
    // Server is 1 hour ahead of this device: a deadline 90 minutes away on the device clock is 30 minutes away.
    clock.sync(new Date(Date.now() + 3_600_000).toISOString())
    const wrapper = await mountSuspended(UiCountdown, { props: { endsAt: new Date(Date.now() + 90 * 60_000).toISOString() } })
    await nextTick()
    expect(wrapper.get('[role="timer"]').text()).toMatch(/^00:(29|30):\d\d$/)
    expect(wrapper.get('[role="timer"]').attributes('aria-live')).toBe('off')
    clock.reset()
  })

  it('shows "N days HH:MM" from 24 hours', async () => {
    useServerTime().reset()
    const wrapper = await mountSuspended(UiCountdown, { props: { endsAt: new Date(Date.now() + (3 * 86_400 + 4 * 3600 + 10 * 60 + 30) * 1000).toISOString() } })
    await nextTick()
    const text = wrapper.get('[role="timer"]').text()
    expect(text).toContain(useNuxtApp().$i18n.t('common.countdown.days', { count: 3 }, 3))
    expect(text).toMatch(/04:1[01]$/)
  })

  it('uses the warning tone in the last 5 minutes, never the danger tone', async () => {
    useServerTime().reset()
    const wrapper = await mountSuspended(UiCountdown, { props: { endsAt: new Date(Date.now() + 4 * 60_000).toISOString() } })
    await nextTick()
    expect(wrapper.get('[role="timer"]').classes()).toContain('text-warning-soft-fg')
    expect(wrapper.html()).not.toContain('danger')
  })
})

describe('UiFileDrop', () => {
  it('accepts matching files and rejects the rest', async () => {
    const wrapper = await mountSuspended(UiFileDrop, { props: { label: 'Attachments', accept: '.pdf', multiple: true, modelValue: [] } })
    const input = wrapper.get('input[type="file"]')
    const files = [
      new File(['%PDF'], 'offer.pdf', { type: 'application/pdf' }),
      new File(['x'], 'photo.exe', { type: 'application/octet-stream' }),
    ]
    Object.defineProperty(input.element, 'files', { value: files, configurable: true })
    await input.trigger('change')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[files[0]]])
    const rejected = wrapper.emitted('rejected')?.at(-1)?.[0] as Array<{ reason: string }>
    expect(rejected.map(r => r.reason)).toEqual(['type'])
  })
})
