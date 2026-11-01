// An open <dialog> sits in the browser's top layer, above everything in <body>: a menu
// portalled to <body> would open under it and take no clicks. Inside a dialog the menu
// goes into the dialog itself.
export function usePortalTarget(anchor: Ref<HTMLElement | null>) {
  const target = ref<string | HTMLElement>('body')
  onMounted(() => {
    target.value = anchor.value?.closest('dialog') ?? 'body'
  })
  return target
}
