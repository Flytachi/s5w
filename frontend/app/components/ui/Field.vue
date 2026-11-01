<script setup lang="ts">
defineProps<{ label?: string; for?: string; hint?: string; error?: string | null }>()
</script>

<template>
  <div class="field">
    <label v-if="label" class="field__label" :for="$props.for">{{ label }}</label>
    <slot />
    <span v-if="hint && !error" class="field__hint">{{ hint }}</span>
    <span v-if="error" class="field__error">{{ error }}</span>
  </div>
</template>
