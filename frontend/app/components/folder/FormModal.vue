<script setup lang="ts">
// One form creates and edits a folder.
import type { Folder } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string; folder?: Folder | null }>()
const emit = defineEmits<{ created: [folder: Folder]; updated: [folder: Folder] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()

const name = ref('')
const isPublic = ref(false)
const retention = ref<number>(0)
const editing = computed(() => !!props.folder)
const retentions = computed(() => RETENTIONS.map((key, id) => ({ value: id, label: t('retention.' + key) })))

watch(open, (value) => {
  if (!value) return
  name.value = props.folder?.name ?? ''
  isPublic.value = props.folder?.public ?? false
  retention.value = props.folder?.retention.id ?? 0
}, { immediate: true })

async function save() {
  const body = { name: name.value.trim(), public: isPublic.value, retention: retention.value }
  const base = `/buckets/${props.bucketId}/folders`
  let saved: Folder | null = null
  const ok = await submit(async () => {
    saved = props.folder
      ? await api.put<Folder>(`${base}/${encodeURIComponent(props.folder.name)}`, body)
      : await api.post<Folder>(base, body)
  }, { nameField: true })
  if (!ok || !saved) return
  open.value = false
  if (editing.value) emit('updated', saved)
  else emit('created', saved)
}
</script>

<template>
  <UiModal v-model:open="open" labelledby="modal-folder-title">
    <form class="modal__inner" @submit.prevent="save">
      <UiModalHead id="modal-folder-title" :title="t(editing ? 'folder.editTitle' : 'files.newFolder')" icon="folder" tone="ok" @close="open = false" />
      <div class="modal__body">
        <div class="stack">
          <UiField :label="t('modal.file.name')" for="folder-name" :hint="t('modal.folder.nameHint')" :error="errors.name">
            <input id="folder-name" v-model="name" class="input" :class="{ 'is-invalid': errors.name }" placeholder="photos" autocomplete="off">
          </UiField>
          <label class="switch">
            <input v-model="isPublic" type="checkbox" name="public">
            <span class="switch__track" />
            <span>{{ t('modal.folder.publicA') }} <span class="mono">/o</span> {{ t('modal.folder.publicB') }}</span>
          </label>
          <UiField :label="t('modal.file.retention')" for="folder-retention" :hint="t('modal.folder.retentionHint')" :error="errors.retention">
            <UiSelect id="folder-retention" v-model="retention" :options="retentions" />
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
