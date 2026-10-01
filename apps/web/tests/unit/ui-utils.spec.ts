import { describe, expect, it } from 'vitest'
import { describedBy, fileMatchesAccept, fileSizeParts, hashToBucket, initials } from '~/utils/ui'
import { normalizeDigits } from '~/utils/digits'
import { safeRedirect } from '~/utils/redirect'
import { arabicPluralIndex } from '~/utils/plural'

describe('initials', () => {
  it('uses two letters for Latin names and one for Arabic names', () => {
    expect(initials('Hamza Alaydi')).toBe('HA')
    expect(initials('acme')).toBe('A')
    expect(initials('شركة الأمل للتجارة')).toBe('ش')
    expect(initials('  ')).toBe('')
    expect(initials(null)).toBe('')
  })
})

describe('fileMatchesAccept', () => {
  const pdf = { name: 'Offer.PDF', type: 'application/pdf' }
  const png = { name: 'logo.png', type: 'image/png' }

  it('matches extensions, wildcards and exact MIME types', () => {
    expect(fileMatchesAccept(pdf, '.pdf')).toBe(true)
    expect(fileMatchesAccept(png, 'image/*')).toBe(true)
    expect(fileMatchesAccept(pdf, 'application/pdf')).toBe(true)
    expect(fileMatchesAccept(png, '.pdf, .docx')).toBe(false)
    expect(fileMatchesAccept(png, undefined)).toBe(true)
  })
})

describe('fileSizeParts', () => {
  it('picks a readable unit', () => {
    expect(fileSizeParts(512)).toEqual({ value: '512', unit: 'b' })
    expect(fileSizeParts(2048)).toEqual({ value: '2', unit: 'kb' })
    expect(fileSizeParts(1.5 * 1024 * 1024)).toEqual({ value: '1.5', unit: 'mb' })
  })
})

describe('describedBy', () => {
  it('joins present ids only', () => {
    expect(describedBy('a', undefined, false, 'b')).toBe('a b')
    expect(describedBy(undefined, null)).toBeUndefined()
  })
})

describe('hashToBucket', () => {
  it('is deterministic and in range', () => {
    expect(hashToBucket('BAFO', 4)).toBe(hashToBucket('BAFO', 4))
    expect(hashToBucket('بافو', 4)).toBeGreaterThanOrEqual(0)
    expect(hashToBucket('بافو', 4)).toBeLessThan(4)
  })
})

describe('normalizeDigits', () => {
  it('maps Arabic-Indic digits and separators to Latin', () => {
    expect(normalizeDigits('٠١٢٣٤٥٦٧٨٩')).toBe('0123456789')
    expect(normalizeDigits('۰۱۲')).toBe('012')
    expect(normalizeDigits('١٬٢٣٤٫٥')).toBe('1,234.5')
    expect(normalizeDigits('abc 12')).toBe('abc 12')
  })
})

describe('safeRedirect', () => {
  it('only allows same-origin relative paths', () => {
    expect(safeRedirect('/ar/dashboard?tab=1', '/ar')).toBe('/ar/dashboard?tab=1')
    expect(safeRedirect('https://evil.example', '/ar')).toBe('/ar')
    expect(safeRedirect('//evil.example', '/ar')).toBe('/ar')
    expect(safeRedirect('/\\evil.example', '/ar')).toBe('/ar')
    expect(safeRedirect(['/ar'], '/ar/x')).toBe('/ar/x')
    expect(safeRedirect(undefined, '/ar')).toBe('/ar')
  })
})

describe('arabicPluralIndex', () => {
  it('follows the six CLDR Arabic forms', () => {
    expect([0, 1, 2, 3, 10, 11, 99, 100, 103].map(n => arabicPluralIndex(n, 6))).toEqual([0, 1, 2, 3, 3, 4, 4, 5, 3])
  })

  it('falls back to the default rule for shorter messages', () => {
    expect([0, 1, 2].map(n => arabicPluralIndex(n, 2))).toEqual([1, 0, 1])
  })
})
