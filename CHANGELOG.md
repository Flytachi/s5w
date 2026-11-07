# Changelog

All notable changes to S5W are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow
[Semantic Versioning](https://semver.org/). Images are published as
[`flytachi/s5w`](https://hub.docker.com/r/flytachi/s5w) with the same tags.

Upgrading means pulling the new image and recreating the container **on the same volume** —
data, tokens and issued links are kept. Notes marked **⚠ Upgrade** need attention.

## [Unreleased]

## [1.1.3] — 2026-11-08

### Added

- **License:** S5W is now released under the [Apache License 2.0](LICENSE) (`LICENSE`, `NOTICE`).

### Fixed

- **Admin panel:** a request for a missing `/_nuxt/` asset now answers `404` with
  `Cache-Control: no-store`. It used to get the app shell with a one-year `immutable` cache, so a
  browser holding a page from a previous build could cache HTML under a script URL.
- **Bucket deletion:** the token cache is cleared again after the bucket rows are gone. A request
  arriving during the asynchronous purge could put a token back into the cache and keep it valid
  for up to 60 seconds after the bucket was deleted.
- **`DemoStatsCmd`** only touches the `demo-stats` bucket it created itself (recognized by its
  description). A bucket with that name created by someone else is left alone, both on refill
  and on `--drop`.

### Removed

- Unused code left from the old server-rendered panel: `TrafficDay::label()`/`title()`,
  `TrafficTotals::isEmpty()`/`averageServed()`, `TrafficBucket::averageServed()`,
  `CacheVisibility::label()`/`labelKey()`/`tone()`, `BlobCounts::ratio()`,
  `BlobStore::bucketExists()`/`blobSize()`.

### Documentation

- OpenAPI and Postman (`docs/{en,ru}`) checked against the code and corrected: download counting
  happens only with `maxDownloads`; `ttl` is optional; `Range` is not served by a temporary link
  with a download limit; an upload expires 24 h after its last chunk; any completion error deletes
  the upload; links are kept for 7 days after expiry; `PATCH …/cache` replaces both fields;
  `416` and `501` added to the status table.
- Code comments that no longer matched the code were corrected.

## [1.1.2] — 2026-11-08

### Changed

- **⚠ Upgrade — time format.** Every timestamp in API responses is now RFC 3339:
  `2026-11-08T17:10:44+00:00`. Before, three forms were returned: `2026-11-08 17:10:44 +00:00`
  for freshly created objects and `2026-11-08 17:10:44+00` (sometimes with fractions of a
  second) for objects read from the database. Clients that parse time with their own pattern for
  the space-separated form need a fix; standard date parsers read the new form as is.

### Fixed

- **File names on rename** are cleaned the same way as on upload: `/`, `\` and control
  characters become `-` (renaming to `a/b.txt` stores `a-b.txt`).

### Added

- OpenAPI 3.1 specification of the client API, in English and Russian: `docs/{en,ru}/openapi.yaml`.
- Postman collections rebuilt from scratch, in English and Russian:
  `docs/{en,ru}/s5w-api.postman_collection.json` — every request in order, real example responses
  including errors, automatic checks and a cleanup section.

## [1.1.1] — 2026-11-07

### Fixed

- **`POST /v1/uploads` with `sha256` and image options** no longer silently skips processing. The
  hash is of the source bytes while the processed result is what gets stored, so with `format`,
  `quality`, `maxWidth` or `maxHeight` the hash lookup is skipped and the upload goes the normal
  way; the hash is still verified on completion.

## [1.1.0] — 2026-11-01

### Added

- **New admin panel** — a standalone web app on its own port **9091**: overview, buckets, files
  with folders and image processing, tokens, temporary links, traffic statistics and cache
  policy. Responsive, light and dark theme, English and Russian. It reaches the API inside the
  container, so neither CORS nor outside access to `/admin` is needed.
- `ADMIN_UI` — turns the panel off (`false`): the container only stores and delivers files.
- `ADMIN_API` — where the admin API `/admin/…` is accepted from: `local` (only the panel inside
  the container) or `public`. Unset, it follows `ADMIN_UI`: `local` with the panel, `public`
  without it.
- `TRUSTED_PROXIES` — which proxies to believe in `X-Forwarded-For`; the default `private`
  covers any Docker network, so nginx-proxy-manager or traefik need no setting.
- The panel warns when `PUBLIC_BASE_URL` is not set.

### Changed

- **⚠ Upgrade — the panel moved.** The old panel at `:9090/admin` is gone; the new one is on
  `9091`. Publish the port (`-p 9091:9091`) or add a proxy host for it. Sign in again after the
  upgrade.
- **⚠ Upgrade — admin API is local by default.** With the panel on, `/admin/…` on 9090 answers
  `404` from outside. Scripts that call it from outside need `ADMIN_API=public`.
- **⚠ Upgrade — code path.** The application lives in `/app` instead of `/var/www/html`. This
  matters only if you mount the source tree (`./:/app/`).
- Runtime image is [`flytachi/winter:8.5`](https://hub.docker.com/r/flytachi/winter) (PHP 8.5,
  Swoole) on the Winter 4.4 framework; PostgreSQL stays at 18, so the volume needs no
  `pg_upgrade`.
- Graceful shutdown: the application finishes requests first (up to 35 s), then PostgreSQL stops
  with a fast shutdown. Give the container `--stop-timeout 45` / `stop_grace_period: 45s`.
- Behind a private-network proxy, the login lockout now counts the real client address instead
  of the proxy's.

### Removed

- The old server-rendered admin panel on port 9090.

## [1.0.1] — 2026-09-11

### Changed

- Dependency updates.
- Postman collection updated.

## [1.0.0] — 2026-08-25

First release.

- Buckets with quotas and access tokens (`BASIC` for delivery, `FULL` for everything).
- Folders with visibility, retention and cache policy.
- Single-request upload and resumable chunked upload, deduplication by SHA-256.
- Image processing at upload: resize, EXIF rotation, `webp` / `avif` / `jpeg` / `png`.
- Delivery: public `/o`, by token `/p`, signed temporary links `/t` with expiry and download limit;
  `Range`, `ETag`, conditional requests and `Cache-Control`.
- Traffic statistics per bucket and day.
- Bundled PostgreSQL and scheduled maintenance in one container, all data in one volume.

[Unreleased]: https://github.com/Flytachi/s5w/compare/v1.1.3...HEAD
[1.1.3]: https://github.com/Flytachi/s5w/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/Flytachi/s5w/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/Flytachi/s5w/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/Flytachi/s5w/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/Flytachi/s5w/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Flytachi/s5w/releases/tag/v1.0.0
