<script setup lang="ts">
import { DropdownMenuItem } from 'reka-ui'

defineProps<{ icon?: string; to?: string; danger?: boolean; selected?: boolean; disabled?: boolean }>()
const emit = defineEmits<{ select: [] }>()
</script>

<template>
  <DropdownMenuItem v-if="to" as-child class="dropdown__item" :class="{ 'is-selected': selected }">
    <NuxtLink :to="to"><span><slot /></span><UiIcon v-if="icon" :name="icon" /></NuxtLink>
  </DropdownMenuItem>
  <DropdownMenuItem
    v-else
    class="dropdown__item"
    :class="{ 'is-danger': danger, 'is-selected': selected }"
    :disabled="disabled"
    @select="emit('select')"
  >
    <span><slot /></span><UiIcon v-if="icon" :name="icon" />
  </DropdownMenuItem>
</template>
