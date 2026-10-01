<script setup lang="ts">
/**
 * SAR amount as an LTR island with tabular figures (SCREENS S6, CONVENTIONS §9.2):
 * «12,500.00 ر.س» / "SAR 12,500.00". Discounts and credits use `sign="minus"` («− 300.00 ر.س»).
 */
const props = withDefaults(defineProps<{
  /** Integer halalas. */
  minor: number | null | undefined
  sign?: 'none' | 'minus' | 'plus'
  size?: 'sm' | 'md' | 'lg' | 'xl'
  /** Shown when `minor` is null. */
  empty?: string
}>(), {
  sign: 'none',
  size: 'md',
  empty: '—',
})

const money = useMoney()

const text = computed(() => {
  if (props.minor === null || props.minor === undefined) return null
  const formatted = money.format(Math.abs(props.minor))
  if (props.sign === 'minus') return `− ${formatted}`
  if (props.sign === 'plus') return `+ ${formatted}`
  return formatted
})

const sizeClasses = { sm: 'text-sm', md: '', lg: 'text-lg font-bold', xl: 'text-2xl font-bold' } as const
</script>

<template>
  <bdi
    class="whitespace-nowrap tabular-nums"
    :class="sizeClasses[size]"
  >{{ text ?? empty }}</bdi>
</template>
