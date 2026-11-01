<script setup lang="ts">
const props = defineProps<{ options: { key: string; label: string }[]; sort: string; dir: string; menuClass?: string }>()
const emit = defineEmits<{ change: [patch: { sort?: string; dir?: string; page: number }] }>()
const { t } = useLang()
const desc = computed(() => props.dir !== 'asc')
const current = computed(() => props.options.find((o) => o.key === props.sort)?.label ?? t('sort.order'))
</script>

<template>
  <UiRowMenu :class="menuClass" align="end">
    <template #trigger>
      <button type="button" class="btn btn--ghost btn--sm" :class="menuClass">
        <UiIcon name="sort" size="sm" />
        <span>{{ current }}</span><span class="text-muted" aria-hidden="true">{{ desc ? '↓' : '↑' }}</span>
        <UiIcon name="chevron-down" size="sm" />
      </button>
    </template>
    <div class="dropdown__menu--check">
      <UiMenuItem
        v-for="option in options"
        :key="option.key"
        :selected="option.key === sort"
        :icon="option.key === sort ? 'check' : undefined"
        @select="emit('change', { sort: option.key, dir: option.key === sort ? dir : 'desc', page: 1 })"
      >
        {{ option.label }}
      </UiMenuItem>
      <div class="dropdown__divider" />
      <UiMenuItem icon="chevron-down" :class="{ 'icon-flip': desc }" @select="emit('change', { dir: desc ? 'asc' : 'desc', page: 1 })">
        {{ t(desc ? 'sort.asc' : 'sort.desc') }}
      </UiMenuItem>
    </div>
  </UiRowMenu>
</template>
