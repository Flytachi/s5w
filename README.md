# S5W — File Store & CDN

Single-container file store on **Swoole** (PHP 8.5) with a **bundled PostgreSQL 18**.
Everything — application, database, scheduler — runs in one container: s6 starts the
database and then the application, and the application supervises its own HTTP server
and scheduler. Built on the [`flytachi/winter`](https://hub.docker.com/r/flytachi/winter) runtime image.

- **Admin panel:** `http://<host>:9091/` — its own port (`UI_PORT`); the old pages at `http://<host>:9090/admin/ui` still work while the move goes on
- **Client API:** `http://<host>/v1/…`
- **Delivery:** `/o` (public) · `/p` (by token) · `/t` (signed temporary links)
- **Image:** `flytachi/s5w:latest`

Unlike its predecessor there is **no external-database mode**. Blobs live on the
container's disk and the database is an index over them, so both sit in one volume.
`WINTER_KEY` is auto-generated if not provided.

> ## ⚠️ Read this first — data & volume
> Without a volume the container is **ephemeral**: recreating it (image update,
> `--force-recreate`, host reboot without a restart policy) **wipes everything**.
> Mount **one** volume for any real use:
>
> | Mount | Keeps |
> |---|---|
> | `/var/lib/s5w` | database, blobs, upload staging, generated `WINTER_KEY` |
>
> Its layout:
>
> ```
> /var/lib/s5w/
>   postgresql/   the cluster            (postgres:postgres, 0700)
>   buckets/      blobs, one dir per bucket
>   staging/      partial chunked uploads
> ```
>
> `buckets/` and `staging/` must stay on the same filesystem: finishing a chunked
> upload is a `rename()`, and rename is only atomic within one filesystem. Splitting
> them across volumes turns every completed upload into a full copy of the file.
>
> **`docker compose down -v` destroys the database and every stored file at once.**

---

## `docker run`

**Quick try (no persistence — for testing only):**
```bash
docker run -d --name s5w -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  flytachi/s5w:latest
# http://localhost:9091/  → admin / change-me
```

**Persistent (recommended):**
```bash
docker run -d --name s5w -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 \
  --restart unless-stopped \
  flytachi/s5w:latest
```

A different port is one variable — it drives both the published port and the port
Swoole binds:

```bash
docker run -d --name s5w -p 8080:8080 -e SERVER_PORT=8080 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w flytachi/s5w:latest
```

---

## `docker compose`

**Minimal (no persistence — for testing only):**
```yaml
services:
  s5w:
    image: flytachi/s5w:latest
    ports:
      - "9090:9090"
      - "9091:9091"
    environment:
      ADMIN_LOGIN: admin
      ADMIN_PASSWORD: change-me
```
```bash
docker compose up -d
```

**Persistent (recommended):**
```yaml
services:
  s5w:
    image: flytachi/s5w:latest
    ports:
      - "9090:9090"
      - "9091:9091"
    environment:
      ADMIN_LOGIN: admin
      ADMIN_PASSWORD: change-me
      TIME_ZONE: Europe/Amsterdam
      LOG_LEVEL: warning
    volumes:
      - s5w_data:/var/lib/s5w
    # The application stops first (up to 35 s), then PostgreSQL. The default 10s is
    # not enough; on SIGKILL the database needs recovery.
    stop_grace_period: 45s
    restart: unless-stopped

volumes:
  s5w_data:
```
```bash
docker compose up -d
```

On the first start the container initialises the cluster, creates the role and
database and runs the migrations. Later starts skip all of it.

---

## Environment variables

| Variable | Default | Notes |
|---|---|---|
| `ADMIN_LOGIN` | — | **Required.** Empty ⇒ panel login denied (fail-closed). |
| `ADMIN_PASSWORD` | — | **Required.** As above. |
| `SERVER_PORT` | `9090` | Port Swoole binds. Publish the same number. |
| `ADMIN_UI` | `true` | `false` ⇒ no admin panel: the container only stores and serves files. |
| `UI_PORT` | `9091` | Port of the admin panel. Publish the same number. |
| `WINTER_KEY` | _auto_ | Session signing key. Generated on first start and persisted to the volume. |
| `ADMIN_API` | `public` | `local` ⇒ the admin API (`/admin/…`) answers only on loopback — to a frontend in the same container — and is a 404 from anywhere else. |
| `TRUSTED_PROXIES` | `private` | Reverse proxies in front of the container: comma-separated IPs, CIDRs, `private` (10/8, 172.16/12, 192.168/16, fc00::/7 — any docker network) or `none`. Only behind them (and loopback) is `X-Forwarded-For` read for the client address — the login lockout counts by it. The default fits a proxy in a docker network (nginx-proxy-manager, traefik); set exact addresses or `none` if clients reach the published ports from a private LAN directly. |
| `PUBLIC_BASE_URL` | _(request host)_ | Base URL used in returned links. Include the scheme; that host must route here. Optional, but without it the address is a guess from the request and the panel shows a notice — set it when the API and delivery are behind a proxy on their own domain. |
| `CACHE_DEFAULT_MAX_AGE` | `86400` | Default `max-age` for public delivery, per bucket override in the panel. |
| `CACHE_PRIVATE_MAX_AGE` | `0` | Same for `private` visibility. |
| `TIME_ZONE` | `UTC` | Fallback when the browser does not report one. |
| `DEBUG` | `false` | **Keep `false` in production** — debug responses include stack traces. |
| `LOG_LEVEL` | `info` | `debug\|info\|warning\|error\|…`; empty disables logging. |
| `LOG_OUTPUT` | `auto` | `auto\|stdout\|stderr\|file`. |
| `SERVER_PROFILE` | `balance` | `stable\|balance\|performance\|stress` — worker count and concurrency. |
| `SERVER_MEMORY_LIMIT` | _(profile)_ | Memory ceiling per worker. |
| `SERVER_REQUEST_TIMEOUT` | `30` | Seconds. Applies to the request, not to a slow download. |
| `DEV` | `false` | `true` ⇒ opcache off and hot reload. Development only. |

Database connection settings are **not** environment variables: the bundled cluster
is the only one, and its credentials live in `Main\Configuration\MainDbConfig`.

---

## Build the image yourself

```bash
docker build -t s5w:local .
docker run -d -p 9090:9090 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w s5w:local
```

The base image brings Swoole, pcntl, pgsql and opcache. What s5w adds — the
PostgreSQL server, gd (webp, avif) and exif — is installed by the scripts in
`docker/dependencies/`, which run in filename order during the build.

---

## Developing the panel

The panel is a Nuxt app in `frontend/`, built to static files the container serves on
`UI_PORT`. The image builds it itself; with the project mounted into the container
(`./:/app`), the mounted `frontend/.output` is what gets served, so build it on the host:

```bash
cd frontend && npm install
npm run generate          # → frontend/.output/public, served on :9091
npm run dev               # or: hot reload on :3000, /api proxied to :9090/admin
```

`S5W_BACKEND` points `npm run dev` at another backend (default `http://127.0.0.1:9090`).

---

## Common operations

```bash
docker logs -f s5w                                       # logs
docker exec -it s5w sh                                   # shell
docker exec s5w su-exec winter php call app status      # components: state, PID, restarts
docker exec s5w psql -U s5w -h /run/postgresql s5w       # psql on the bundled DB
docker exec s5w su-exec winter php call db migrate       # create missing tables
docker exec s5w su-exec winter php call script main.Cmd.SweepCmd retention
```

Maintenance runs inside the container on a schedule:

| When | Task |
|---|---|
| every 20 s | flush traffic counters to `bucket_traffic` |
| every 5 min | delete files whose folder retention expired |
| hourly | drop abandoned chunked uploads |
| 03:15 | remove expired and revoked share links |
| 03:30 | remove blob rows nothing references |
| Sun 03:45 | remove blob files on disk with no database row |
| 04:00 | remove bucket directories of deleted buckets |
| 04:10 | drop traffic rows older than a year |
| 04:20 | recompute `used_bytes` per bucket |

---

## What it does

- **Buckets** — isolated namespaces, each with its own quota (100 MB by default),
  cache policy and access tokens.
- **Folders** — optional, with retention (`DAY` … `YEAR`); files are removed when
  their retention expires.
- **Deduplication** — identical content is stored once per bucket, keyed by SHA-256.
  A duplicate costs a database row, not disk.
- **Chunked uploads** — for large files, chunks of 4–16 MiB, resumable, staging
  discarded after 24 h.
- **Image processing** — resize, EXIF rotation and re-encode to `webp`, `jpeg`,
  `png` or `avif` at upload time.
- **Three delivery channels** — `/o` public, `/p` by access token, `/t` signed links
  with an expiry and an optional download limit. All support `Range`, `ETag` and
  conditional requests.
- **Traffic statistics** — egress, ingress and request counts per bucket, stored
  hourly in UTC and shown in the viewer's timezone.

---

## Notes & gotchas

- **The panel needs `ADMIN_LOGIN` + `ADMIN_PASSWORD`** — without them login is refused
  by design.
- **`WINTER_KEY` survives only with the volume.** Lose it and every issued session
  cookie stops validating.
- **Migrations do not alter existing tables.** `call db migrate` builds `CREATE`
  statements from the entity declarations and never reads the live schema: it creates
  what is missing and reports the rest as `EXIST`. Adding a column to an entity will
  **not** add it to a table that already exists — write that `ALTER` yourself.
  This is why migrations run only when the database is created, not on every start.
- **The major PostgreSQL version is pinned to the volume.** Bumping `postgresql18`
  in `docker/dependencies/10-postgres.sh` without `pg_upgrade` leaves the server
  unable to open the existing data directory.
- **Give the container time to stop.** The application stops its components within
  35 s, and PostgreSQL shuts down after it; the Docker default of 10 s can cut that
  short and force crash recovery on the next start. Use `stop_grace_period: 45s`
  (`--stop-timeout 45`).
- **Public URLs `/o/{bucketId}/{slug}`** need no authentication — only files marked
  public are served there.
- **`PUBLIC_BASE_URL`** only rewrites the host in returned links; that host must
  proxy to this container, and the value must include the scheme.
