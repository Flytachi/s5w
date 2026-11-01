# S5W — File Store & CDN

A file store in one container: an upload API, file delivery and an admin panel.
Swoole (PHP 8.5) with a **bundled PostgreSQL 18**. Image: `flytachi/s5w:latest`.

- 📦 **One container, zero dependencies.** The database, the scheduler and the panel are already inside: `docker run` and it works.
- 🗂 **Buckets and folders** — own quotas, access tokens, retention and cache policy.
- 🔗 **Three delivery channels** — public links, access by token and signed temporary links with a download limit.
- ♻️ **Deduplication** — identical files are stored once; a copy takes no disk space.
- ⏫ **Large files** — chunked uploads that resume after a break.
- 🖼 **Images on the fly** — resize and re-encode to `webp` / `avif` / `jpeg` / `png` at upload time.
- ⚡ **CDN-ready** — `Range`, `ETag`, conditional requests and a configurable `Cache-Control`.
- 📊 **Traffic statistics** — per bucket and per day, in the viewer's own timezone.
- 🖥 **Admin panel** — a standalone SPA on its own port: responsive, light and dark theme, English and Russian.

## What it runs

No external database, Redis or other services are **needed**: everything starts inside one container.

| Inside the container | Port | What it is |
|---|---|---|
| PostgreSQL 18 | — | The bundled database. Starts by itself, creates and migrates itself on the first start. Not exposed. |
| Application | **9090** | Client API (`/v1/…`) and file delivery (`/o`, `/p`, `/t`). |
| Admin panel | **9091** | Web management panel. Can be turned off. |
| Scheduler | — | Scheduled maintenance: retention, cleanup, statistics. |

All data — the database, the files, the session key — lives in **one volume**, `/var/lib/s5w`.

---

## 1. Quick start

**Try it** (no volume — everything is gone when the container is recreated):

```bash
docker run -d --name s5w -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  flytachi/s5w:latest
```

**Recommended:**

```bash
docker run -d --name s5w -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 \
  --restart unless-stopped \
  flytachi/s5w:latest
```

Then:

1. Open the panel at `http://<host>:9091/` and sign in as `admin` / `change-me`.
2. Create a bucket and issue a token for it.
3. Use that token with the API at `http://<host>:9090/v1/…`:
   ```bash
   curl -H "Authorization: Bearer <token>" http://<host>:9090/v1/check
   ```

> **Without the `/var/lib/s5w` volume the container is disposable:** an image update or a
> recreate wipes the database and every file. And separately: **`docker compose down -v`
> deletes everything at once.**

Ports inside the container are fixed. For another port outside, change only the
publishing: `-p 8080:9090`.

---

## 2. Advanced setup

### docker run

```bash
docker run -d --name s5w -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -e PUBLIC_BASE_URL=https://files.example.com \
  -e TIME_ZONE=Asia/Tashkent \
  -e LOG_LEVEL=warning \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 \
  --restart unless-stopped \
  flytachi/s5w:latest
```

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
      ADMIN_PASSWORD: change-me
      PUBLIC_BASE_URL: https://files.example.com
      TIME_ZONE: Asia/Tashkent
      LOG_LEVEL: warning
    volumes:
      - s5w_data:/var/lib/s5w
    # The application stops first (up to 35 s), then PostgreSQL. Docker's default
    # 10 s is not enough: after a SIGKILL the database recovers on the next start.
    stop_grace_period: 45s
    restart: unless-stopped

