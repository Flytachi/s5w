<script setup lang="ts">
// The old panel's custom select (the browser's own list cannot be themed), on Reka.
// Reka keeps "" for "nothing selected", so an empty option travels as a sentinel.
import { SelectContent, SelectItem, SelectItemText, SelectPortal, SelectRoot, SelectTrigger, SelectValue, SelectViewport } from 'reka-ui'
import type { Option } from '~/types/ui'

const model = defineModel<string | number | null>({ required: true })
const props = defineProps<{ options: Option[]; id?: string; ariaLabel?: string; disabled?: boolean; invalid?: boolean }>()
const EMPTY = '\u0000empty'
const open = ref(false)
const anchor = ref<HTMLElement | null>(null)
const portal = usePortalTarget(anchor)

const inner = computed({
  get: () => (model.value === null || model.value === '' ? EMPTY : String(model.value)),
  set: (value: string) => {
    const option = props.options.find((o) => (o.value === '' ? EMPTY : String(o.value)) === value)
    model.value = option ? option.value : null
  },
})
const keyOf = (o: Option) => (o.value === '' ? EMPTY : String(o.value))
const label = computed(() => props.options.find((o) => keyOf(o) === inner.value)?.label ?? '')
</script>

<template>
  <SelectRoot v-model="inner" v-model:open="open" :disabled="disabled">
    <div ref="anchor" class="cselect cselect--field" :class="{ 'is-open': open, 'is-invalid': invalid }">
      <SelectTrigger :id="id" class="cselect__btn" :aria-label="ariaLabel">
        <span class="cselect__value"><SelectValue>{{ label }}</SelectValue></span>
        <UiIcon name="chevron-down" size="sm" class="cselect__chev" />
      </SelectTrigger>
    </div>
    <SelectPortal :to="portal">
      <SelectContent class="cselect__menu is-reka-select" position="popper" :side-offset="6" :collision-padding="8">
        <SelectViewport>
          <SelectItem v-for="option in options" :key="keyOf(option)" :value="keyOf(option)" class="cselect__option">
            <SelectItemText>{{ option.label }}</SelectItemText>
            <UiIcon name="check" class="icon--check" />
          </SelectItem>
        </SelectViewport>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
