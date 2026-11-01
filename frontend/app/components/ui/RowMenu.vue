<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui'

withDefaults(defineProps<{ icon?: string; label?: string; triggerClass?: string; align?: 'start' | 'end' }>(), {
  icon: 'more-h', align: 'end',
})
const { t } = useLang()
const open = ref(false)
const outside = useOutsideClose()
const anchor = ref<HTMLElement | null>(null)
const portal = usePortalTarget(anchor)
watch(open, (value) => {
  if (value) rememberMenuButton(anchor.value?.querySelector('button') ?? null)
})
</script>

<template>
  <DropdownMenuRoot v-model:open="open" :modal="false">
    <div ref="anchor" class="dropdown" :class="{ 'is-open': open }">
      <DropdownMenuTrigger as-child>
        <slot name="trigger">
          <button type="button" class="icon-btn icon-btn--ghost" :class="triggerClass" :aria-label="label ?? t('common.actions')">
            <UiIcon :name="icon" />
          </button>
        </slot>
      </DropdownMenuTrigger>
    </div>
    <DropdownMenuPortal :to="portal">
      <DropdownMenuContent class="dropdown__menu is-reka" @interact-outside="outside.onInteractOutside" @close-auto-focus="outside.onCloseAutoFocus" :align="align" :side-offset="6" :collision-padding="8">
        <slot />
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
