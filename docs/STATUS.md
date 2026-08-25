# Top-Halal V2 — Status

Last updated: 2026-08-25

| Area | Status | Notes |
|---|---|---|
| Architecture | DECIDED | Laravel 13 / PHP 8.4 / Apache / MariaDB / no Docker |
| Starter documentation | DONE | Initial Codex handoff created |
| Legacy SQL inventory | BASELINE | Must be reproduced by migration tooling |
| htaccess redirect inventory | IN PROGRESS | Parser included; application importer not built yet |
| Preprod SSH access | PARTIAL | SSH alias `top-halal-preprod` configured locally; still need deploy path, web vhost/URL details and database credentials/scope |
| Server audit | BLOCKED | Must run `scripts/server-audit.sh` directly on preprod and save output to `docs/generated/server-audit.txt` before Laravel bootstrap |
| Git repository | WAITING | Local Git initialized with `origin` set to `git@github.com:pamayo91/top-halal-v2.git`; local branches `main` and `develop` exist; push is blocked until this workstation has GitHub SSH access |
| Preprod GitHub deploy key | WAITING | Dedicated deploy key generated on preprod; public key must be added manually to GitHub before server-side clone/pull |
| Preprod deployment strategy | DECIDED | Deploy via SSH and Git pull from `develop`; app checkout must be outside DocumentRoot and Apache must expose only Laravel `public/` |
| Laravel bootstrap | BLOCKED | Must be created by official Composer install in the appropriate environment after server audit; no manual Laravel skeleton |
| Legacy DB import connection | TODO | Read-only legacy DB connection |
| Migration: restaurants | TODO | |
| Migration: taxonomies/geography | TODO | |
| Migration: articles/pages | TODO | |
| Migration: comments | TODO | No spam; no links in new comments |
| Migration: reviews | TODO | ListingPro reviews separate from comments |
| Redirect manager | TODO | Exact + regex/query rules + htaccess import |
| Public design system | TODO | Simple, modern, mobile-first, CWV-first |
| Restaurant frontend | TODO | |
| Search/geolocation | TODO | MariaDB spatial capabilities to be validated in implementation |
| Accounts/claims | TODO | |
| Admin/moderation | TODO | |
| Advertising | TODO | Native/sponsored placements |
| Email | TODO | Provider/config/queue/logging/templates |
| AI provider abstraction | TODO | OpenAI + alternative providers |
| Automated news workflow | TODO | Disclosure configurable |
| SEO validation | TODO | |
| Playwright E2E | TODO | Must exercise preprod as real users |
| PageSpeed/CWV gates | TODO | Representative templates |
| Production cutover | TODO | Full migration + delta + URL/redirect validation |
