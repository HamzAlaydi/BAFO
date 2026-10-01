import type { MaybeRefOrGetter } from 'vue'

/**
 * Unsaved-changes guard for long forms and wizard steps (SCREENS S7): asks before leaving the page
 * in the app (route change) and before closing or reloading the tab.
 */
export function useUnsavedChangesGuard(dirty: MaybeRefOrGetter<boolean>) {
  const { t } = useI18n()

  onBeforeRouteLeave(() => {
    if (!toValue(dirty)) return true
    return window.confirm(t('common.unsaved_confirm'))
  })

  useEventListener('beforeunload', (event: BeforeUnloadEvent) => {
    if (!toValue(dirty)) return
    event.preventDefault()
    // Legacy browsers show the prompt only when returnValue is set.
    event.returnValue = ''
  })
}
