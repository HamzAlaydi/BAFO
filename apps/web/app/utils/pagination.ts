export type PageToken = number | 'ellipsis-start' | 'ellipsis-end'

/**
 * Page numbers to render: always the first and last page, `siblings` pages around the current one,
 * and ellipses for the gaps. The output length is constant while pageCount is large enough, so the
 * control does not jump as the user pages.
 *
 *   paginationRange(5, 10) → [1, 'ellipsis-start', 4, 5, 6, 'ellipsis-end', 10]
 */
export function paginationRange(page: number, pageCount: number, siblings = 1): PageToken[] {
  const total = Math.max(0, Math.floor(pageCount))
  if (total === 0) return []
  const current = Math.min(Math.max(1, Math.floor(page)), total)
  // first + last + current + 2*siblings + 2 ellipses
  const slots = 2 * siblings + 5
  if (total <= slots) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  const start = Math.max(current - siblings, 2)
  const end = Math.min(current + siblings, total - 1)
  const showStartGap = start > 3
  const showEndGap = end < total - 2

  if (!showStartGap && showEndGap) {
    const count = 3 + 2 * siblings
    return [...Array.from({ length: count }, (_, i) => i + 1), 'ellipsis-end', total]
  }
  if (showStartGap && !showEndGap) {
    const count = 3 + 2 * siblings
    return [1, 'ellipsis-start', ...Array.from({ length: count }, (_, i) => total - count + 1 + i)]
  }
  const middle = Array.from({ length: end - start + 1 }, (_, i) => start + i)
  return [1, 'ellipsis-start', ...middle, 'ellipsis-end', total]
}
