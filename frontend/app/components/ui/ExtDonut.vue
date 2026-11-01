<script setup lang="ts">
// Space by extension: a ring and a table that light each other up on hover.
import type { ExtUsage } from '~/types/panel'

const props = defineProps<{ usage: ExtUsage[] }>()
const { t, bytes, num } = useLang()
const CIRCLE = 2 * Math.PI * 54
const active = ref<number | null>(null)
const tip = ref<{ index: number; x: number; y: number } | null>(null)

const total = computed(() => props.usage.reduce((sum, row) => sum + row.bytes, 0))
const slices = computed(() => {
  let offset = 0
  return props.usage.map((row, i) => {
    const share = total.value > 0 ? row.bytes / total.value : 0
    const length = CIRCLE * share
    const slice = {
      ...row, i,
      name: row.ext === '' ? t('overview.noExtension') : '.' + row.ext,
      color: `var(--chart-${(i % 8) + 1})`,
      share: Math.round(share * 1000) / 10,
      dash: `${length.toFixed(2)} ${(CIRCLE - length).toFixed(2)}`,
      offset: (-offset).toFixed(2),
    }
    offset += length
    return slice
  })
})

function show(i: number, event: PointerEvent) {
  active.value = i
  tip.value = { index: i, x: Math.min(event.clientX + 14, innerWidth - 220), y: Math.max(event.clientY - 70, 8) }
}
function hide() {
  active.value = null
  tip.value = null
}
</script>

<template>
  <div v-if="usage.length === 0" class="empty-inline">
    <UiIcon name="file" />
    <div>
      <div class="empty-inline__title">{{ t('files.empty.title') }}</div>
      <div class="text-sm text-muted">{{ t('files.empty.hint') }}</div>
    </div>
  </div>
  <div v-else class="ring-row">
    <div class="ring-box">
      <svg class="ring" :class="{ 'is-hover': active !== null }" viewBox="0 0 140 140" role="img" :aria-label="t('overview.donutAria')" @pointerleave="hide">
        <circle class="ring__track" cx="70" cy="70" r="54" />
        <circle
          v-for="slice in slices"
          :key="slice.i"
          class="ring__slice"
          :class="{ 'is-active': active === slice.i }"
          :style="{ stroke: slice.color }"
          cx="70" cy="70" r="54"
          :stroke-dasharray="slice.dash"
          :stroke-dashoffset="slice.offset"
          @pointermove="show(slice.i, $event)"
        />
        <text class="ring__total" x="70" y="67">{{ bytes(total) }}</text>
        <text class="ring__cap" x="70" y="84">{{ t('overview.onDisk') }}</text>
      </svg>
    </div>
    <div class="kinds-scroll">
      <table class="kinds">
        <tr
          v-for="slice in slices"
          :key="slice.i"
          :class="{ 'is-active': active === slice.i }"
          @pointerenter="active = slice.i"
          @pointerleave="active = null"
        >
          <td class="kinds__name">
            <span class="kinds__dot" :style="{ background: slice.color }" />
            <span :class="slice.ext === '' ? 'text-muted' : 'mono'">{{ slice.name }}</span>
            <span class="kinds__count">{{ t('common.pcs', { n: num(slice.total) }) }}</span>
          </td>
          <td class="kinds__value">{{ bytes(slice.bytes) }}</td>
          <td class="kinds__share">{{ Math.round(slice.share) }}%</td>
        </tr>
      </table>
    </div>
  </div>
  <Teleport to="body">
    <div v-if="tip" class="ttip is-open" role="tooltip" :style="{ left: tip.x + 'px', top: tip.y + 'px' }">
      <div class="ttip__title">{{ slices[tip.index]?.name }}</div>
      <div class="ttip__row"><span class="ttip__name">{{ bytes(slices[tip.index]?.bytes ?? 0) }}</span><span class="ttip__value">{{ slices[tip.index]?.share }}%</span></div>
      <div class="ttip__row"><span class="ttip__name">{{ t('common.pcs', { n: num(slices[tip.index]?.total ?? 0) }) }}</span></div>
    </div>
  </Teleport>
</template>
