<script setup lang="ts">
const emit = defineEmits<{ menu: [] }>()
const { t, lang, setLang } = useLang()
const header = usePageHeaderState()
const timezone = viewerTimezone()

const tones: Record<string, string> = { ACTIVE: 'ok', CREATED: 'warn', PENDING: 'warn' }
</script>

<template>
  <header class="topbar">
    <button type="button" class="icon-btn burger" :aria-label="t('a11y.menu')" aria-controls="sidebar" @click="emit('menu')">
      <UiIcon name="menu" />
    </button>

    <div class="topbar__text">
      <h1 class="topbar__title">
        {{ header.titleKey ? t(header.titleKey) : header.title }}
        <span v-if="header.status" class="tone" :class="`tone--${tones[header.status] ?? 'mute'}`">
          {{ t('status.' + header.status) }}
        </span>
      </h1>
      <div v-if="header.subtitleKey" class="topbar__subtitle">{{ t(header.subtitleKey) }}</div>
    </div>

    <div class="topbar__tools">
      <span class="tzchip" :title="t('tz.hint')">
        <UiIcon name="clock" size="sm" /> {{ timezone }}
      </span>
      <button type="button" class="icon-btn lang-btn" :aria-label="t('lang.switch')" :title="t('lang.switch')" @click="setLang(lang === 'en' ? 'ru' : 'en')">
        <span>{{ lang.toUpperCase() }}</span>
      </button>
      <UiThemeToggle />
    </div>
  </header>
</template>