volumes:
  s5w_data:
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `PUBLIC_BASE_URL` | _(request address)_ | Base address of the links the service returns: file addresses, temporary links. With the scheme, e.g. `https://files.example.com`. Optional, but needed behind a proxy on its own domain: without it the address is guessed from the request, and the panel warns about it. |
| `TIME_ZONE` | `UTC` | Timezone when the browser does not report its own. |
| `CACHE_DEFAULT_MAX_AGE` | `86400` | Default `max-age` for public delivery, seconds. Each bucket and folder can override it in the panel. |
| `CACHE_PRIVATE_MAX_AGE` | `0` | Same for private caching. |
| `LOG_LEVEL` | `info` | `debug` · `info` · `warning` · `error`. Empty — no logs. |
| `LOG_OUTPUT` | `auto` | `auto` · `stdout` · `stderr` · `file`. |
| `SERVER_MEMORY_LIMIT` | _(from the image)_ | Memory ceiling of a worker, e.g. `512M`. |
| `SERVER_REQUEST_TIMEOUT` | `30` | Request processing timeout, seconds. Does not cut slow downloads. |
| `WINTER_KEY` | _(generated)_ | Session signing key. Generated on the first start and kept in the volume. Set it only if you want to manage it yourself. |

There are no database connection settings, and none are needed: the database is bundled and the only one.

### The `/var/lib/s5w` volume

```
/var/lib/s5w/
  postgresql/        the PostgreSQL cluster
  buckets/           files, one directory per bucket
  staging/           unfinished chunked uploads
  .runtime_secrets   the generated WINTER_KEY
```

- Mount **one** volume as a whole. `buckets/` and `staging/` must be on the same
  filesystem: a chunked upload finishes with a rename, and a rename is atomic only
  within one filesystem.
- The PostgreSQL major version is tied to the volume. Moving to a new major version
  requires `pg_upgrade`.

---

## 3. Running with the admin panel

The panel is on by default and runs on port **9091**. It talks to the backend
**inside the container**: the browser only reaches the panel's address, so neither
CORS nor separate access to `/admin` is needed.

### Panel variables

| Variable | Default | Description |
|---|---|---|
| `ADMIN_LOGIN` | — | **Required.** Without it, signing in to the panel is closed. |
| `ADMIN_PASSWORD` | — | **Required.** Without it, signing in to the panel is closed. |
| `ADMIN_UI` | `true` | `false` — no panel: the container only stores and serves files, port 9091 is not needed. |
| `ADMIN_API` | _(from `ADMIN_UI`)_ | Where the admin API `/admin/…` is accepted from. `local` — only from inside the container, i.e. from its own panel; a 404 from outside. `public` — from anywhere, e.g. for scripts that create buckets. Unset: `local` with the panel on, `public` without it. |
| `TRUSTED_PROXIES` | `private` | Which proxies to believe in `X-Forwarded-For`: the login lockout after failed attempts counts the client's IP by it. `private` — private networks, i.e. any docker network: fits nginx-proxy-manager, traefik and the like. Also accepts comma-separated IPs or CIDRs, or `none`. |

| How it runs | Panel | `/admin` from outside |
|---|---|---|
| by default | ✅ | 404 |
| `ADMIN_API=public` | ✅ | ✅ |
| `ADMIN_UI=false` | — | ✅ |

### Behind a reverse proxy (nginx-proxy-manager, traefik)

A typical layout — two domains, no ports published:

| Domain | Points to | What |
|---|---|---|
| `s5w.example.com` | `s5w:9091` | admin panel |
| `s5.example.com` | `s5w:9090` | API and delivery |

```yaml
services:
  s5w:
    image: flytachi/s5w:latest
    environment:
      ADMIN_LOGIN: admin
      ADMIN_PASSWORD: change-me
      PUBLIC_BASE_URL: https://s5.example.com   # links in the panel point to the app domain
    volumes:
      - s5w_data:/var/lib/s5w
    networks: [proxy]
    stop_grace_period: 45s
    restart: unless-stopped

volumes:
  s5w_data:

networks:
  proxy:
    external: true   # the network the proxy lives in
```

- `PUBLIC_BASE_URL` is needed: otherwise file addresses in the panel are built from the panel's domain.
- `TRUSTED_PROXIES` needs no setting: a proxy in a docker network is already trusted.
- Request body size: nginx-proxy-manager allows 2000 MB by default, which is enough.
  Plain nginx allows 1 MB — raise it: `client_max_body_size 32m;`. The panel uploads
  files over 16 MiB in chunks, so 32 MB covers any size.
