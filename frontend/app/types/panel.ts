// Shapes of /admin/panel/* and the resource API, as the backend DTOs write them.

export interface Named { id: number; name: string }

export interface Bucket {
  id: string
  name: string
  description: string
  bytes: { quota: number; used: number; free: number }
  status: Named
  cache: { maxAge: number | null; visibility: Named }
  createdAt: string
  updatedAt: string
}

export interface BucketCard { bucket: Bucket; files: number; blobs: number; folders: number }

export interface TrafficDay { day: string; egress: number; ingress: number; deliveries: number; api: number }
export interface TrafficTotals { egress: number; ingress: number; deliveries: number; api: number }
export interface Traffic { from: string; to: string; timezone: string; series: TrafficDay[]; totals: TrafficTotals }
export interface TrafficBucket extends TrafficTotals { bucket_id: string; name: string }

export interface BucketCounts { total: number; active: number; pending: number; full: number; used: number; quota: number }
export interface BlobCounts { blobs: number; stored: number; copies: number; saved: number }
export interface TokenCounts { total: number; active: number; full: number; inactive: number; expired: number }
export interface LinkCounts { total: number; active: number; revoked: number; expired: number }
export interface UploadCounts { total: number; staged: number; expired: number }

export interface Dashboard {
  traffic: Traffic
  top: TrafficBucket[]
  topTotal: number
  quiet: string[]
  fill: BucketCard[]
  fillTotal: number
  buckets: BucketCounts
  blobs: BlobCounts
  tokens: TokenCounts
  links: LinkCounts
  uploads: UploadCounts
}

export interface PageMeta { current: number; size: number; total: number; pages: number; previous: number | null; next: number | null }
export interface Page<T> { meta: PageMeta; data: T[] }
export interface CountedPage<T, C> extends Page<T> { counts: C }

export interface Folder {
  name: string
  public: boolean
  retention: Named
  cache: { maxAge: number | null; visibility: Named | null }
  createdAt: string
  updatedAt: string
}
export interface FolderCard { folder: Folder; files: number }
export interface ExtUsage { ext: string; bytes: number; total: number }

export interface Overview {
  card: BucketCard
  traffic: Traffic
  folders: FolderCard[]
  usage: ExtUsage[]
  placement: { root: number; in_folders: number; in_temp: number }
  folderCounts: { total: number; temp: number }
  tokens: TokenCounts
  links: LinkCounts
  cacheDefaultMaxAge: number
}

export interface AccessToken {
  id: number
  name: string
  access: Named
  accessLabel: string
  tail: string
  status: Named
  expired: boolean
  expiresAt: string | null
  lastUsedAt: string | null
  createdAt: string
}
export interface AccessTokenSecret { token: string; accessToken: AccessToken }

export interface ShareLink {
  id: number | null
  url: string
  expiresAt: string
  revocable: boolean
  maxDownloads: number | null
  downloads: number
  revoked: boolean
  disposition: Named
  note: string
}
export interface LinkCard {
  link: ShareLink
  file: { slug: string; name: string } | null
  state: 'active' | 'revoked' | 'expired' | 'exhausted'
  createdAt: string
}

export interface FileItem {
  id: string
  name: string
  folder: string | null
  content: { size: number; mime: string; extension: string; hash: string }
  public: boolean
  deduplicated: boolean
  processed: { applied: boolean; operations: string[]; source: { size: number }; result: { size: number } } | null
  expiresAt: string | null
  privateUrl: string
  publicUrl: string | null
  createdAt: string
  updatedAt: string
}
export interface FilePage extends Page<FileItem> { folders: FolderCard[]; total: number }

export interface UploadSession {
  id: string
  name: string
  size: number
  offset: number
  chunkSize: number
  expiresAt: string
  file: FileItem | null
}
