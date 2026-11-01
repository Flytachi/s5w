<script setup lang="ts">
import type { CountedPage, LinkCard, LinkCounts } from '~/types/panel'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { t, plural, num, ago, left } = useLang()
useBucketHeader()

const { query, set } = useListQuery({ page: 1, limit: 15, search: null, sort: 'created', dir: 'desc' })
const { data, error, refresh } = useAsyncData(
  () => `links-${id.value}`,
  () => api.get<CountedPage<LinkCard, LinkCounts>>(`/panel/buckets/${id.value}/links`, query.value),
  { watch: [id, query] },
)
const searching = computed(() => !!query.value.search)
const sorts = computed(() => [
  { key: 'created', label: t('sort.by.created') },
  { key: 'file', label: t('sort.by.file') },
  { key: 'mode', label: t('sort.by.mode') },
  { key: 'state', label: t('sort.by.state') },
])
const DEAD: Record<string, string> = { revoked: 'link.dead.revoked', expired: 'link.dead.expired', exhausted: 'link.dead.limit' }
const downloadTone = (card: LinkCard) => {
  const rest = (card.link.maxDownloads ?? 0) - card.link.downloads
  return rest <= 0 ? 'danger' : rest <= 1 ? 'warn' : 'mute'
}

async function revoke(card: LinkCard) {
  const ok = await confirmDialog({ title: t('link.revokeTitle'), html: esc(t('link.revokeText')), confirmLabel: t('link.revoke') })
  if (!ok || card.link.id === null) return
  if (!(await attempt(() => api.delete(`/buckets/${id.value}/links/${card.link.id}`)))) return
  await refresh()
  toast.ok(t('link.revoked'))
}

async function revokeAll() {
  const ok = await confirmDialog({ title: t('link.revokeAllTitle'), html: esc(t('link.revokeAllText')), confirmLabel: t('links.revokeAll') })
  if (!ok) return
  if (!(await attempt(() => api.delete(`/buckets/${id.value}/links`)))) return
  await refresh()
  toast.ok(t('link.allRevoked'), t('link.allRevokedNote'))
}

