<script setup lang="ts">
import { ArrowUp, MessagesSquare } from '@lucide/vue'
import { listComments, postComment } from '~/services/competitions'
import type { Comment, ParticipantCompetition } from '~/types/api/competitions'

/**
 * W18 Q&A for a **participant** (SCREENS §2.4): ask a question (other participants see it without
 * the organisation's name; the issuer sees the name), read every public question and the issuer's
 * answers and announcements, and add follow-ups under the organisation's own questions. Top-level
 * threads are newest first, replies oldest first; "Show older questions" loads the next page.
 *
 * Realtime: `comment.created` (projected per audience) is inserted and briefly highlighted; a pill
 * counts new messages while the top of the list is scrolled away. Posting is allowed only while
 * `permissions.can_comment` (scheduled or live); otherwise a read-only notice replaces the composer.
 * Mount it in the Q&A tab for participants; it reads `useCompetitionContext()`.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'participant' ? ctx.competition.value as ParticipantCompetition : null))
const closedByServer = ref(false)
const canComment = computed(() => Boolean(competition.value?.permissions.can_comment) && !closedByServer.value)

const threads = ref<Comment[]>([])
const page = ref(1)
const hasMore = ref(false)
const loading = ref(false)
const loadingMore = ref(false)
const loadError = ref<unknown>(null)
let loadSeq = 0

const body = ref('')
const posting = ref(false)
const postError = ref<string | null>(null)
const replyingTo = ref<string | null>(null)
const replyErrors = ref<Record<string, string | null>>({})
const highlighted = ref<Set<string>>(new Set())

const top = useTemplateRef<HTMLElement>('top')
const topVisible = useElementVisibility(top)
const unseen = ref(0)

watch(topVisible, (visible) => {
  if (visible) unseen.value = 0
})

async function load(reset = true): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  const seq = ++loadSeq
  const target = reset ? 1 : page.value + 1
  if (reset) loading.value = true
  else loadingMore.value = true
  try {
    const result = await listComments(id, { page: target })
    if (seq !== loadSeq) return
    threads.value = reset ? result.items : mergeThreads(threads.value, result.items)
    page.value = target
    hasMore.value = result.pagination.has_more
    loadError.value = null
  }
  catch (error) {
    if (seq !== loadSeq) return
    if (reset) loadError.value = error
    else toast.error(message(error))
  }
  finally {
    if (seq === loadSeq) {
      loading.value = false
      loadingMore.value = false
    }
  }
}

function mergeThreads(current: Comment[], older: Comment[]): Comment[] {
  const known = new Set(current.map(thread => thread.id))
  return [...current, ...older.filter(thread => !known.has(thread.id))]
}

watch(() => competition.value?.id, (id) => {
  threads.value = []
  if (id) void load()
}, { immediate: true })

function flash(id: string): void {
  highlighted.value = new Set([...highlighted.value, id])
  setTimeout(() => {
    const next = new Set(highlighted.value)
    next.delete(id)
    highlighted.value = next
  }, 1500)
}

/** Inserts a server comment (realtime or the POST answer), de-duplicated by id. */
function insert(comment: Comment, fromRealtime: boolean): void {
  if (comment.parent_id === null) {
    if (threads.value.some(thread => thread.id === comment.id)) return
    threads.value = [{ ...comment, replies: comment.replies ?? [] }, ...threads.value]
  }
  else {
    const parent = threads.value.find(thread => thread.id === comment.parent_id)
    if (!parent || parent.replies.some(reply => reply.id === comment.id)) return
    threads.value = threads.value.map(thread => (thread.id === comment.parent_id ? { ...thread, replies: [...thread.replies, comment] } : thread))
  }
  if (fromRealtime) {
    flash(comment.id)
    if (!topVisible.value) unseen.value += 1
  }
}

ctx.on('commentCreated', comment => insert(comment, true))

function onPostError(error: unknown): string {
  if (error instanceof ApiError && error.code === 'comments_closed') {
    closedByServer.value = true
    void ctx.refetch()
  }
  if (error instanceof ApiError && error.isValidation) return error.fieldError('body') ?? message(error)
  return message(error)
}

