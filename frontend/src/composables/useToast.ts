import { ref, readonly } from 'vue'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface Toast {
  id: number
  message: string
  type: ToastType
  duration?: number
}

const toasts = ref<Toast[]>([])
let nextId = 0

export function useToast() {

  /**
   * Show a new toast notification
   * @param message The message to display
   * @param type 'success' | 'error' | 'info' | 'warning'
   * @param duration Duration in ms (default 3000)
   */
  const showToast = (message: string, type: ToastType = 'success', duration = 3000) => {
    const id = nextId++
    const toast: Toast = { id, message, type, duration }
    toasts.value.push(toast)

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }
  }

  const removeToast = (id: number) => {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  return {
    toasts: readonly(toasts),
    showToast,
    removeToast
  }
}
