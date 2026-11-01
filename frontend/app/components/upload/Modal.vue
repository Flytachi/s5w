<script setup lang="ts">
// File upload. Small files go in one request, heavy ones in 8 MiB chunks. The threshold
// is not cosmetic: in one request the server holds the whole body in memory, in chunks
// only a chunk, however big the file; and a break halfway costs one chunk, not all of it.
import type { FileItem, FolderCard, UploadSession } from '~/types/panel'
import type { ImageSettings } from '~/types/ui'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string; folders: FolderCard[]; folder: string }>()
const emit = defineEmits<{ uploaded: [file: FileItem] }>()
const { t, plural, bytes } = useLang()

const CHUNK_THRESHOLD = 16 * 1024 * 1024
const CHUNK_SIZE = 8 * 1024 * 1024
const CHUNK_RETRIES = 3
/** Formats the service can re-encode (ImageProcessor::SUPPORTED). */
const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif']

interface Item {
  key: number
  file: File
  status: 'waiting' | 'working' | 'done' | 'failed'
  percent: number
  note: string
  badge: string
  upload: string | null
  xhr: XMLHttpRequest | null
  image: ImageSettings | null
}

class UploadError extends Error {
  constructor(public status: number, message: string) {
    super(message)
  }
}

const queue = ref<Item[]>([])
const target = ref('')
const dragging = ref(false)
const running = ref(false)
const input = ref<HTMLInputElement | null>(null)
let seq = 0

watch(open, (value) => {
  if (value) target.value = props.folder
})

const folderOptions = computed(() => [
  { value: '', label: t('modal.upload.rootOption') },
  ...props.folders.map(({ folder }) => {
    const vars: Record<string, string> = { name: folder.name, vis: folder.public ? '@folder.public' : '@folder.private' }
    if (folder.retention.id > 0) vars.ret = '@retention.' + folder.retention.name
    return { value: folder.name, label: t(folder.retention.id > 0 ? 'modal.upload.folderOptRet' : 'modal.upload.folderOpt', vars) }
  }),
])

const waiting = computed(() => queue.value.filter((item) => item.status !== 'done'))
const images = computed(() => waiting.value.filter((item) => item.image).length)
const total = computed(() => (waiting.value.length
  ? `${plural('files.count', waiting.value.length)} · ${bytes(waiting.value.reduce((sum, item) => sum + item.file.size, 0))}`
  : ''))

function enqueue(files: FileList | null | undefined) {
  for (const file of Array.from(files ?? [])) {
    const image = IMAGE_MIMES.includes(file.type)
    queue.value.unshift({
      key: ++seq, file, status: 'waiting', percent: 0, note: t('upload.queued'), badge: t('upload.waiting'), upload: null, xhr: null,
      image: image ? { resize: false, maxWidth: null, maxHeight: null, compress: false, quality: 82, convert: false, format: 'WEBP' } : null,
    })
  }
}

function onPick(event: Event) {
  const el = event.target as HTMLInputElement
  enqueue(el.files)
  el.value = ''
}

function onDrop(event: DragEvent) {
  dragging.value = false
  enqueue(event.dataTransfer?.files)
}

/** Removed halfway: the request stops and the server drops what it already has. */
function drop(item: Item) {
  queue.value = queue.value.filter((q) => q !== item)
  item.xhr?.abort()
  if (item.upload) api.delete(`/buckets/${props.bucketId}/uploads/${item.upload}`).catch(() => {})
}

/** "Apply to all" — ten pictures need not be set up ten times. */
function share(from: Item) {
  for (const item of waiting.value) {
    if (item !== from && item.image && from.image) item.image = { ...from.image }
  }
  toast.show(t('upload.optionsShared'), { type: 'ok', timeout: 2000 })
}

function options(item: Item) {
  const o = item.image
  return {
    format: o?.convert ? o.format : 'ORIGINAL',
    quality: o?.compress ? o.quality : null,
    maxWidth: o?.resize ? o.maxWidth || null : null,
    maxHeight: o?.resize ? o.maxHeight || null : null,
  }
}

