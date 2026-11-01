export type Visibility = 'SHARED' | 'PRIVATE' | 'NO_STORE'

export interface CacheTarget {
  bucketId: string
  folder?: string
  maxAge: number | null
  visibility: Visibility | null
  /** A folder's empty fields mean "as the bucket": what the bucket has. */
  inherited?: { maxAge: number | null; visibility: Visibility | null }
}

export interface Option { value: string | number; label: string }

/** An image's processing on upload; a switch that is off sends null. */
export interface ImageSettings {
  resize: boolean
  maxWidth: number | null
  maxHeight: number | null
  compress: boolean
  quality: number
  convert: boolean
  format: string
}
