<script setup lang="ts">
import type { FileItem, FilePage, Folder, FolderCard, ShareLink } from '~/types/panel'
import type { CacheTarget, Visibility } from '~/types/ui'

const route = useRoute()
const id = computed(() => String(route.params.id))
const { t, plural, num, bytes, ago, left } = useLang()
const bucket = useBucketHeader()

// folder: null — every file, '/' — the root, a name — that folder.
const { query, set } = useListQuery({ page: 1, limit: 20, search: null, folder: null, sort: 'created', dir: 'desc' })
const { data, error, refresh } = useAsyncData(
  () => `files-${id.value}`,
  () => api.get<FilePage>(`/panel/buckets/${id.value}/files`, query.value),
  { watch: [id, query] },
)

const current = computed(() => query.value.folder as string | null)
const folders = computed(() => data.value?.folders ?? [])
const rootCount = computed(() => Math.max(0, (data.value?.total ?? 0) - folders.value.reduce((sum, card) => sum + card.files, 0)))
const currentCard = computed(() => folders.value.find((card) => card.folder.name === current.value) ?? null)
const searching = computed(() => !!query.value.search)
const sorts = computed(() => [
  { key: 'created', label: t('sort.by.date') },
  { key: 'name', label: t('sort.by.name') },
  { key: 'type', label: t('sort.by.type') },
  { key: 'size', label: t('sort.by.size') },
])
const folderLink = (folder: string | null) => ({ query: { ...route.query, folder: folder ?? undefined, page: undefined } })

/* ---------- folders ---------- */

const folderOpen = ref(false)
const folderEditing = ref<Folder | null>(null)
const cacheOpen = ref(false)
const cacheTarget = ref<CacheTarget | null>(null)

function createFolder() {
  folderEditing.value = null
  folderOpen.value = true
}

function editFolder(folder: Folder) {
  folderEditing.value = folder
  folderOpen.value = true
}

function onFolderCreated(folder: Folder) {
  toast.ok(tb('folder.created', { name: folder.name }), t(folder.public ? 'folder.createdPublic' : 'folder.createdPrivate'))
  refresh()
}

function onFolderUpdated(folder: Folder) {
  toast.ok(tb('folder.saved', { name: folder.name }))
  // A renamed folder that was open stays open under its new name.
  if (folderEditing.value && current.value === folderEditing.value.name && folder.name !== current.value) set({ folder: folder.name, page: 1 })
  else refresh()
}

function folderCache(folder: Folder) {
  const own = bucket.value?.cache
  cacheTarget.value = {
    bucketId: id.value,
    folder: folder.name,
    maxAge: folder.cache.maxAge,
    visibility: (folder.cache.visibility?.name ?? null) as Visibility | null,
    inherited: own ? { maxAge: own.maxAge, visibility: own.visibility.name as Visibility } : undefined,
  }
  cacheOpen.value = true
}

async function removeFolder(card: FolderCard) {
  const name = card.folder.name
  const ok = await confirmDialog({
    title: t('folder.deleteTitle'),
    html: card.files > 0
      ? tb('folder.deleteText', { name, files: plural('files.count', card.files) }, ['name'])
      : tb('folder.deleteEmpty', { name }),
  })
  if (!ok) return
  if (!(await attempt(() => api.delete(`/buckets/${id.value}/folders/${encodeURIComponent(name)}`)))) return
  toast.ok(tb('folder.deleted', { name }), card.files > 0 ? esc(t('folder.deletedWith', { files: plural('files.count', card.files) })) : undefined)
  if (current.value === name) set({ folder: null, page: 1 })
  else refresh()
}

/* ---------- files ---------- */

const drawerOpen = ref(false)
const placeOpen = ref(false)
const placeMode = ref<'rename' | 'move'>('rename')
const linkOpen = ref(false)
const uploadOpen = ref(false)
const selected = ref<FileItem | null>(null)
const linksVersion = ref(0)

function details(file: FileItem) {
  selected.value = file
  drawerOpen.value = true
}

function place(file: FileItem, mode: 'rename' | 'move') {
  selected.value = file
  placeMode.value = mode
  placeOpen.value = true
}

function issueLink(file: FileItem) {
  selected.value = file
  linkOpen.value = true
}

function onPlaced(file: FileItem) {
  selected.value = file
  toast.ok(tb('file.saved', { name: file.name }), file.folder ? esc(t('file.inFolder', { folder: file.folder })) : t('file.inRoot'))
  refresh()
}

function onIssued(link: ShareLink) {
  // The file's card may be open behind: its list is stale now.
  linksVersion.value++
  showSecret({ title: t('link.issued'), value: link.url, html: esc(t('link.issuedNote')) })
  toast.info(t(link.id === null ? 'link.issuedUnlisted' : 'link.issuedListed'))
}

