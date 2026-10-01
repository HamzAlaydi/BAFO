import type { MaybeRefOrGetter } from 'vue'
import type { Direction } from '~/types/api/competitions'

/**
 * Direction-variant copy (SCREENS S2, §5): keys with `.tender` / `.auction` sub-keys are picked by
 * `competition.direction`, never by comparing numbers.
 *
 *   const { td } = useDirectionCopy(() => competition.value.direction)
 *   td('live.status.not_leading')            // live.status.not_leading.tender | .auction
 *   td('offers.hint.required_next', { amount })
 */
export function useDirectionCopy(direction: MaybeRefOrGetter<Direction | null | undefined>) {
  const { t } = useI18n()
  const current = computed<Direction>(() => toValue(direction) ?? 'tender')

  function key(base: string): string {
    return `${base}.${current.value}`
  }

  function td(base: string, params: Record<string, unknown> = {}): string {
    return t(key(base), params)
  }

  return { direction: current, key, td }
}
