// The bucket list behind the sidebar switcher. Loaded once, refreshed after a page
// creates or deletes a bucket.
import type { Bucket, Page } from '~/types/panel'

const list = ref<Bucket[]>([])
let loaded: Promise<void> | null = null

async function load(): Promise<void> {
  list.value = (await api.get<Page<Bucket>>('/buckets', { limit: 100 })).data
}

export function useBuckets() {
  if (!loaded) loaded = load().catch(() => { loaded = null })
  return {
    buckets: readonly(list),
    refresh: () => (loaded = load()),
  }
}
