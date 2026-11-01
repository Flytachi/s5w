// Where the client API and delivery live — another port than this panel, so the
// panel asks once instead of guessing from its own address.
export interface PanelInfo { baseUrl: string; configured: boolean }

const info = ref<PanelInfo | null>(null)
let loading: Promise<unknown> | null = null

export function useInfo() {
  loading ??= api.get<PanelInfo>('/panel/info').then((value) => (info.value = value)).catch(() => (loading = null))
  return info
}
