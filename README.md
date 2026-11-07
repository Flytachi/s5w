<div align="center">

<img src="frontend/public/favicon.svg" alt="S5W" width="96" height="96" />

# S5W

**Self-hosted file storage and CDN in a single container.**<br />
Upload API, file delivery with CDN headers, temporary links and an admin panel — one `docker run`, no external services.

[![Docker image version](https://img.shields.io/docker/v/flytachi/s5w?sort=semver&label=image&logo=docker&logoColor=white)](https://hub.docker.com/r/flytachi/s5w)
[![Docker pulls](https://img.shields.io/docker/pulls/flytachi/s5w?logo=docker&logoColor=white)](https://hub.docker.com/r/flytachi/s5w)
[![Image size](https://img.shields.io/docker/image-size/flytachi/s5w?sort=semver&label=size)](https://hub.docker.com/r/flytachi/s5w)
![Platforms](https://img.shields.io/badge/platforms-amd64%20%7C%20arm64-informational)
[![Docs](https://img.shields.io/badge/docs-winterframe.net-0ea5e9)](https://winterframe.net/apps/s5w)
[![License](https://img.shields.io/badge/license-Apache--2.0-blue)](LICENSE)

[Documentation](https://winterframe.net/apps/s5w) ·
[Quick start](#quick-start) ·
[API](#api) ·
[Changelog](CHANGELOG.md) ·
[Security](SECURITY.md) ·
[Документация на русском](https://winterframe.net/ru/apps/s5w/overview)

</div>

---

S5W is the service your applications hand files to and ask for links back. Avatars, attachments,
documents, images for a website, exports — anything an application would rather not keep on its
own disk or in its database. It runs **on your server, inside your perimeter**: no external cloud,
no third party billing you for traffic.

## Features

- 📦 **One container, zero dependencies** — PostgreSQL, the scheduler and the admin panel are already inside.
- 🗂 **Buckets and folders** — per-bucket quota and tokens; per-folder visibility, retention and cache policy.
- 🔗 **Three delivery channels** — public links, access by token, and signed temporary links with an expiry and a download limit.
- ♻️ **Deduplication** — identical content is stored once per bucket; a copy costs neither disk space nor quota.
- ⏫ **Large files** — chunked uploads that resume after a broken connection.
- 🖼 **Images on the fly** — resize, EXIF rotation and re-encoding to `webp`, `avif`, `jpeg` or `png` at upload.
- ⚡ **CDN-ready** — `Range`, `ETag`, conditional requests and a configurable `Cache-Control`.
- 📊 **Traffic statistics** — egress, ingress and requests per bucket, per day.
- 🖥 **Admin panel** — a standalone web app on its own port: responsive, light and dark theme, English and Russian.

## What runs inside

| Inside the container | Port | What it is |
|---|---|---|
| Application | **9090** | Client API `/v1/…` and file delivery `/o`, `/p`, `/t` |
| Admin panel | **9091** | Web management panel; can be turned off |
| PostgreSQL 18 | — | Metadata; created and migrated on first start, never exposed |
| Scheduler | — | Retention, cleanup, quota recalculation, statistics |

Everything stateful — the database, the files, the signing key — lives in **one volume**, `/var/lib/s5w`.
Backing it up is backing up the whole service.

## Quick start

```bash
docker run -d --name s5w \
  -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 --restart unless-stopped \
  flytachi/s5w:latest
```

1. Open the panel at `http://<host>:9091/` and sign in as `admin` / `change-me`.
2. Create a bucket and issue a token with **full access**.
3. Upload a file:

```bash
curl -H "Authorization: Bearer <token>" -F file=@photo.jpg http://<host>:9090/v1/files
```

The answer carries the file `id`, its `privateUrl` (download with the token) and, for a public file,
its `publicUrl` — ready for `<img src>` or a CDN.

> [!WARNING]
> **Mount the volume.** Without `/var/lib/s5w` the container is disposable: an image update or a
> recreate wipes the database and every file. And `docker compose down -v` deletes the volume too.

> [!TIP]
> Give the container time to stop — `--stop-timeout 45` or `stop_grace_period: 45s`. With Docker's
> default 10 s the database may be killed and recover on the next start.

## Configuration

S5W is configured with environment variables only. None is required to store and serve files;
signing in to the panel needs the first two.

| Variable | Default | What it does |
|---|---|---|
| `ADMIN_LOGIN`, `ADMIN_PASSWORD` | — | The panel account. Without both, signing in is closed for everyone. |
| `PUBLIC_BASE_URL` | _(request address)_ | Base of every address the service returns, e.g. `https://files.example.com`. Set it behind a proxy. |
| `ADMIN_UI` | `true` | `false` — no panel, port 9091 unused. |
| `ADMIN_API` | _(from `ADMIN_UI`)_ | Where `/admin/…` is accepted from: `local` (only the panel) or `public`. |
| `TRUSTED_PROXIES` | `private` | Which proxies to believe in `X-Forwarded-For`; private networks cover any Docker network. |
| `TIME_ZONE` | `UTC` | Time zone of panel statistics when the browser does not send its own. |
| `CACHE_DEFAULT_MAX_AGE` | `86400` | Default `max-age` of the shared (CDN) cache. |
| `CACHE_PRIVATE_MAX_AGE` | `0` | Default `max-age` of the private (browser) cache. |
| `LOG_LEVEL` | `info` | `debug` · `info` · `warning` · `error`. |
| `SERVER_MEMORY_LIMIT` | `512M` | Memory ceiling of an application process. |
| `WINTER_KEY` | _(generated)_ | Key that signs panel sessions and temporary links; kept in the volume. |

Every variable — what it does, when to change it and what happens without it — is in
**[Configuration](https://winterframe.net/apps/s5w/configuration)**.

### docker compose

```yaml
services:
  s5w:
    image: flytachi/s5w:latest
    ports:
      - "9090:9090"   # API and delivery
      - "9091:9091"   # admin panel
    environment:
      ADMIN_LOGIN: admin
      ADMIN_PASSWORD: ${S5W_ADMIN_PASSWORD}
      PUBLIC_BASE_URL: https://files.example.com
    volumes:
      - s5w_data:/var/lib/s5w
    stop_grace_period: 45s
    restart: unless-stopped

volumes:
  s5w_data:
```

### Behind a reverse proxy

The usual layout is two domains and no published ports: `s5.example.com → s5w:9090` (API and
delivery) and `s5w.example.com → s5w:9091` (panel), with TLS on the proxy. Set `PUBLIC_BASE_URL` to
the API domain and allow request bodies of at least 32 MiB. Ready configs for nginx-proxy-manager,
traefik and nginx: **[Behind a reverse proxy](https://winterframe.net/apps/s5w/reverse-proxy)**.

## API

The client API is plain REST with a bearer token; the bucket is decided by the token.

| | |
|---|---|
| **OpenAPI 3.1** | [`docs/en/openapi.yaml`](docs/en/openapi.yaml) · [`docs/ru/openapi.yaml`](docs/ru/openapi.yaml) — for Swagger, Redoc and SDK generators |
| **Postman** | [`docs/en/s5w-api.postman_collection.json`](docs/en/s5w-api.postman_collection.json) · [`docs/ru/…`](docs/ru/s5w-api.postman_collection.json) — every request in order, with examples and checks |
| **Reference** | [Client API](https://winterframe.net/apps/s5w/api) — tokens, files, chunked upload, folders, links, delivery |

```text
GET    /v1/check                 token, bucket and free space
POST   /v1/files                 upload up to 32 MiB
POST   /v1/uploads               chunked upload: start → PATCH chunks → /complete
GET    /v1/files                 list, search, sort
PUT    /v1/files/{id}            rename or move
POST   /v1/files/{id}/link       temporary link
GET    /o/{bucketId}/{id}        public delivery
GET    /p/{id}                   delivery with a token
GET    /t/{token}                temporary link
```

## Operations

```bash
docker logs -f s5w                                     # logs
docker exec s5w su-exec winter php call app status     # components: state, PID, restarts
docker exec -it s5w psql -U s5w -h /run/postgresql s5w # the bundled database
```

- **Backup** — stop the container and archive the volume; restore by unpacking into a new volume.
- **Health** — `GET /v1/check` without a token answers `401` while the application is up.
- **Upgrade** — pull the new image and recreate the container on the same volume. Read the
  [changelog](CHANGELOG.md) first.

Backup and restore commands, a ready healthcheck, monitoring and troubleshooting:
**[Operations](https://winterframe.net/apps/s5w/operations)**.

## Documentation

| | |
|---|---|
| [Overview](https://winterframe.net/apps/s5w) | What it is, when it fits, limits in numbers |
| [Architecture](https://winterframe.net/apps/s5w/architecture) | Processes, storage by hash, deduplication, quotas, schedule |
| [Security](https://winterframe.net/apps/s5w/security) | Tokens, panel sign-in, public files, link signing |
| [Resources](https://winterframe.net/apps/s5w/resources) | Memory, disk, request size and timeouts |
| [Upgrading from 1.0.x](https://winterframe.net/apps/s5w/upgrading) | What changes for existing installations |

## Development

```bash
docker build -t s5w:local .
```

The admin panel (Nuxt, `frontend/`) is built during the image build — no Node.js needed on the
machine. What S5W adds to the [`flytachi/winter`](https://hub.docker.com/r/flytachi/winter) base
image — PostgreSQL, gd (webp, avif), exif — is installed by the scripts in `docker/dependencies/`.

**The panel locally.** With the project mounted into the container (`./:/app`), the local build
`frontend/.output` is served instead of the one built into the image:

```bash
cd frontend && npm install
npm run generate     # → frontend/.output/public, served on :9091
npm run dev          # hot reload on :3000, /api → :9090/admin (needs ADMIN_API=public)
```

`S5W_BACKEND` points `npm run dev` at another backend (default `http://127.0.0.1:9090`).

---

<div align="center">

Built on the [Winter](https://winterframe.net) framework · Swoole · PHP 8.5 · PostgreSQL 18<br />
Licensed under the [Apache License 2.0](LICENSE)

</div>
