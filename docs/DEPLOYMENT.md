# Deployment — Preproduction & Production

Le registre vérifié à consulter avant toute opération de production est
[`docs/PRODUCTION_RUNBOOK.md`](PRODUCTION_RUNBOOK.md). Il sépare les constats
techniquement confirmés des paramètres qui restent à vérifier; ne complétez pas
un chemin, une crontab ou un runtime par analogie avec la préproduction.

## Principles
- No Docker.
- Deploy preproduction over SSH using Git directly on the server; rsync is not the primary deployment method.
- Apache DocumentRoot must point to Laravel's `public/` directory.
- The Git checkout must live outside any publicly accessible directory.
- Never expose project root, `.env`, `storage`, legacy dumps, SSH material or Git metadata through the web server.
- Production/preproduction secrets live in server-side environment configuration, never Git.
- Production is active. A production deployment remains an explicit, owner-approved promotion; no deployment in this repository update is authorised by this document.

## Git Strategy
- Remote repository: private GitHub repository at `git@github.com:pamayo91/top-halal-v2.git`.
- Codex workstation remote URL: `git@github-tophalal-codex:pamayo91/top-halal-v2.git`.
- Preproduction server remote URL: `git@github-tophalal:pamayo91/top-halal-v2.git`.
- `main`: stable branch deployed to production.
- `develop`: preproduction integration branch.
- `feature/...`: temporary branches for significant work; merge into `develop` only after validation.
- Codex workflow: edit code, run targeted local tests, commit, push to GitHub, SSH to preproduction, pull `develop`, run deployment steps, then validate preproduction with browser/Playwright.
- A preproduction revision considered valid for production must always be
  reachable from `origin/develop` before validation is accepted. Never retain
  durable development commits only in the server checkout: the mandatory order
  is local commit, GitHub push, then preproduction pull/deployment. Before a
  production-readiness audit, verify `git merge-base --is-ancestor HEAD
  origin/develop` and `git rev-parse HEAD = git rev-parse origin/develop`.

## Post-production maintenance and selective promotion

For an isolated production fix, branch from stable `main` (`fix/...` or
`hotfix/...`), keep the diff limited to that correction and add its necessary
tests. Integrate the correction into `develop`, deploy it to `dev.top-halal.fr`,
run targeted checks and browser validation where relevant, then run the full
`composer test:regression` gate. A failure returns the fix to the same loop.

Only after explicit owner validation may the exact tested correction be promoted
to `main`. Use a reviewed merge or a reviewed cherry-pick of the precise commit;
do not merge `develop` wholesale when it contains unrelated, unfinished or
unapproved work. Inspect the promoted diff and deploy `main`, then confirm the
deployed commit, site response and `/health` where applicable, changed flow,
new HTTP 500s, pertinent Laravel logs and frontend console/network errors.
For redirects, also check old URL, expected 301/410, final destination and no
chain/loop; for robots/sitemap/routes/assets, inspect the response actually
served by production.

## Production data, storage and migrations

The preproduction and production databases are independent. A normal code
deployment must never copy, replace, merge or synchronise preproduction data
or individual rows into production. Production BO changes are production data
and the source of truth after launch; a future code deployment must not erase
them. Likewise, do not globally synchronise preproduction storage to production
or delete, replace or reset production uploads during a Git deployment.

Deploy a tested schema migration with its code, then execute that same migration
in production in a controlled manner (normally `artisan migrate --force`), so
the schema changes in place while existing production data remains. Test data
from preproduction never moves with it. Before a data-changing migration or
script, document affected tables/data, predicted creates/updates/deletes when
calculable, idempotence, interaction with BO edits, backup/rollback plan,
preproduction evidence and necessary human approval. Prefer create-if-absent
for new BO-managed settings. `migrate:fresh`, global reseeds, restores, legacy
reimports and global rebuilds are not deployment operations and retain the
two-confirmation safeguard in `AGENTS.md`.

Laravel cache maintenance is distinct from business data: use only applicable
cache commands. Clear route cache after route changes; do not route-cache this
application while closure routes exist.

