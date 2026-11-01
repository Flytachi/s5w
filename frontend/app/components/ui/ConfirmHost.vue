<script setup lang="ts">
const { t } = useLang()
const current = useConfirmState()
const open = ref(false)
let answer = false

watch(current, (value) => {
  answer = false
  open.value = value !== null
})

function decide(ok: boolean) {
  answer = ok
  open.value = false
}

function onClosed() {
  current.value?.resolve(answer)
  current.value = null
}
</script>

<template>
  <UiModal v-model:open="open" variant="modal--sm" labelledby="confirm-title" @closed="onClosed">
    <div v-if="current" class="modal__inner">
      <header class="modal__header">
        <span class="modal__icon tone" :class="`tone--${current.tone ?? 'danger'}`"><UiIcon name="alert-triangle" /></span>
        <h2 id="confirm-title" class="modal__title">{{ current.title }}</h2>
      </header>
      <div class="modal__body"><p class="modal__text" v-html="current.html" /></div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="decide(false)">{{ t('common.cancel') }}</button>
        <button
          type="button"
          class="btn"
          :class="(current.tone ?? 'danger') === 'danger' ? 'btn--danger' : 'btn--primary'"
          autofocus
          @click="decide(true)"
        >
          {{ current.confirmLabel ?? t('common.delete') }}
        </button>
      </footer>
    </div>
  </UiModal>
</template>
