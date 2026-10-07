import { describe, expect, it, vi } from 'vitest'
import { makeAppConfig } from '../fixtures/api'

describe('useMoney', () => {
  it('formats with the active locale', () => {
    const money = useMoney()
    const { locale } = useNuxtApp().$i18n
    const expected = locale.value === 'ar' ? '1,500.00 ر.س' : 'SAR 1,500.00'
    expect(money.format(150_000)).toBe(expected)
    expect(money.parse('1,500')).toBe(150_000)
    expect(money.vatOf(150_000)).toBe(22_500)
  })
})

describe('useDate', () => {
  it('appends the Riyadh zone label to deadlines', () => {
    const { formatDeadline } = useDate()
    const { t } = useNuxtApp().$i18n
    expect(formatDeadline('2026-09-29T12:05:00Z')).toContain(t('common.time.riyadh_suffix'))
  })
})

describe('useToast', () => {
  it('queues, caps and dismisses toasts', () => {
    vi.useFakeTimers()
    const toast = useToast()
    toast.clear()
    const id = toast.success('Saved')
    toast.error('Failed', { duration: 0 })
    expect(toast.toasts.value.map(t => t.variant)).toEqual(['success', 'error'])

    vi.advanceTimersByTime(5000)
    expect(toast.toasts.value.map(t => t.id)).not.toContain(id)
    expect(toast.toasts.value).toHaveLength(1) // duration 0 stays

    for (let i = 0; i < 6; i++) toast.info(`n${i}`, { duration: 0 })
    expect(toast.toasts.value).toHaveLength(4)
    toast.clear()
    vi.useRealTimers()
  })
})

describe('useTheme', () => {
  it('maps the system preference to no data-theme attribute', () => {
    // The preference applies only while `dark_mode` is on (full scope); core forces light.
    useAppConfigStore().config = makeAppConfig()
    const theme = useTheme()
    theme.setPreference('dark')
    expect(theme.dataTheme.value).toBe('dark')
    theme.setPreference('system')
    expect(theme.dataTheme.value).toBeUndefined()
  })
})

describe('shared cookies', () => {
  it('returns one ref per cookie so every reader sees writes immediately', () => {
    const first = useTheme()
    const second = useTheme()
    first.setPreference('light')
    expect(second.preference.value).toBe('light')
    expect(useAuthToken()).toBe(useAuthToken())
    first.setPreference('system')
  })
})

describe('useThrottledAnnouncer', () => {
  it('announces at once, then at most once per gap with the latest text', () => {
    vi.useFakeTimers()
    try {
      const announcer = useThrottledAnnouncer(10_000)
      announcer.announce('first')
      expect(announcer.message.value).toBe('first')
      let latest = 'second'
      announcer.announce(() => latest)
      latest = 'third'
      expect(announcer.message.value).toBe('first')
      vi.advanceTimersByTime(9_999)
      expect(announcer.message.value).toBe('first')
      vi.advanceTimersByTime(1)
      expect(announcer.message.value).toBe('third')
      announcer.announce('fourth')
      announcer.cancel()
      vi.advanceTimersByTime(20_000)
      expect(announcer.message.value).toBe('third')
    }
    finally {
      vi.useRealTimers()
    }
  })
})
