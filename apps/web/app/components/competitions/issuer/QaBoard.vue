<script setup lang="ts">
import { ArrowUp, Megaphone, MessagesSquare, Reply } from '@lucide/vue'
import { listComments, postComment } from '~/services/competitions'
import type { Comment, CommentAuthor, IssuerCompetition } from '~/types/api/competitions'

/**
 * W18 Q&A for the **issuer** (SCREENS §2.4): publish announcements to every participant and reply to
 * any question. Authors are rendered from the projection the issuer receives: the issuer's
 * organisation with the «طارح المنافسة» badge, and participants as «المتنافس {n} · {organisation}».
 * Top-level threads are newest first, replies oldest first; "Show older" loads the next page.
 *
 * Realtime `comment.created` inserts (de-duplicated by id; replies under their parent) and highlights
 * new items; a pill counts new messages while the top is scrolled away. Posting is allowed while
 * `permissions.can_comment` (scheduled or live); `comments_closed` switches to a read-only notice.
 */
const ctx = useCompetitionContext()
const { t } = useI18n()
const toast = useToast()
const { message } = useErrorMessage()

const competition = computed(() => (ctx.competition.value?.viewer_role === 'issuer' ? ctx.competition.value as IssuerCompetition : null))
const closedByServer = ref(false)
const canComment = computed(() => Boolean(competition.value?.permissions.can_comment) && !closedByServer.value)

const threads = ref<Comment[]>([])
const page = ref(1)
const hasMore = ref(false)
const loading = ref(true)
const loadingMore = ref(false)
const loadError = ref<unknown>(null)
const body = ref('')
const posting = ref(false)
const postError = ref<string | null>(null)
const replyOpen = ref<string | null>(null)
const replyBody = ref('')
const replyBusy = ref(false)
const replyError = ref<string | null>(null)
const highlighted = ref<Set<string>>(new Set())
const top = useTemplateRef<HTMLElement>('top')
const topVisible = useElementVisibility(top)
const unseen = ref(0)

watch(topVisible, (visible) => {
  if (visible) unseen.value = 0
})

async function load(next = 1): Promise<void> {
  const id = competition.value?.id
  if (!id) return
  if (next === 1) loading.value = true
  else loadingMore.value = true
  loadError.value = null
  try {
    const result = await listComments(id, { page: next, per_page: 20 })
    threads.value = next === 1 ? result.items : [...threads.value, ...result.items.filter(item => !threads.value.some(existing => existing.id === item.id))]
    page.value = next
    hasMore.value = result.pagination.has_more
  }
  catch (error) {
    loadError.value = error
  }
  finally {
    loading.value = false
    loadingMore.value = false
  }
}

onMounted(() => void load())

function highlight(id: string): void {
  highlighted.value = new Set([...highlighted.value, id])
  setTimeout(() => {
    const next = new Set(highlighted.value)
    next.delete(id)
    highlighted.value = next
  }, 2500)
}

/** Inserts a comment (realtime or own post): top-level first, replies at the end of their thread. */
function insert(comment: Comment): boolean {
  if (comment.parent_id) {
    let inserted = false
    threads.value = threads.value.map((thread) => {
      if (thread.id !== comment.parent_id || thread.replies.some(reply => reply.id === comment.id)) return thread
      inserted = true
      return { ...thread, replies: [...thread.replies, comment] }
    })
    return inserted
  }
  if (threads.value.some(thread => thread.id === comment.id)) return false
  threads.value = [{ ...comment, replies: comment.replies ?? [] }, ...threads.value]
  return true
}

ctx.on('commentCreated', (comment) => {
  if (insert(comment)) {
    highlight(comment.id)
    if (!topVisible.value) unseen.value += 1
  }
})

function handlePostError(error: unknown): string {
  if (error instanceof ApiError && error.code === 'comments_closed') {
    closedByServer.value = true
    return t('qa.closed')
  }
  return message(error)
}

async function publish(): Promise<void> {
  const id = competition.value?.id
  const text = body.value.trim()
  if (!id || !text) return
  posting.value = true
  postError.value = null
  try {
    const created = await postComment(id, text, null)
    insert(created)
    body.value = ''
    toast.success(t('qa.issuer.published'))
  }
  catch (error) {
    postError.value = handlePostError(error)
  }
  finally {
    posting.value = false
  }
}

function openReply(threadId: string): void {
  replyOpen.value = replyOpen.value === threadId ? null : threadId
  replyBody.value = ''
  replyError.value = null
}

async function reply(threadId: string): Promise<void> {
  const id = competition.value?.id
  const text = replyBody.value.trim()
  if (!id || !text) return
  replyBusy.value = true
  replyError.value = null
  try {
    insert(await postComment(id, text, threadId))
    replyOpen.value = null
    replyBody.value = ''
  }
  catch (error) {
    replyError.value = handlePostError(error)
  }
  finally {
    replyBusy.value = false
  }
}

function authorName(author: CommentAuthor): string {
  if (author.kind === 'issuer') return author.organization_name
  if (author.kind === 'me') return t('qa.author.you')
  const alias = t('offers.participant_alias', { number: author.alias_no })
  return author.organization_name ? `${alias} · ${author.organization_name}` : alias
}

function scrollToTop(): void {
  top.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  unseen.value = 0
}
</script>

