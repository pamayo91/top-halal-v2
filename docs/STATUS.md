# Top-Halal V2 — Status

Last updated: 2026-08-25

| Area | Status | Notes |
|---|---|---|
| Architecture | DECIDED | Laravel 13 / PHP 8.4 / Apache / MariaDB / no Docker |
| Starter documentation | DONE | Initial Codex handoff created |
| Legacy SQL inventory | BASELINE | Must be reproduced by migration tooling |
| htaccess redirect inventory | IN PROGRESS | Parser included; application importer not built yet |
| Preprod SSH access | PARTIAL | SSH alias `top-halal-preprod` configured locally; still need deploy path, web vhost/URL details and database credentials/scope |
| Server audit | DONE_WITH_BLOCKERS | Audit saved to `docs/generated/server-audit.txt`; default PHP is 8.1.34, PHP 8.4 exists at `/opt/alt/php84/usr/bin/php` but lacks required extensions/PHAR for Composer |
| Git repository | DONE | Local Git initialized with `origin` set to `git@github-tophalal-codex:pamayo91/top-halal-v2.git`; `main` and `develop` pushed to GitHub |
| Preprod GitHub deploy key | DONE | Dedicated deploy key works with `git@github-tophalal:pamayo91/top-halal-v2.git`; server-side read access tested |
| Preprod deployment strategy | DECIDED | Deploy via SSH and Git pull from `develop`; app checkout must be outside DocumentRoot and Apache must expose only Laravel `public/` |
| Preprod paths | NEEDS_CONFIRMATION | Proposed app path: `/home/meyo5199/top-halal-v2`; proposed Apache DocumentRoot after setup: `/home/meyo5199/top-halal-v2/public`; current subdomain folder detected: `/home/meyo5199/dev.top-halal.fr.meyo5199.odns.fr` |
| Laravel bootstrap | BLOCKED | Must be created by official Composer install after PHP 8.4 CLI has Composer-required extensions, including PHAR, DOM, mbstring, fileinfo and zip; no manual Laravel skeleton |
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