No production infrastructure command is inferred here. The repository documents
the production application-root and Cron model, but the production SSH alias,
DocumentRoot confirmation, database identifier and current user-crontab entries
still require an authoritative operational record before being used or changed.

## Required Manual GitHub Actions
- Confirm whether branch protection is desired for `main` and `develop`.
- Confirm who may merge `develop` into `main`; production promotion must stay explicit.

## Target deployment flow
1. SSH to preproduction using the saved alias `top-halal-preprod`.
2. Before updating Git or running migrations, run `artisan regression:snapshot --json` and retain its `run_id` outside Git for this deployment.
2. Enter the non-public application directory.
3. `git fetch --prune` and update the working tree from `origin/develop`.
4. `composer install` when dependencies changed; use the preproduction-safe flags decided after server audit.
5. Run Laravel migrations when needed, with preproduction safeguards.
6. Build frontend assets when needed. Run `/opt/alt/php84/usr/bin/php artisan filament:assets` after Composer install (and before any BO validation) on both preproduction and production: Filament CSS, JavaScript and fonts are vendor-published and are not part of Vite's `public/build/` output.
7. Run Laravel optimization/cache commands appropriate for preproduction.
8. Restart queue workers when workers exist and code affecting jobs changed.
9. Clear the route cache whenever `routes/` changes; this project contains closure routes and must not route-cache them. Run health check and targeted smoke/E2E tests against the preproduction URL.
10. Run `composer test:regression` from the workstation after deployment with `PREPROD_REGRESSION_SNAPSHOT_ID` set to that pre-deployment run ID. It blocks on count losses introduced during this deployment, dynamic database/media/relation integrity failures, HTTP 500, Playwright console/network failures or new Laravel exceptions.
11. If validation fails, stop and fix before considering deployment complete.

## Frontend build and test safety

The preproduction SSH environment has no Node.js executable in PATH. After the Git deployment, build Vite assets with the approved workstation runtime and copy only the resulting `public/build/` artifacts to the exact preproduction `public/build/` directory; copy the *contents* (including `manifest.json`) into that exact directory, rather than nesting a `build/` directory or leaving an older manifest in place. Do not copy source files or secrets.

### Filament assets

Filament back-office CSS, JavaScript and fonts are vendor-published files under `public/css/filament/`, `public/js/filament/` and `public/fonts/filament/`. They are intentionally ignored by Git but must remain physically present because Apache serves them directly. Regenerate them with `/opt/alt/php84/usr/bin/php artisan filament:assets` after every Composer install and before validating the back office; this is a mandatory deployment step, not a Vite build substitute.

Before running `artisan test` on preproduction, run `artisan optimize:clear` first so PHPUnit’s SQLite testing configuration is not shadowed by the cached preproduction configuration. Recreate the configuration, route and view caches after the test run. Never run database-migrating tests against a cached preproduction configuration.

## SEO structural deployment addition
After approval to deploy this release to preproduction, run the explicit PHP 8.4 Artisan commands in this order: `migrate --force`, `redirects:import-htaccess`, `redirects:audit`, then application cache commands. Do not move content redirects back to Apache; only preserve the existing host/HTTPS rules. Validate representative exact, regex and query redirects plus sitemap, robots, 404 and a restaurant aggregate-rating page in Playwright before promotion.

## Public commune-search reference

After the `commune_references` migration, run `/opt/alt/php84/usr/bin/php artisan communes:sync-reference --no-interaction` once. It reads only the tracked local TSV and writes the local search reference; it does not access WordPress, alter restaurants or create SEO pages. A new official millésime requires the reviewed TSV update followed by `communes:sync-reference --fresh`.

## Future Automation
Create `scripts/deploy-preprod.sh` after the server audit confirms final paths, PHP/Composer locations, Node/npm needs, worker manager and Apache setup. The script should automate the target deployment flow without embedding secrets.

