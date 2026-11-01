<script setup lang="ts">
import type { BucketCard, BucketCounts, CountedPage } from '~/types/panel'

const { t, plural, num, bytes } = useLang()
usePageHeader(() => ({ titleKey: 'page.buckets.title', subtitleKey: 'page.buckets.subtitle' }))

const { query, set } = useListQuery({ page: 1, limit: 15, search: null, sort: 'created', dir: 'desc' })
const { data, refresh, error } = useAsyncData(
  'buckets',
  () => api.get<CountedPage<BucketCard, BucketCounts>>('/panel/buckets', query.value),
  { watch: [query] },
)
const actions = useBucketActions(() => refresh())

const searching = computed(() => !!query.value.search)
const sorts = computed(() => [
  { key: 'created', label: t('sort.by.created') },
  { key: 'name', label: t('sort.by.name') },
  { key: 'used', label: t('sort.by.used') },
])
const tones: Record<string, string> = { ACTIVE: 'ok', CREATED: 'warn', PENDING: 'warn' }
const cacheTones: Record<string, string> = { SHARED: 'ok', PRIVATE: 'brand', NO_STORE: 'warn' }
const percent = (card: BucketCard) => percentOf(card.bucket.bytes.used, card.bucket.bytes.quota)
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <template v-else-if="data">
    <div class="grid grid--4 metrics-row mb-3">
      <div class="card stat stat--ok">
        <span class="stat__icon"><UiIcon name="database" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.active === 0 }">{{ num(data.counts.active) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('buckets.stat.active') }}</span>
          <span class="stat__note">{{ t('common.total') }} {{ num(data.counts.total) }}</span>
        </span>
      </div>
      <div class="card stat stat--brand">
        <span class="stat__icon"><UiIcon name="layers" /></span>
        <span class="stat__value">{{ bytes(data.counts.used) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('buckets.stat.used') }}</span>
          <span class="stat__note">{{ t('quota.of') }} {{ bytes(data.counts.quota) }}</span>
        </span>
      </div>
      <div class="card stat stat--warn">
        <span class="stat__icon"><UiIcon name="alert-triangle" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.full === 0 }">{{ num(data.counts.full) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('buckets.stat.full') }}</span>
          <span class="stat__note">{{ t('buckets.stat.fullNote') }}</span>
        </span>
      </div>
      <div class="card stat stat--mute">
        <span class="stat__icon"><UiIcon name="clock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.pending === 0 }">{{ num(data.counts.pending) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('buckets.stat.pending') }}</span>
          <span class="stat__note">{{ t('buckets.stat.pendingNote') }}</span>
        </span>
      </div>
    </div>

    <div class="card panel panel--buckets">
      <div class="card__header">
        <div>
          <h2 class="card__title">{{ t('page.buckets.title') }}</h2>
          <div class="card__subtitle">
            {{ plural('buckets.count', data.meta.total) }} {{ t(searching ? 'buckets.inSearch' : 'buckets.inStorage') }}
          </div>
        </div>
        <div class="card__spacer" />
        <div class="panel__tools">
          <UiSearch :value="query.search" :placeholder="t('buckets.searchPlaceholder')" @search="set({ search: $event, page: 1 })" />
          <UiSortMenu menu-class="only-md" :options="sorts" :sort="query.sort" :dir="query.dir" @change="set($event)" />
          <button type="button" class="btn btn--primary btn--sm" @click="actions.create()">
            <UiIcon name="plus" size="sm" /> <span>{{ t('buckets.new') }}</span>
          </button>
        </div>
      </div>

      <div class="panel__scroll">
        <table class="table table--cards">
          <thead>
            <tr>
              <UiSortTh field="name" :label="t('table.bucket')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh :label="t('table.status')" :sort="query.sort" :dir="query.dir" />
              <UiSortTh field="used" :label="t('table.quota')" :sort="query.sort" :dir="query.dir" class="col-quota" @change="set($event)" />
              <th class="num">{{ t('table.filesBlobs') }}</th>
              <th>{{ t('table.cache') }}</th>
              <UiSortTh field="created" :label="t('table.created')" :sort="query.sort" :dir="query.dir" class="col-optional" @change="set($event)" />
              <th><span class="visually-hidden">{{ t('common.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="card in data.data" :key="card.bucket.id" :class="{ 'is-dimmed': card.bucket.status.name === 'PENDING' }">
              <td data-primary>
                <NuxtLink class="fileline" :to="`/buckets/${card.bucket.id}`">
                  <span class="ftype ftype--image"><UiIcon name="database" /></span>
                  <span class="fileline__body">
                    <span class="fileline__name">{{ card.bucket.name }}</span>
                    <span class="fileline__meta">{{ card.bucket.description }}</span>
                  </span>
                </NuxtLink>
              </td>
              <td :data-label="t('table.status')" data-half>
                <span class="tone" :class="`tone--${tones[card.bucket.status.name] ?? 'mute'}`">
                  <span class="status-dot status-dot--current" />
                  <span>{{ t('status.' + card.bucket.status.name) }}</span>
                </span>
              </td>
              <td :data-label="t('table.quota')" class="col-quota">
                <UiQuota :used="card.bucket.bytes.used" :quota="card.bucket.bytes.quota" />
              </td>
              <td :data-label="t('table.filesBlobs')" data-half class="num nowrap">
                {{ num(card.files) }} <span class="text-muted">/ {{ num(card.blobs) }}</span>
              </td>
              <td :data-label="t('table.cache')">
                <span class="cache-cell">
                  <span class="tone" :class="`tone--${cacheTones[card.bucket.cache.visibility.name] ?? 'mute'}`">
                    {{ t('cache.visibility.' + card.bucket.cache.visibility.name) }}
                  </span>
                  <span class="text-sm text-muted">
                    <template v-if="card.bucket.cache.maxAge === null">{{ t('cache.default') }}</template>
                    <template v-else>{{ card.bucket.cache.maxAge }}s</template>
                  </span>
                </span>
              </td>
              <td :data-label="t('table.created')" class="text-muted text-sm nowrap col-optional">{{ dateTime(card.bucket.createdAt) }}</td>
              <td data-actions>
                <UiRowMenu>
                  <UiMenuItem icon="arrow-right" :to="`/buckets/${card.bucket.id}`">{{ t('common.open') }}</UiMenuItem>
                  <UiMenuItem icon="edit" @select="actions.edit(card.bucket)">{{ t('common.edit') }}</UiMenuItem>
                  <UiMenuItem icon="clock" @select="actions.cache(card.bucket)">{{ t('bucket.cachePolicy') }}</UiMenuItem>
                  <UiMenuItem icon="trash" danger @select="actions.remove(card.bucket)">{{ t('common.delete') }}</UiMenuItem>
                </UiRowMenu>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="data.data.length === 0" class="empty">
        <UiIcon name="database" />
        <template v-if="searching">
          <div class="empty__title">{{ t('empty.nothing') }}</div>
          <div class="text-sm">{{ t('buckets.empty.search', { query: query.search ?? '' }) }}</div>
        </template>
        <template v-else>
          <div class="empty__title">{{ t('buckets.empty.title') }}</div>
          <div class="text-sm">{{ t('buckets.empty.hint') }}</div>
        </template>
      </div>

      <UiPagination :meta="data.meta" />
    </div>
  </template>

  <BucketFormModal v-model:open="actions.formOpen.value" :bucket="actions.editing.value" @created="actions.onCreated" @updated="actions.onUpdated" />
  <CachePolicyModal v-model:open="actions.cacheOpen.value" :target="actions.cacheTarget.value" @saved="actions.reload()" />
</template>
