// The old panel's dictionaries as they are: flat keys, plural forms by Intl.PluralRules.
// The language lives in a cookie (and localStorage, for the old panel) and is read
// before the app mounts, so the first frame is already in the right language.
import { en } from '~/i18n/en.js'
import { ru } from '~/i18n/ru.js'

export type Lang = 'en' | 'ru'
type Dict = Record<string, string | Record<string, string>>

const LANGS: Lang[] = ['en', 'ru']
const DICTS: Record<Lang, Dict> = { en, ru }
const FALLBACK: Lang = 'en'
const STEPS: [number, string][] = [[31536000, 'y'], [2592000, 'mo'], [86400, 'd'], [3600, 'h'], [60, 'min']]

function restore(): Lang {
  if (import.meta.server) return FALLBACK
  const fromCookie = document.cookie.match(/(?:^|; )lang=(en|ru)/)?.[1]
  let saved: string | null = fromCookie ?? null
  if (!saved) {
    try {
      saved = localStorage.getItem('lang')
    } catch {
      saved = null
    }
  }
  return saved === 'ru' ? 'ru' : FALLBACK
}

const lang = ref<Lang>(restore())
const rules = computed(() => new Intl.PluralRules(lang.value))
const locale = computed(() => (lang.value === 'ru' ? 'ru-RU' : 'en-US'))

type Vars = Record<string, string | number>

function interpolate(value: string, vars: Vars): string {
  return value.replace(/\{(\w+)\}/g, (whole, name: string) => {
    if (!(name in vars)) return whole
    const slot = vars[name]
    return typeof slot === 'string' && slot.startsWith('@') ? t(slot.slice(1)) : String(slot)
  })
}

export function t(key: string, vars?: Vars): string {
  const value = DICTS[lang.value][key] ?? DICTS[FALLBACK][key] ?? key
  if (typeof value !== 'string') return key
  return vars ? interpolate(value, vars) : value
}

export function plural(key: string, count: number, vars?: Vars): string {
  const forms = DICTS[lang.value][key] ?? DICTS[FALLBACK][key]
  if (!forms || typeof forms !== 'object') return t(key, vars)
  const form = forms[rules.value.select(count)] ?? forms.other ?? forms.many ?? forms.one ?? key
  return interpolate(form, { n: num(count), ...vars })
}

export function num(value: number | string): string {
  return Number(value).toLocaleString(locale.value)
}

export function bytes(value: number | string): string {
  const units = t('unit.bytes').split(',')
  let size = Number(value)
  let i = 0
  while (size >= 1024 && i < units.length - 1) {
    size /= 1024
    i++
  }
  return (i === 0 ? String(Math.trunc(size)) : size.toFixed(1)) + ' ' + units[i]
}

export function ago(value: string): string {
  const diff = Math.floor((Date.now() - Date.parse(value)) / 1000)
  if (diff < 60) return t('time.now')
  for (const [size, unit] of STEPS) {
    if (diff >= size) return t('time.ago', { value: Math.floor(diff / size) + ' ' + t('unit.' + unit) })
  }
  return t('time.now')
}

export function left(value: string | null): string {
  if (!value) return t('time.forever')
  const diff = Math.floor((Date.parse(value) - Date.now()) / 1000)
  if (diff <= 0) return t('time.expired')
  for (const [size, unit] of STEPS.slice(2)) {
    if (diff >= size) return t('time.left', { value: Math.floor(diff / size) + ' ' + t('unit.' + unit) })
  }
  return t('time.soon')
}

/** A calendar day `YYYY-MM-DD` in the viewer's language. */
export function day(value: string, options: Intl.DateTimeFormatOptions): string {
  const date = new Date(value + 'T00:00:00')
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(locale.value, options)
}

export function setLang(next: Lang): void {
  if (!LANGS.includes(next) || next === lang.value) return
  lang.value = next
  document.cookie = `lang=${next}; path=/; max-age=31536000; samesite=lax`
  try {
    localStorage.setItem('lang', next)
  } catch {
    // private mode: the cookie still carries it
  }
  document.documentElement.lang = next
}

export function useLang() {
  return { lang: readonly(lang), langs: LANGS, t, plural, num, bytes, ago, left, day, setLang }
}
