<script setup lang="ts">
const { t } = useLang()
const { toasts, raised, dismiss } = useToasts()
const box = ref<HTMLElement | null>(null)
const ICON = { ok: 'check-circle', error: 'x-circle', warn: 'alert-triangle', info: 'info' } as const

watch([raised, () => toasts.value.length], async () => {
  await nextTick()
  const el = box.value as (HTMLElement & { showPopover?: () => void; hidePopover?: () => void }) | null
  if (!el?.showPopover) return
  try {
    if (el.matches(':popover-open')) el.hidePopover!()
    if (toasts.value.length) el.showPopover()
  } catch {
    // not in a popover context: it stays a fixed box
  }
})
</script>

<template>
  <div ref="box" class="toasts" popover="manual" aria-live="polite">
    <div
      v-for="item in toasts"
      :key="item.id"
      class="toast"
      :class="[`toast--${item.type}`, { 'is-leaving': item.leaving }]"
      :role="item.type === 'error' ? 'alert' : 'status'"
    >
      <UiIcon :name="ICON[item.type]" />
      <div class="toast__body">
        <div v-html="item.html" />
        <div v-if="item.detail" class="toast__detail" v-html="item.detail" />
      </div>
      <button v-if="item.action" type="button" class="toast__action" @click="dismiss(item.id); item.action.onClick()">
        {{ item.action.label }}
      </button>
      <button type="button" class="toast__close" :aria-label="t('common.close')" @click="dismiss(item.id)">
        <UiIcon name="x" size="sm" />
      </button>
    </div>
  </div>
</template>
