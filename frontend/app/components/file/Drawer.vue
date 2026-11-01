<script setup lang="ts">
// A file's card: what it is, where it is served, and its temporary links.
import type { FileItem, ShareLink } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string; file: FileItem | null; linksVersion: number }>()
const emit = defineEmits<{ rename: []; move: []; link: []; remove: [] }>()
const { t, plural, bytes, left } = useLang()

const links = ref<ShareLink[] | null>(null)
const failed = ref<string | null>(null)
const badge = computed(() => kind(props.file?.content.mime))
const urls = computed(() => {
  const file = props.file
  if (!file) return []
  return [
    ...(file.publicUrl ? [{ url: file.publicUrl, channel: 'o', label: t('file.urlPublic') }] : []),
    { url: file.privateUrl, channel: 'p', label: t('file.urlToken') },
  ]
})
const ticking = (link: ShareLink) => (toDate(link.expiresAt)?.getTime() ?? 0) > Date.now()

async function load() {
  const slug = props.file?.id
  if (!open.value || !slug) return
  failed.value = null
  try {
    const list = await api.get<ShareLink[]>(`/buckets/${props.bucketId}/files/${slug}/links`)
    // While it loaded, the card may have been closed or opened on another file.
    if (props.file?.id === slug) links.value = list
  } catch (err) {
    failed.value = (err as Error).message || t('file.listFailed')
  }
}

watch(() => [open.value, props.file?.id] as const, ([isOpen], before) => {
  if (!isOpen) return
  if (!before || before[1] !== props.file?.id || !before[0]) links.value = null
  load()
}, { immediate: true })
watch(() => props.linksVersion, load)

function state(link: ShareLink): { tone: string; text: string; live: boolean } {
  if (link.revoked) return { tone: 'mute', text: t('link.dead.revoked'), live: false }
  if (!ticking(link)) return { tone: 'mute', text: t('link.dead.expired'), live: false }
  if (link.maxDownloads !== null && link.downloads >= link.maxDownloads) return { tone: 'mute', text: t('link.dead.limit'), live: true }
  return { tone: 'ok', text: left(link.expiresAt), live: true }
}

function facts(link: ShareLink): string {
  return [
    t(link.disposition.name === 'ATTACHMENT' ? 'link.byDownload' : 'link.byOpening'),
    link.maxDownloads === null ? plural('link.downloads', link.downloads) : t('link.downloadedOf', { done: link.downloads, max: link.maxDownloads }),
  ].join(' · ')
}

async function revoke(link: ShareLink) {
  const ok = await confirmDialog({ title: t('link.revokeTitle'), html: esc(t('link.revokeText')), confirmLabel: t('link.revoke') })
  if (!ok || link.id === null) return
  if (!(await attempt(() => api.delete(`/buckets/${props.bucketId}/links/${link.id}`)))) return
  toast.ok(t('link.revoked'))
  load()
}
</script>

