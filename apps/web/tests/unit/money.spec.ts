import { describe, expect, it } from 'vitest'
import { formatAmount, formatBps, formatBpsPercent, formatMoney, parseAmountToMinor, vatOf, withVat } from '~/utils/money'

describe('money', () => {
  it('formats halalas as riyals with two decimals and Latin digits', () => {
    expect(formatAmount(1_250_050)).toBe('12,500.50')
    expect(formatAmount(5)).toBe('0.05')
    expect(formatAmount(0)).toBe('0.00')
    expect(formatAmount(1_250_050, { grouping: false })).toBe('12500.50')
  })

  it('places the currency label per locale', () => {
    expect(formatMoney(1_250_050, 'ar')).toBe('12,500.50 ر.س')
    expect(formatMoney(1_250_050, 'en')).toBe('SAR 12,500.50')
  })

  it('refuses non-integer minor amounts', () => {
    expect(() => formatAmount(12.5)).toThrow(TypeError)
    expect(() => vatOf(Number.NaN)).toThrow(TypeError)
  })

  it.each([
    ['12,500.5', 1_250_050],
    ['12500.50', 1_250_050],
    ['12500', 1_250_000],
    ['0.01', 1],
    ['.5', null],
    ['7.', 700],
    [' 1 000 ', 100_000],
    ['١٢٬٥٠٠٫٥', 1_250_050],
    ['۱۲۳', 12_300],
  ])('parses %j to %j halalas', (input, expected) => {
    expect(parseAmountToMinor(input)).toBe(expected)
  })

  it.each(['', '   ', 'abc', '-5', '1.234', '1.2.3', '1e5'])('rejects %j', (input) => {
    expect(parseAmountToMinor(input)).toBeNull()
  })

  it('does not lose precision on large amounts', () => {
    expect(parseAmountToMinor('99999999999.99')).toBe(9_999_999_999_999)
    expect(formatAmount(9_999_999_999_999)).toBe('99,999,999,999.99')
  })

  it('computes 15% VAT rounded half-up to the halala', () => {
    expect(vatOf(10_000)).toBe(1_500)
    expect(vatOf(1)).toBe(0)
    expect(vatOf(3)).toBe(0) // 0.45
    expect(vatOf(10)).toBe(2) // 1.5 → 2
    expect(withVat(1_250_050)).toBe(1_437_558) // 1,250,050 + 187,507.5 → 187,508
  })
})

describe('percentages from basis points (SCREENS S6)', () => {
  it('trims to at most 2 decimals', () => {
    expect(formatBps(1500)).toBe('15')
    expect(formatBps(50)).toBe('0.5')
    expect(formatBps(6080)).toBe('60.8')
    expect(formatBps(1234)).toBe('12.34')
    expect(formatBps(0)).toBe('0')
  })

  it('shows the magnitude, or the server sign when asked', () => {
    expect(formatBpsPercent(-50)).toBe('0.5%')
    expect(formatBpsPercent(6080, { signed: true })).toBe('+60.8%')
    expect(formatBpsPercent(-1090, { signed: true })).toBe('−10.9%')
    expect(formatBpsPercent(5, { signed: true })).toBe('+0.05%')
    expect(formatBpsPercent(0, { signed: true })).toBe('0%')
  })
})
