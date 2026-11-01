<script setup lang="ts">
// Cache policy of a bucket or a folder, with the Cache-Control a client will actually
// get on each channel. cacheHeader() repeats CachePolicy::resolve on the server —
// change both together.
import type { CacheTarget, Visibility } from '~/types/ui'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ target: CacheTarget | null }>()
const emit = defineEmits<{ saved: [cache: { maxAge: number | null; visibility: { name: Visibility } | null }] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()

const DEFAULT_AGE = 86400
const IDS: Record<Visibility, string> = { SHARED: '1', PRIVATE: '2', NO_STORE: '3' }
const NAMES: Record<string, Visibility | null> = { '': null, '1': 'SHARED', '2': 'PRIVATE', '3': 'NO_STORE' }
const SUGGEST: Record<'bucket' | 'folder', Record<string, number | null>> = {
  bucket: { '1': DEFAULT_AGE, '2': 3600, '3': null },
  folder: { '': null, '1': DEFAULT_AGE, '2': 3600, '3': null },
}
const HINT: Record<string, string> = { '': 'cache.ttlHint.inherit', '1': 'cache.ttlHint.shared', '2': 'cache.ttlHint.private', '3': 'cache.ttlHint.none' }

const isFolder = computed(() => !!props.target?.folder)
const level = computed(() => (isFolder.value ? 'folder' : 'bucket'))
const visibility = ref('')
const maxAge = ref<number | null>(null)
let suggested: number | null = null

watch(() => [open.value, props.target] as const, ([isOpen, target]) => {
  if (!isOpen || !target) return
  visibility.value = target.visibility ? IDS[target.visibility] : (isFolder.value ? '' : '1')
  maxAge.value = target.maxAge
  suggested = null
}, { immediate: true })

const off = computed(() => visibility.value === '3')

// A sensible lifetime for the chosen mode, but only over an empty field or the last
// suggestion: a value typed by hand is never overwritten.
function pick(value: string) {
  visibility.value = value
  const suggest = SUGGEST[level.value][value] ?? null
  const untouched = maxAge.value === null || maxAge.value === suggested
  if (value === '3') {
    maxAge.value = null
    suggested = null
  } else if (untouched) {
    maxAge.value = suggest
    suggested = suggest
  }
}

function preset(value: number | null) {
  maxAge.value = value
  suggested = null
}

const inheritedAge = computed((): string | null => {
  const up = props.target?.inherited
  if (up?.maxAge !== null && up?.maxAge !== undefined) return String(up.maxAge)
  if (up?.visibility === 'NO_STORE') return null
  if (up?.visibility === 'PRIVATE') return '0'
  return String(DEFAULT_AGE)
})

const placeholder = computed(() => {
  if (visibility.value === '3') return '—'
  if (visibility.value === '2') return t('cache.ttlNoCache')
  if (visibility.value === '1') return t('cache.ttlDefault', { n: DEFAULT_AGE })
  return inheritedAge.value === null ? t('cache.ttlInherit') : t('cache.ttlInheritAge', { age: inheritedAge.value })
})

function cacheHeader(age: number | null, scope: Visibility | null, filePublic: boolean, channel: string): string {
  let resolved: Visibility = scope ?? (filePublic ? 'SHARED' : 'PRIVATE')
  if (resolved === 'NO_STORE') return 'no-store'
  // A private file and the /p and /t channels are never public.
  if (!filePublic || channel !== 'o') resolved = 'PRIVATE'
  const seconds = age !== null ? age : resolved === 'SHARED' ? DEFAULT_AGE : 0
  const word = resolved === 'SHARED' ? 'public' : 'private'
  if (seconds <= 0) return `${word}, max-age=0, must-revalidate`
  return `${word}, max-age=${seconds}` + (resolved === 'SHARED' ? ', immutable' : '')
}

const preview = computed(() => {
  let scope = NAMES[visibility.value] ?? null
  let age = maxAge.value
  const inherited = age === null && scope === null
  // A folder's empty field is the bucket's setting, not the service default: show
  // what the client will really see.
  if (isFolder.value) {
    scope ??= props.target?.inherited?.visibility ?? null
    age ??= props.target?.inherited?.maxAge ?? null
  }
  return {
    note: t(inherited ? `cache.empty.${level.value}` : 'cache.previewNote'),
    rows: [
      { channel: 'o', label: t('cache.row.public'), value: cacheHeader(age, scope, true, 'o') },
      { channel: 'p', label: t('cache.row.token'), value: cacheHeader(age, scope, false, 'p') },
      { channel: 't', label: t('cache.row.temp'), value: cacheHeader(age, scope, false, 't') },
    ],
  }
})

async function save() {
  const target = props.target
  if (!target) return
  const path = target.folder
    ? `/buckets/${target.bucketId}/folders/${encodeURIComponent(target.folder)}/cache`
    : `/buckets/${target.bucketId}/cache`
  let saved: any = null
  const ok = await submit(async () => {
    saved = await api.patch(path, { maxAge: off.value ? null : maxAge.value, visibility: visibility.value === '' ? null : Number(visibility.value) })
  })
  if (!ok) return
  open.value = false
  toast.ok(target.folder ? tb('cache.savedFolder', { name: target.folder }) : t('cache.savedBucket'))
  emit('saved', saved?.cache ?? { maxAge: null, visibility: null })
}

const choices = computed(() => [
  ...(isFolder.value ? [{ value: '', title: t('cache.autoTitle'), text: t('cache.autoText') }] : []),
  { value: '1', title: t('modal.cache.publicTitle'), text: t('modal.cache.publicText') },
  { value: '2', title: t('modal.cache.privateTitle'), text: t('modal.cache.privateText') },
  { value: '3', title: t('modal.cache.noneTitle'), text: t('modal.cache.noneText') },
])
</script>

<template>
  <UiModal v-model:open="open" variant="modal--cache modal--full" labelledby="modal-cache-title">
    <form class="modal__inner" @submit.prevent="save">
      <UiModalHead id="modal-cache-title" title="" icon="clock" @close="open = false">
        <template #title>
          <h2 id="modal-cache-title" class="modal__title">
            {{ t('bucket.cachePolicy') }}
            <span class="text-muted">{{ target?.folder ? t('cache.targetFolder', { name: target.folder }) : t('cache.targetBucket') }}</span>
          </h2>
        </template>
      </UiModalHead>

      <div class="modal__body">
        <p class="modal__text">{{ t(`cache.intro.${level}`) }}</p>
        <div class="cache-grid">
          <div class="stack">
            <fieldset class="field">
              <legend class="field__label">{{ t('modal.cache.whoTitle') }}</legend>
              <div class="choices">
                <label v-for="choice in choices" :key="choice.value" class="choice">
                  <input type="radio" name="visibility" :value="choice.value" :checked="visibility === choice.value" @change="pick(choice.value)">
                  <span class="radio__dot" />
                  <span class="choice__body">
                    <span class="choice__title">{{ choice.title }}</span>
                    <span class="choice__text">{{ choice.text }}</span>
                  </span>
                </label>
              </div>
            </fieldset>

            <UiField :label="t('modal.cache.ttlLabel')" for="cache-max-age" :error="errors.maxAge" :class="{ 'is-off': off }">
              <UiNumberInput id="cache-max-age" v-model="maxAge" :min="0" :max="31536000" :disabled="off" :placeholder="placeholder" :invalid="!!errors.maxAge" />
              <div class="cache-presets mt-1">
                <button type="button" class="btn btn--ghost btn--sm" :disabled="off" @click="preset(3600)">{{ t('modal.cache.hour') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" :disabled="off" @click="preset(86400)">{{ t('modal.cache.day') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" :disabled="off" @click="preset(31536000)">{{ t('retention.YEAR') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" :disabled="off" @click="preset(0)">{{ t('modal.cache.never') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" :disabled="off" @click="preset(null)">{{ t('cache.default') }}</button>
              </div>
              <span class="field__hint">{{ t(HINT[visibility] ?? 'cache.ttlHint.inherit') }}</span>
            </UiField>
          </div>

          <aside class="stack">
            <div class="cache-preview" aria-live="polite">
              <div class="cache-preview__note">{{ preview.note }}</div>
              <div v-for="row in preview.rows" :key="row.channel" class="cache-preview__row">
                <span class="tone chan" :class="`chan--${row.channel}`">/{{ row.channel }}</span>
                <span class="cache-preview__body">
                  <span class="cache-preview__value">{{ row.value }}</span>
                  <span class="cache-preview__note">{{ row.label }}</span>
                </span>
              </div>
            </div>
            <div class="alert alert--outline">
              <UiIcon name="info" />
              <div class="alert__body"><div class="alert__text">{{ t('modal.cache.note') }}</div></div>
            </div>
          </aside>
        </div>
      </div>

      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="btn btn--primary" :class="{ 'is-busy': busy }" :disabled="busy">{{ t('common.save') }}</button>
      </footer>
    </form>
  </UiModal>
</template>
