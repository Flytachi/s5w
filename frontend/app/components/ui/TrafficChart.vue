<script setup lang="ts">
// Egress over ingress (or delivery over API requests) by day, on one scale. Two scales
// would make the series look comparable without being so — the worst kind of chart lie.
import type { TrafficDay } from '~/types/panel'

const props = withDefaults(defineProps<{
  series: TrafficDay[]
  metric?: 'bytes' | 'hits'
  height?: number
  emptyKey?: string
  /** The daily peak in the legend; off where the legend shows the totals instead. */
  peakHint?: boolean
  /** Totals of the two series, printed in their legend items. */
  totals?: [number, number]
}>(), { metric: 'bytes', height: 240, emptyKey: 'stats.emptyTraffic', peakHint: true, totals: undefined })

const { t, bytes, num, day } = useLang()
const GRID_LINES = 4

const inBytes = computed(() => props.metric === 'bytes')
const top = (d: TrafficDay) => (inBytes.value ? d.egress : d.deliveries)
const bottom = (d: TrafficDay) => (inBytes.value ? d.ingress : d.api)
const fmt = (v: number) => (inBytes.value ? bytes(v) : num(v))
const labels = computed(() => (inBytes.value ? ['Egress', 'Ingress'] : ['Delivery', 'API']))

const peak = computed(() => props.series.reduce((max, d) => Math.max(max, top(d) + bottom(d)), 0))
const grid = computed(() =>
  peak.value > 0
    ? Array.from({ length: GRID_LINES }, (_, i) => fmt(Math.round((peak.value * (GRID_LINES - i)) / GRID_LINES)))
    : [],
)

// A label under every column of a month runs together; every n-th one reads.
const step = computed(() => Math.max(1, Math.ceil(props.series.length / 12)))
const columns = computed(() =>
  props.series.map((d, i) => {
    const date = new Date(d.day + 'T00:00:00')
    const a = top(d)
    const b = bottom(d)
    return {
      key: d.day,
      title: day(d.day, { day: 'numeric', month: 'long', year: 'numeric' }),
      label: i % step.value !== 0 ? '' : date.getDate() === 1 ? day(d.day, { day: 'numeric', month: 'short' }) : String(date.getDate()),
      topPercent: peak.value > 0 ? (a / peak.value) * 100 : 0,
      bottomPercent: peak.value > 0 ? (b / peak.value) * 100 : 0,
      topValue: fmt(a),
      bottomValue: fmt(b),
      empty: a === 0 && b === 0,
    }
  }),
)

const tip = ref<{ index: number; left: number; top: number } | null>(null)
const tipEl = ref<HTMLElement | null>(null)

async function show(index: number, event: Event) {
  const box = (event.currentTarget as HTMLElement).getBoundingClientRect()
  tip.value = { index, left: box.left + box.width / 2, top: box.top }
  await nextTick()
  const own = tipEl.value?.getBoundingClientRect()
  if (!own || !tip.value) return
  // Kept inside the window: at the edge columns it would otherwise slide off.
  tip.value.left = Math.min(Math.max(8, box.left + box.width / 2 - own.width / 2), window.innerWidth - own.width - 8)
  const above = box.top - own.height - 10
  tip.value.top = above < 8 ? box.bottom + 10 : above
}

const hide = () => (tip.value = null)
onMounted(() => window.addEventListener('scroll', hide, true))
onBeforeUnmount(() => window.removeEventListener('scroll', hide, true))
</script>

<template>
  <div v-if="peak === 0" class="tchart-empty" :style="{ '--tchart-h': height + 'px' }">{{ t(emptyKey) }}</div>
  <div v-else class="tchart-wrap" :style="{ '--tchart-h': height + 'px' }">
    <div class="tchart-axis" aria-hidden="true">
      <span v-for="line in grid" :key="line">{{ line }}</span><span>0</span>
    </div>
    <div class="tchart" role="img" :aria-label="t('chart.aria', { a: labels[0], b: labels[1] })" @pointerleave="hide">
      <div
        v-for="(col, i) in columns"
        :key="col.key"
        class="tchart__col"
        @pointerenter="show(i, $event)"
        @click="show(i, $event)"
      >
        <div class="tchart__stack">
          <div v-if="col.empty" class="tchart__zero" />
          <template v-else>
            <div v-if="col.bottomPercent > 0" class="tchart__bar tchart__bar--in tchart__bar--set" :style="{ height: col.bottomPercent + '%' }" />
            <div v-if="col.topPercent > 0" class="tchart__bar tchart__bar--out tchart__bar--set" :style="{ height: col.topPercent + '%' }" />
          </template>
        </div>
        <span class="tchart__label">{{ col.label }}</span>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div
      v-if="tip"
      ref="tipEl"
      class="ttip is-open"
      role="tooltip"
      :style="{ left: tip.left + 'px', top: tip.top + 'px' }"
    >
      <div class="ttip__title">{{ columns[tip.index]?.title }}</div>
      <div class="ttip__row">
        <span class="ttip__dot" style="background: var(--brand)" />
        <span class="ttip__name">{{ labels[0] }}</span>
        <span class="ttip__value">{{ columns[tip.index]?.topValue }}</span>
      </div>
      <div class="ttip__row">
        <span class="ttip__dot" style="background: var(--chart-4)" />
        <span class="ttip__name">{{ labels[1] }}</span>
        <span class="ttip__value">{{ columns[tip.index]?.bottomValue }}</span>
      </div>
    </div>
  </Teleport>

  <div class="legend mt-2">
    <span class="legend__item">
      <span class="legend__swatch legend__swatch--out" />{{ labels[0] }}
      <span class="legend__hint">· {{ t('chart.hint.' + labels[0]!.toLowerCase()) }}</span>
      <b v-if="totals">{{ fmt(totals[0]) }}</b>
    </span>
    <span class="legend__item">
      <span class="legend__swatch legend__swatch--in" />{{ labels[1] }}
      <span class="legend__hint">· {{ t('chart.hint.' + labels[1]!.toLowerCase()) }}</span>
      <b v-if="totals">{{ fmt(totals[1]) }}</b>
    </span>
    <span v-if="peakHint && peak > 0" class="legend__item legend__hint">{{ t('stats.peak') }} — {{ fmt(peak) }}</span>
    <slot name="legend" />
  </div>
</template>
