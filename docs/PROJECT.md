# Top-Halal V2 — Project Brief

## Goal
Rebuild Top-Halal from scratch as a fast, SEO-first Laravel application while migrating useful WordPress/ListingPro data and preserving historical SEO value.

## Existing-data inventory (from pre-project analysis)
Approximate legacy inventory to reconcile again with scripts before migration:
- 7,704 restaurant/listing records; ~7,633 published.
- 121 published articles.
- 90 published pages.
- 2,239 media/attachments.
- 545 user accounts.
- 2,003 legacy location terms.
- 22 restaurant/category terms.
- 13 listing features.
- 84 ListingPro review records.
- 20,183 WordPress comment rows, of which ~19,454 are spam; ~713 approved and 15 pending real comments were observed.
- ~63 legacy contents contain Visual Composer `[vc_*]` shortcodes.
These numbers are a baseline only. The migration command must produce authoritative current counts from the imported legacy database.

## Hosting
- Dedicated server.
- Apache.
- PHP 8.4.
- MariaDB 11.4.13.
- SSH available.
- Cron available.
- No Docker.

## Preproduction data sources
- Laravel V2 uses a distinct MariaDB database: `meyo5199_top_halal_v2`.
- Legacy WordPress data is read directly from the existing `meyo5199_th` database through a dedicated SELECT-only user.
- Legacy WordPress table prefix: `tp_`.
- The legacy SQL dump under `legacy/` is a safety/reference snapshot only and is not the primary source for now.
- No WordPress content import has started.

## Current preproduction status
- Public preproduction URL: `https://dev.top-halal.fr/`.
- Apache DocumentRoot points to Laravel `public/`.
- HTTPS, HTTP-to-HTTPS redirect, `/health`, cookies/session, noindex headers, robots blocking and sensitive path protections are validated.
- WordPress/ListingPro inventory has been generated through read-only legacy access.
- The next migration step is a reviewed sample only, not a full import.
- The first sample must stay limited to 10 restaurants, 5 articles, a few pages, threaded comments and restaurant reviews until validation approves broader migration.

## Bootstrap requirement
The Laravel application must be initialized through the official Composer workflow on the appropriate preproduction/development environment after the preproduction server audit has been run and reviewed. Do not create or commit a hand-made Laravel source skeleton to work around missing PHP or Composer on the local Windows workstation.

Before bootstrapping Laravel, collect preproduction SSH/deployment details, run `scripts/server-audit.sh` on the server, verify PHP/extensions, Apache/modules, Composer, MariaDB, Node/npm if needed, Git, optional Redis, filesystem permissions and Apache DocumentRoot, then save the result as `docs/generated/server-audit.txt`.

## Application shape
One Laravel application containing:
- public server-rendered website;
- administration/back-office;
- restaurant directory/search;
- editorial pages/articles;
- reviews/comments/moderation;
- user/restaurateur accounts and claims;
- native advertising/sponsorship;
- redirect manager;
- SEO/sitemaps/structured data;
- email/notification system;
- scheduled AI-assisted news workflow with switchable providers.

## Primary delivery principle
Data/migration and automated validation come before final visual polish. Build the migration and reconciliation tooling before treating the frontend as production-ready.
