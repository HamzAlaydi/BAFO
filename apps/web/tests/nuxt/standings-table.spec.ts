import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h, ref } from 'vue'
import { makeAppConfig } from '../fixtures/api'
import { makeStandingRow } from '../fixtures/issuer'
import StandingsTable from '~/components/competitions/issuer/StandingsTable.vue'

/**
 * Award selection (W22). `UiTable` renders every row twice (the table, and the stacked cards shown
 * below the breakpoint), so each participant's radio exists in both layouts. Found by the live e2e
 * auction: the two copies shared one radio group, so checking the visible radio made the browser
 * uncheck it again when the hidden copy was bound checked — the winner looked unselected.
 */
describe('StandingsTable award radios', () => {
  const rows = [makeStandingRow(1), makeStandingRow(2)]

  async function mountSelectable() {
    const selected = ref<string | null>(null)
    const wrapper = await mountSuspended(defineComponent({
      setup: () => () => h(StandingsTable, {
        'rows': rows,
        'direction': 'auction',
        'selectable': true,
        'selected': selected.value,
        'onUpdate:selected': (value: string | null) => {
          selected.value = value
        },
      }),
    }))
    return { wrapper, selected }
  }

  const radiosOf = (wrapper: Awaited<ReturnType<typeof mountSelectable>>['wrapper'], participantId: string) =>
    wrapper.findAll<HTMLInputElement>(`input[type="radio"][value="${participantId}"]`)

  it('keeps the table and the card copies in separate radio groups', async () => {
    const { wrapper } = await mountSelectable()
    const copies = radiosOf(wrapper, 'p2')
    expect(copies).toHaveLength(2)
    const names = new Set(copies.map(radio => radio.element.name))
    expect(names.size).toBe(2)
    // Within one layout the participants still form one group (arrow keys move between them).
    const tableNames = new Set(wrapper.find('table').findAll<HTMLInputElement>('input[type="radio"]').map(radio => radio.element.name))
    expect(tableNames.size).toBe(1)
  })

  it('shows the chosen participant as checked in both layouts', async () => {
    const { wrapper, selected } = await mountSelectable()
    const [inTable] = radiosOf(wrapper, 'p2')
    await inTable!.setValue(true)
    await wrapper.vm.$nextTick()
    expect(selected.value).toBe('p2')
    for (const radio of radiosOf(wrapper, 'p2')) expect(radio.element.checked).toBe(true)
    for (const radio of radiosOf(wrapper, 'p1')) expect(radio.element.checked).toBe(false)
  })
})

describe('StandingsTable coverage column (RELEASE_SCOPE §1.3 sponsorship)', () => {
  const header = () => useNuxtApp().$i18n.t('invitations.issuer.table.coverage')

  it('hides the fee coverage column in core unless a covered pass exists', async () => {
    useAppConfigStore().config = makeAppConfig({}, 'core')
    const plain = await mountSuspended(StandingsTable, { props: { rows: [makeStandingRow(1)], direction: 'tender' } })
    expect(plain.find('thead').text()).not.toContain(header())
    plain.unmount()

    const covered = makeStandingRow(2)
    covered.participant.coverage = 'sponsored'
    const existing = await mountSuspended(StandingsTable, { props: { rows: [makeStandingRow(1), covered], direction: 'tender' } })
    expect(existing.find('thead').text()).toContain(header())
    existing.unmount()

    useAppConfigStore().config = makeAppConfig({}, 'full')
    const full = await mountSuspended(StandingsTable, { props: { rows: [makeStandingRow(1)], direction: 'tender' } })
    expect(full.find('thead').text()).toContain(header())
    full.unmount()
  })
})
