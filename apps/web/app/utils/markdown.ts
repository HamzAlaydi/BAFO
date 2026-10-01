import MarkdownIt from 'markdown-it'

/**
 * Sanitised Markdown for server-authored documents (legal pages, W02).
 *
 * Safety comes from the parser configuration, not from post-filtering:
 * - `html: false` escapes every raw HTML tag in the source;
 * - markdown-it's `validateLink` rejects `javascript:`, `vbscript:`, `file:` and non-image `data:` URLs;
 * - links are further limited to `https:`, `mailto:`, `tel:`, in-page `#` anchors and same-site paths,
 *   and external ones open with `rel="noopener noreferrer"`.
 */
const md = new MarkdownIt({ html: false, linkify: false, typographer: false, breaks: false })

const SAFE_LINK = /^(?:https:\/\/|mailto:|tel:|#|\/(?!\/))/i
const defaultValidate = md.validateLink.bind(md)
md.validateLink = (url: string) => defaultValidate(url) && SAFE_LINK.test(url.trim())

const defaultLinkOpen = md.renderer.rules.link_open
md.renderer.rules.link_open = (tokens, index, options, env, self) => {
  const token = tokens[index]
  const href = token?.attrGet('href') ?? ''
  if (token && /^https:\/\//i.test(href)) {
    token.attrSet('target', '_blank')
    token.attrSet('rel', 'noopener noreferrer')
  }
  return defaultLinkOpen ? defaultLinkOpen(tokens, index, options, env, self) : self.renderToken(tokens, index, options)
}

// Images in legal text are not supported: render their alt text only.
md.renderer.rules.image = (tokens, index) => md.utils.escapeHtml(tokens[index]?.content ?? '')

export interface MarkdownOptions {
  /** The page's own title: a level-1 heading with the same text is left out (the page shows it). */
  title?: string
  /** Heading levels move down by this much, so a page with its own `<h1>` renders `#` as `<h2>`. */
  headingOffset?: number
}

export function renderMarkdown(source: string, options: MarkdownOptions = {}): string {
  const tokens = md.parse(source ?? '', {})
  const title = options.title?.trim()
  if (title) {
    const index = tokens.findIndex((token, at) => token.type === 'heading_open' && token.tag === 'h1' && tokens[at + 1]?.content.trim() === title)
    if (index !== -1) tokens.splice(index, 3)
  }
  const offset = options.headingOffset ?? 0
  if (offset > 0) {
    for (const token of tokens) {
      if (token.type === 'heading_open' || token.type === 'heading_close') token.tag = `h${Math.min(6, Number(token.tag.slice(1)) + offset)}`
    }
  }
  return md.renderer.render(tokens, md.options, {})
}