- The panel's domain is worth closing further on the proxy side: an access list, basic auth.

### Without the panel

```bash
docker run -d --name s5w -p 9090:9090 \
  -e ADMIN_UI=false \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 --restart unless-stopped \
  flytachi/s5w:latest
```

In this mode the admin API is open (`/admin/…` on 9090): buckets and tokens are
created by requests to it.

---

## Operations

```bash
docker logs -f s5w                                         # logs
docker exec -it s5w sh                                     # shell
docker exec s5w su-exec winter php call app status        # components: state, PID, restarts
docker exec s5w psql -U s5w -h /run/postgresql s5w         # psql into the bundled database
docker exec s5w su-exec winter php call db migrate         # create missing tables
```

Maintenance runs inside the container on a schedule:

| When | What |
|---|---|
| every 20 s | flush traffic counters to the database |
| every 5 min | delete files whose folder retention expired |
| hourly | delete abandoned chunked uploads |
| 03:15 | delete expired and revoked temporary links |
| 03:30 | delete content records nothing refers to |
| Sun 03:45 | delete files on disk the database does not know |
| 04:00 | delete directories of deleted buckets |
| 04:10 | delete traffic statistics older than a year |
| 04:20 | recompute used space per bucket |

---

## Features

- **Buckets** — isolated spaces with their own quota, cache policy and access tokens.
- **Folders** — optional. A folder has a visibility and a retention (a day to a year);
  when it expires, files are deleted by themselves.
- **Deduplication** — identical content is stored once per bucket (by SHA-256).
  A duplicate costs a database row, not disk space.
- **Chunked uploads** — for large files: chunks of 4–16 MiB, resumable after a break.
  Abandoned uploads are deleted after 24 h.
- **Image processing** at upload: resize, EXIF rotation, re-encode to `webp`, `jpeg`,
  `png` or `avif`.
- **Three delivery channels:** `/o` — public, `/p` — by token, `/t` — signed temporary
  links with an expiry and an optional download limit. All support `Range`, `ETag` and
  conditional requests.
- **Traffic statistics** — egress, ingress and request counts per bucket.

---

## Good to know

- **Without `ADMIN_LOGIN` and `ADMIN_PASSWORD` nobody can sign in to the panel** — by design.
- **`WINTER_KEY` lives in the volume.** Lose the volume and every panel session becomes invalid.
- **Give the container time to stop:** `--stop-timeout 45` / `stop_grace_period: 45s`.
  With the default 10 s the database may be killed and recover on the next start.
- **Migrations do not change existing tables.** `call db migrate` creates missing
  tables but does not add columns to existing ones — such an `ALTER` is written by hand.
  That is why migrations run only when the database is created, not on every start.
- **Public addresses `/o/{bucketId}/{slug}`** open without authorization, but only for
  files marked public.

---

## Development

**Your own image:**

```bash
docker build -t s5w:local .
```

The panel (Nuxt, `frontend/`) is built during the image build; no Node.js needed on the machine.
What s5w adds to the base image [`flytachi/winter`](https://hub.docker.com/r/flytachi/winter)
— PostgreSQL, gd (webp, avif), exif — is installed by the scripts in `docker/dependencies/`.

**The panel locally.** With the project mounted into the container (`./:/app`), the local
build `frontend/.output` is served instead of the one built into the image. Build it on the host:

```bash
cd frontend && npm install
npm run generate     # → frontend/.output/public, served on :9091
npm run dev          # or with hot reload on :3000, /api → :9090/admin
```

`S5W_BACKEND` points `npm run dev` at another backend (default `http://127.0.0.1:9090`).
The dev server reaches `/admin` from outside the container, so the backend needs
`ADMIN_API=public` for it.
