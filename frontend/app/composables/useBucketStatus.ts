// A bucket is prepared and removed in the background: ask until the server says it is
// done. A 404 is an answer too — that is how a deletion ends.
export async function watchBucket(id: string, done: (status: string) => boolean, attempts = 12): Promise<void> {
  for (let i = 0; i < attempts; i++) {
    await new Promise((resolve) => setTimeout(resolve, 400 + i * 120))
    try {
      const bucket = await api.get<{ status: { name: string } }>(`/buckets/${id}`)
      if (done(bucket.status.name)) return
    } catch (err) {
      if (err instanceof ApiError && err.status === 404) {
        done('GONE')
        return
      }
    }
  }
}
