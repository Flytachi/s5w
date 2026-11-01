<script setup lang="ts">
import type { PageMeta } from '~/types/panel'

const props = defineProps<{ meta: PageMeta }>()
const { t } = useLang()
const route = useRoute()
const to = (page: number) => ({ query: { ...route.query, page: page === 1 ? undefined : String(page) } })
const numbers = computed(() => pageNumbers(props.meta.current, props.meta.pages))
</script>

<template>
  <nav v-if="meta.pages > 1" class="pagination" :aria-label="t('page.nav')">
    <span class="pagination__info">{{ t('page.info', { current: meta.current, total: meta.pages }) }}</span>
    <span v-if="meta.previous === null" class="page-btn is-disabled" aria-hidden="true"><UiIcon name="chevron-left" size="sm" /></span>
    <NuxtLink v-else class="page-btn" :to="to(meta.previous)" :aria-label="t('page.prev')"><UiIcon name="chevron-left" size="sm" /></NuxtLink>
    <template v-for="(number, i) in numbers" :key="i">
      <span v-if="number === null" class="page-btn is-gap" aria-hidden="true">…</span>
      <span v-else-if="number === meta.current" class="page-btn active" aria-current="page">{{ number }}</span>
      <NuxtLink v-else class="page-btn" :to="to(number)" :aria-label="t('page.number', { n: number })">{{ number }}</NuxtLink>
    </template>
    <span v-if="meta.next === null" class="page-btn is-disabled" aria-hidden="true"><UiIcon name="chevron-right" size="sm" /></span>
    <NuxtLink v-else class="page-btn" :to="to(meta.next)" :aria-label="t('page.next')"><UiIcon name="chevron-right" size="sm" /></NuxtLink>
  </nav>
</template>
