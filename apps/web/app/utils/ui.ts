/** Joins aria-describedby ids, skipping empty values. */
export function describedBy(...ids: Array<string | false | null | undefined>): string | undefined {
  const joined = ids.filter(Boolean).join(' ')
  return joined === '' ? undefined : joined
}

/** Initials for avatars. Arabic letters join, so Arabic names get one letter; Latin names get two. */
export function initials(name: string | null | undefined): string {
  const words = (name ?? '').trim().split(/\s+/).filter(Boolean)
  const first = words[0]
  if (!first) return ''
  const firstChar = Array.from(first)[0] ?? ''
  if (/\p{Script=Arabic}/u.test(firstChar)) return firstChar
  const second = words.length > 1 ? Array.from(words[words.length - 1] ?? '')[0] ?? '' : ''
  return (firstChar + second).toUpperCase()
}

/** Deterministic index in [0, buckets) for colouring avatars by name. */
export function hashToBucket(value: string, buckets: number): number {
  let hash = 0
  for (const ch of value) {
    hash = (hash * 31 + (ch.codePointAt(0) ?? 0)) >>> 0
  }
  return buckets > 0 ? hash % buckets : 0
}

/** "1.2 MB" style size (Latin digits); the unit label is localised by the caller. */
export function fileSizeParts(bytes: number): { value: string, unit: 'b' | 'kb' | 'mb' | 'gb' } {
  if (bytes < 1024) return { value: String(bytes), unit: 'b' }
  if (bytes < 1024 ** 2) return { value: (bytes / 1024).toFixed(1).replace(/\.0$/, ''), unit: 'kb' }
  if (bytes < 1024 ** 3) return { value: (bytes / 1024 ** 2).toFixed(1).replace(/\.0$/, ''), unit: 'mb' }
  return { value: (bytes / 1024 ** 3).toFixed(1).replace(/\.0$/, ''), unit: 'gb' }
}

/** Whether a file matches an `accept` attribute value (".pdf,image/*,application/zip"). */
export function fileMatchesAccept(file: { name: string, type: string }, accept: string | undefined): boolean {
  if (!accept) return true
  const rules = accept.split(',').map(rule => rule.trim().toLowerCase()).filter(Boolean)
  if (rules.length === 0) return true
  const name = file.name.toLowerCase()
  const type = file.type.toLowerCase()
  return rules.some((rule) => {
    if (rule.startsWith('.')) return name.endsWith(rule)
    if (rule.endsWith('/*')) return type.startsWith(rule.slice(0, -1))
    return type === rule
  })
}
