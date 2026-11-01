<script setup lang="ts">
defineProps<{ id: string; title: string; icon?: string; tone?: string }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useLang()
</script>

<template>
  <header class="modal__header">
    <span v-if="icon" class="modal__icon tone" :class="`tone--${tone ?? 'brand'}`"><UiIcon :name="icon" /></span>
    <slot name="title">
      <h2 :id="id" class="modal__title">{{ title }}</h2>
    </slot>
    <button type="button" class="icon-btn icon-btn--ghost modal__close" :aria-label="t('common.close')" @click="emit('close')">
      <UiIcon name="x" />
    </button>
  </header>
</template>
