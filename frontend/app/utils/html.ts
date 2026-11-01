// Messages that put a name in bold. The dictionary text is ours; every value that goes
// into it is escaped first, so v-html on the result cannot carry markup from the data.
export function esc(value: unknown): string {
  return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)
}

/** t() with escaped values; the ones named in `bold` come out in <b>. */
export function tb(key: string, vars: Record<string, string | number>, bold: string[] = Object.keys(vars)): string {
  const safe: Record<string, string> = {}
  for (const [name, value] of Object.entries(vars)) {
    safe[name] = bold.includes(name) ? `<b>${esc(value)}</b>` : esc(value)
  }
  return t(key, safe)
}
