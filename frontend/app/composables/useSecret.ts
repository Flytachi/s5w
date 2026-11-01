// A secret is shown once: a token after it is issued or rotated, a link after it is made.
export interface Secret {
  title: string
  value: string
  html?: string
  check?: { command: string; hint: string }
}

const secret = ref<Secret | null>(null)
export const showSecret = (value: Secret) => (secret.value = value)
export const useSecretState = () => secret