function xhrSend<T>(method: string, url: string, body: XMLHttpRequestBodyInit, item: Item, headers: Record<string, string> = {}, onProgress?: (loaded: number, total: number) => void): Promise<T> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open(method, '/api' + url)
    xhr.setRequestHeader('Accept', 'application/json')
    for (const [name, value] of Object.entries(headers)) xhr.setRequestHeader(name, value)
    // Only XHR reports upload progress — fetch has none.
    if (onProgress) xhr.upload.addEventListener('progress', (ev) => ev.lengthComputable && onProgress(ev.loaded, ev.total))
    xhr.addEventListener('load', () => {
      let data: any = null
      try {
        data = xhr.responseText ? JSON.parse(xhr.responseText) : null
      } catch {
        data = null
      }
      if (xhr.status === 401) location.assign('/login?next=' + encodeURIComponent(location.pathname + location.search))
      if (xhr.status >= 200 && xhr.status < 300) resolve(data as T)
      else reject(new UploadError(xhr.status, data?.message || t('upload.rejected')))
    })
    xhr.addEventListener('error', () => reject(new UploadError(0, t('upload.connectionLost'))))
    xhr.addEventListener('abort', () => reject(new UploadError(-1, t('upload.cancelled'))))
    item.xhr = xhr
    xhr.send(body)
  })
}

/** Three tries per chunk: a network break alone is no reason to lose a gigabyte. */
async function withRetries<T>(attempt: () => Promise<T>, resync: () => Promise<boolean>): Promise<T> {
  for (let tries = 1; ; tries++) {
    try {
      return await attempt()
    } catch (err) {
      const status = (err as UploadError).status
      if (tries >= CHUNK_RETRIES || (status !== 0 && status !== 409)) throw err
      if (!(await resync())) throw err
      await new Promise((r) => setTimeout(r, 400 * tries))
    }
  }
}

function working(item: Item, note: string) {
  item.status = 'working'
  item.badge = '0%'
  item.note = note
}

function progress(item: Item, loaded: number, size: number) {
  item.percent = size > 0 ? Math.min(100, Math.round((loaded / size) * 100)) : 100
  item.badge = item.percent + '%'
}

function done(item: Item, file: FileItem) {
  item.status = 'done'
  item.percent = 100
  item.badge = t('upload.done')
  item.upload = null
  item.note = file.processed?.applied ? file.processed.operations.join(', ') : file.deduplicated ? t('upload.dedup') : t('upload.noProcessing')
  emit('uploaded', file)
  toast.ok(
    tb('upload.uploaded', { name: file.name }),
    file.processed?.applied ? `${bytes(file.processed.source.size)} → ${bytes(file.processed.result.size)}` : undefined,
  )
  setTimeout(() => (queue.value = queue.value.filter((q) => q !== item)), 2500)
}

function failed(item: Item, err: UploadError, hint: string | null) {
  item.status = 'failed'
  item.percent = 100
  item.badge = err.status > 0 ? String(err.status) : t('upload.network')
  item.note = hint ?? err.message
  toast.show(tb('upload.failed', { name: item.file.name }), { type: 'error', detail: esc(err.message) })
}

/** One request: multipart through XHR for the progress. */
async function send(item: Item, folder: string) {
  const form = new FormData()
  form.append('file', item.file)
  if (folder) form.append('folder', folder)
  for (const [key, value] of Object.entries(options(item))) if (value !== null) form.append(key, String(value))
  working(item, t('upload.step.sending'))
  try {
    const file = await xhrSend<FileItem>('POST', `/buckets/${props.bucketId}/files`, form, item, {}, (loaded, size) => progress(item, loaded, size))
    done(item, file)
  } catch (err) {
    if ((err as UploadError).status === -1) return
    failed(item, err as UploadError, (err as UploadError).status === 0 ? t('upload.retryHint') : null)
  }
}

/** In chunks: a session → PATCH by offset → complete. Progress moves inside a chunk. */
async function sendChunked(item: Item, folder: string) {
  const base = `/buckets/${props.bucketId}/uploads`
  const size = item.file.size
  working(item, t('upload.step.prepare'))
  try {
    let session: UploadSession
    if (item.upload) {
      // Resuming a broken one: the server remembers how many bytes arrived.
      session = await api.get<UploadSession>(`${base}/${item.upload}`)
      working(item, t('upload.step.resume'))
    } else {
      session = await api.post<UploadSession>(base, { name: item.file.name, size, folder: folder || null, ...options(item) })
      // The content was found by its hash — nothing to upload.
      if (session.file) return done(item, session.file)
      item.upload = session.id
      working(item, t('upload.step.sending'))
    }
    const step = session.chunkSize || CHUNK_SIZE
    let offset = session.offset
    progress(item, offset, size)
    while (offset < size) {
      const sent = offset
      const chunk = item.file.slice(sent, Math.min(sent + step, size))
      const res = await withRetries(
        () => xhrSend<UploadSession>('PATCH', `${base}/${item.upload}`, chunk, item, { 'Upload-Offset': String(sent), 'Content-Type': 'application/octet-stream' }, (loaded) => progress(item, sent + loaded, size)),
        async () => {
          // Before a retry, ask the server how much it really took.
          const fresh = await api.get<UploadSession>(`${base}/${item.upload}`)
          offset = fresh.offset
          return fresh.offset === sent
        },
      )
      offset = res.offset
      progress(item, offset, size)
    }
    item.note = t('upload.step.assemble')
    done(item, await api.post<FileItem>(`${base}/${item.upload}/complete`))
  } catch (err) {
    const status = (err as UploadError | ApiError).status
    if (status === -1) return
    failed(item, err instanceof UploadError ? err : new UploadError(status ?? 0, (err as Error).message), item.upload ? t('upload.resumeHint') : null)
  }
}