async function removeFile(file: FileItem) {
  const ok = await confirmDialog({ title: t('file.deleteTitle'), html: tb('file.deleteText', { name: file.name }) })
  if (!ok) return
  if (!(await attempt(() => api.delete(`/buckets/${id.value}/files/${file.id}`)))) return
  if (selected.value?.id === file.id) drawerOpen.value = false
  toast.ok(tb('file.deleted', { name: file.name }))
  refresh()
}

// Several files may land in a row: one reload for the lot.
let reloadTimer: ReturnType<typeof setTimeout> | undefined
function onUploaded() {
  clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => refresh(), 300)
}
onBeforeUnmount(() => clearTimeout(reloadTimer))

// Three narrow columns fit far below the 800px at which wider tables turn into cards:
// this one switches by its own measure.
const scroller = ref<HTMLElement | null>(null)
const cards = ref(false)
let observer: ResizeObserver | null = null
watch(scroller, (el) => {
  observer?.disconnect()
  if (!el) return
  observer = new ResizeObserver(([entry]) => (cards.value = (entry?.contentRect.width ?? 0) < 520))
  observer.observe(el)
})
onBeforeUnmount(() => observer?.disconnect())

const uploadFolder = computed(() => (current.value && current.value !== '/' ? current.value : ''))
</script>