async function purge(state: 'revoked' | 'expired') {
  const prefix = state === 'revoked' ? 'link.purgeRevoked' : 'link.purgeExpired'
  const ok = await confirmDialog({ title: t(prefix + 'Title'), html: esc(t(prefix + 'Text')), confirmLabel: t('common.delete') })
  if (!ok) return
  let removed = 0
  if (!(await attempt(async () => (removed = (await api.delete<{ removed: number }>(`/buckets/${id.value}/links/purge/${state}`)).removed)))) return
  toast.show(removed === 0 ? t('link.nothingToPurge') : plural('link.purged', removed), { type: removed === 0 ? 'info' : 'ok' })
  await refresh()
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
        <span class="stat__icon"><UiIcon name="link" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.active === 0 }">{{ num(data.counts.active) }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('links.stat.active') }}</span><span class="stat__note">{{ t('links.stat.activeNote') }}</span></span>
      </div>
      <div class="card stat stat--brand">
        <span class="stat__icon"><UiIcon name="layers" /></span>
        <span class="stat__value">{{ num(data.counts.total) }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('links.stat.issued') }}</span><span class="stat__note">{{ t('links.stat.issuedNote') }}</span></span>
      </div>
      <div class="card stat stat--danger">
        <span class="stat__icon"><UiIcon name="x-circle" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.revoked === 0 }">{{ num(data.counts.revoked) }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('links.stat.revoked') }}</span><span class="stat__note">{{ t('links.stat.revokedNote') }}</span></span>
      </div>
      <div class="card stat stat--warn">
        <span class="stat__icon"><UiIcon name="clock" /></span>
        <span class="stat__value" :class="{ 'is-zero': data.counts.expired === 0 }">{{ num(data.counts.expired) }}</span>
        <span class="stat__body"><span class="stat__label">{{ t('links.stat.expired') }}</span><span class="stat__note">{{ t('time.expired') }}</span></span>
      </div>
    </div>

    <div class="card panel panel--links">
      <div class="card__header">
        <div>
          <h2 class="card__title">{{ t('links.title') }}</h2>
          <div class="card__subtitle">{{ t('links.subtitleBefore') }} <span class="mono">/t</span> {{ t('links.subtitleAfter') }}</div>
        </div>
        <div class="card__spacer" />
        <div class="panel__tools">
          <UiSearch :value="query.search" :placeholder="t('links.searchPlaceholder')" @search="set({ search: $event, page: 1 })" />
          <UiSortMenu menu-class="only-md" :options="sorts" :sort="query.sort" :dir="query.dir" @change="set($event)" />
          <button v-if="data.counts.revoked > 0" type="button" class="btn btn--danger-soft btn--sm" @click="purge('revoked')">
            <UiIcon name="trash" size="sm" /> <span>{{ t('links.purgeRevoked') }}</span> <span class="badge badge--danger">{{ num(data.counts.revoked) }}</span>
          </button>
          <button v-if="data.counts.expired > 0" type="button" class="btn btn--danger-soft btn--sm" @click="purge('expired')">
            <UiIcon name="clock" size="sm" /> <span>{{ t('links.purgeExpired') }}</span> <span class="badge badge--danger">{{ num(data.counts.expired) }}</span>
          </button>
          <button v-if="data.counts.active > 0" type="button" class="btn btn--ghost btn--sm" @click="revokeAll">
            <UiIcon name="x-circle" size="sm" /> <span>{{ t('links.revokeAll') }}</span>
          </button>
        </div>
      </div>

      <div class="panel__scroll">
        <table class="table table--cards">
          <thead>
            <tr>
              <UiSortTh field="file" :label="t('table.file')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh field="mode" :label="t('table.mode')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh :label="t('table.downloads')" :sort="query.sort" :dir="query.dir" />
              <UiSortTh field="state" :label="t('table.state')" :sort="query.sort" :dir="query.dir" @change="set($event)" />
              <UiSortTh field="created" :label="t('table.createdF')" :sort="query.sort" :dir="query.dir" class="col-optional" @change="set($event)" />
              <UiSortTh :label="t('table.note')" :sort="query.sort" :dir="query.dir" />
              <th><span class="visually-hidden">{{ t('common.actions') }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="card in data.data" :key="card.link.id ?? card.link.url" :class="{ 'is-dimmed': card.state !== 'active' }">
              <td data-primary>
                <div class="fileline">
                  <span class="ftype ftype--video"><UiIcon name="link" /></span>
                  <span class="fileline__body">
                    <span class="fileline__name">{{ card.file?.name ?? t('link.fileGone') }}</span>
                    <span class="fileline__meta mono">{{ card.file?.slug ?? '' }}</span>
                  </span>
                </div>
              </td>
              <td :data-label="t('table.mode')" data-half>
                <span class="tone" :class="card.link.disposition.name === 'ATTACHMENT' ? 'tone--brand' : 'tone--mute'">
                  {{ t(card.link.disposition.name === 'ATTACHMENT' ? 'link.mode.download' : 'link.mode.view') }}
                </span>
              </td>
              <td :data-label="t('table.downloads')" data-half>
                <span v-if="card.link.maxDownloads === null" class="text-sm">{{ card.link.downloads }} <span class="text-muted">{{ t('link.noLimit') }}</span></span>
                <span v-else class="tone" :class="`tone--${downloadTone(card)}`">{{ card.link.downloads }} / {{ card.link.maxDownloads }}</span>
              </td>
              <td :data-label="t('table.state')" data-half class="text-sm nowrap">
                <span v-if="card.state !== 'active'" class="tone tone--danger">{{ t(DEAD[card.state] ?? 'link.dead.revoked') }}</span>
                <span v-else :title="dateTime(card.link.expiresAt)">{{ left(card.link.expiresAt) }}</span>
              </td>
              <td :data-label="t('table.createdF')" data-half class="text-sm nowrap col-optional" :title="dateTime(card.createdAt)">
                <span class="text-muted">{{ ago(card.createdAt) }}</span>
              </td>
              <td v-if="card.link.note !== ''" :data-label="t('table.note')" class="text-sm text-muted break">{{ card.link.note }}</td>
              <td v-else class="hide-in-cards" />
              <td data-actions>
                <div v-if="card.state === 'active'" class="row-actions">
                  <button type="button" class="icon-btn icon-btn--ghost" :aria-label="t('link.copy')" :title="t('link.copy')" @click="copyText(card.link.url)">
                    <UiIcon name="copy" />
                  </button>
                  <a class="icon-btn icon-btn--ghost" :href="card.link.url" target="_blank" rel="noopener" :aria-label="t('common.open')" :title="t('link.openNew')">
                    <UiIcon name="arrow-right" />
                  </a>
                  <button type="button" class="icon-btn icon-btn--ghost text-danger" :aria-label="t('link.revoke')" :title="t('link.revoke')" @click="revoke(card)">
                    <UiIcon name="x-circle" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="data.data.length === 0" class="empty">
          <UiIcon name="link" />
          <template v-if="searching">
            <div class="empty__title">{{ t('empty.nothing') }}</div>
            <div class="text-sm">{{ t('links.empty.search', { query: query.search ?? '' }) }}</div>
          </template>
          <template v-else>
            <div class="empty__title">{{ t('links.empty.title') }}</div>
            <div class="text-sm">{{ t('links.empty.hint') }}</div>
          </template>
        </div>
      </div>
      <UiPagination :meta="data.meta" />
    </div>
  </template>
</template>
