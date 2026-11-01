// A list's page, search and order live in the address, so a reload or a shared link
// shows the same thing. Defaults are left out of the address.
type Value = string | number | null
type Defaults = Record<string, Value>

export function useListQuery<T extends Defaults>(defaults: T) {
  const route = useRoute()
  const router = useRouter()

  const query = computed(() => {
    const out: Record<string, Value> = {}
    for (const [key, fallback] of Object.entries(defaults)) {
      const raw = route.query[key]
      const value = Array.isArray(raw) ? raw[0] : raw
      out[key] = value === undefined || value === null || value === ''
        ? fallback
        : typeof fallback === 'number' ? Number(value) || fallback : value
    }
    return out as { [K in keyof T]: T[K] }
  })

  function set(patch: Partial<Record<keyof T, Value>>) {
    const next: Record<string, string> = {}
    for (const [key, raw] of Object.entries(route.query)) {
      if (!(key in defaults) && typeof raw === 'string') next[key] = raw
    }
    const merged = { ...query.value, ...patch } as Record<string, Value>
    for (const [key, value] of Object.entries(merged)) {
      if (value !== null && value !== '' && value !== defaults[key]) next[key] = String(value)
    }
    return router.replace({ query: next })
  }

  return { query, set }
}
