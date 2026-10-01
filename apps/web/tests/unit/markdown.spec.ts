import { describe, expect, it } from 'vitest'
import { renderMarkdown } from '~/utils/markdown'

describe('renderMarkdown (legal pages, W02)', () => {
  it('renders headings, lists and emphasis', () => {
    const html = renderMarkdown('# الشروط\n\n1. **أولاً**\n2. ثانياً')
    expect(html).toContain('<h1>الشروط</h1>')
    expect(html).toContain('<ol>')
    expect(html).toContain('<strong>أولاً</strong>')
  })

  it('leaves out the heading that repeats the page title and moves the others down one level', () => {
    const html = renderMarkdown('Intro.\n\n# الشروط والأحكام\n\n# أولاً\n\n## تفاصيل', { title: 'الشروط والأحكام', headingOffset: 1 })
    expect(html).not.toContain('الشروط والأحكام')
    expect(html).not.toContain('<h1>')
    expect(html).toContain('<h2>أولاً</h2>')
    expect(html).toContain('<h3>تفاصيل</h3>')
  })

  it('escapes raw HTML', () => {
    const html = renderMarkdown('<script>alert(1)</script><img src=x onerror=alert(1)>')
    expect(html).not.toContain('<script')
    expect(html).not.toContain('<img')
    expect(html).toContain('&lt;script&gt;')
  })

  it('drops unsafe link schemes and opens external links safely', () => {
    expect(renderMarkdown('[x](javascript:alert(1))')).not.toContain('href="javascript')
    expect(renderMarkdown('[x](http://insecure.example)')).not.toContain('href=')
    const external = renderMarkdown('[BAFO](https://bafo.sa)')
    expect(external).toContain('href="https://bafo.sa"')
    expect(external).toContain('rel="noopener noreferrer"')
    expect(external).toContain('target="_blank"')
    expect(renderMarkdown('[terms](/ar/legal/terms)')).toContain('href="/ar/legal/terms"')
    expect(renderMarkdown('[mail](mailto:legal@bafo.sa)')).toContain('href="mailto:legal@bafo.sa"')
  })

  it('renders images as their alt text only', () => {
    expect(renderMarkdown('![logo](https://bafo.sa/x.png)')).not.toContain('<img')
  })
})
