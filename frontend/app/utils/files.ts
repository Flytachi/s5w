import type { Folder } from '~/types/panel'

/** The file's kind for its badge: a css modifier and an icon. */
export function kind(mime: string | null | undefined): { kind: string; icon: string } {
  const type = mime ?? ''
  if (type.startsWith('image/')) return { kind: 'image', icon: 'image' }
  if (type.startsWith('video/')) return { kind: 'video', icon: 'film' }
  if (type.startsWith('audio/')) return { kind: 'audio', icon: 'music' }
  if (/zip|gzip|tar/.test(type)) return { kind: 'arch', icon: 'archive' }
  return { kind: 'doc', icon: 'file' }
}

export interface FolderMark { icon: string; tone: string; text: string }

/** A folder's marks: closed, with a retention, with its own cache. */
export function folderMarks(folder: Folder): FolderMark[] {
  const marks: FolderMark[] = []
  if (!folder.public) marks.push({ icon: 'lock', tone: 'brand', text: t('folder.mark.private') })
  if (folder.retention.id > 0) {
    marks.push({ icon: 'clock', tone: 'warn', text: t('folder.mark.retention', { value: '@retention.' + folder.retention.name }) })
  }
  const { maxAge, visibility } = folder.cache
  if (maxAge !== null || visibility !== null) {
    const vars: Record<string, string> = {}
    if (visibility !== null) vars.vis = '@cache.visibility.' + visibility.name
    if (maxAge !== null) vars.age = maxAge + ' s'
    const key = visibility !== null && maxAge !== null ? 'folder.mark.cacheBoth' : visibility !== null ? 'folder.mark.cacheVis' : 'folder.mark.cacheAge'
    marks.push({ icon: 'zap', tone: 'temp', text: t(key, vars) })
  }
  return marks
}

export const RETENTIONS = ['NONE', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'HALF_YEAR', 'YEAR']
