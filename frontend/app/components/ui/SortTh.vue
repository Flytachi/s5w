<script setup lang="ts">
const props = defineProps<{ field?: string; label: string; sort: string; dir: string }>()
const emit = defineEmits<{ change: [patch: { sort: string; dir: string; page: number }] }>()
const active = computed(() => !!props.field && props.field === props.sort)
const desc = computed(() => props.dir !== 'asc')
</script>

<template>
  <th v-if="!field"><span>{{ label }}</span></th>
  <th v-else :aria-sort="active ? (desc ? 'descending' : 'ascending') : 'none'">
    <a
      href="#" class="th-sort" :class="{ 'is-active': active }"
      @click.prevent="emit('change', { sort: field!, dir: active && desc ? 'asc' : 'desc', page: 1 })"
    >
      <span>{{ label }}</span><span v-if="active" class="th-sort__arrow" aria-hidden="true">{{ desc ? '↓' : '↑' }}</span>
    </a>
  </th>
</template>
