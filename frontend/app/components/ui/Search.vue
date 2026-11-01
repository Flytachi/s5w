<script setup lang="ts">
// Searches as you type (after a short pause) and at once on Enter.
const props = defineProps<{ value: string | null; placeholder: string }>()
const emit = defineEmits<{ search: [value: string | null] }>()
const { t } = useLang()
const text = ref(props.value ?? '')
let timer: ReturnType<typeof setTimeout> | undefined

watch(() => props.value, (value) => {
  if ((value ?? '') !== text.value.trim()) text.value = value ?? ''
})

function commit() {
  clearTimeout(timer)
  const value = text.value.trim()
  if (value !== (props.value ?? '')) emit('search', value === '' ? null : value)
}

function onInput() {
  clearTimeout(timer)
  timer = setTimeout(commit, 350)
}

function reset() {
  text.value = ''
  commit()
}
</script>

<template>
  <form class="search-pill" role="search" @submit.prevent="commit">
    <UiIcon name="search" size="sm" />
    <input v-model="text" type="search" :placeholder="placeholder" :aria-label="placeholder" enterkeyhint="search" @input="onInput">
  </form>
  <button v-if="value" type="button" class="icon-btn icon-btn--ghost" :aria-label="t('search.reset')" :title="t('search.reset')" @click="reset">
    <UiIcon name="x" />
  </button>
</template>
