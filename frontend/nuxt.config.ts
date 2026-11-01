// SPA: the HTML is a shell and the app renders in the browser. Swoole serves the
// generated files on its own port and proxies /api to the backend, so the browser
// talks to one origin and the admin session cookie just works.
const backend = process.env.S5W_BACKEND ?? 'http://127.0.0.1:9090'

export default defineNuxtConfig({
  compatibilityDate: '2026-10-01',
  ssr: false,
  devtools: { enabled: false },
  telemetry: false,

  css: [
    '~/assets/css/fonts.css',
    '~/assets/css/base.css',
    '~/assets/css/shell.css',
    '~/assets/css/components.css',
    '~/assets/css/pages.css',
    '~/assets/css/app.css',
  ],

  app: {
    head: {
      title: 's5w',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'robots', content: 'noindex, nofollow' },
        { name: 'theme-color', content: '#3b5bdb' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' },
        { rel: 'icon', href: '/favicon-32.png', sizes: '32x32', type: 'image/png' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
      ],
      script: [
        {
          // Theme and language before the first paint: the shell must not flash light
          // in the dark theme or English in Russian.
          tagPosition: 'head',
          innerHTML: `(function(){try{var t=localStorage.getItem('theme');var d=t?t==='dark':matchMedia('(prefers-color-scheme: dark)').matches;if(d)document.documentElement.dataset.theme='dark';var l=(document.cookie.match(/(?:^|; )lang=(en|ru)/)||[])[1];document.documentElement.lang=l==='ru'?'ru':'en'}catch(e){}})()`,
        },
      ],
    },
  },

  // `nuxt dev` on the host: the same /api the Swoole proxy serves in the container.
  nitro: {
    devProxy: {
      // Nitro adds its own X-Forwarded-Port unless one is set: the backend would take this
      // dev port for its own and write it into file URLs and links.
      '/api': {
        target: backend + '/admin',
        changeOrigin: true,
        headers: { 'x-forwarded-port': new URL(backend).port || '80' },
      },
    },
  },
})
