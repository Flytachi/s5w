export function percentOf(used: number, quota: number): number {
  return quota > 0 ? Math.min(100, (used / quota) * 100) : 0
}

/** The quota bar's tone: amber from 70%, red from 90%. */
export function quotaState(percent: number): string {
  if (percent >= 90) return 'is-danger'
  if (percent >= 70) return 'is-warn'
  return ''
}

export const isActive = (status: { name: string }) => status.name === 'ACTIVE'