async function ask(): Promise<void> {
  const id = competition.value?.id
  const text = body.value.trim()
  if (!id || !text || posting.value) return
  posting.value = true
  postError.value = null
  try {
    insert(await postComment(id, text, null), false)
    body.value = ''
    toast.success(t('qa.participant.posted'))
  }
  catch (error) {
    postError.value = onPostError(error)
  }
  finally {
    posting.value = false
  }
}

async function reply(thread: Comment, text: string): Promise<void> {
  const id = competition.value?.id
  if (!id || replyingTo.value) return
  replyingTo.value = thread.id
  replyErrors.value = { ...replyErrors.value, [thread.id]: null }
  try {
    insert(await postComment(id, text, thread.id), false)
    toast.success(t('qa.participant.reply_posted'))
  }
  catch (error) {
    replyErrors.value = { ...replyErrors.value, [thread.id]: onPostError(error) }
  }
  finally {
    replyingTo.value = null
  }
}

function scrollToTop(): void {
  top.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  unseen.value = 0
}
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-4"
    data-testid="participant-qa"
  >
    <div ref="top" />

    <UiCard v-if="canComment">
      <form
        class="flex flex-col gap-3"
        novalidate
        @submit.prevent="ask"
      >
        <UiTextarea
          v-model="body"
          :label="t('qa.participant.ask_label')"
          :hint="t('qa.participant.ask_hint')"
          :maxlength="2000"
          :rows="3"
          :error="postError"
          required
          data-testid="qa-question"
        />
        <UiButton
          type="submit"
          class="self-end"
          :loading="posting"
          :disabled="body.trim() === ''"
          data-testid="qa-ask"
        >
          {{ t('qa.participant.ask_submit') }}
        </UiButton>
      </form>
    </UiCard>
    <UiAlert
      v-else
      tone="info"
      :title="t('errors.comments_closed')"
      data-testid="qa-closed"
    >
      {{ t('qa.participant.closed_body') }}
    </UiAlert>

    <div
      v-if="loading && threads.length === 0"
      class="flex flex-col gap-3"
      aria-busy="true"
      :aria-label="t('common.loading')"
    >
      <UiSkeleton class="h-24" />
      <UiSkeleton class="h-24" />
    </div>
    <UiErrorState
      v-else-if="loadError && threads.length === 0"
      :error="loadError"
      @retry="load()"
    />
    <UiCard
      v-else-if="threads.length === 0"
      padding="none"
    >
      <UiEmptyState
        :icon="MessagesSquare"
        :title="t('qa.participant.empty.title')"
        :description="t('qa.participant.empty.body')"
      />
    </UiCard>
    <template v-else>
      <ol
        class="flex flex-col gap-3"
        :aria-label="t('qa.participant.title')"
      >
        <li
          v-for="thread in threads"
          :key="thread.id"
        >
          <CompetitionsParticipantQaItem
            :comment="thread"
            :can-reply="canComment"
            :highlighted="highlighted"
            :replying="replyingTo === thread.id"
            :reply-error="replyErrors[thread.id] ?? null"
            @reply="reply(thread, $event)"
          />
        </li>
      </ol>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-center"
        :loading="loadingMore"
        @click="load(false)"
      >
        {{ t('qa.participant.load_more') }}
      </UiButton>
    </template>

    <div
      v-if="unseen > 0"
      class="pointer-events-none sticky bottom-4 flex justify-center"
    >
      <UiButton
        class="pointer-events-auto shadow-md"
        size="sm"
        :icon="ArrowUp"
        data-testid="qa-new-pill"
        @click="scrollToTop"
      >
        {{ t('qa.participant.new_messages', { count: unseen }, unseen) }}
      </UiButton>
    </div>
    <p
      class="sr-only"
      aria-live="polite"
    >
      {{ unseen > 0 ? t('qa.participant.new_messages', { count: unseen }, unseen) : '' }}
    </p>
  </div>
</template>
