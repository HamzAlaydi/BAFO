import { describe, expect, it } from 'vitest'
import { emptyNationalAddress, nationalAddressFormatErrors, nationalAddressFromApi, nationalAddressPayload, optionalText } from '~/utils/organization-form'

describe('national address form helpers', () => {
  it('turns API values into strings and back into a clean payload', () => {
    const form = nationalAddressFromApi({ building_number: '1234', street: null, district: 'Olaya', postal_code: null, additional_number: null, short_address: 'rrrd2929' })
    expect(form.street).toBe('')
    expect(nationalAddressPayload({ ...form, postal_code: ' ١٢٣٤٥ ' })).toEqual({
      building_number: '1234',
      street: null,
      district: 'Olaya',
      postal_code: '12345',
      additional_number: null,
      short_address: 'RRRD2929',
    })
    expect(nationalAddressFromApi(null)).toEqual(emptyNationalAddress())
  })

  it('reports format problems of filled parts only', () => {
    expect(nationalAddressFormatErrors(emptyNationalAddress())).toEqual({})
    expect(nationalAddressFormatErrors({ ...emptyNationalAddress(), building_number: '12', short_address: 'ABC123' })).toEqual({
      building_number: 'validation.building_number',
      short_address: 'validation.short_address',
    })
  })

  it('maps blank optional text to null', () => {
    expect(optionalText('  ')).toBeNull()
    expect(optionalText(' x ')).toBe('x')
    expect(optionalText(null)).toBeNull()
  })
})
