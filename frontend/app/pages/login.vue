<script setup lang="ts">
definePageMeta({ layout: 'auth' })

const { t, lang, setLang } = useLang()
const route = useRoute()
usePageHeader(() => ({ titleKey: 'page.login.title' }))

const login = ref('')
const password = ref('')
const peek = ref(false)
const busy = ref(false)
const error = ref<string | null>(null)
const locked = ref(false)
const passwordInput = ref<HTMLInputElement | null>(null)

// Only a path of this panel: `next` comes from the address bar and must not send the
// browser to another site after a successful login.
const next = computed(() => {
  const value = typeof route.query.next === 'string' ? route.query.next : '/'
  return value.startsWith('/') && !value.startsWith('//') ? value : '/'
})

async function submit() {
  busy.value = true
  error.value = null
  try {
    await api.post('/auth/login', { login: login.value.trim(), password: password.value })
    await navigateTo(next.value)
  } catch (err) {
    const status = err instanceof ApiError ? err.status : 0
    locked.value = status === 503
    error.value = status === 429 ? t('login.tooMany') : (err as Error).message
    password.value = ''
    passwordInput.value?.focus()
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <button type="button" class="icon-btn lang-btn auth__lang" :aria-label="t('lang.switch')" @click="setLang(lang === 'en' ? 'ru' : 'en')">
    <span>{{ lang.toUpperCase() }}</span>
  </button>
  <UiThemeToggle class="auth__theme" />

  <div class="auth">
    <form class="auth__card" autocomplete="on" @submit.prevent="submit">
      <div class="auth__logo">
        <svg><use href="/icons.svg#i-logo" /></svg>
        s5w
      </div>
      <div class="auth__sub">{{ t('login.sub') }}</div>

      <div v-if="locked" class="alert alert--danger mt-3" role="alert">
        <UiIcon name="lock" />
        <div class="alert__body">
          <div class="alert__title">{{ t('login.locked.title') }}</div>
          <div class="alert__text">
            {{ t('login.locked.before') }} <span class="mono">ADMIN_LOGIN</span>
            {{ t('login.locked.join') }} <span class="mono">ADMIN_PASSWORD</span>.
          </div>
        </div>
      </div>

      <div v-else class="stack mt-3">
        <div class="field">
          <label class="field__label" for="login">{{ t('login.user') }}</label>
          <div class="input-group">
            <UiIcon name="user" />
            <input
              id="login" v-model="login" class="input" name="login" placeholder="admin"
              autocomplete="username" autocapitalize="off" spellcheck="false" autofocus required
            >
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="password">{{ t('login.password') }}</label>
          <div class="input-group">
            <UiIcon name="lock" />
            <input
              id="password" ref="passwordInput" v-model="password" class="input" name="password"
              :type="peek ? 'text' : 'password'" placeholder="••••••••" autocomplete="current-password" required
            >
            <button
              type="button" class="icon-btn icon-btn--ghost icon-btn--sm input-group__end"
              :aria-label="t(peek ? 'login.hide' : 'login.peek')" :aria-pressed="peek"
              @click="peek = !peek; passwordInput?.focus()"
            >
              <UiIcon :name="peek ? 'eye-off' : 'eye'" size="sm" />
            </button>
          </div>
        </div>

        <div v-if="error" class="alert alert--danger" role="alert">
          <UiIcon name="alert-triangle" />
          <div class="alert__body"><div class="alert__text">{{ error }}</div></div>
        </div>

        <button type="submit" class="btn btn--primary btn--lg btn--block" :disabled="busy" :aria-busy="busy">
          {{ t('login.submit') }}
        </button>
      </div>
    </form>
  </div>
</template>
