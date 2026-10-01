import type { Ref } from 'vue'

/**
 * Drives a native `<dialog>` from an `open` ref: modal top layer, focus containment and Esc come
 * from the platform. Adds backdrop-click dismissal and page scroll locking.
 */
export function useDialogElement(
  dialog: Readonly<Ref<HTMLDialogElement | null>>,
  open: Ref<boolean>,
  options: { dismissible: () => boolean },
) {
  const scrollLock = import.meta.client ? useScrollLock(document.body) : ref(false)

  watch([open, dialog], ([isOpen, element]) => {
    if (!element) return
    if (isOpen && !element.open) {
      element.showModal()
      scrollLock.value = true
    }
    else if (!isOpen && element.open) {
      element.close()
    }
  }, { flush: 'post', immediate: true })

  function onClose(): void {
    scrollLock.value = false
    open.value = false
  }

  function onCancel(event: Event): void {
    event.preventDefault()
    if (options.dismissible()) open.value = false
  }

  function onBackdropClick(event: MouseEvent): void {
    if (event.target === dialog.value && options.dismissible()) open.value = false
  }

  onBeforeUnmount(() => {
    scrollLock.value = false
  })

  return { onClose, onCancel, onBackdropClick }
}
