<script setup lang="ts">
import type { AccessTokenSecret } from '~/types/panel'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ bucketId: string }>()
const emit = defineEmits<{ issued: [secret: AccessTokenSecret] }>()
const { t } = useLang()
const { errors, busy, submit } = useForm()
const name = ref('')
const access = ref(1)
const expires = ref<string | number>('')

watch(open, (value) => {
  if (!value) return
  name.value = ''
  access.value = 1
  expires.value = ''
})

async function issue() {
  let issued: AccessTokenSecret | null = null
  const ok = await submit(async () => {
    issued = await api.post<AccessTokenSecret>(`/buckets/${props.bucketId}/tokens`, {
      name: name.value.trim(),
      access: access.value,
      expiresInDays: expires.value === '' ? null : Number(expires.value),
    })
  })
  if (!ok || !issued) return
  open.value = false
  emit('issued', issued)
}
</script>

<template>
  <UiModal v-model:open="open" variant="modal--token" labelledby="modal-token-title">
    <form class="modal__inner" @submit.prevent="issue">
      <UiModalHead id="modal-token-title" :title="t('modal.token.title')" icon="key" @close="open = false" />
      <div class="modal__body">
        <div class="stack">
          <UiField :label="t('modal.token.name')" for="token-name" :hint="t('modal.token.nameHint')" :error="errors.name">
            <input id="token-name" v-model="name" class="input" :class="{ 'is-invalid': errors.name }" :placeholder="t('modal.token.namePlaceholder')" autocomplete="off">
          </UiField>
          <fieldset class="field">
            <legend class="field__label">{{ t('modal.token.opens') }}</legend>
            <div class="choices choices--pair">
              <label class="choice">
                <input v-model="access" type="radio" name="access" :value="1">
                <span class="radio__dot" />
                <span class="choice__body">
                  <span class="choice__title">{{ t('access.BASIC') }}</span>
                  <span class="choice__text">{{ t('modal.token.basicText') }}</span>
                </span>
              </label>
              <label class="choice">
                <input v-model="access" type="radio" name="access" :value="2">
                <span class="radio__dot" />
                <span class="choice__body">
                  <span class="choice__title">{{ t('access.FULL') }}</span>
                  <span class="choice__text">{{ t('modal.token.fullText') }}</span>
                </span>
              </label>
            </div>
          </fieldset>
          <UiField :label="t('table.expiry')" for="token-expires" :hint="t('modal.token.expiryHint')" :error="errors.expiresInDays">
            <UiSelect
              id="token-expires"
              v-model="expires"
              :options="[
                { value: '', label: t('time.forever') },
                { value: 30, label: t('modal.token.days30') },
                { value: 90, label: t('modal.token.days90') },
                { value: 365, label: t('retention.YEAR') },
              ]"
            />
          </UiField>
        </div>
      </div>
      <footer class="modal__footer">
        <button type="button" class="btn btn--ghost" @click="open = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="btn btn--primary" :class="{ 'is-busy': busy }" :disabled="busy">{{ t('modal.issue') }}</button>
      </footer>
    </form>
  </UiModal>
</template>
