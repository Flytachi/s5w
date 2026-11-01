<script setup lang="ts">
defineProps<{ name: string; size?: 'sm' }>()
</script>

<template>
  <svg class="icon" :class="size && `icon--${size}`" aria-hidden="true"><use :href="`/icons.svg#i-${name}`" /></svg>
</template>
