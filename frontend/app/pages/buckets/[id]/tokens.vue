<script setup lang="ts">
import type { AccessToken, AccessTokenSecret, CountedPage, TokenCounts } from '~/types/panel'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { t, num, ago, left } = useLang()
useBucketHeader()
const info = useInfo()

const { query, set } = useListQuery({ page: 1, limit: 15, search: null, sort: 'created', dir: 'desc' })
const { data, error, refresh } = useAsyncData(
  () => `tokens-${id.value}`,
  () => api.get<CountedPage<AccessToken, TokenCounts>>(`/panel/buckets/${id.value}/tokens`, query.value),
  { watch: [id, query] },
)
const issueOpen = ref(false)
const searching = computed(() => !!query.value.search)
const sorts = computed(() => [
  { key: 'created', label: t('sort.by.issued') },
  { key: 'name', label: t('sort.by.name') },
  { key: 'access', label: t('sort.by.access') },
  { key: 'state', label: t('sort.by.state') },
  { key: 'used', label: t('sort.by.used_at') },
])
const isFull = (token: AccessToken) => token.access.name === 'FULL'
const isLive = (token: AccessToken) => token.status.name === 'ACTIVE' && !token.expired

function check(secret: string, full: boolean) {
  const base = info.value?.baseUrl ?? location.origin
  return { command: `curl -H "Authorization: Bearer ${secret}" ${base}/v1/check`, hint: t(full ? 'token.checkFull' : 'token.checkBasic') }
}

function onIssued(issued: AccessTokenSecret) {
  refresh()
  showSecret({ title: t('token.issued'), value: issued.token, check: check(issued.token, isFull(issued.accessToken)) })
}

async function rotate(token: AccessToken) {
  const ok = await confirmDialog({
    title: t('token.rotateTitle'),
    html: tb('token.rotateText', { name: token.name }, []),
    confirmLabel: t('token.rotateConfirm'),
    tone: 'brand',
  })
  if (!ok) return
  let rotated: AccessTokenSecret | null = null
  if (!(await attempt(async () => (rotated = await api.post<AccessTokenSecret>(`/buckets/${id.value}/tokens/${token.id}/rotate`))))) return
  refresh()
  showSecret({
    title: t('token.newValue'),
    value: rotated!.token,
    html: esc(t('token.newValueNote')),
    check: check(rotated!.token, isFull(rotated!.accessToken)),
  })
}

async function toggle(token: AccessToken) {
  const next = token.status.name === 'ACTIVE' ? 0 : 1
  if (!(await attempt(() => api.patch(`/buckets/${id.value}/tokens/${token.id}/status`, { status: next })))) return
  await refresh()
  toast.show(t(next === 1 ? 'token.enabled' : 'token.disabled'), { type: next === 1 ? 'ok' : 'warn' })
}

