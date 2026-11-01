<script setup lang="ts">
// PUBLIC_BASE_URL is optional: without it the addresses the panel hands out are a guess
// from the request. Said once, at the top; hidden until the guessed address changes.
const { t } = useLang()
const info = useInfo()
const KEY = 'notice.baseUrl'
const hidden = ref<string | null>(null)

onMounted(() => {
  try {
    hidden.value = localStorage.getItem(KEY)
  } catch {
    hidden.value = null
  }
})

const shown = computed(() => info.value !== null && !info.value.configured && hidden.value !== info.value.baseUrl)

function hide() {
  const url = info.value?.baseUrl ?? ''
  hidden.value = url
  try {
    localStorage.setItem(KEY, url)
  } catch {
    // private mode: hidden until a reload
  }
}
</script>

<template>
  <div v-if="shown" class="alert alert--warn notice mb-3" role="status">
    <UiIcon name="info" />
    <div class="alert__body">
      <div class="alert__title">{{ t('notice.baseUrl.title') }}</div>
      <div class="alert__text">{{ t('notice.baseUrl.text', { url: info!.baseUrl }) }}</div>
    </div>
    <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" :aria-label="t('notice.hide')" :title="t('notice.hide')" @click="hide">
      <UiIcon name="x" size="sm" />
    </button>
  </div>
</template>
