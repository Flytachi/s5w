<script setup lang="ts">
import type { Trend } from '~/utils/trend'

defineProps<{ trend: Trend }>()
const { t } = useLang()
</script>

<template>
  <span v-if="!trend.known" class="metric__delta metric__delta--flat">{{ t('trend.unknown') }}</span>
  <span v-else class="metric__delta" :class="trend.up ? 'metric__delta--up' : 'metric__delta--down'">
    <UiIcon name="trending" size="sm" /> {{ t('trend.wow', { delta: trend.delta }) }}
  </span>
</template>
