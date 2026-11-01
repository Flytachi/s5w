<script setup lang="ts">
// Rename and move are one call of the API; the form asks only for what was chosen
// and sends the other field as it is.
import type { FileItem, FolderCard } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string; file: FileItem | null; mode: 'rename' | 'move'; folders: FolderCard[] }>()
const emit = defineEmits<{ saved: [file: FileItem] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()

const name = ref('')
const folder = ref<string>('')
const renaming = computed(() => props.mode === 'rename')
const options = computed(() => [
  { value: '', label: t('files.bucketRoot') },
  ...props.folders.map((card) => ({ value: card.folder.name, label: card.folder.name })),
])

watch(open, (value) => {
  if (!value || !props.file) return
  name.value = props.file.name
  folder.value = props.file.folder ?? ''
}, { immediate: true })

async function save() {
  const file = props.file
  if (!file) return
  let saved: FileItem | null = null
  const ok = await submit(async () => {
    saved = await api.put<FileItem>(`/buckets/${props.bucketId}/files/${file.id}`, {
      name: name.value.trim(),
      folder: folder.value === '' ? null : folder.value,
    })
  }, { nameField: true })
  if (!ok || !saved) return
  open.value = false
  emit('saved', saved)
}
</script>

<template>
  <UiModal v-model:open="open" labelledby="modal-file-title">
    <form class="modal__inner" @submit.prevent="save">
      <UiModalHead id="modal-file-title" :title="t(renaming ? 'file.rename' : 'file.move')" :icon="renaming ? 'edit' : 'folder'" @close="open = false" />
      <div class="modal__body">
        <UiField v-if="renaming" :label="t('modal.file.name')" for="file-name" :hint="t('modal.file.nameHint')" :error="errors.name">
          <input id="file-name" v-model="name" class="input" :class="{ 'is-invalid': errors.name }" autocomplete="off" @focus="($event.target as HTMLInputElement).select()">
        </UiField>
        <UiField v-else :label="t('modal.file.folder')" for="file-folder" :hint="t('modal.file.moveHint')" :error="errors.folder ?? errors.name">
          <UiSelect id="file-folder" v-model="folder" :options="options" />
        </UiField>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="btn btn--primary" :class="{ 'is-busy': busy }" :disabled="busy">
          {{ t(renaming ? 'file.rename' : 'file.move') }}
        </button>
      </footer>
    </form>
  </UiModal>
</template>
