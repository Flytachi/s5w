// The one way to the server. Requests go to /api, which the Swoole process on this port
// proxies to the backend's /admin; the session is a cookie, so nothing to attach here.

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> | null = null,
  ) {
    super(message)
  }

  /** The first error of a form field, if the server gave one. */
  field(name: string): string | null {
    return this.errors?.[name]?.[0] ?? null
  }
}

export const viewerTimezone = (): string => Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'

type Query = Record<string, string | number | null | undefined>

async function request<T>(method: string, path: string, body?: unknown, query?: Query): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', Timezone: viewerTimezone() }
  const init: RequestInit = { method, headers }
  if (body instanceof FormData) {
    init.body = body
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    init.body = JSON.stringify(body)
  }

  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== null && value !== undefined && value !== '') params.set(key, String(value))
  }
  const url = '/api' + path + (params.size ? '?' + params : '')

  const res = await fetch(url, init)
  const text = await res.text()
  let json: any = null
  try {
    json = text ? JSON.parse(text) : null
  } catch {
    json = { message: text.slice(0, 200) }
  }

  if (res.status === 401 && location.pathname !== '/login') {
    location.assign('/login?next=' + encodeURIComponent(location.pathname + location.search))
    await new Promise(() => {})
  }
  if (!res.ok) {
    throw new ApiError(res.status, json?.message || t('error.request'), json?.errors ?? null)
  }
  return json as T
}

export const api = {
  get: <T>(path: string, query?: Query) => request<T>('GET', path, undefined, query),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
