<script setup lang="ts">
import type { Dashboard } from '~/types/panel'

const { t, plural, num, bytes } = useLang()
usePageHeader(() => ({ titleKey: 'page.dashboard.title', subtitleKey: 'page.dashboard.subtitle' }))

const { data, error, status } = useAsyncData('dashboard', () => api.get<Dashboard>('/panel/dashboard'))

const WINDOW = 30
const used = computed(() => percentOf(data.value?.buckets.used ?? 0, data.value?.buckets.quota ?? 0))
const free = computed(() => Math.max(0, (data.value?.buckets.quota ?? 0) - (data.value?.buckets.used ?? 0)))
const dedup = computed(() => {
  const blobs = data.value?.blobs
  const would = (blobs?.stored ?? 0) + (blobs?.saved ?? 0)
  return would > 0 ? Math.round(((blobs?.saved ?? 0) / would) * 100) : 0
})
const egressTrend = computed(() => trendOf(data.value?.traffic.series ?? [], (d) => d.egress))
const hitsTrend = computed(() => trendOf(data.value?.traffic.series ?? [], (d) => d.deliveries))
const topEgress = computed(() => Math.max(0, ...(data.value?.top ?? []).map((row) => row.egress)))
const quietShown = computed(() => (data.value?.quiet ?? []).slice(0, 4))
const avg = (egress: number, deliveries: number) => (deliveries > 0 ? Math.floor(egress / deliveries) : 0)
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <div v-else-if="status === 'pending' && !data" class="skeleton-page" aria-busy="true" />

  <template v-else-if="data">
    <div class="dashtop mb-3">
      <div class="card card--brand">
        <div class="card__header">
          <div class="card__title">{{ t('dash.space') }}</div>
          <div class="card__spacer" />
          <span class="tone">{{ t('dash.ofQuota', { percent: Math.round(used) }) }}</span>
        </div>

        <div class="usage">
          <div class="usage__part">
            <span class="usage__label">{{ t('dash.used') }}</span>
            <span class="usage__value">{{ bytes(data.buckets.used) }}</span>
          </div>
          <div class="usage__sep" />
          <div class="usage__part">
            <span class="usage__label">{{ t('dash.free') }}</span>
            <span class="usage__value usage__value--free">{{ bytes(free) }}</span>
          </div>
        </div>

        <UiQuota :used="data.buckets.used" :quota="data.buckets.quota">
          <span>{{ t('dash.allocated') }} {{ bytes(data.buckets.quota) }}</span>
          <span>{{ plural('buckets.count', data.buckets.total) }}</span>
        </UiQuota>

        <div v-if="data.buckets.full > 0" class="mt-2">
          <span class="tone"><UiIcon name="alert-triangle" /> {{ plural('dash.atLimit', data.buckets.full) }}</span>
        </div>

        <div class="text-sm mt-3">
          {{ t('dash.dedupBefore') }} <b>{{ bytes(data.blobs.saved) }}</b> {{ t('dash.dedupAfter', { percent: dedup }) }}
        </div>
      </div>

      <div class="grid grid--2">
        <div class="card">
          <div class="metric">
            <span class="metric__label">{{ t('dash.egressFor') }} {{ plural('stats.days', WINDOW) }}</span>
            <span class="metric__value">{{ bytes(data.traffic.totals.egress) }}</span>
            <UiTrendDelta :trend="egressTrend" />
          </div>
        </div>
        <div class="card">
          <div class="metric">
            <span class="metric__label">{{ t('dash.deliveriesFor') }} {{ plural('stats.days', WINDOW) }}</span>
            <span class="metric__value">{{ num(data.traffic.totals.deliveries) }}</span>
            <UiTrendDelta :trend="hitsTrend" />
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card__header">
        <div>
          <div class="card__title">{{ t('dash.traffic') }}</div>
          <div class="card__subtitle">{{ t('dash.allBuckets') }}, {{ data.traffic.from }} — {{ data.traffic.to }}</div>
        </div>
      </div>
      <UiTrafficChart :series="data.traffic.series">
        <template v-if="data.traffic.totals.egress + data.traffic.totals.ingress > 0" #legend>
          <span class="legend__item legend__hint">
            {{ t('dash.avgPerDelivery') }} — {{ bytes(avg(data.traffic.totals.egress, data.traffic.totals.deliveries)) }}
          </span>
          <span class="legend__item legend__hint">{{ t('dash.apiRequests') }} — {{ num(data.traffic.totals.api) }}</span>
        </template>
      </UiTrafficChart>
    </div>

    <div class="grid grid--2 mb-3">
      <div class="card card--flex">
        <div class="card__header">
          <div>
            <div class="card__title">{{ t('dash.topTitle') }}</div>
            <div class="card__subtitle">
              {{ t('dash.byEgressFor') }} {{ plural('stats.days', WINDOW) }}<template v-if="data.topTotal > data.top.length">,
                {{ t('dash.firstOf', { shown: data.top.length, total: data.topTotal }) }}</template>
            </div>
          </div>
        </div>

        <p v-if="data.top.length === 0" class="text-sm text-muted">{{ t('dash.topEmpty') }}</p>
        <div v-else class="tbrank">
          <NuxtLink v-for="row in data.top" :key="row.bucket_id" class="tbrank__row" :to="`/buckets/${row.bucket_id}/stats`">
            <span class="tbrank__name">{{ row.name }}</span>
            <span class="tbrank__value">{{ bytes(row.egress) }}</span>
            <span class="tbrank__meter"><i :style="{ width: (topEgress > 0 ? (row.egress / topEgress) * 100 : 0) + '%' }" /></span>
            <span class="tbrank__meta">
              {{ plural('dash.deliveries', row.deliveries) }}
              <span class="dot-sep" />{{ bytes(avg(row.egress, row.deliveries)) }} {{ t('dash.perDelivery') }}
              <span class="dot-sep" />{{ t('dash.received') }} {{ bytes(row.ingress) }}
            </span>
          </NuxtLink>
        </div>

        <div v-if="data.quiet.length" class="text-sm text-muted quiet">
          {{ t('dash.quiet') }} {{ quietShown.join(', ') }}
          <template v-if="data.quiet.length > quietShown.length">{{ t('dash.andMore', { n: data.quiet.length - quietShown.length }) }}</template>
        </div>
      </div>

      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">{{ t('dash.fillTitle') }}</div>
            <div class="card__subtitle">{{ t('dash.fullestOf', { shown: data.fill.length, total: data.fillTotal }) }}</div>
          </div>
          <div class="card__spacer" />
          <NuxtLink class="btn btn--ghost btn--sm" to="/buckets">{{ t('dash.allLink') }} <UiIcon name="arrow-right" size="sm" /></NuxtLink>
        </div>

        <p v-if="data.fill.length === 0" class="text-sm text-muted">{{ t('dash.fillEmpty') }}</p>
        <div v-else class="tbrank">
          <NuxtLink
            v-for="card in data.fill"
            :key="card.bucket.id"
            class="tbrank__row"
            :class="quotaState(percentOf(card.bucket.bytes.used, card.bucket.bytes.quota))"
            :to="`/buckets/${card.bucket.id}`"
          >
            <span class="tbrank__name">{{ card.bucket.name }}</span>
            <span class="tbrank__value">{{ Math.round(percentOf(card.bucket.bytes.used, card.bucket.bytes.quota)) }}%</span>
            <span class="tbrank__meter"><i :style="{ width: percentOf(card.bucket.bytes.used, card.bucket.bytes.quota) + '%' }" /></span>
            <span class="tbrank__meta">
              {{ bytes(card.bucket.bytes.used) }} {{ t('quota.of') }} {{ bytes(card.bucket.bytes.quota) }}
              <span class="dot-sep" />{{ plural('files.count', card.files) }}
            </span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <div class="grid grid--3">
      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('nav.tokens') }}</div>
          <div class="card__spacer" />
          <span class="tone tone--brand"><UiIcon name="key" /> {{ num(data.tokens.active) }}</span>
        </div>
        <dl class="kv">
          <dt>{{ t('dash.tok.total') }}</dt><dd>{{ num(data.tokens.total) }}</dd>
          <dt>{{ t('dash.tok.active') }}</dt><dd>{{ num(data.tokens.active) }}</dd>
          <dt>{{ t('dash.tok.full') }}</dt><dd>{{ num(data.tokens.full) }}</dd>
          <dt>{{ t('dash.tok.expired') }}</dt>
          <dd><span v-if="data.tokens.expired > 0" class="tone tone--warn">{{ num(data.tokens.expired) }}</span><template v-else>0</template></dd>
          <dt>{{ t('dash.tok.off') }}</dt><dd>{{ num(data.tokens.inactive) }}</dd>
        </dl>
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('links.title') }}</div>
          <div class="card__spacer" />
          <span class="tone tone--temp"><UiIcon name="link" /> {{ num(data.links.active) }}</span>
        </div>
        <dl class="kv">
          <dt>{{ t('dash.tok.total') }}</dt><dd>{{ num(data.links.total) }}</dd>
          <dt>{{ t('dash.lnk.alive') }}</dt><dd>{{ num(data.links.active) }}</dd>
          <dt>{{ t('dash.tok.expired') }}</dt><dd>{{ num(data.links.expired) }}</dd>
          <dt>{{ t('links.stat.revoked') }}</dt><dd>{{ num(data.links.revoked) }}</dd>
        </dl>
        <div class="text-sm text-muted mt-3">{{ t('dash.lnk.note') }}</div>
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('dash.upl.title') }}</div>
          <div class="card__spacer" />
          <span class="tone" :class="data.uploads.expired > 0 ? 'tone--warn' : 'tone--mute'">
            <UiIcon name="upload" /> {{ num(data.uploads.total) }}
          </span>
        </div>
        <dl class="kv">
          <dt>{{ t('dash.upl.running') }}</dt><dd>{{ num(data.uploads.total - data.uploads.expired) }}</dd>
          <dt>{{ t('dash.upl.expired') }}</dt><dd>{{ num(data.uploads.expired) }}</dd>
          <dt>{{ t('dash.upl.staged') }}</dt><dd>{{ bytes(data.uploads.staged) }}</dd>
        </dl>
        <div class="text-sm text-muted mt-3">{{ t('dash.upl.note') }}</div>
      </div>
    </div>
  </template>
</template>
