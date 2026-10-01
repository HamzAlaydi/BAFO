export type ToastVariant = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  variant: ToastVariant
  message: string
  title?: string
  /** Auto-dismiss delay in ms; 0 keeps the toast until dismissed. */
  duration: number
}

export type ToastInput = Omit<Toast, 'id' | 'duration' | 'variant'> & Partial<Pick<Toast, 'duration' | 'variant'>>

const MAX_VISIBLE = 4
const DEFAULT_DURATION: Record<ToastVariant, number> = {
  success: 5000,
  info: 5000,
  warning: 7000,
  error: 8000,
}

/** Global toast queue rendered by `<UiToaster />` (mounted once in app.vue). */
export function useToast() {
  const toasts = useState<Toast[]>('bafo:toasts', () => [])
  const sequence = useState<number>('bafo:toast-seq', () => 0)

  function dismiss(id: number): void {
    toasts.value = toasts.value.filter(toast => toast.id !== id)
  }

  function push(input: ToastInput): number {
    const variant = input.variant ?? 'info'
    const toast: Toast = {
      id: ++sequence.value,
      variant,
      message: input.message,
      title: input.title,
      duration: input.duration ?? DEFAULT_DURATION[variant],
    }
    toasts.value = [...toasts.value, toast].slice(-MAX_VISIBLE)
    if (import.meta.client && toast.duration > 0) {
      window.setTimeout(() => dismiss(toast.id), toast.duration)
    }
    return toast.id
  }

  const shortcut = (variant: ToastVariant) =>
    (message: string, options: Omit<ToastInput, 'message' | 'variant'> = {}) => push({ ...options, message, variant })

  return {
    toasts: readonly(toasts),
    push,
    dismiss,
    clear: () => { toasts.value = [] },
    success: shortcut('success'),
    error: shortcut('error'),
    warning: shortcut('warning'),
    info: shortcut('info'),
  }
}
