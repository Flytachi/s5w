<script setup lang="ts">
import type { Traffic } from '~/types/panel'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { t, plural, num, bytes } = useLang()
useBucketHeader()

const { query, set } = useListQuery({ from: null, to: null })
const { data, error } = useAsyncData(
  () => `stats-${id.value}`,
  () => api.get<Traffic>(`/panel/buckets/${id.value}/stats`, { from: query.value.from, to: query.value.to }),
  { watch: [id, query] },
)

// The range the server settled on (it swaps and caps a wrong one) goes back into the fields.
const from = ref('')
const to = ref('')
watch(data, (value) => {
  if (!value) return
  from.value = value.from
  to.value = value.to
}, { immediate: true })

// Presets are days back from today in the viewer's own calendar, as the server counts them.
const isoDay = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const presets = computed(() => [7, 30, 90].map((span) => {
  const end = new Date()
  const start = new Date()
  start.setDate(end.getDate() - (span - 1))
  return { span, from: isoDay(start), to: isoDay(end) }
}))
const isActive = (preset: { from: string; to: string }) => data.value?.from === preset.from && data.value?.to === preset.to

const average = computed(() => {
  const totals = data.value?.totals
  return totals && totals.deliveries > 0 ? Math.floor(totals.egress / totals.deliveries) : 0
})
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <template v-else-if="data">
    <form class="drange mb-3" @submit.prevent="set({ from: from || null, to: to || null })">
      <span class="drange__label from-md">{{ t('stats.date') }}</span>
      <input v-model="from" class="input drange__date" type="date" :aria-label="t('stats.from')">
      <span class="drange__dash from-md">—</span>
      <input v-model="to" class="input drange__date" type="date" :aria-label="t('stats.to')">
      <button class="btn btn--primary" type="submit">{{ t('stats.show') }}</button>
      <span class="drange__spacer" />
      <div class="drange__presets">
        <NuxtLink
          v-for="preset in presets"
          :key="preset.span"
          class="btn btn--sm"
          :class="isActive(preset) ? 'btn--primary' : 'btn--ghost'"
          :aria-current="isActive(preset) ? 'true' : undefined"
          :to="{ query: { from: preset.from, to: preset.to } }"
          replace
        >
          {{ t('stats.preset', { n: preset.span }) }}
        </NuxtLink>
      </div>
    </form>

    <div class="grid grid--4 metrics-row mb-3">
      <div class="card">
        <div class="text-sm text-muted">Egress <span class="legend__hint">· {{ t('stats.egressHint') }}</span></div>
        <div class="stat-tile">{{ bytes(data.totals.egress) }}</div>
      </div>
      <div class="card">
        <div class="text-sm text-muted">Ingress <span class="legend__hint">· {{ t('stats.ingressHint') }}</span></div>
        <div class="stat-tile">{{ bytes(data.totals.ingress) }}</div>
      </div>
      <div class="card">
        <div class="text-sm text-muted">Delivery requests</div>
        <div class="stat-tile">{{ num(data.totals.deliveries) }}</div>
      </div>
      <div class="card">
        <div class="text-sm text-muted">API requests</div>
        <div class="stat-tile">{{ num(data.totals.api) }}</div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card__header">
        <div class="card__title">{{ t('stats.trafficTitle') }}</div>
        <div class="card__spacer" />
        <span class="text-sm text-muted">{{ data.from }} — {{ data.to }}, {{ plural('stats.days', data.series.length) }}</span>
      </div>
      <UiTrafficChart :series="data.series">
        <template v-if="data.totals.egress + data.totals.ingress > 0" #legend>
          <span class="legend__item legend__hint">{{ t('stats.avgPerRequest') }} — {{ bytes(average) }}</span>
        </template>
      </UiTrafficChart>
    </div>

    <div class="card">
      <div class="card__header">
        <div class="card__title">{{ t('stats.hitsTitle') }}</div>
        <div class="card__spacer" />
        <span class="text-sm text-muted row" :title="t('stats.hitsHintTitle')">
          <UiIcon name="info" size="sm" /> <span>{{ t('stats.hitsHint') }}</span>
        </span>
      </div>
      <UiTrafficChart :series="data.series" metric="hits" :height="170" empty-key="stats.emptyHits" :peak-hint="false" />
    </div>
  </template>
</template>
