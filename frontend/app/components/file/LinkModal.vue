<script setup lang="ts">
import type { FileItem, ShareLink } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string; file: FileItem | null }>()
const emit = defineEmits<{ issued: [link: ShareLink] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()

const ttl = ref<number>(3600)
const disposition = ref(0)
const maxDownloads = ref<number | null>(null)
const note = ref('')
const revocable = ref(false)
const ttls = computed(() => [
  { value: 300, label: t('modal.link.min5') },
  { value: 900, label: t('modal.link.min15') },
  { value: 1800, label: t('modal.link.min30') },
  { value: 3600, label: t('modal.cache.hour') },
  { value: 86400, label: t('modal.cache.day') },
  { value: 604800, label: t('retention.WEEK') },
  { value: 2592000, label: t('retention.MONTH') },
])
// A long link without a row in the database closes only together with all the others:
// better said before it is issued than after.
const warn = computed(() => !revocable.value && maxDownloads.value === null && ttl.value >= 604800)

watch(open, (value) => {
  if (!value) return
  ttl.value = 3600
  disposition.value = 0
  maxDownloads.value = null
  note.value = ''
  revocable.value = false
})

async function issue() {
  const file = props.file
  if (!file) return
  let link: ShareLink | null = null
  const ok = await submit(async () => {
    link = await api.post<ShareLink>(`/buckets/${props.bucketId}/files/${file.id}/link`, {
      ttl: ttl.value,
      disposition: disposition.value,
      maxDownloads: maxDownloads.value,
      note: note.value.trim(),
      revocable: revocable.value,
    })
  })
  if (!ok || !link) return
  open.value = false
  emit('issued', link)
}
</script>

<template>
  <UiModal v-model:open="open" labelledby="modal-link-title">
    <form class="modal__inner" @submit.prevent="issue">
      <UiModalHead id="modal-link-title" :title="t('file.tempLink')" icon="link" tone="temp" @close="open = false" />
      <div class="modal__body">
        <p class="modal__text break">{{ t('modal.link.forFile') }} <b>{{ file?.name }}</b></p>
        <div class="stack">
          <UiField :label="t('modal.link.ttl')" for="link-ttl" :hint="t('modal.link.ttlHint')" :error="errors.ttl">
            <UiSelect id="link-ttl" v-model="ttl" :options="ttls" />
          </UiField>
          <fieldset class="field">
            <legend class="field__label">{{ t('modal.link.what') }}</legend>
            <div class="row">
              <label class="radio"><input v-model="disposition" type="radio" name="disposition" :value="0"><span class="radio__dot" /> <span>{{ t('modal.link.open') }}</span></label>
              <label class="radio"><input v-model="disposition" type="radio" name="disposition" :value="1"><span class="radio__dot" /> <span>{{ t('modal.link.download') }}</span></label>
            </div>
          </fieldset>
          <UiField :label="t('modal.link.limit')" for="link-max" :hint="t('modal.link.limitHint')" :error="errors.maxDownloads">
            <UiNumberInput id="link-max" v-model="maxDownloads" :min="1" :placeholder="t('link.noLimit')" :invalid="!!errors.maxDownloads" />
          </UiField>
          <UiField :label="t('table.note')" for="link-note" :hint="t('modal.link.noteHint')" :error="errors.note">
            <input id="link-note" v-model="note" class="input" :placeholder="t('modal.link.notePlaceholder')" autocomplete="off">
          </UiField>
          <label class="check">
            <input v-model="revocable" type="checkbox" name="revocable">
            <span class="check__box"><UiIcon name="check" /></span>
            <span class="opt__label">
              <span class="opt__title">{{ t('modal.link.revocable') }}</span>
              <span class="opt__hint">{{ t('modal.link.revocableHint') }}</span>
            </span>
          </label>
          <div v-if="warn" class="alert alert--outline">
            <UiIcon name="info" />
            <div class="alert__body"><div class="alert__text">{{ t('modal.link.warn') }}</div></div>
          </div>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="btn btn--primary" :class="{ 'is-busy': busy }" :disabled="busy">{{ t('modal.issue') }}</button>
      </footer>
    </form>
  </UiModal>
</template>
