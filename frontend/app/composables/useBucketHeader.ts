// A bucket page's top bar: the bucket's name and status, from the switcher's list.
export function useBucketHeader() {
  const route = useRoute()
  const { buckets } = useBuckets()
  const bucket = computed(() => buckets.value.find((b) => b.id === route.params.id) ?? null)
  usePageHeader(() => ({ title: bucket.value?.name ?? '', status: bucket.value?.status.name }))
  return bucket
}
