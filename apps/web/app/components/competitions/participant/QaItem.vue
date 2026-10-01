<script setup lang="ts">
import { BadgeCheck, CornerDownLeft } from '@lucide/vue'
import type { Comment, CommentAuthor } from '~/types/api/competitions'

/**
 * One Q&A thread as a participant sees it (SCREENS W18, API.md §2.7 `author` projection):
 * the issuer by organisation name with the «طارح المنافسة» badge; the viewer's own organisation as
 * «أنتم»; other participants as «المتنافس {n}» only (the server never sends their names). A
 * participant may add a follow-up only under its own organisation's questions.
 */
const props = defineProps<{
  comment: Comment
  canReply: boolean
  /** Ids to highlight briefly (new realtime items). */
  highlighted?: ReadonlySet<string>
  replying?: boolean
  replyError?: string | null
}>()

const emit = defineEmits<{ reply: [body: string] }>()
const { t } = useI18n()
const open = ref(false)
const body = ref('')
const inputId = `qa-reply-${useId()}`

function authorLabel(author: CommentAuthor): string {
  switch (author.kind) {
    case 'issuer': return author.organization_name
    case 'me': return t('qa.participant.author.me')
    default: return t('qa.participant.author.participant', { alias: author.alias_no })
  }
}

const ownThread = computed(() => props.comment.author.kind === 'me')

function submit(): void {
  const text = body.value.trim()
  if (!text || props.replying) return
  emit('reply', text)
}

watch(() => props.replying, (now, before) => {
  if (before && !now && !props.replyError) {
    body.value = ''
    open.value = false
  }
})
</script>

<template>
  <article
    class="flex flex-col gap-3 rounded-lg border border-line bg-surface p-4 transition-colors duration-300"
    :class="highlighted?.has(comment.id) && 'motion-safe:bg-primary-soft/40'"
    :aria-label="t('qa.participant.thread_label', { author: authorLabel(comment.author) })"
    data-testid="qa-thread"
  >
    <header class="flex flex-wrap items-center gap-2 text-sm">
      <span class="font-bold text-fg">{{ authorLabel(comment.author) }}</span>
      <UiBadge
        v-if="comment.author.kind === 'issuer'"
        tone="primary"
        size="sm"
        :icon="BadgeCheck"
      >
        {{ t('glossary.issuer') }}
      </UiBadge>
      <span class="text-fg-muted">·</span>
      <UiRelativeTime
        :value="comment.created_at"
        class="text-fg-muted"
      />
    </header>
    <p
      dir="auto"
      class="text-sm leading-relaxed break-words whitespace-pre-line text-fg"
    >
      {{ comment.body }}
    </p>

    <ol
      v-if="comment.replies.length > 0"
      class="flex flex-col gap-3 border-s-2 border-line ps-4"
      :aria-label="t('qa.participant.replies_label')"
    >
      <li
        v-for="reply in comment.replies"
        :key="reply.id"
        class="flex flex-col gap-1 rounded-md transition-colors duration-300"
        :class="highlighted?.has(reply.id) && 'motion-safe:bg-primary-soft/40'"
      >
        <p class="flex flex-wrap items-center gap-2 text-sm">
          <span class="font-semibold text-fg">{{ authorLabel(reply.author) }}</span>
          <UiBadge
            v-if="reply.author.kind === 'issuer'"
            tone="primary"
            size="sm"
          >
            {{ t('glossary.issuer') }}
          </UiBadge>
          <span class="text-fg-muted">·</span>
          <UiRelativeTime
            :value="reply.created_at"
            class="text-fg-muted"
          />
        </p>
        <p
          dir="auto"
          class="text-sm leading-relaxed break-words whitespace-pre-line text-fg"
        >
          {{ reply.body }}
        </p>
      </li>
    </ol>

    <template v-if="canReply && ownThread">
      <UiButton
        v-if="!open"
        variant="ghost"
        size="sm"
        :icon="CornerDownLeft"
        :flip-icons="true"
        class="self-start"
        @click="open = true"
      >
        {{ t('qa.participant.reply_open') }}
      </UiButton>
      <form
        v-else
        class="flex flex-col gap-2"
        novalidate
        @submit.prevent="submit"
      >
        <UiTextarea
          :id="inputId"
          v-model="body"
          :label="t('qa.participant.reply_label')"
          :maxlength="2000"
          :rows="2"
          :error="replyError"
          required
        />
        <div class="flex justify-end gap-2">
          <UiButton
            variant="secondary"
            size="sm"
            :disabled="replying"
            @click="open = false"
          >
            {{ t('common.actions.cancel') }}
          </UiButton>
          <UiButton
            type="submit"
            size="sm"
            :loading="replying"
            :disabled="body.trim() === ''"
          >
            {{ t('qa.participant.reply_submit') }}
          </UiButton>
        </div>
      </form>
    </template>
  </article>
</template>
