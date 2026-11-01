<script setup lang="ts">
const { t } = useLang()
const route = useRoute()
const menu = ref(false)

watch(() => route.fullPath, () => (menu.value = false))

function onKey(event: KeyboardEvent) {
  if (event.key === 'Escape') menu.value = false
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <a class="skip-link" href="#content">{{ t('a11y.skip') }}</a>
  <div class="app">
    <ShellSidebar :open="menu" @close="menu = false" />
    <div v-if="menu" class="sidebar-overlay" @click="menu = false" />
    <div class="main">
      <ShellTopbar @menu="menu = true" />
      <main id="content" class="content">
        <ShellBaseUrlNotice />
        <slot />
      </main>
    </div>
  </div>
  <UiConfirmHost />
  <UiSecretHost />
  <UiToasts />
</template>
