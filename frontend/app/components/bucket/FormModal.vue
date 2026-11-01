<script setup lang="ts">
// One form creates and edits a bucket: the title, the button and the request change.
import type { Bucket } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucket?: Bucket | null }>()
const emit = defineEmits<{ created: [bucket: Bucket]; updated: [bucket: Bucket] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()

const MB = 1048576
const GB = 1073741824
const name = ref('')
const description = ref('')
const quota = ref<number | null>(512)
const unit = ref<number>(MB)
const editing = computed(() => !!props.bucket)

watch(open, (value) => {
  if (!value) return
  const bucket = props.bucket
  name.value = bucket?.name ?? ''
  description.value = bucket?.description ?? ''
  unit.value = MB
  quota.value = bucket ? Math.round(bucket.bytes.quota / MB) : 512
}, { immediate: true })

async function save() {
  const body = { name: name.value.trim(), description: description.value, quotaBytes: Math.round(Number(quota.value ?? 0) * unit.value) }
  let saved: Bucket | null = null
  const ok = await submit(async () => {
    saved = props.bucket
      ? await api.put<Bucket>(`/buckets/${props.bucket.id}`, body)
      : await api.post<Bucket>('/buckets', body)
  }, { nameField: true })
  if (!ok || !saved) return
  open.value = false
  if (editing.value) emit('updated', saved)
  else emit('created', saved)
}
</script>

<template>
  <UiModal v-model:open="open" labelledby="modal-bucket-title">
    <form class="modal__inner" @submit.prevent="save">
      <UiModalHead id="modal-bucket-title" :title="t(editing ? 'bucket.editTitle' : 'buckets.new')" icon="database" @close="open = false" />
      <div class="modal__body">
        <p v-if="!editing" class="modal__text">{{ t('modal.bucket.noteA') }} <b>ACTIVE</b> {{ t('modal.bucket.noteB') }}</p>
        <div class="stack">
          <UiField :label="t('modal.file.name')" for="bucket-name" :hint="t('modal.bucket.nameHint')" :error="errors.name">
            <input id="bucket-name" v-model="name" class="input" :class="{ 'is-invalid': errors.name }" placeholder="media-lab" autocomplete="off">
          </UiField>
          <UiField :label="t('overview.description')" for="bucket-description" :error="errors.description">
            <input id="bucket-description" v-model="description" class="input" :placeholder="t('modal.bucket.descPlaceholder')" autocomplete="off">
          </UiField>
          <UiField :label="t('table.quota')" for="bucket-quota" :error="errors.quotaBytes">
            <div class="quota-input">
              <UiNumberInput id="bucket-quota" v-model="quota" :min="1" :invalid="!!errors.quotaBytes" />
              <UiSelect v-model="unit" :aria-label="t('modal.bucket.unit')" :options="[{ value: MB, label: t('unit.mb') }, { value: GB, label: t('unit.gb') }]" />
            </div>
          </UiField>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="btn btn--primary" :class="{ 'is-busy': busy }" :disabled="busy">
          {{ t(editing ? 'common.save' : 'common.create') }}
        </button>
      </footer>
    </form>
  </UiModal>
</template>
