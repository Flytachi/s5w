// A menu closed by a click elsewhere must not pull the focus back to its button: that
// click may have opened the next menu, and the returning focus would close it at once —
// two clicks to get from one menu to another.
export function useOutsideClose() {
  let outside = false
  return {
    onInteractOutside() {
      outside = true
    },
    onCloseAutoFocus(event: Event) {
      if (outside) event.preventDefault()
      outside = false
    },
  }
}
