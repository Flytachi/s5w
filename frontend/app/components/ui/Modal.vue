<script setup lang="ts">
// A native <dialog>: the top layer gives what used to be fought by hand — the backdrop
// is not clipped by containers, focus stays inside, Escape closes the topmost one.
// Kept here: page scroll lock, focus back to the trigger, toasts above the dialog.
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ variant?: string; labelledby?: string }>()
const emit = defineEmits<{ closed: [] }>()
const dialog = ref<HTMLDialogElement | null>(null)
let trigger: HTMLElement | null = null
let locked = false

// Dialogs stack (a confirm over a form): the page unlocks when the last one closes.
function lock(on: boolean) {
  if (on === locked) return
  locked = on
  const root = document.documentElement
  const count = Number(root.dataset.scrollLock || 0) + (on ? 1 : -1)
  if (count > 0) root.dataset.scrollLock = String(count)
  else delete root.dataset.scrollLock
}

watch(open, async (value) => {
  await nextTick()
  const el = dialog.value
  if (!el) return
  if (value && !el.open) {
    trigger = focusOrigin()
    el.showModal()
    lock(true)
    toast.raise()
    // On a phone the keyboard would cover half the sheet: no autofocus there.
    if (!matchMedia('(pointer: coarse)').matches) {
      const first = el.querySelector<HTMLElement>('[autofocus], input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])')
      setTimeout(() => first?.focus({ preventScroll: true }), 60)
    }
  } else if (!value && el.open) {
    el.close()
  }
}, { immediate: true })

function onClose() {
  lock(false)
  open.value = false
  emit('closed')
  if (trigger?.isConnected) trigger.focus({ preventScroll: true })
  trigger = null
}

onBeforeUnmount(() => {
  if (dialog.value?.open) dialog.value.close()
  lock(false)
})
</script>

<template>
  <dialog
    ref="dialog"
    class="modal"
    :class="props.variant"
    :aria-labelledby="labelledby"
    @close="onClose"
    @click.self="open = false"
  >
    <slot v-if="open" />
  </dialog>
</template>
