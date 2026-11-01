<script setup lang="ts">
const props = defineProps<{ used: number; quota: number }>()
const { t, bytes } = useLang()
const percent = computed(() => percentOf(props.used, props.quota))
</script>

<template>
  <div class="quota" :class="quotaState(percent)">
    <div class="quota__bar"><div class="quota__fill" :style="{ width: percent + '%' }" /></div>
    <div class="quota__meta">
      <slot :percent="percent">
        <span><b>{{ bytes(used) }}</b> {{ t('quota.of') }} {{ bytes(quota) }}</span>
        <span>{{ Math.round(percent) }}%</span>
      </slot>
    </div>
  </div>
</template>
