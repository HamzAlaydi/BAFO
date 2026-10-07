<script setup lang="ts">
import { FileQuestion } from '@lucide/vue'
import { fetchLegalDocument } from '~/services/platform'
import { LEGAL_DOCUMENT_CODES, type LegalDocument, type LegalDocumentCode } from '~/types/api/platform'

/**
 * W02 Legal · `/legal/{code}` (SCREENS §2.4): `terms`, `privacy`, `refund`, `competition_rules`,
 * `api_terms`. Server-rendered from `GET /legal/{code}` in the route's language; sanitised Markdown
 * with the version and publication date. No published version → «غير متاحة حالياً»; an unknown
 * code → the 404 page.
 */
const route = useRoute()
const { t } = useI18n()
const locale = useAppLocale()

const code = computed(() => String(route.params.code ?? ''))
if (!LEGAL_DOCUMENT_CODES.includes(code.value as LegalDocumentCode)) {
  throw createError({ statusCode: 404, statusMessage: 'Not Found', fatal: true })
}

type LegalResult = { kind: 'ok', document: LegalDocument } | { kind: 'not_found' } | { kind: 'error' }

const { data, status, refresh } = await useAsyncData<LegalResult>(
  () => `legal:${code.value}:${locale.value}`,
  async () => {
    try {
      return { kind: 'ok', document: await fetchLegalDocument(code.value as LegalDocumentCode) }
    }
    catch (error) {
      return error instanceof ApiError && error.isNotFound ? { kind: 'not_found' } : { kind: 'error' }
    }
  },
  { watch: [locale] },
)

const title = computed(() => (data.value?.kind === 'ok' ? data.value.document.title : t(`legal.codes.${code.value}`)))

const description = computed(() => t('legal.meta_description', { title: title.value }))

// Canonical and hreflang come from `app.vue`; a code without a published version is not indexed.
useSeoMeta({
  title: () => title.value,
  description: () => description.value,
  ogTitle: () => title.value,
  ogDescription: () => description.value,
  ogType: 'article',
  articleModifiedTime: () => (data.value?.kind === 'ok' ? data.value.document.published_at : undefined),
  robots: () => (data.value?.kind === 'ok' ? 'index, follow' : 'noindex, nofollow'),
})
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
    <div
      v-if="status === 'pending' && !data"
      class="flex flex-col gap-4"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-9 w-2/3" />
      <UiSkeleton :lines="8" />
    </div>

    <article
      v-else-if="data?.kind === 'ok'"
      class="flex flex-col gap-6"
    >
      <header class="flex flex-col gap-2 border-b border-line pb-6">
        <h1 class="text-3xl font-bold text-balance text-fg">
          {{ data.document.title }}
        </h1>
        <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-fg-muted">
          <span>{{ t('legal.version') }} <bdi class="font-semibold text-fg">{{ data.document.version }}</bdi></span>
          <span>{{ t('legal.published') }} <UiDateTime
            :value="data.document.published_at"
            format="date"
            class="font-semibold text-fg"
          /></span>
        </p>
      </header>
      <UiMarkdown
        :source="data.document.body_markdown"
        :title="data.document.title"
        :heading-offset="1"
      />
    </article>

    <UiEmptyState
      v-else-if="data?.kind === 'not_found'"
      :icon="FileQuestion"
      :title="t(`legal.codes.${code}`)"
      :description="t('legal.not_available')"
    >
      <UiButton
        to="/"
        variant="secondary"
      >
        {{ t('errors.page.back_home') }}
      </UiButton>
    </UiEmptyState>

    <UiErrorState
      v-else
      :retrying="status === 'pending'"
      @retry="refresh()"
    />
  </div>
</template>
