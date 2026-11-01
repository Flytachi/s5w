<script setup lang="ts">
const { t } = useLang()
const { dark, toggle } = useTheme()
</script>

<template>
  <button type="button" class="icon-btn" :aria-label="t(dark ? 'theme.light' : 'theme.dark')" :aria-pressed="dark" @click="toggle">
    <UiIcon :name="dark ? 'sun' : 'moon'" />
  </button>
</template>