<template>
  <UiModal v-model:open="open" variant="modal--drawer" labelledby="drawer-file-title">
    <div v-if="file" class="modal__inner">
      <header class="modal__header">
        <span class="ftype" :class="`ftype--${badge.kind}`"><UiIcon :name="badge.icon" /></span>
        <div class="grow">
          <h2 id="drawer-file-title" class="modal__title break">{{ file.name }}</h2>
          <div class="card__subtitle mono">{{ file.id }}</div>
        </div>
        <button type="button" class="icon-btn icon-btn--ghost modal__close" :aria-label="t('common.close')" @click="open = false">
          <UiIcon name="x" />
        </button>
      </header>

      <div class="modal__body">
        <div class="row mt-1">
          <template v-if="file.public">
            <span class="tone chan chan--o">/o</span><span class="text-sm text-muted">{{ t('file.openToAll') }}</span>
          </template>
          <template v-else>
            <span class="tone chan chan--p">/p</span><span class="text-sm text-muted">{{ t('file.tokenOnly') }}</span>
          </template>
        </div>

        <dl class="kv mt-3">
          <dt>{{ t('modal.file.size') }}</dt><dd>{{ bytes(file.content.size) }}</dd>
          <dt>{{ t('modal.file.type') }}</dt><dd class="mono">{{ file.content.mime }}</dd>
          <dt>{{ t('modal.file.folder') }}</dt><dd>{{ file.folder ?? t('files.bucketRoot') }}</dd>
          <dt>{{ t('table.uploaded') }}</dt><dd>{{ dateTime(file.createdAt) }}</dd>
          <dt>{{ t('modal.file.retention') }}</dt>
          <dd>{{ file.expiresAt === null ? t('time.forever') : `${left(file.expiresAt)} (${dateTime(file.expiresAt)})` }}</dd>
        </dl>

        <div class="field__label mt-3">{{ t('modal.file.content') }}</div>
        <div class="secret mt-1">
          <span>{{ file.content.hash }}</span>
          <button type="button" class="icon-btn icon-btn--sm" :aria-label="t('modal.file.copyHash')" @click="copyText(file.content.hash)">
            <UiIcon name="copy" size="sm" />
          </button>
        </div>

        <div class="field__label mt-3">{{ t('modal.file.permanent') }}</div>
        <div class="stack mt-1">
          <div v-for="row in urls" :key="row.channel" class="url-row">
            <span class="tone chan" :class="`chan--${row.channel}`">/{{ row.channel }}</span>
            <span class="url-row__value mono" :title="row.label">{{ row.url }}</span>
            <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" :aria-label="t('modal.secret.copy')" @click="copyText(row.url)">
              <UiIcon name="copy" size="sm" />
            </button>
            <a class="icon-btn icon-btn--ghost icon-btn--sm" :href="row.url" target="_blank" rel="noopener" :aria-label="t('common.open')">
              <UiIcon name="arrow-right" size="sm" />
            </a>
          </div>
        </div>

        <div class="field__label mt-3">{{ t('links.title') }}</div>
        <p class="text-sm text-muted">{{ t('modal.file.linksNote') }}</p>
        <div class="stack mt-1" data-file-links>
          <span v-if="failed" class="text-sm text-warn">{{ failed }}</span>
          <span v-else-if="links === null" class="text-sm text-muted">{{ t('file.loading') }}</span>
          <span v-else-if="links.length === 0" class="text-sm text-muted">{{ t('file.noLinks') }}</span>
          <div v-for="link in links ?? []" v-else :key="link.id ?? link.url" class="link-row" :class="{ 'is-off': !state(link).live }">
            <span class="tone chan chan--t">/t</span>
            <span class="link-row__body">
              <span class="link-row__head">
                <span class="tone" :class="`tone--${state(link).tone}`">{{ state(link).text }}</span>
                <span v-if="link.note" class="text-sm">{{ link.note }}</span>
              </span>
              <span class="text-sm text-muted">{{ facts(link) }}</span>
            </span>
            <span v-if="!link.revoked && ticking(link)" class="link-row__actions">
              <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" :aria-label="t('modal.secret.copy')" :title="t('link.copy')" @click="copyText(link.url)">
                <UiIcon name="copy" size="sm" />
              </button>
              <a class="icon-btn icon-btn--ghost icon-btn--sm" :href="link.url" target="_blank" rel="noopener" :aria-label="t('common.open')" :title="t('link.openNew')">
                <UiIcon name="arrow-right" size="sm" />
              </a>
              <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" data-revoke :aria-label="t('link.revoke')" :title="t('link.revokeOne')" @click="revoke(link)">
                <UiIcon name="x-circle" size="sm" />
              </button>
            </span>
          </div>
        </div>
      </div>

      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost btn--sm" @click="emit('rename')"><UiIcon name="edit" size="sm" /> <span>{{ t('modal.file.name') }}</span></button>
        <button type="button" class="btn btn--ghost btn--sm" @click="emit('move')"><UiIcon name="folder" size="sm" /> <span>{{ t('modal.file.folder') }}</span></button>
        <button type="button" class="btn btn--ghost btn--sm" @click="emit('link')"><UiIcon name="link" size="sm" /> <span>{{ t('modal.file.link') }}</span></button>
        <button type="button" class="btn btn--danger btn--sm" @click="emit('remove')"><UiIcon name="trash" size="sm" /> <span>{{ t('common.delete') }}</span></button>
      </footer>
    </div>
  </UiModal>
</template>
