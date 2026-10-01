<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'

const props = withDefaults(defineProps<{
  pageCount: number
  siblings?: number
}>(), {
  siblings: 1,
})

const page = defineModel<number>('page', { default: 1 })
const { t } = useI18n()
const tokens = computed(() => paginationRange(page.value, props.pageCount, props.siblings))

function go(target: number): void {
  const clamped = Math.min(Math.max(1, target), props.pageCount)
  if (clamped !== page.value) page.value = clamped
}
</script>

<template>
  <nav
    v-if="pageCount > 1"
    :aria-label="t('common.pagination.label')"
    class="flex items-center justify-between gap-2 sm:justify-center"
  >
    <UiIconButton
      :icon="ChevronLeft"
      variant="secondary"
      size="sm"
      flip-icon
      :label="t('common.pagination.previous')"
      :disabled="page <= 1"
      @click="go(page - 1)"
    />
    <p class="text-sm text-fg-muted tabular-nums sm:hidden">
      {{ t('common.pagination.status', { page, total: pageCount }) }}
    </p>
    <ul class="hidden items-center gap-1 sm:flex">
      <li
        v-for="token in tokens"
        :key="token"
      >
        <span
          v-if="typeof token !== 'number'"
          class="inline-flex size-8 items-center justify-center text-fg-muted"
          aria-hidden="true"
        >…</span>
        <button
          v-else
          type="button"
          class="inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm font-semibold tabular-nums transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          :class="token === page ? 'bg-primary text-primary-fg' : 'text-fg hover:bg-surface-muted'"
          :aria-current="token === page ? 'page' : undefined"
          :aria-label="t('common.pagination.page', { page: token })"
          @click="go(token)"
        >
          {{ token }}
        </button>
      </li>
    </ul>
    <UiIconButton
      :icon="ChevronRight"
      variant="secondary"
      size="sm"
      flip-icon
      :label="t('common.pagination.next')"
      :disabled="page >= pageCount"
      @click="go(page + 1)"
    />
  </nav>
</template>
