# Security policy

## Supported versions

Security fixes go into the latest minor release and are published as a new patch image.

| Version | Supported |
|---|---|
| 1.1.x | ✅ |
| 1.0.x | ❌ — upgrade to 1.1 ([what changes](https://winterframe.net/en/apps/s5w/upgrading)) |

Run a pinned tag (`flytachi/s5w:1.1.x`) in production and follow the [changelog](CHANGELOG.md).

## Reporting a vulnerability

**Please do not open a public issue for a security problem.**

Report it privately through GitHub: **Security → Report a vulnerability** in this repository. The
report is visible only to the maintainers.

Helpful to include:

- the S5W version (image tag) and how it is deployed (behind a proxy, `ADMIN_API` mode, …);
- what an attacker can do and what they need for it (no token, a `BASIC` token, a `FULL` token,
  panel access, network position);
- steps or a request sequence that reproduce it.

You will get an answer within a few days. Once the problem is confirmed, a fix is released as a
patch version and the changelog names the issue after the release. Credit is given unless you ask
otherwise.

## Scope

In scope — anything that breaks the model described in
[Security](https://winterframe.net/en/apps/s5w/security), for example:

- reaching a file, bucket or link without the token or link that should be required;
- a token acting outside its bucket, or a `BASIC` token doing what needs `FULL`;
- getting into the admin panel or the admin API without the credentials, or around the login
  lockout;
- forging or extending a temporary link;
- executing uploaded content in the browser on the S5W origin;
- reading or writing files outside the data volume.

Out of scope — behaviour that is documented and intended:

- the bucket root is public: a file uploaded or moved there opens at `/o/…` without a token;
- the admin API is reachable from outside with `ADMIN_API=public` or `ADMIN_UI=false`;
- with `TRUSTED_PROXIES=private`, any host in a private network may set `X-Forwarded-For`;
- S5W speaks plain HTTP — TLS is the job of the proxy in front of it;
- denial of service by sheer volume of requests or uploads.

## Hardening checklist

- Set a long random `ADMIN_PASSWORD` and pass it as a secret, not in a committed compose file.
- Put the panel behind HTTPS and, ideally, an access list or basic auth on the proxy.
- Publish only the ports you need; behind a proxy, publish none.
- Keep `ADMIN_API` local unless automation needs it; then restrict `/admin` at the proxy.
- Narrow `TRUSTED_PROXIES` to your proxy's address if untrusted containers share its network.
- Keep private files in folders with `public: false`, never in the bucket root.
- Protect backups of `/var/lib/s5w` like the data itself: they hold every file and the signing key.