async function remove(token: AccessToken) {
  const ok = await confirmDialog({ title: t('token.deleteTitle'), html: esc(t('token.deleteText')) })
  if (!ok) return
  if (!(await attempt(() => api.delete(`/buckets/${id.value}/tokens/${token.id}`)))) return
  await refresh()
  toast.ok(tb('token.deleted', { name: token.name }))
}
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <template v-else-if="data">
    <div class="grid grid--4 metrics-row mb-3">
      <div class="card stat stat--ok">
        <span class="stat__icon"><UiIcon name="key" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.active === 0 }">{{ num(data.counts.active) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('tokens.stat.active') }}</span>
          <span class="stat__note">{{ t('common.total') }} {{ num(data.counts.total) }}</span>
        </span>
      </div>
      <div class="card stat stat--warn">
        <span class="stat__icon"><UiIcon name="zap" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.full === 0 }">{{ num(data.counts.full) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('tokens.stat.full') }}</span>
          <span class="stat__note">{{ t('tokens.stat.fullNote') }}</span>
        </span>
      </div>
      <div class="card stat stat--mute">
        <span class="stat__icon"><UiIcon name="lock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.inactive === 0 }">{{ num(data.counts.inactive) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('tokens.stat.off') }}</span>
          <span class="stat__note">{{ t('tokens.stat.offNote') }}</span>
        </span>
      </div>
      <div class="card stat stat--danger">
        <span class="stat__icon"><UiIcon name="clock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.expired === 0 }">{{ num(data.counts.expired) }}</span>
        <span class="stat__body">
          <span class="stat__label">{{ t('tokens.stat.expired') }}</span>
          <span class="stat__note">{{ t('time.expired') }}</span>
        </span>
      </div>
    </div>

    <div class="card panel panel--tokens">
      <div class="card__header">
        <div><h2 class="card__title">{{ t('tokens.title') }}</h2></div>
        <div class="card__spacer" />
        <div class="panel__tools">
          <UiSearch :value="query.search" :placeholder="t('tokens.searchPlaceholder')" @search="set({ search: $event, page: 1 })" />
          <UiSortMenu menu-class="only-md" :options="sorts" :sort="query.sort" :dir="query.dir" @change="set($event)" />
          <button type="button" class="btn btn--primary btn--sm" @click="issueOpen = true">
            <UiIcon name="plus" size="sm" /> <span>{{ t('tokens.issue') }}</span>
          </button>
        </div>
      </div>

      <div class="panel__scroll">
        <table class="table table--cards">
          <thead>
            <tr>
              <UiSortTh field="name" :label="t('table.token')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh field="access" :label="t('table.access')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh field="state" :label="t('table.state')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh :label="t('table.expiry')" :sort="query.sort" :dir="query.dir" />
              <UiSortTh field="used" :label="t('table.lastUsed')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh field="created" :label="t('table.issued')" :sort="query.sort" :dir="query.dir" class="col-optional" @change="set($event)" />
              <th><span class="visually-hidden">{{ t('common.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="token in data.data" :key="token.id" :class="{ 'is-dimmed': !isLive(token) }">
              <td data-primary>
                <div class="fileline">
                  <span class="ftype" :class="isFull(token) ? 'ftype--doc' : 'ftype--arch'"><UiIcon name="key" /></span>
                  <span class="fileline__body">
                    <span class="fileline__name" :title="token.name">{{ token.name }}</span>
                    <span class="fileline__meta mono">{{ token.tail === '' ? '—' : 's5w_…' + token.tail }}</span>
                  </span>
                </div>
              </td>
              <td :data-label="t('table.access')" data-half>
                <span class="tone" :class="isFull(token) ? 'tone--warn' : 'tone--mute'">{{ t('access.' + token.access.name) }}</span>
              </td>
              <td :data-label="t('table.state')" data-half>
                <span v-if="token.status.name !== 'ACTIVE'" class="tone tone--mute">{{ t('token.state.off') }}</span>
                <span v-else-if="token.expired" class="tone tone--danger">{{ t('token.state.expired') }}</span>
                <span v-else class="tone tone--ok"><span class="status-dot status-dot--current" /> {{ t('token.state.active') }}</span>
              </td>
              <td :data-label="t('table.expiry')" data-half class="text-sm nowrap">
                <span v-if="token.expiresAt === null" class="text-muted">{{ t('time.forever') }}</span>
                <span v-else :title="dateTime(token.expiresAt)">{{ left(token.expiresAt) }}</span>
              </td>
              <td :data-label="t('table.lastUsed')" data-half class="text-sm text-muted nowrap">
                <span v-if="token.lastUsedAt === null">{{ t('token.neverUsed') }}</span>
                <span v-else :title="dateTime(token.lastUsedAt)">{{ ago(token.lastUsedAt) }}</span>
              </td>
              <td :data-label="t('table.issued')" class="text-sm text-muted nowrap col-optional" :title="dateTime(token.createdAt)">{{ ago(token.createdAt) }}</td>
              <td data-actions>
                <UiRowMenu>
                  <UiMenuItem icon="refresh" @select="rotate(token)">{{ t('token.rotate') }}</UiMenuItem>
                  <UiMenuItem icon="lock" @select="toggle(token)">{{ t(token.status.name === 'ACTIVE' ? 'token.disable' : 'token.enable') }}</UiMenuItem>
                  <UiMenuItem icon="trash" danger @select="remove(token)">{{ t('common.delete') }}</UiMenuItem>
                </UiRowMenu>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="data.data.length === 0" class="empty">
          <UiIcon name="key" />
          <template v-if="searching">
            <div class="empty__title">{{ t('empty.nothing') }}</div>
            <div class="text-sm">{{ t('tokens.empty.search', { query: query.search ?? '' }) }}</div>
          </template>
          <template v-else>
            <div class="empty__title">{{ t('tokens.empty.title') }}</div>
            <div class="text-sm">{{ t('tokens.empty.hint') }}</div>
          </template>
        </div>
      </div>
      <UiPagination :meta="data.meta" />
    </div>
  </template>

  <TokenIssueModal v-model:open="issueOpen" :bucket-id="id" @issued="onIssued" />
</template>
