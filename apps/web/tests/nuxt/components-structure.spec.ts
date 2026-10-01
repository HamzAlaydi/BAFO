import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { UiDropdownMenu, UiModal, UiTable } from '#components'

const t = (key: string) => useNuxtApp().$i18n.t(key)

describe('UiTable', () => {
  // mountSuspended erases the component's generic, so rows are typed as `object` here.
  const rowKey = (row: object) => (row as { id: string }).id
  const columns = [
    { key: 'name', label: 'Name', primary: true },
    { key: 'amount', label: 'Amount', numeric: true },
  ]

  it('renders a captioned table and stacked cards from the same rows', async () => {
    const wrapper = await mountSuspended(UiTable, {
      props: { columns, rows: [{ id: 'a', name: 'Offer A', amount: '1,000.00' }], rowKey, caption: 'Offers' },
    })
    expect(wrapper.get('caption').text()).toBe('Offers')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.get('td.tabular-nums').classes()).toContain('text-end')
    expect(wrapper.get('ul[aria-label="Offers"] dd').text()).toBe('1,000.00')
  })

  it('shows the empty state without rows', async () => {
    const wrapper = await mountSuspended(UiTable, { props: { columns, rows: [], rowKey, caption: 'Offers' } })
    expect(wrapper.text()).toContain(t('common.table.empty_title'))
  })
})

describe('UiDropdownMenu', () => {
  const items = [
    { key: 'one', label: 'One' },
    { type: 'separator' as const, key: 'sep' },
    { key: 'two', label: 'Two', disabled: true },
    { key: 'three', label: 'Three' },
  ]

  it('opens, skips disabled items with the arrow keys and selects', async () => {
    const wrapper = await mountSuspended(UiDropdownMenu, { props: { label: 'Actions', items }, attachTo: document.body })
    const trigger = wrapper.get('button[aria-haspopup="menu"]')
    await trigger.trigger('click')
    await nextTick()
    expect(trigger.attributes('aria-expanded')).toBe('true')
    const menu = wrapper.get('[role="menu"]')
    expect(document.activeElement?.textContent?.trim()).toBe('One')

    await menu.trigger('keydown', { key: 'ArrowDown' })
    expect(document.activeElement?.textContent?.trim()).toBe('Three')

    await wrapper.findAll('[role="menuitem"]')[2]!.trigger('click')
    expect(wrapper.emitted('select')?.at(-1)).toEqual(['three'])
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('closes on Escape and returns focus to the trigger', async () => {
    const wrapper = await mountSuspended(UiDropdownMenu, { props: { label: 'Actions', items }, attachTo: document.body })
    await wrapper.get('button').trigger('click')
    await nextTick()
    await wrapper.get('[role="menu"]').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    expect(document.activeElement).toBe(wrapper.get('button').element)
    wrapper.unmount()
  })
})

describe('UiModal', () => {
  it('labels the dialog with its title and closes from the close button', async () => {
    const wrapper = await mountSuspended(UiModal, {
      props: { title: 'Close competition', open: true },
      slots: { default: () => 'Body' },
    })
    const dialog = wrapper.get('dialog')
    const titleId = dialog.attributes('aria-labelledby')
    expect(wrapper.get(`#${titleId}`).text()).toBe('Close competition')
    await wrapper.get(`button[aria-label="${t('common.actions.close')}"]`).trigger('click')
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })

  it('cannot be dismissed when not dismissible', async () => {
    const wrapper = await mountSuspended(UiModal, { props: { title: 'Pay', open: true, dismissible: false } })
    expect(wrapper.find(`button[aria-label="${t('common.actions.close')}"]`).exists()).toBe(false)
    await wrapper.get('dialog').trigger('cancel')
    expect(wrapper.emitted('update:open')).toBeUndefined()
  })
})
