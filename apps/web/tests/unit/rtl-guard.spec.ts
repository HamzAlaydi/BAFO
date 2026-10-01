import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

/**
 * RTL guard: layout must use logical utilities (ms-/me-/ps-/pe-/start-/end-/text-start/border-s ...)
 * so one markup serves Arabic and English. Physical left/right utilities are rejected.
 */
const APP_DIR = fileURLToPath(new URL('../../app', import.meta.url))

const PHYSICAL = /(?<![\w-])-?(?:ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|rounded-tl|rounded-tr|rounded-bl|rounded-br|scroll-ml|scroll-mr|scroll-pl|scroll-pr)-(?:\d|px|auto|full|none|xs|sm|md|lg|xl|2xl|3xl|\[|\()|(?<![\w-])(?:text-left|text-right|float-left|float-right|clear-left|clear-right|border-l|border-r|rounded-l|rounded-r)(?![\w-])/

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((entry) => {
    const path = join(dir, entry)
    if (statSync(path).isDirectory()) return files(path)
    return /\.(vue|ts)$/.test(entry) ? [path] : []
  })
}

describe('RTL guard', () => {
  it('uses logical instead of physical direction utilities', () => {
    const offences: string[] = []
    for (const file of files(APP_DIR)) {
      readFileSync(file, 'utf8').split('\n').forEach((line, index) => {
        if (PHYSICAL.test(line)) offences.push(`${relative(APP_DIR, file)}:${index + 1}: ${line.trim()}`)
      })
    }
    expect(offences).toEqual([])
  })
})