<template>
  <div v-if="error" class="alert alert--danger" role="alert">
    <UiIcon name="alert-triangle" />
    <div class="alert__body"><div class="alert__text">{{ error.message }}</div></div>
  </div>

  <div v-else-if="data" class="fm">
    <aside class="fm__side" :aria-label="t('files.folders')">
      <div class="fm__head">
        <span class="fm__title">{{ t('files.folders') }}</span>
        <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" :aria-label="t('files.newFolder')" :title="t('files.newFolder')" @click="createFolder">
          <UiIcon name="plus" size="sm" />
        </button>
      </div>

      <div class="fm__scroll">
        <NuxtLink class="fm__folder" :class="{ 'is-active': current === null }" :aria-current="current === null ? 'true' : undefined" :to="folderLink(null)" replace>
          <UiIcon name="layers" />
          <span class="fm__folder-name">{{ t('files.all') }}</span>
          <span class="fm__count">{{ num(data.total) }}</span>
        </NuxtLink>
        <NuxtLink class="fm__folder" :class="{ 'is-active': current === '/' }" :aria-current="current === '/' ? 'true' : undefined" :to="folderLink('/')" replace>
          <UiIcon name="file" />
          <span class="fm__folder-name">{{ t('files.root') }}</span>
          <span class="fm__count">{{ num(rootCount) }}</span>
        </NuxtLink>
        <div class="fm__divider" />

        <div v-for="card in folders" :key="card.folder.name" class="fm__folder-wrap" data-row="folder">
          <NuxtLink
            class="fm__folder" :class="{ 'is-active': current === card.folder.name }"
            :aria-current="current === card.folder.name ? 'true' : undefined"
            :to="folderLink(card.folder.name)" replace
            :title="[card.folder.name, ...folderMarks(card.folder).map((m) => m.text)].join(' — ')"
          >
            <UiIcon name="folder" />
            <span class="fm__folder-name">{{ card.folder.name }}</span>
            <span class="fm__badges">
              <UiIcon v-for="mark in folderMarks(card.folder)" :key="mark.icon" :name="mark.icon" size="sm" :class="`text-${mark.tone}`" role="img" :aria-label="mark.text" />
            </span>
            <span class="fm__count">{{ num(card.files) }}</span>
          </NuxtLink>
          <div class="fm__folder-menu">
            <UiRowMenu icon="more-v" trigger-class="icon-btn--sm">
              <UiMenuItem icon="edit" @select="editFolder(card.folder)">{{ t('common.edit') }}</UiMenuItem>
              <UiMenuItem icon="clock" @select="folderCache(card.folder)">{{ t('bucket.cachePolicy') }}</UiMenuItem>
              <UiMenuItem icon="trash" danger @select="removeFolder(card)">{{ t('common.delete') }}</UiMenuItem>
            </UiRowMenu>
          </div>
        </div>

        <p v-if="folders.length === 0" class="text-sm text-muted fm__empty">{{ t('files.noFolders') }}</p>
      </div>

      <button type="button" class="btn btn--ghost btn--sm fm__add" @click="createFolder">
        <UiIcon name="plus" size="sm" /> <span>{{ t('files.newFolder') }}</span>
      </button>
    </aside>

    <section class="fm__main" :aria-label="t('nav.files')">
      <div class="fm__head">
        <div class="fm__where">
          <UiIcon :name="current === null ? 'layers' : current === '/' ? 'file' : 'folder'" />
          <b>{{ current === null ? t('files.all') : current === '/' ? t('files.bucketRoot') : current }}</b>
          <span class="text-sm text-muted">{{ plural('files.count', data.meta.total) }}</span>
          <!-- On a phone the folder strip has no menus: the open folder's actions live here. -->
          <UiRowMenu v-if="currentCard" icon="more-v" trigger-class="icon-btn--sm only-md" :label="t('common.actions')">
            <UiMenuItem icon="edit" @select="editFolder(currentCard.folder)">{{ t('common.edit') }}</UiMenuItem>
            <UiMenuItem icon="clock" @select="folderCache(currentCard.folder)">{{ t('bucket.cachePolicy') }}</UiMenuItem>
            <UiMenuItem icon="trash" danger @select="removeFolder(currentCard)">{{ t('common.delete') }}</UiMenuItem>
          </UiRowMenu>
          <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm only-md" :aria-label="t('files.newFolder')" @click="createFolder">
            <UiIcon name="plus" size="sm" />
          </button>
        </div>
        <UiSearch :value="query.search" :placeholder="t('files.searchPlaceholder')" @search="set({ search: $event, page: 1 })" />
        <UiSortMenu :options="sorts" :sort="query.sort" :dir="query.dir" @change="set($event)" />
        <button type="button" class="btn btn--primary btn--sm" @click="uploadOpen = true">
          <UiIcon name="upload" size="sm" /> <span>{{ t('files.upload') }}</span>
        </button>
      </div>

      <div ref="scroller" class="fm__scroll panel__scroll">
        <table class="table" :class="{ 'table--cards': cards }">
          <tbody>
            <tr v-for="file in data.data" :key="file.id" data-row="file">
              <td data-primary>
                <button type="button" class="fileline fileline--button" @click="details(file)">
                  <span class="ftype" :class="`ftype--${kind(file.content.mime).kind}`"><UiIcon :name="kind(file.content.mime).icon" /></span>
                  <span class="fileline__body">
                    <span class="fileline__name" :title="file.name">{{ file.name }}</span>
                    <span class="fileline__meta">
                      {{ bytes(file.content.size) }}<span class="dot-sep" />{{ file.content.mime }}
                      <template v-if="current === null && file.folder !== null"><span class="dot-sep" />{{ file.folder }}</template>
                    </span>
                  </span>
                </button>
              </td>
              <td :data-label="t('table.channel')" data-half>
                <span class="tone chan" :class="file.public ? 'chan--o' : 'chan--p'">/{{ file.public ? 'o' : 'p' }}</span>
              </td>
              <td :data-label="t(file.expiresAt === null ? 'table.uploaded' : 'table.expiry')" data-half class="text-sm nowrap">
                <span v-if="file.expiresAt === null" class="text-muted" :title="dateTime(file.createdAt)">{{ ago(file.createdAt) }}</span>
                <span v-else class="text-warn" :title="dateTime(file.expiresAt)">{{ left(file.expiresAt) }}</span>
              </td>
              <td data-actions>
                <UiRowMenu>
                  <UiMenuItem icon="eye" @select="details(file)">{{ t('file.details') }}</UiMenuItem>
                  <UiMenuItem icon="copy" @select="copyText(file.publicUrl ?? file.privateUrl)">{{ t('file.copyLink') }}</UiMenuItem>
                  <UiMenuItem icon="link" @select="issueLink(file)">{{ t('file.tempLink') }}</UiMenuItem>
                  <UiMenuItem icon="edit" @select="place(file, 'rename')">{{ t('file.rename') }}</UiMenuItem>
                  <UiMenuItem icon="folder" @select="place(file, 'move')">{{ t('file.move') }}</UiMenuItem>
                  <UiMenuItem icon="trash" danger @select="removeFile(file)">{{ t('common.delete') }}</UiMenuItem>
                </UiRowMenu>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="data.data.length === 0" class="empty">
          <UiIcon name="file" />
          <template v-if="searching">
            <div class="empty__title">{{ t('empty.nothing') }}</div>
            <div class="text-sm">{{ t('files.empty.search', { query: query.search ?? '' }) }}</div>
          </template>
          <template v-else>
            <div class="empty__title">{{ t('files.empty.title') }}</div>
            <div class="text-sm">{{ t('files.empty.hint') }}</div>
          </template>
        </div>
      </div>
      <UiPagination :meta="data.meta" />
    </section>
  </div>

  <FolderFormModal v-model:open="folderOpen" :bucket-id="id" :folder="folderEditing" @created="onFolderCreated" @updated="onFolderUpdated" />
  <CachePolicyModal v-model:open="cacheOpen" :target="cacheTarget" @saved="refresh()" />
  <FileDrawer
    v-model:open="drawerOpen" :bucket-id="id" :file="selected" :links-version="linksVersion"
    @rename="selected && place(selected, 'rename')" @move="selected && place(selected, 'move')"
    @link="selected && issueLink(selected)" @remove="selected && removeFile(selected)"
  />
  <FilePlaceModal v-model:open="placeOpen" :bucket-id="id" :file="selected" :mode="placeMode" :folders="folders" @saved="onPlaced" />
  <FileLinkModal v-model:open="linkOpen" :bucket-id="id" :file="selected" @issued="onIssued" />
  <UploadModal v-model:open="uploadOpen" :bucket-id="id" :folders="folders" :folder="uploadFolder" @uploaded="onUploaded" />
</template>
