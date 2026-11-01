<script setup lang="ts">
// Image processing, on the card of the image itself: one file wants its own format,
// another must not be touched. A setting that is off is null — the service decides —
// so "quality 82" in the form never means compression switched on silently.
import type { ImageSettings } from '~/types/ui'

const model = defineModel<ImageSettings>({ required: true })
const props = defineProps<{ mime: string; canShare: boolean }>()
const emit = defineEmits<{ share: [] }>()
const { t } = useLang()
const expanded = ref(false)

/** The service's starting quality per format (ImageFormat::defaultQuality()). */
const PRESET: Record<string, number> = { AVIF: 55, PNG: 60, WEBP: 82, JPEG: 82 }
const ABOUT: Record<string, string> = { WEBP: 'image.about.webp', JPEG: 'image.about.jpeg', PNG: 'image.about.png', AVIF: 'image.about.avif' }
const FROM_MIME: Record<string, string> = { 'image/webp': 'WEBP', 'image/jpeg': 'JPEG', 'image/png': 'PNG', 'image/avif': 'AVIF', 'image/gif': 'GIF' }
const formats = ['WEBP', 'JPEG', 'PNG', 'AVIF'].map((value) => ({ value, label: value }))

const target = computed(() => (model.value.convert ? model.value.format : FROM_MIME[props.mime] ?? ''))
const preset = computed(() => PRESET[target.value] ?? 82)
// Until quality is touched, the slider stands where the service starts for the chosen
// format: avif has its own scale, 82 there is not "as webp".
watch(preset, (value) => {
  if (!model.value.compress) model.value.quality = value
}, { immediate: true })

const warning = computed(() => {
  if (!model.value.convert) return null
  const format = model.value.format
  if (FROM_MIME[props.mime] === format) return t('image.warn.same')
  if (format === 'JPEG' && props.mime !== 'image/jpeg') return t('image.warn.jpeg')
  if (format === 'PNG' && props.mime === 'image/jpeg') return t('image.warn.png')
  return null
})

const parts = computed(() => {
  const o = model.value
  const out: string[] = []
  if (o.convert) out.push(t('image.sum.format', { format: o.format.toLowerCase() }))
  if (o.resize && (o.maxWidth || o.maxHeight)) out.push(t('image.sum.size', { w: o.maxWidth || '∞', h: o.maxHeight || '∞' }))
  if (o.compress) out.push(t('image.sum.quality', { n: o.quality }))
  return out
})
</script>

<template>
  <div class="fold fold--inline mt-2" :class="{ 'is-open': expanded, 'is-set': parts.length > 0 }" data-image-options>
    <button type="button" class="fold__head" :aria-expanded="expanded" @click="expanded = !expanded">
      <UiIcon name="crop" size="sm" />
      <span class="fold__title">{{ t('image.title') }}</span>
      <span class="fold__note">{{ parts.length ? parts.join(' · ') : t('image.asIs') }}</span>
      <UiIcon name="chevron-down" size="sm" class="fold__chev" />
    </button>
    <div v-show="expanded" class="fold__body">
      <div class="stack">
        <div class="opt">
          <label class="switch">
            <input v-model="model.resize" type="checkbox" data-opt="resize">
            <span class="switch__track" />
            <span class="opt__label"><span class="opt__title">{{ t('image.resize') }}</span><span class="opt__hint">{{ t('image.resizeHint') }}</span></span>
          </label>
          <div v-if="model.resize" class="opt__body">
            <div class="row row--nowrap">
              <UiField :label="t('image.width')" class="w-full">
                <UiNumberInput v-model="model.maxWidth" name="maxWidth" :min="1" :placeholder="t('image.noLimit')" />
              </UiField>
              <UiField :label="t('image.height')" class="w-full">
                <UiNumberInput v-model="model.maxHeight" name="maxHeight" :min="1" :placeholder="t('image.noLimit')" />
              </UiField>
            </div>
            <span class="field__hint">{{ t('image.resizeNote') }}</span>
          </div>
        </div>

        <div class="opt">
          <label class="switch">
            <input v-model="model.compress" type="checkbox" data-opt="quality">
            <span class="switch__track" />
            <span class="opt__label"><span class="opt__title">{{ t('image.quality') }}</span><span class="opt__hint">{{ t('image.qualityHint') }}</span></span>
          </label>
          <div v-if="model.compress" class="opt__body">
            <label class="field__label">{{ t('image.quality') }} · <b>{{ model.quality }}</b></label>
            <input v-model.number="model.quality" class="range" type="range" name="quality" min="1" max="100" :aria-label="t('image.quality')">
            <span class="field__hint">{{ t('image.qualityNote', { preset }) }}</span>
          </div>
        </div>

        <div class="opt">
          <label class="switch">
            <input v-model="model.convert" type="checkbox" data-opt="format">
            <span class="switch__track" />
            <span class="opt__label"><span class="opt__title">{{ t('image.format') }}</span><span class="opt__hint">{{ t('image.formatHint') }}</span></span>
          </label>
          <div v-if="model.convert" class="opt__body">
            <UiSelect v-model="model.format" :options="formats" :aria-label="t('image.format')" />
            <span class="field__hint">{{ t(ABOUT[model.format] ?? '') }}</span>
            <span v-if="warning" class="field__hint text-warn">{{ warning }}</span>
          </div>
        </div>
      </div>
      <div class="row mt-2">
        <span class="text-sm text-muted grow">{{ t(parts.length ? 'image.quotaProcessed' : 'image.quotaExact') }}</span>
        <button v-if="canShare" type="button" class="btn btn--ghost btn--sm" @click="emit('share')">
          <UiIcon name="copy" size="sm" /> <span>{{ t('image.applyAll') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