## Preproduction GitHub Deploy Key
- Key location on preproduction: `~/.ssh/top-halal-v2-github-deploy`.
- Public key generated: yes.
- Private key handling: remains only on the preproduction server and must never be committed or displayed.
- GitHub status: read authentication tested successfully through the `github-tophalal` SSH alias.

## Codex Workstation GitHub Key
- Key location on workstation: `~/.ssh/top-halal-v2-github-codex-write`.
- Private key handling: remains outside the repository and must never be committed or displayed.
- GitHub status: write authentication tested successfully through the `github-tophalal-codex` SSH alias.

## Server Audit Findings
- Audit artifact: `docs/generated/server-audit.txt`.
- Server host reported by audit: `belette.o2switch.net`.
- Default `php` in SSH PATH: PHP 8.1.34, not acceptable for Laravel 13/PHP 8.4 commands.
- PHP 8.4 binary: `/opt/alt/php84/usr/bin/php`.
- Composer works when invoked explicitly with PHP 8.4: `/opt/alt/php84/usr/bin/php /usr/local/bin/composer`.
- Required PHP 8.4 extensions for Laravel bootstrap are enabled.
- Le module `opcache.so` est présent pour PHP 8.4, mais il n'est pas chargé par le CLI (`php -m`/`php -i`). Le compte de déploiement ne peut pas confirmer ni modifier le SAPI web : activer/vérifier OPcache auprès de l'hébergement avant une mise en production, sans tenter une activation CLI isolée.
- Composer 2.10.2 and Git 2.48.2 are available.
- MariaDB client is 11.4.13.
- Node/npm are not available in SSH PATH.
- Apache control binary is not visible to the deployment user.

## PHP Command Policy
Use `/opt/alt/php84/usr/bin/php` explicitly for Composer, Artisan, tests, deployment scripts and Cron until the server PATH/default PHP is safely aligned with PHP 8.4.

## Restaurant location schema release

The simplified restaurant-location release includes a destructive-schema-only migration: it drops the obsolete geocoding/qualification columns but does not alter `address` or any structured address/GPS value. Deploy the code first, run `migrate --force` once, then run the complete preproduction regression gate. Do not run any legacy enrichment, restore or import command as part of this deployment.

## Administrative geographic pages release

Deploy the application code and run the two forward-only migrations for `city_seo_pages.city_code` and the `restaurants(status, city_code)` index. They do not rewrite restaurant geography or query the legacy database. Clear application caches before verification, then rebuild configuration and views. Validate a precise homonym city, its noindex disambiguation page, a suffixed city/department collision, Paris, a department, a region, Corse, a DOM page and `/sitemap.xml`; finish with the complete workstation `composer test:regression` gate.

## Nearby city-linking release

After deploying this release to preproduction, run the forward-only `city_reference_points` migration, then `/opt/alt/php84/usr/bin/php artisan city-reference-points:sync` once. The command makes one explicit maintenance request to the official French commune API and writes only local reference points for published V2 city pages; it neither reads legacy WordPress nor changes restaurant data. Clear application caches, rebuild the public assets and validate `/restos/marseille`, `/restos/paris`, `/restos/lyon`, `/restos/ris-orangis`, a cross-department candidate, a homonym and a city with no eligible neighbour. Finish with the complete workstation `composer test:regression` gate.

## Current Validation Status
- PHP audit: passed for required PHP 8.4 extensions and Composer.
- Laravel bootstrap: generated by official Composer `create-project` for `laravel/laravel:^13.0`.
- PHP tests: passed in an isolated preproduction temp directory using `/opt/alt/php84/usr/bin/php artisan test`.
- Artisan command registration: `legacy:inventory` appears in `artisan list`.
- Preproduction clone: `develop` is cloned at `/home/meyo5199/top-halal-v2` and Composer dependencies are installed.
- Preproduction environment file: created server-side only with permissions `600`; secrets are not in Git or documentation.
- MariaDB application connection: `meyo5199_top_halal_v2` read/write verified with the dedicated app user.
- Legacy connection: points to existing `meyo5199_th` using dedicated SELECT-only user and prefix `tp_`; SELECT works and INSERT/UPDATE/DELETE/ALTER/DROP are denied.
- Laravel migrations: initial framework migrations ran only on the V2 database.
- Server verification: `artisan about`, `migrate:status`, `route:list` and PHPUnit passed under explicit PHP 8.4.
- Browser/Playwright preproduction validation: HTTPS desktop/mobile smoke passed against `https://dev.top-halal.fr/`.
- HTTP to HTTPS redirect: active.
- Preproduction indexing protection: `X-Robots-Tag: noindex, nofollow` and `robots.txt` disallow-all are active.
- Sensitive public path checks: `.env`, `composer.json`, `artisan`, `storage/`, `vendor/` and `.git/` are not publicly readable.

