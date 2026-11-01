// Notifications. The container is popover="manual", so it lives in the top layer and
// stays above an open <dialog>; it is re-shown when a dialog opens to be on top again.
export interface Toast {
  id: number
  type: 'ok' | 'error' | 'warn' | 'info'
  html: string
  detail?: string
  action?: { label: string; onClick: () => void }
  leaving?: boolean
}

const toasts = ref<Toast[]>([])
const raised = ref(0)
let seq = 0
const MAX = 4

function dismiss(id: number) {
  const toast = toasts.value.find((x) => x.id === id)
  if (!toast || toast.leaving) return
  toast.leaving = true
  setTimeout(() => (toasts.value = toasts.value.filter((x) => x.id !== id)), 260)
}

/** `html` is markup: build it with tb()/esc(), never from raw data. */
function show(html: string, opts: Omit<Toast, 'id' | 'html' | 'type'> & { type?: Toast['type']; timeout?: number } = {}) {
  const id = ++seq
  toasts.value = [...toasts.value.slice(-(MAX - 1)), { id, html, type: opts.type ?? 'info', detail: opts.detail, action: opts.action }]
  raised.value++
  setTimeout(() => dismiss(id), opts.timeout ?? (opts.action ? 7000 : 4200))
  return id
}

export const toast = {
  show,
  dismiss,
  ok: (html: string, detail?: string) => show(html, { type: 'ok', detail }),
  info: (html: string, detail?: string) => show(html, { type: 'info', detail }),
  warn: (html: string) => show(html, { type: 'warn' }),
  error: (html: string) => show(html, { type: 'error' }),
  /** Brings the container above a dialog that has just opened. */
  raise: () => raised.value++,
}

export const useToasts = () => ({ toasts: readonly(toasts), raised: readonly(raised), dismiss })

export function copyText(value: string) {
  const done = () => show(t('toast.copied'), { type: 'ok', timeout: 1800 })
  if (navigator.clipboard) {
    navigator.clipboard.writeText(value).then(done, done)
    return
  }
  const area = document.createElement('textarea')
  area.value = value
  document.body.appendChild(area)
  area.select()
  document.execCommand('copy')
  area.remove()
  done()
}
