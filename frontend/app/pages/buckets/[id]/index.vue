<script setup lang="ts">
import type { Overview } from '~/types/panel'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { t, plural, num, bytes, day } = useLang()
const { data, error, refresh } = useAsyncData(() => `overview-${id.value}`, () => api.get<Overview>(`/panel/buckets/${id.value}`), { watch: [id] })
useBucketHeader()
const actions = useBucketActions(() => refresh())

const card = computed(() => data.value?.card)
const bucket = computed(() => card.value?.bucket)
const percent = computed(() => (bucket.value ? percentOf(bucket.value.bytes.used, bucket.value.bytes.quota) : 0))
const tones: Record<string, string> = { ACTIVE: 'ok', CREATED: 'warn', PENDING: 'warn' }
const cacheTones: Record<string, string> = { SHARED: 'ok', PRIVATE: 'brand', NO_STORE: 'warn' }
const month = computed(() => (data.value ? day(data.value.traffic.from, { month: 'long' }) : ''))
const dedup = computed(() => Math.max(0, (card.value?.files ?? 0) - (card.value?.blobs ?? 0)))
const where = computed(() => {
  const o = data.value
  if (!o) return []
  return [
    { key: 'overview.where.root', color: 1, count: o.placement.root, dirs: null },
    { key: 'overview.where.folders', color: 3, count: o.placement.in_folders, dirs: o.folderCounts.total - o.folderCounts.temp },
    { key: 'overview.where.temp', color: 4, count: o.placement.in_temp, dirs: o.folderCounts.temp },
  ]
})
const share = (count: number) => ((card.value?.files ?? 0) > 0 ? (count / card.value!.files) * 100 : 0)
const base = computed(() => `/buckets/${id.value}`)
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <template v-else-if="data && bucket && card">
    <div class="grid grid--3 mb-3">
      <div class="card card--brand">
        <div class="card__header">
          <div class="card__title">{{ t('dash.space') }}</div>
          <div class="card__spacer" />
          <span class="tone">{{ Math.round(percent) }}%</span>
        </div>
        <div class="usage">
          <div class="usage__part">
            <span class="usage__label">{{ t('dash.used') }}</span>
            <span class="usage__value">{{ bytes(bucket.bytes.used) }}</span>
          </div>
          <div class="usage__sep" />
          <div class="usage__part">
            <span class="usage__label">{{ t('dash.free') }}</span>
            <span class="usage__value usage__value--free">{{ bytes(bucket.bytes.free) }}</span>
          </div>
        </div>
        <UiQuota class="mt-2" :used="bucket.bytes.used" :quota="bucket.bytes.quota">
          <span>{{ t('table.quota') }} {{ bytes(bucket.bytes.quota) }}</span>
          <span>{{ plural('files.count', card.files) }}</span>
        </UiQuota>
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('table.bucket') }}</div>
          <div class="card__spacer" />
          <button type="button" class="btn btn--ghost btn--sm" @click="actions.edit(bucket)">
            <UiIcon name="edit" size="sm" /> <span>{{ t('common.edit') }}</span>
          </button>
        </div>
        <dl class="kv">
          <dt>{{ t('table.status') }}</dt>
          <dd><span class="tone" :class="`tone--${tones[bucket.status.name] ?? 'mute'}`">{{ t('status.' + bucket.status.name) }}</span></dd>
          <dt>{{ t('table.created') }}</dt><dd>{{ dateTime(bucket.createdAt) }}</dd>
          <dt>{{ t('overview.description') }}</dt>
          <dd><span v-if="bucket.description === ''" class="text-muted">{{ t('overview.noDescription') }}</span><template v-else>{{ bucket.description }}</template></dd>
        </dl>
        <div class="text-sm text-muted mt-3">{{ t('overview.idIn') }} <span class="mono">/o</span> {{ t('overview.idAddresses') }}</div>
        <div class="row mt-1">
          <button type="button" class="copyable copyable--wide mono" :aria-label="t('overview.copyId')" @click="copyText(bucket.id)">
            {{ bucket.id }} <UiIcon name="copy" />
          </button>
        </div>
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('overview.defaultCache') }}</div>
          <div class="card__spacer" />
          <button type="button" class="btn btn--ghost btn--sm" @click="actions.cache(bucket)">
            <UiIcon name="edit" size="sm" /> <span>{{ t('common.edit') }}</span>
          </button>
        </div>
        <dl class="kv">
          <dt>{{ t('overview.visibility') }}</dt>
          <dd><span class="tone" :class="`tone--${cacheTones[bucket.cache.visibility.name] ?? 'mute'}`">{{ t('cache.visibility.' + bucket.cache.visibility.name) }}</span></dd>
          <dt>max-age</dt>
          <dd>
            <template v-if="bucket.cache.maxAge === null">{{ t('overview.maxAgeDefault', { n: data.cacheDefaultMaxAge }) }}</template>
            <template v-else>{{ bucket.cache.maxAge }} s</template>
          </dd>
        </dl>
        <div class="text-sm text-muted mt-3">
          {{ t('overview.cacheNoteA') }} <span class="mono">/o</span>{{ t('overview.cacheNoteB') }} <span class="mono">/p</span>
          {{ t('overview.cacheNoteC') }} <span class="mono">/t</span> {{ t('overview.cacheNoteD') }} <span class="mono">private</span>.
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card__header">
        <div class="card__title">{{ t('overview.spendFor') }} {{ month }}</div>
        <div class="card__spacer" />
        <NuxtLink class="btn btn--ghost btn--sm" :to="`${base}/stats`">{{ t('overview.forPeriod') }} <UiIcon name="arrow-right" size="sm" /></NuxtLink>
      </div>
      <UiTrafficChart
        :series="data.traffic.series" :height="220" empty-key="overview.emptyMonth" :peak-hint="false"
        :totals="[data.traffic.totals.egress, data.traffic.totals.ingress]"
      >
        <template #legend>
          <span class="legend__item legend__hint">{{ plural('overview.deliveryRequests', data.traffic.totals.deliveries) }}</span>
        </template>
      </UiTrafficChart>
    </div>

    <div class="grid grid--overview mb-3">
      <div class="card">
        <div class="card__header">
          <div>
            <div class="card__title">{{ t('overview.whatTakesSpace') }}</div>
            <div class="card__subtitle">{{ t('overview.byExtension') }}</div>
          </div>
        </div>
        <UiExtDonut :usage="data.usage" />
      </div>

      <div class="card">
        <div class="card__header">
          <div class="card__title">{{ t('nav.files') }}</div>
          <div class="card__spacer" />
          <NuxtLink class="btn btn--ghost btn--sm" :to="`${base}/files`">{{ t('common.open') }}</NuxtLink>
        </div>
        <div class="pair">
          <div class="pair__side">
            <span class="pair__value">{{ num(card.files) }}</span>
            <span class="pair__label">{{ t('overview.filesLabel') }}</span>
          </div>
          <span class="pair__vs">{{ t('overview.versus') }}</span>
          <div class="pair__side">
            <span class="pair__value">{{ num(card.blobs) }}</span>
            <span class="pair__label">{{ t('overview.blobsLabel') }}</span>
          </div>
        </div>
        <div class="bar-mini mt-2">
          <div class="bar-mini__fill" :style="{ width: (card.files > 0 ? Math.round((card.blobs / card.files) * 100) : 0) + '%' }" />
        </div>
        <div class="text-sm text-muted mt-1">{{ t('overview.dedupBefore') }} <b>{{ num(dedup) }}</b> {{ t('overview.dedupAfter') }}</div>
        <div class="text-sm text-muted mt-3">{{ t('overview.whereThey') }}</div>
        <div class="share-bar mt-1">
          <span v-for="part in where" :key="part.key" class="share-bar__part" :style="{ width: share(part.count) + '%', background: `var(--chart-${part.color})` }" />
        </div>
        <div v-for="part in where" :key="part.key" class="kv-row">
          <span>
            <span class="kinds__dot" :style="{ background: `var(--chart-${part.color})` }" />
            <span>{{ t(part.key) }}</span>
            <span v-if="part.dirs !== null" class="kinds__count">{{ t('common.pcs', { n: num(part.dirs) }) }}</span>
          </span>
          <b>{{ num(part.count) }}</b>
        </div>
      </div>
    </div>

    <div class="grid grid--4 metrics-row">
      <NuxtLink class="card stat stat--ok access-tile" :to="`${base}/tokens`">
        <span class="stat__icon"><UiIcon name="key" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.tokens.active === 0 }">{{ data.tokens.active }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('nav.tokens') }}</span><span class="stat__note">{{ t('token.state.active') }}</span></span>
      </NuxtLink>
      <NuxtLink class="card stat stat--danger access-tile" :to="`${base}/tokens`">
        <span class="stat__icon"><UiIcon name="clock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.tokens.expired === 0 }">{{ data.tokens.expired }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('nav.tokens') }}</span><span class="stat__note">{{ t('tokens.stat.expired') }}</span></span>
      </NuxtLink>
      <NuxtLink class="card stat stat--brand access-tile" :to="`${base}/links`">
        <span class="stat__icon"><UiIcon name="link" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.links.active === 0 }">{{ data.links.active }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('nav.links') }}</span><span class="stat__note">{{ t('links.stat.active') }}</span></span>
      </NuxtLink>
      <NuxtLink class="card stat stat--warn access-tile" :to="`${base}/links`">
        <span class="stat__icon"><UiIcon name="clock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.links.expired === 0 }">{{ data.links.expired }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('nav.links') }}</span><span class="stat__note">{{ t('links.stat.expired') }}</span></span>
      </NuxtLink>
    </div>
  </template>

  <BucketFormModal v-model:open="actions.formOpen.value" :bucket="actions.editing.value" @created="actions.onCreated" @updated="actions.onUpdated" />
  <CachePolicyModal v-model:open="actions.cacheOpen.value" :target="actions.cacheTarget.value" @saved="actions.reload()" />
</template>
