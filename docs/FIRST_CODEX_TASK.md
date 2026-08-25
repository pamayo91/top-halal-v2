# First Codex Task

You are starting the Top-Halal V2 implementation.

Read `AGENTS.md`, `docs/PROJECT.md`, `docs/STATUS.md`, `docs/DEPLOYMENT.md`, and `docs/MIGRATION_WORDPRESS.md`. Do not read unrelated module specs yet unless needed.

## Objective
Bootstrap a Laravel 13 / PHP 8.4 project for Apache + MariaDB on the existing non-Docker environment, without developing the final visual design yet.

## Required work
1. Review the output of `scripts/server-audit.sh` if `server-audit.txt` exists. Report missing/incorrect requirements before making destructive server changes.
2. Initialize the Laravel application and keep public web root at `public/`.
3. Configure `.env.example` with placeholders only; never copy real secrets into Git.
4. Set up MariaDB application connection and a second **read-only** legacy WordPress connection via environment variables.
5. Add the initial health route and a minimal neutral Blade response so the preprod deployment can be verified.
6. Add test tooling (Pest/PHPUnit as appropriate) and Playwright scaffolding for E2E tests against the preprod base URL.
7. Create a deterministic Artisan command `legacy:inventory` that queries the imported legacy WordPress database and produces a concise machine-readable + human-readable inventory: table names/counts relevant to migration, post types/statuses, users, comments by approval/type, ListingPro reviews, core taxonomies, media, SEO/plugin data presence, and anomalies useful for planning. It must not dump PII or entire content bodies into reports.
8. Run `scripts/htaccess_inventory.py legacy/redirects.htaccess --out docs/redirect-inventory` and review whether the parser captures the observed rule forms. Do not build the final redirect manager yet.
9. Create/update `docs/STATUS.md`, `docs/CHANGELOG.md` and any deployment notes needed.
10. Commit only after relevant tests pass and `git diff`/`git status` confirm no secrets/dumps are staged.

## Validation
- Laravel boots successfully on preproduction under Apache.
- Health endpoint returns success.
- App DB connection works.
- Legacy DB connection is read-only and `legacy:inventory` runs successfully.
- A minimal Playwright smoke test opens preproduction and verifies the health/public page as a real browser.
- Browser console has no unexplained error on the minimal public page.
- Documentation reflects actual results, not assumptions.

## Constraints
- No Docker.
- No WordPress runtime dependency.
- Do not read the SQL dump into model context.
- Do not install a heavy JS frontend framework.
- Do not build final UI yet.
- Do not make production changes.
- Ask the user only when credentials/hostnames or an irreversible server decision truly cannot be inferred or safely tested.
