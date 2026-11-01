// The button of the menu opened last. A dialog opened from a menu item gets the focus
// while it is still in the closing menu; this is where the focus should go back to.
let lastMenuButton: HTMLElement | null = null

export function rememberMenuButton(el: HTMLElement | null) {
  lastMenuButton = el
}

/** Where focus returns when a dialog closes: the element it came from, or that menu's button. */
export function focusOrigin(): HTMLElement | null {
  const active = document.activeElement as HTMLElement | null
  const fromMenu = !active || active === document.body || !!active.closest('[role="menu"], [role="listbox"]')
  return fromMenu ? lastMenuButton : active
}
