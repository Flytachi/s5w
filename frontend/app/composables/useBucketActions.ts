// Create, edit, cache policy and delete of a bucket — shared by the list and the
// bucket's own pages. `changed` is called whenever what is shown may be stale.
import type { Bucket } from '~/types/panel'
import type { CacheTarget, Visibility } from '~/types/ui'

export function useBucketActions(changed: () => unknown) {
  const { refresh } = useBuckets()
  const formOpen = ref(false)
  const editing = ref<Bucket | null>(null)
  const cacheOpen = ref(false)
  const cacheTarget = ref<CacheTarget | null>(null)

  const reload = () => {
    refresh()
    return changed()
  }

  function create() {
    editing.value = null
    formOpen.value = true
  }

  function edit(bucket: Bucket) {
    editing.value = bucket
    formOpen.value = true
  }

  function cache(bucket: Bucket) {
    cacheTarget.value = {
      bucketId: bucket.id,
      maxAge: bucket.cache.maxAge,
      visibility: bucket.cache.visibility.name as Visibility,
    }
    cacheOpen.value = true
  }

  async function onCreated(bucket: Bucket) {
    toast.info(tb('bucket.creating', { name: bucket.name }), t('bucket.creatingNote'))
    await reload()
    // A 202: the row exists, the directory not yet. Asked until the provisioner reports
    // ACTIVE — its actual work, not an animation.
    watchBucket(bucket.id, (status) => {
      if (status !== 'ACTIVE') return false
      toast.ok(tb('bucket.ready', { name: bucket.name }))
      reload()
      return true
    })
  }

  function onUpdated(bucket: Bucket) {
    toast.ok(tb('bucket.saved', { name: bucket.name }))
    reload()
  }

  /** Irreversible and done in the background: PENDING at once, gone when the server stops answering for it. */
  async function remove(bucket: Bucket, after?: () => unknown) {
    const ok = await confirmDialog({ title: t('bucket.deleteTitle'), html: tb('bucket.deleteText', { name: bucket.name }) })
    if (!ok) return
    if (!(await attempt(() => api.delete(`/buckets/${bucket.id}`)))) return
    toast.info(tb('bucket.deleting', { name: bucket.name }), t('bucket.deletingNote'))
    await (after ? after() : reload())
    watchBucket(bucket.id, (status) => {
      if (status !== 'GONE') return false
      toast.ok(tb('bucket.deleted', { name: bucket.name }))
      reload()
      return true
    })
  }

  return { formOpen, editing, cacheOpen, cacheTarget, create, edit, cache, remove, onCreated, onUpdated, reload }
}
