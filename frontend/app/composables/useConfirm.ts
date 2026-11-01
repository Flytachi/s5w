// One confirmation at a time, rendered by <UiConfirmHost> in the layout.
export interface ConfirmRequest {
  title: string
  html: string
  confirmLabel?: string
  tone?: 'danger' | 'brand'
}

const current = ref<(ConfirmRequest & { resolve: (ok: boolean) => void }) | null>(null)

export function confirmDialog(request: ConfirmRequest): Promise<boolean> {
  current.value?.resolve(false)
  return new Promise((resolve) => (current.value = { ...request, resolve }))
}

export const useConfirmState = () => current