<template>
  <div
    v-if="competition"
    class="flex flex-col gap-5"
  >
    <div ref="top" />
    <UiCard
      v-if="canComment"
      padding="sm"
    >
      <form
        class="flex flex-col gap-3"
        novalidate
        @submit.prevent="publish"
      >
        <UiTextarea
          v-model="body"
          :label="t('qa.issuer.composer_label')"
          :hint="t('qa.issuer.composer_hint')"
          :maxlength="2000"
          :rows="3"
          :error="postError"
        />
        <div class="flex justify-end">
          <UiButton
            type="submit"
            :icon="Megaphone"
            :loading="posting"
            :disabled="!body.trim()"
          >
            {{ t('qa.issuer.publish') }}
          </UiButton>
        </div>
      </form>
    </UiCard>
    <UiAlert
      v-else
      tone="info"
    >
      {{ t('qa.closed') }}
    </UiAlert>

    <button
      v-if="unseen > 0"
      type="button"
      class="sticky top-20 z-10 inline-flex items-center gap-1.5 self-center rounded-full bg-fg px-4 py-2 text-sm font-semibold text-fg-inverse shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
      @click="scrollToTop"
    >
      <ArrowUp
        :size="16"
        aria-hidden="true"
      />
      {{ t('qa.new_messages', { count: unseen }, unseen) }}
    </button>

    <div
      v-if="loading"
      class="flex flex-col gap-3"
    >
      <UiSkeleton class="h-24 w-full" />
      <UiSkeleton class="h-24 w-full" />
    </div>
    <UiCard
      v-else-if="loadError && threads.length === 0"
      padding="none"
    >
      <UiErrorState
        :error="loadError"
        @retry="load()"
      />
    </UiCard>
    <UiCard
      v-else-if="threads.length === 0"
      padding="none"
    >
      <UiEmptyState
        :title="t('qa.empty.title')"
        :description="t('qa.issuer.empty_body')"
        :icon="MessagesSquare"
      />
    </UiCard>

    <ul
      v-else
      class="flex flex-col gap-4"
      :aria-label="t('qa.threads_label')"
    >
      <li
        v-for="thread in threads"
        :key="thread.id"
      >
        <article
          class="flex flex-col gap-3 rounded-lg border bg-surface p-4 transition-colors duration-300 motion-reduce:transition-none"
          :class="highlighted.has(thread.id) ? 'border-primary bg-primary-soft/40' : 'border-line'"
        >
          <header class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-bold text-fg">{{ authorName(thread.author) }}</span>
            <UiBadge
              v-if="thread.author.kind === 'issuer'"
              size="sm"
              tone="neutral"
              solid
            >
              {{ t('glossary.issuer') }}
            </UiBadge>
            <UiRelativeTime
              :value="thread.created_at"
              class="text-fg-muted"
            />
          </header>
          <p class="text-sm leading-relaxed whitespace-pre-line text-fg">
            {{ thread.body }}
          </p>
          <ul
            v-if="thread.replies.length > 0"
            class="flex flex-col gap-3 border-s-2 border-line ps-4"
          >
            <li
              v-for="replyItem in thread.replies"
              :key="replyItem.id"
              class="flex flex-col gap-1 rounded-md transition-colors duration-300 motion-reduce:transition-none"
              :class="highlighted.has(replyItem.id) && 'bg-primary-soft/40'"
            >
              <span class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold text-fg">{{ authorName(replyItem.author) }}</span>
                <UiBadge
                  v-if="replyItem.author.kind === 'issuer'"
                  size="sm"
                  tone="neutral"
                  solid
                >
                  {{ t('glossary.issuer') }}
                </UiBadge>
                <UiRelativeTime
                  :value="replyItem.created_at"
                  class="text-fg-muted"
                />
              </span>
              <p class="text-sm whitespace-pre-line text-fg">
                {{ replyItem.body }}
              </p>
            </li>
          </ul>
          <div v-if="canComment">
            <UiButton
              v-if="replyOpen !== thread.id"
              variant="ghost"
              size="sm"
              :icon="Reply"
              flip-icons
              @click="openReply(thread.id)"
            >
              {{ t('qa.reply') }}
            </UiButton>
            <form
              v-else
              class="flex flex-col gap-2"
              novalidate
              @submit.prevent="reply(thread.id)"
            >
              <UiTextarea
                v-model="replyBody"
                :label="t('qa.reply_label')"
                :maxlength="2000"
                :rows="2"
                :error="replyError"
              />
              <div class="flex justify-end gap-2">
                <UiButton
                  variant="secondary"
                  size="sm"
                  :disabled="replyBusy"
                  @click="openReply(thread.id)"
                >
                  {{ t('common.actions.cancel') }}
                </UiButton>
                <UiButton
                  type="submit"
                  size="sm"
                  :loading="replyBusy"
                  :disabled="!replyBody.trim()"
                >
                  {{ t('qa.send_reply') }}
                </UiButton>
              </div>
            </form>
          </div>
        </article>
      </li>
    </ul>

    <div
      v-if="hasMore"
      class="flex justify-center"
    >
      <UiButton
        variant="secondary"
        :loading="loadingMore"
        @click="load(page + 1)"
      >
        {{ t('qa.load_older') }}
      </UiButton>
    </div>
  </div>
</template>
