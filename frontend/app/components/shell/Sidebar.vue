<script setup lang="ts">
defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: [] }>()
const { t } = useLang()
const route = useRoute()
const { buckets } = useBuckets()

const bucketId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null))
const bucket = computed(() => buckets.value.find((b) => b.id === bucketId.value) ?? null)
const base = computed(() => '/buckets/' + bucketId.value)
const section = computed(() => {
  const last = route.path.split('/')[3]
  return ['files', 'tokens', 'links', 'stats'].includes(last ?? '') ? (last as string) : 'overview'
})

const sections = [
  { key: 'overview', path: '', icon: 'grid' },
  { key: 'files', path: '/files', icon: 'folder' },
  { key: 'tokens', path: '/tokens', icon: 'key' },
  { key: 'links', path: '/links', icon: 'link' },
  { key: 'stats', path: '/stats', icon: 'chart' },
]

async function logout() {
  try {
    await api.post('/auth/logout')
  } finally {
    await navigateTo('/login')
  }
}
</script>

<template>
  <aside id="sidebar" class="sidebar" :data-open="open ? '' : undefined" :aria-label="t('a11y.nav')">
    <div class="sidebar__top">
      <NuxtLink class="sidebar__logo" to="/">
        <svg class="sidebar__logo-mark"><use href="/icons.svg#i-logo" /></svg>
        s5w
      </NuxtLink>
      <button type="button" class="icon-btn icon-btn--ghost sidebar__close" :aria-label="t('a11y.closeMenu')" @click="emit('close')">
        <UiIcon name="x" />
      </button>
    </div>

    <nav class="stack stack--tight" :aria-label="t('a11y.sections')">
      <NuxtLink class="nav-item" exact-active-class="active" to="/">
        <UiIcon name="grid" /> <span>{{ t('nav.dashboard') }}</span>
      </NuxtLink>
      <NuxtLink class="nav-item" exact-active-class="active" to="/buckets">
        <UiIcon name="database" /> <span>{{ t('nav.buckets') }}</span>
      </NuxtLink>
    </nav>

    <ShellBucketSwitch :current="bucketId" :section="section" />

    <p v-if="!bucketId" class="sidebar__hint">{{ t('bucket.pick') }}</p>
    <template v-else>
      <nav class="nav-sub" :aria-label="t('a11y.bucketSections')">
        <NuxtLink
          v-for="item in sections"
          :key="item.key"
          class="nav-item"
          exact-active-class="active"
          :to="base + item.path"
        >
          <UiIcon :name="item.icon" /> <span>{{ t('nav.' + item.key) }}</span>
        </NuxtLink>
      </nav>
      <UiQuota v-if="bucket" class="sidebar__quota" :used="bucket.bytes.used" :quota="bucket.bytes.quota" />
    </template>

    <div class="sidebar__footer">
      <button type="button" class="nav-item" @click="logout">
        <UiIcon name="logout" /> <span>{{ t('nav.logout') }}</span>
      </button>
    </div>
  </aside>
</template>
