<script setup lang="ts" generic="Row extends object">
import type { TableColumn } from '~/types/ui'

/**
 * Data table that stacks into labelled cards on narrow screens (SCREENS S9): below `md` by default,
 * or below `lg` / `xl` for wide tables that would otherwise scroll sideways inside their card.
 * Low-priority columns can also leave the table layout below a breakpoint (`column.hideBelow`); they
 * stay in the stacked cards. Cells render `row[column.key]` unless a `cell-<key>` slot is provided
 * (receives `{ row, value, layout }`).
 *
 * Every row renders in both layouts (one is hidden by CSS), so slot content exists twice. `layout`
 * (`'table'` or `'card'`) lets a slot keep form controls apart per layout, for example a radio
 * group's `name`: two copies in one group would uncheck each other.
 */
const props = withDefaults(defineProps<{
  columns: TableColumn[]
  rows: Row[]
  rowKey: keyof Row | ((row: Row) => string)
  /** Accessible table caption (visually hidden). */
  caption: string
  loading?: boolean
  skeletonRows?: number
  emptyTitle?: string
  emptyDescription?: string
  /** The width below which rows render as stacked cards. */
  stackBelow?: 'md' | 'lg' | 'xl'
}>(), {
  skeletonRows: 4,
  stackBelow: 'md',
})

const { t } = useI18n()

function keyOf(row: Row): string {
  return typeof props.rowKey === 'function' ? props.rowKey(row) : String(row[props.rowKey])
}

function valueOf(row: Row, key: string): unknown {
  return (row as Record<string, unknown>)[key]
}

function display(value: unknown): string {
  return value === null || value === undefined || value === '' ? '—' : String(value)
}

function alignClass(column: TableColumn): string {
  if (column.numeric || column.align === 'end') return 'text-end'
  if (column.align === 'center') return 'text-center'
  return 'text-start'
}

// Static class names, so Tailwind generates them.
const TABLE_VISIBLE = { md: 'hidden md:block', lg: 'hidden lg:block', xl: 'hidden xl:block' } as const
const CARDS_VISIBLE = { md: 'md:hidden', lg: 'lg:hidden', xl: 'xl:hidden' } as const
const EMPTY_BORDER = { md: 'md:border-t-0', lg: 'lg:border-t-0', xl: 'xl:border-t-0' } as const
const HIDE_BELOW = { 'lg': 'hidden lg:table-cell', 'xl': 'hidden xl:table-cell', '2xl': 'hidden 2xl:table-cell' } as const

function visibilityClass(column: TableColumn): string | undefined {
  return column.hideBelow ? HIDE_BELOW[column.hideBelow] : undefined
}

const primaryColumn = computed(() => props.columns.find(column => column.primary) ?? props.columns[0])
const secondaryColumns = computed(() =>
  props.columns.filter(column => column !== primaryColumn.value && !column.hideOnMobile),
)
</script>

<template>
  <div class="overflow-hidden rounded-lg border border-line bg-surface">
    <!-- wide screens: real table -->
    <div
      class="overflow-x-auto"
      :class="TABLE_VISIBLE[stackBelow]"
    >
      <table class="w-full border-collapse text-sm">
        <caption class="sr-only">
          {{ caption }}
        </caption>
        <thead class="bg-surface-muted">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              scope="col"
              class="px-3 py-3 font-semibold whitespace-nowrap text-fg-muted first:ps-4 last:pe-4"
              :class="[alignClass(column), visibilityClass(column), column.class]"
            >
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody
          v-if="loading"
          :aria-label="t('common.loading')"
        >
          <tr
            v-for="n in skeletonRows"
            :key="n"
            class="border-t border-line"
          >
            <td
              v-for="column in columns"
              :key="column.key"
              class="px-3 py-3.5 first:ps-4 last:pe-4"
              :class="visibilityClass(column)"
            >
              <UiSkeleton class="h-4 w-3/4" />
            </td>
          </tr>
        </tbody>
        <tbody v-else-if="rows.length > 0">
          <tr
            v-for="row in rows"
            :key="keyOf(row)"
            class="border-t border-line transition-colors hover:bg-surface-muted/60"
          >
            <td
              v-for="column in columns"
              :key="column.key"
              class="px-3 py-3.5 text-fg first:ps-4 last:pe-4"
              :class="[alignClass(column), column.numeric && 'tabular-nums', visibilityClass(column), column.class]"
            >
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="valueOf(row, column.key)"
                layout="table"
              >
                {{ display(valueOf(row, column.key)) }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- narrow screens: stacked cards -->
    <ul
      class="divide-y divide-line"
      :class="CARDS_VISIBLE[stackBelow]"
      :aria-label="caption"
    >
      <template v-if="loading">
        <li
          v-for="n in skeletonRows"
          :key="n"
          class="flex flex-col gap-2 p-4"
        >
          <UiSkeleton class="h-4 w-1/2" />
          <UiSkeleton class="h-3 w-3/4" />
        </li>
      </template>
      <template v-else>
        <li
          v-for="row in rows"
          :key="keyOf(row)"
          class="flex flex-col gap-2 p-4"
        >
          <div
            v-if="primaryColumn"
            class="font-semibold text-fg"
          >
            <slot
              :name="`cell-${primaryColumn.key}`"
              :row="row"
              :value="valueOf(row, primaryColumn.key)"
              layout="card"
            >
              {{ display(valueOf(row, primaryColumn.key)) }}
            </slot>
          </div>
          <!-- Labels take at most half the row and wrap, so values (amounts, dates) keep their room. -->
          <dl class="grid grid-cols-[fit-content(50%)_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
            <template
              v-for="column in secondaryColumns"
              :key="column.key"
            >
              <dt class="text-fg-muted">
                {{ column.label }}
              </dt>
              <dd
                class="min-w-0 text-end text-fg"
                :class="column.numeric && 'tabular-nums'"
              >
                <slot
                  :name="`cell-${column.key}`"
                  :row="row"
                  :value="valueOf(row, column.key)"
                  layout="card"
                >
                  {{ display(valueOf(row, column.key)) }}
                </slot>
              </dd>
            </template>
          </dl>
        </li>
      </template>
    </ul>

    <div
      v-if="!loading && rows.length === 0"
      class="border-t border-line"
      :class="EMPTY_BORDER[stackBelow]"
    >
      <slot name="empty">
        <UiEmptyState
          :title="emptyTitle ?? t('common.table.empty_title')"
          :description="emptyDescription"
        />
      </slot>
    </div>
  </div>
</template>
