<script setup lang="ts">
const { t } = useLang()
const secret = useSecretState()
const open = ref(false)
watch(secret, (value) => (open.value = value !== null))
</script>

<template>
  <UiModal v-model:open="open" labelledby="modal-secret-title" @closed="secret = null">
    <div v-if="secret" class="modal__inner">
      <header class="modal__header">
        <span class="modal__icon tone tone--ok"><UiIcon name="check-circle" /></span>
        <h2 id="modal-secret-title" class="modal__title">{{ secret.title }}</h2>
      </header>
      <div class="modal__body">
        <p class="modal__text" v-html="secret.html ?? esc(t('modal.secret.note'))" />
        <div class="secret mt-2">
          <span>{{ secret.value }}</span>
          <button type="button" class="icon-btn icon-btn--sm" :aria-label="t('modal.secret.copy')" @click="copyText(secret.value)">
            <UiIcon name="copy" size="sm" />
          </button>
        </div>
        <div v-if="secret.check" class="field mt-3">
          <span class="field__label">{{ t('modal.secret.check') }}</span>
          <div class="secret">
            <span>{{ secret.check.command }}</span>
            <button type="button" class="icon-btn icon-btn--sm" :aria-label="t('modal.secret.copyCmd')" @click="copyText(secret.check.command)">
              <UiIcon name="copy" size="sm" />
            </button>
          </div>
          <span class="field__hint">{{ secret.check.hint }}</span>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--primary" autofocus @click="open = false">{{ t('modal.secret.title') }}</button>
      </footer>
    </div>
  </UiModal>
</template>
