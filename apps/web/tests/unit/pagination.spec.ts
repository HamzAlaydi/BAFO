import { describe, expect, it } from 'vitest'
import { paginationRange } from '~/utils/pagination'

describe('paginationRange', () => {
  it('lists every page when they fit', () => {
    expect(paginationRange(1, 1)).toEqual([1])
    expect(paginationRange(3, 7)).toEqual([1, 2, 3, 4, 5, 6, 7])
  })

  it('returns nothing without pages', () => {
    expect(paginationRange(1, 0)).toEqual([])
  })

  it('keeps first, last and siblings with ellipses', () => {
    expect(paginationRange(1, 10)).toEqual([1, 2, 3, 4, 5, 'ellipsis-end', 10])
    expect(paginationRange(5, 10)).toEqual([1, 'ellipsis-start', 4, 5, 6, 'ellipsis-end', 10])
    expect(paginationRange(10, 10)).toEqual([1, 'ellipsis-start', 6, 7, 8, 9, 10])
  })

  it('has a constant length for large page counts', () => {
    for (let page = 1; page <= 50; page++) {
      expect(paginationRange(page, 50)).toHaveLength(7)
    }
  })

  it('clamps out-of-range pages', () => {
    expect(paginationRange(99, 10)).toEqual(paginationRange(10, 10))
    expect(paginationRange(-3, 10)).toEqual(paginationRange(1, 10))
  })
})
