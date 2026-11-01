/** A backend timestamp (`2026-10-31 18:56:28.59+00`) as a Date, or null. */
export function toDate(value: string | null | undefined): Date | null {
  if (!value) return null
  const date = new Date(value.replace(' ', 'T').replace(/([+-]\d\d)$/, '$1:00'))
  return Number.isNaN(date.getTime()) ? null : date
}

/** `d.m.Y H:i` in the viewer's own timezone. */
export function dateTime(value: string | null | undefined): string {
  const date = toDate(value)
  if (!date) return value ?? ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
