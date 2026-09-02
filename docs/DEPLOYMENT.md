# Deployment — Preproduction & Production

## Principles
- No Docker.
- Deploy preproduction over SSH using Git directly on the server; rsync is not the primary deployment method.
- Apache DocumentRoot must point to Laravel's `public/` directory.
- The Git checkout must live outside any publicly accessible directory.
- Never expose project root, `.env`, `storage`, legacy dumps, SSH material or Git metadata through the web server.
- Production/preproduction secrets live in server-side environment configuration, never Git.
- Do not deploy to production until the production cutover plan is explicitly approved.

## Git Strategy
- Remote repository: private GitHub repository at `git@github.com:pamayo91/top-halal-v2.git`.
- Codex workstation remote URL: `git@github-tophalal-codex:pamayo91/top-halal-v2.git`.
- Preproduction server remote URL: `git@github-tophalal:pamayo91/top-halal-v2.git`.
- `main`: stable branch for future production-ready code.
- `develop`: preproduction integration branch.
- `feature/...`: temporary branches for significant work; merge into `develop` only after validation.
- Codex workflow: edit code, run targeted local tests, commit, push to GitHub, SSH to preproduction, pull `develop`, run deployment steps, then validate preproduction with browser/Playwright.

## Required Manual GitHub Actions
- Confirm whether branch protection is desired for `main` and `develop`.
- Confirm who may merge `develop` into `main`; production promotion must stay explicit.

## Target deployment flow
1. SSH to preproduction using the saved alias `top-halal-preprod`.
2. Enter the non-public application directory.
3. `git fetch --prune` and update the working tree from `origin/develop`.
4. `composer install` when dependencies changed; use the preproduction-safe flags decided after server audit.
5. Run Laravel migrations when needed, with preproduction safeguards.
6. Build frontend assets when needed.
7. Run Laravel optimization/cache commands appropriate for preproduction.
8. Restart queue workers when workers exist and code affecting jobs changed.
9. Clear the route cache whenever `routes/` changes; this project contains closure routes and must not route-cache them. Run health check and targeted smoke/E2E tests against the preproduction URL.
10. Run `composer test:regression` from the workstation after deployment. It requires the deployed `regression_sentinels` baseline and blocks on database/media/relation drift, HTTP 500, Playwright console/network failures or new Laravel exceptions.
11. If validation fails, stop and fix before considering deployment complete.

## Frontend build and test safety

The preproduction SSH environment has no Node.js executable in PATH. After the Git deployment, build Vite assets with the approved workstation runtime and copy only the resulting `public/build/` artifacts to the exact preproduction `public/build/` directory; do not copy source files or secrets.

Before running `artisan test` on preproduction, run `artisan optimize:clear` first so PHPUnit’s SQLite testing configuration is not shadowed by the cached preproduction configuration. Recreate the configuration, route and view caches after the test run. Never run database-migrating tests against a cached preproduction configuration.

## SEO structural deployment addition
After approval to deploy this release to preproduction, run the explicit PHP 8.4 Artisan commands in this order: `migrate --force`, `redirects:import-htaccess`, `redirects:audit`, then application cache commands. Do not move content redirects back to Apache; only preserve the existing host/HTTPS rules. Validate representative exact, regex and query redirects plus sitemap, robots, 404 and a restaurant aggregate-rating page in Playwright before promotion.

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
- `opcache` is still missing from PHP 8.4 and should be enabled before performance validation.
- Composer 2.10.2 and Git 2.48.2 are available.
- MariaDB client is 11.4.13.
- Node/npm are not available in SSH PATH.
- Apache control binary is not visible to the deployment user.

## PHP Command Policy
Use `/opt/alt/php84/usr/bin/php` explicitly for Composer, Artisan, tests, deployment scripts and Cron until the server PATH/default PHP is safely aligned with PHP 8.4.

## Restaurant location schema release

The simplified restaurant-location release includes a destructive-schema-only migration: it drops the obsolete geocoding/qualification columns but does not alter `address` or any structured address/GPS value. Deploy the code first, run `migrate --force` once, then run the complete preproduction regression gate. Do not run any legacy enrichment, restore or import command as part of this deployment.

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
Laravel scheduler should have one server Cron entry (exact path/user decided after server audit), calling `php artisan schedule:run` every minute.

## Queue worker
Use systemd or Supervisor after server audit. Do not rely on a browser request to process mail/AI queues.

## Transactional email
- Keep `MAIL_*` values exclusively in server `.env`; preproduction may use a log/capture transport until an operator supplies a real test recipient.
- Run the database queue worker with three attempts and progressive backoff: `/opt/alt/php84/usr/bin/php artisan queue:work --tries=3 --backoff=30,120,300`.
- Inspect failures with `artisan queue:failed`; do not paste message content or credentials into deployment logs.
