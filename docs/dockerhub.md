# S5W — file storage and CDN in one container

**Self-hosted file storage for your applications.** S5W accepts files over an API, stores them
and delivers them to users — with CDN headers, temporary links and an admin panel. PostgreSQL,
the scheduler and the panel are inside the image: one `docker run`, no external services.

[Documentation](https://winterframe.net/apps/s5w) ·
[GitHub](https://github.com/Flytachi/s5w) ·
[Changelog](https://github.com/Flytachi/s5w/blob/main/CHANGELOG.md) ·
[API (OpenAPI)](https://github.com/Flytachi/s5w/blob/main/docs/en/openapi.yaml)

## Tags

| Tag | What it is |
|---|---|
| `1.1.3`, `1.1.2`, … | An exact release — **pin one of these in production** |
| `1.1` | The latest `1.1.x` patch release |
| `latest` | The latest release |

Platforms: `linux/amd64`, `linux/arm64`.

## Quick start

```bash
docker run -d --name s5w \
  -p 9090:9090 -p 9091:9091 \
  -e ADMIN_LOGIN=admin -e ADMIN_PASSWORD=change-me \
  -v s5w_data:/var/lib/s5w \
  --stop-timeout 45 --restart unless-stopped \
  flytachi/s5w:1.1
```

1. Open the panel at `http://<host>:9091/` and sign in as `admin` / `change-me`.
2. Create a bucket and issue a token with **full access**.
3. Upload a file:

```bash
curl -H "Authorization: Bearer <token>" -F file=@photo.jpg http://<host>:9090/v1/files
```

> **Mount the volume.** Without `/var/lib/s5w` the container is disposable: an image update or a
> recreate wipes the database and every file.
>
> **Give it time to stop:** `--stop-timeout 45` / `stop_grace_period: 45s`. With the default 10 s
> the database may be killed and recover on the next start.

## What runs inside

| Inside the container | Port | What it is |
|---|---|---|
| Application | **9090** | Client API `/v1/…` and file delivery `/o`, `/p`, `/t` |
| Admin panel | **9091** | Web management panel; `ADMIN_UI=false` turns it off |
| PostgreSQL 18 | — | Metadata; created and migrated on first start, never exposed |
| Scheduler | — | Retention, cleanup, quota recalculation, statistics |

Ports inside the container are fixed; to use another port outside, change only the mapping:
`-p 8080:9090`.

## Volume

Everything stateful lives in **one volume**, `/var/lib/s5w`:

```text
/var/lib/s5w/
  postgresql/        the PostgreSQL cluster — metadata
  buckets/           files, one directory per bucket
  staging/           unfinished chunked uploads
  .runtime_secrets   the generated WINTER_KEY
```

Mount it whole: `buckets/` and `staging/` must be on the same filesystem. Backing up this volume
backs up the whole service — stop the container and archive it.

## Environment variables

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

There are no database settings: the database is bundled. Changes apply when the container is
recreated, not on `docker restart`. Every variable in detail:
[Configuration](https://winterframe.net/apps/s5w/configuration).

## docker compose

```yaml
services:
  s5w:
    image: flytachi/s5w:1.1
    ports:
      - "9090:9090"   # API and delivery
      - "9091:9091"   # admin panel
    environment:
      ADMIN_LOGIN: admin
      ADMIN_PASSWORD: ${S5W_ADMIN_PASSWORD}
      PUBLIC_BASE_URL: https://files.example.com
    volumes:
      - s5w_data:/var/lib/s5w
    healthcheck:
      test: ["CMD-SHELL", "test \"$$(curl -s -o /dev/null -w %{http_code} http://127.0.0.1:9090/v1/check)\" = 401"]
      interval: 30s
      timeout: 5s
      start_period: 30s
      retries: 3
    stop_grace_period: 45s
    restart: unless-stopped

volumes:
  s5w_data:
```

The healthcheck relies on `GET /v1/check` answering `401` without a token while the application
is up — do not use `curl -f` for it.

## Behind a reverse proxy

The usual layout is two domains and no published ports: `s5.example.com → s5w:9090` (API and
delivery) and `s5w.example.com → s5w:9091` (panel), with TLS on the proxy. Set `PUBLIC_BASE_URL`
to the API domain and allow request bodies of at least 32 MiB. Ready configs for
nginx-proxy-manager, traefik and nginx:
[Behind a reverse proxy](https://winterframe.net/apps/s5w/reverse-proxy).

## Upgrading

Pull the new tag and recreate the container **on the same volume** — data, tokens and issued links
are kept. Read the [changelog](https://github.com/Flytachi/s5w/blob/main/CHANGELOG.md) first;
coming from 1.0.x, see [what changes](https://winterframe.net/apps/s5w/upgrading).

## License

[Apache License 2.0](https://github.com/Flytachi/s5w/blob/main/LICENSE). Security issues:
[SECURITY.md](https://github.com/Flytachi/s5w/blob/main/SECURITY.md).
