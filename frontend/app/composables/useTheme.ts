// The theme is set before the first paint by the inline script in <head>; this only
// switches it and remembers the choice.
const dark = ref(false)

function apply(value: boolean) {
  dark.value = value
  if (value) document.documentElement.dataset.theme = 'dark'
  else delete document.documentElement.dataset.theme
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', value ? '#162033' : '#3b5bdb')
}

export function useTheme() {
  onMounted(() => (dark.value = document.documentElement.dataset.theme === 'dark'))
  return {
    dark: readonly(dark),
    toggle() {
      apply(!dark.value)
      try {
        localStorage.setItem('theme', dark.value ? 'dark' : 'light')
      } catch {
        // private mode: the theme lasts for this page only
      }
    },
  }
}
