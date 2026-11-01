<script setup lang="ts">
// Our own arrows: the browser's ones cannot be styled.
const model = defineModel<number | null>({ required: true })
const props = defineProps<{ id?: string; min?: number; max?: number; step?: number; placeholder?: string; disabled?: boolean; invalid?: boolean; name?: string }>()
const { t } = useLang()
const input = ref<HTMLInputElement | null>(null)

function onInput(event: Event) {
  const value = (event.target as HTMLInputElement).value
  model.value = value === '' ? null : Number(value)
}

function bump(direction: 1 | -1) {
  if (props.disabled || !input.value) return
  if (direction > 0) input.value.stepUp()
  else input.value.stepDown()
  model.value = input.value.value === '' ? null : Number(input.value.value)
}
</script>

<template>
  <div class="number">
    <input
      :id="id" ref="input" class="input" :class="{ 'is-invalid': invalid }" type="number" inputmode="numeric"
      :name="name" :min="min" :max="max" :step="step" :placeholder="placeholder" :disabled="disabled"
      :value="model ?? ''" @input="onInput"
    >
    <div class="number__spin">
      <button type="button" tabindex="-1" :aria-label="t('number.up')" @click="bump(1)">
        <UiIcon name="chevron-down" style="transform: rotate(180deg)" />
      </button>
      <button type="button" tabindex="-1" :aria-label="t('number.down')" @click="bump(-1)">
        <UiIcon name="chevron-down" />
      </button>
    </div>
  </div>
</template>