## Apache Layout
- Confirmed application path before first clone/deploy: `/home/meyo5199/top-halal-v2`.
- Confirmed Apache DocumentRoot after Laravel setup: `/home/meyo5199/top-halal-v2/public`.
- Current subdomain folder detected: `/home/meyo5199/dev.top-halal.fr.meyo5199.odns.fr`.
- The DocumentRoot has not been changed yet.
- `.env`, database dumps, uploads awaiting migration, SSH keys, API keys, passwords and Git metadata must never be web-accessible.

## Cron
Laravel scheduler runs through this server Cron entry every minute:

```cron
* * * * * cd /home/meyo5199/top-halal-v2 && /opt/alt/php84/usr/bin/php artisan schedule:run --no-interaction >> /home/meyo5199/top-halal-v2/storage/logs/scheduler.log 2>&1
```

## Production operating Cron

The application has a finite, lock-protected worker model suitable for
production. The actual production application root, user crontab entries,
frequencies and retention command must be read and confirmed from the production
environment before use; do not infer them from preproduction. The scheduler
owns verification cleanup, while delivery-history retention remains separate
from `routes/console.php`. Keep SMTP credentials exclusively in the encrypted
BO setting. `prod-preview.top-halal.fr` is an application-level 301 to the
canonical production URL, while `dev.top-halal.fr` remains separate.

## Queue worker

Preproduction is hosted on o2switch mutualised and has no persistent Supervisor/systemd worker. Use the user Cron every minute instead, with a non-blocking `flock` lock so overlapping runs cannot process a job twice:

```cron
* * * * * /home/meyo5199/top-halal-v2/scripts/run-queue-worker-cron.sh >> /home/meyo5199/top-halal-v2/storage/logs/queue-worker.log 2>&1
```

The executable script first reconciles tracked delivery-history rows whose database job has disappeared, then uses `/opt/alt/php84/usr/bin/php artisan queue:work database --queue=default --stop-when-empty --tries=4 --timeout=75 --sleep=1 --no-interaction` behind `/usr/bin/flock -n`. `config/queue.php` sets database `retry_after=90`, leaving a 15-second safety margin above the worker timeout. New transactional mailables and notifications define their own progressive `30,120,300`-second backoff arrays; Laravel 13 serialises these into queue payloads. Do not run a permanent worker for this setup.

The worker script resolves its application directory from its own location. It
may be used unchanged once the production checkout and Cron target are
confirmed; never copy a preproduction path into a production Cron entry.

## Transactional email
- Keep SMTP settings server-side / encrypted in the V2 settings; do not put credentials in Cron or logs.
- The BO SMTP test remains synchronous and bypasses the queue. Site transactional messages use the Cron-drained database queue.
- Inspect failures with `artisan queue:failed`; do not paste message content or credentials into deployment logs.

## Delivery-history retention

Keep this daily preproduction Cron entry alongside the queue-worker Cron. It deletes only `cancelled` and `expired` e-mail history records older than 60 days; it never removes sent or failed audit records:

```cron
5 3 * * * cd /home/meyo5199/top-halal-v2 && /opt/alt/php84/usr/bin/php artisan emails:purge-delivery-logs --days=60 --no-interaction >> /home/meyo5199/top-halal-v2/storage/logs/queue-worker.log 2>&1
```

Audit a pending purge without deletion using `artisan emails:purge-delivery-logs --days=60 --dry-run`.