async function start() {
  if (running.value) return
  running.value = true
  const folder = target.value
  // One by one, not in a batch: the bucket has one quota, and one at a time shows on
  // which file it ran out.
  try {
    for (const item of queue.value.filter((q) => q.status === 'waiting' || q.status === 'failed').reverse()) {
      if (!queue.value.includes(item)) continue
      await (item.file.size > CHUNK_THRESHOLD || item.upload ? sendChunked(item, folder) : send(item, folder))
    }
  } finally {
    running.value = false
  }
}

const tone = (item: Item) => ({ waiting: 'tone--mute', working: 'tone--brand', done: 'tone--ok', failed: 'tone--danger' })[item.status]
</script>

<template>
  <UiModal v-model:open="open" variant="modal--upload modal--full" labelledby="modal-upload-title">
    <div class="modal__inner">
      <UiModalHead id="modal-upload-title" :title="t('modal.upload.title')" icon="upload" @close="open = false" />
      <div class="modal__body">
        <div class="stack">
          <UiField :label="t('modal.upload.where')" for="upload-folder" :hint="t('modal.upload.hint')">
            <UiSelect id="upload-folder" v-model="target" :options="folderOptions" :disabled="running" />
          </UiField>

          <label
            class="file-drop" :class="{ 'is-drag': dragging }"
            @dragenter.prevent="dragging = true" @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false" @drop.prevent="onDrop"
          >
            <UiIcon name="upload" />
            <div class="file-drop__title">{{ t('modal.upload.drop') }}</div>
            <div class="text-sm text-muted">{{ waiting.length ? t('upload.inQueue', { n: waiting.length }) : t('modal.upload.pick') }}</div>
            <input ref="input" type="file" multiple @change="onPick">
          </label>

          <div v-if="queue.length" data-upload-list>
            <div v-for="item in queue" :key="item.key" class="upload-item" :class="{ 'upload-item--image': item.image }">
              <div class="upload-item__head">
                <div class="fileline">
                  <div class="ftype" :class="`ftype--${kind(item.file.type).kind}`"><UiIcon :name="kind(item.file.type).icon" /></div>
                  <div class="fileline__body">
                    <div class="fileline__name">{{ item.file.name }}</div>
                    <div class="fileline__meta">{{ bytes(item.file.size) }}<span class="dot-sep" /><span data-state>{{ item.note }}</span></div>
                  </div>
                </div>
                <div class="upload-item__side">
                  <span class="tone" :class="tone(item)" data-badge>{{ item.badge }}</span>
                  <button type="button" class="icon-btn icon-btn--ghost icon-btn--sm" :aria-label="t('upload.remove')" @click="drop(item)">
                    <UiIcon name="x" size="sm" />
                  </button>
                </div>
              </div>
              <div class="progress mt-2"><div class="progress__bar" :class="{ 'is-failed': item.status === 'failed' }" :style="{ width: item.percent + '%' }" /></div>
              <UploadImageOptions
                v-if="item.image && item.status !== 'done'" v-model="item.image" :mime="item.file.type"
                :can-share="images > 1" @share="share(item)"
              />
            </div>
          </div>
        </div>
      </div>
      <footer class="modal__footer">
        <span class="text-sm text-muted grow">{{ total }}</span>
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.close') }}</button>
        <button type="button" class="btn btn--primary" :class="{ 'is-busy': running }" :disabled="running || waiting.length === 0" data-upload-start @click="start">
          <UiIcon name="upload" size="sm" /> <span>{{ t('files.upload') }}</span>
        </button>
      </footer>
    </div>
  </UiModal>
</template>
