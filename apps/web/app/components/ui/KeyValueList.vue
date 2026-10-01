<script setup lang="ts">
import type { KeyValueItem } from '~/types/ui'

/**
 * Definition list (award, invoice, organization details). Values render through a `value-<key>`
 * slot when provided; empty values show «—». Stacks label over value below `sm`.
 */
defineProps<{ items: KeyValueItem[] }>()
</script>

<template>
  <dl class="divide-y divide-line">
    <div
      v-for="item in items"
      :key="item.key"
      class="grid grid-cols-1 gap-1 py-3 text-sm first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] sm:gap-4"
    >
      <dt class="text-fg-muted">
        {{ item.label }}
      </dt>
      <dd class="min-w-0 font-medium break-words text-fg">
        <slot
          :name="`value-${item.key}`"
          :item="item"
        >
          <bdi v-if="item.ltr && item.value !== null && item.value !== undefined && item.value !== ''">{{ item.value }}</bdi>
          <template v-else>
            {{ item.value === null || item.value === undefined || item.value === '' ? '—' : item.value }}
          </template>
        </slot>
      </dd>
    </div>
  </dl>
</template>
