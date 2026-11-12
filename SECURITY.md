# Security Policy

## Reporting a vulnerability

Do not open a public issue for a security problem.

1. Use GitHub's private vulnerability reporting: **Security → Report a vulnerability** in this repository.
2. Include affected version (`VERSION` or the release tag), a reproduction, and the impact you observed.
3. Allow reasonable time for a fix before disclosure. Fixes ship in the latest release; security updates are not provided for older tags.

## Supported versions

Only the latest release receives security fixes. `main` contains unreleased work and is not a supported production target.

| Version | Supported |
|---------|-----------|
| 1.17.x  | Yes |
| < 1.17  | No |

## Scope

In scope:

- Authentication or authorization bypass in plugin routes.
- Cross-site scripting through map names, labels, node `meta`, webhooks, or rendered SVG.
- SQL injection, unsafe deserialization, or path traversal in import, export, setup, or release scripts.
- Credential or secret disclosure from configuration, logs, or diagnostics.
- Server-side request forgery in alert, webhook, or lookup paths.

Out of scope: vulnerabilities in LibreNMS itself, the LibreNMS Docker image, or a host that exposes backup files, `.env`, logs, or SQL dumps under the web root.

## Security model

- All routes sit under LibreNMS `web` + `auth`, except the three public probes (`/health`, `/ready`, `/live`).
- Mutations and operational detail endpoints require a LibreNMS administrator; reads are available to authenticated users because the plugin has no per-map ownership model.
- Per-user map favorites are the one non-admin mutation: they change only the caller's own row.
- An embed token (`WEATHERMAPNG_EMBED_TOKEN`) authorizes only the embed, JSON, live, and SSE read routes.
- Do not expose the plugin checkout's `output/` directory, configuration overrides, or logs as static content.

See [docs/API.md](docs/API.md) for the per-route authorization tiers and [docs/INSTALL.md](docs/INSTALL.md#configuration-and-precedence) for secret handling.
