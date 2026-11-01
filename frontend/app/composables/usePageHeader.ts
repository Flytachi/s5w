// What the top bar shows: set by each page, read by the layout. A title key is
// translated live, so switching the language retitles the page without a reload.
export interface PageHeader {
  titleKey?: string
  title?: string
  subtitleKey?: string
  status?: string
}

export const usePageHeaderState = () => useState<PageHeader>('page-header', () => ({}))

export function usePageHeader(header: () => PageHeader) {
  const state = usePageHeaderState()
  watchEffect(() => {
    state.value = header()
  })
  useHead({
    title: () => {
      const value = state.value
      return (value.titleKey ? t(value.titleKey) : value.title || '') + ' — s5w'
    },
  })
}
