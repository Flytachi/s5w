// A form's request: busy flag, server field errors under the fields, the rest as a toast.
export function useForm() {
  const errors = reactive<Record<string, string>>({})
  const busy = ref(false)

  function clear() {
    for (const key of Object.keys(errors)) delete errors[key]
  }

  /** Runs the request; true when it went through. */
  async function submit(request: () => Promise<unknown>, opts: { nameField?: boolean } = {}): Promise<boolean> {
    clear()
    busy.value = true
    try {
      await request()
      return true
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        for (const [field, messages] of Object.entries(err.errors)) errors[field] = messages[0] ?? ''
        toast.warn(t('form.check'))
      } else if (err instanceof ApiError && err.status === 409 && opts.nameField) {
        errors.name = t('form.nameTaken')
      } else {
        toast.error(esc((err as Error).message || t('error.generic')))
      }
      return false
    } finally {
      busy.value = false
    }
  }

  return { errors, busy, submit, clear }
}

/** An action outside a form (a menu item): errors become a toast. */
export async function attempt(action: () => Promise<unknown>): Promise<boolean> {
  try {
    await action()
    return true
  } catch (err) {
    toast.error(esc((err as Error).message || t('error.generic')))
    return false
  }
}
