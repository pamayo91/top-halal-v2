# Top-Halal V2 — Agent Rules

## Mission
Rebuild https://top-halal.fr from scratch. WordPress is a legacy data source only; the final site must not depend on WordPress or another CMS.

## Non-negotiable architecture
- Laravel 13, PHP 8.4.
- Apache, MariaDB 11.4.x, SSH, Cron.
- No Docker.
- Server-rendered public frontend (Blade); minimal JavaScript.
- Redis may be installed for cache/queues if server audit confirms it is appropriate.
- Preproduction is the primary integration environment.

## Windows local environment 
do not treat missing local PHP/Composer as a blocker. PHP/Laravel tests must use the project's approved preproduction SSH/PHP 8.4 workflow when PHP is unavailable locally. Do not attempt to substitute or skip required tests because the Windows workstation lacks PHP.


## Non-negotiable product / SEO rules
- Preserve current URLs whenever possible.
- Historical redirects from `legacy/redirects.htaccess` must be migrated into an application-managed redirect system, except domain/protocol/infrastructure rules that belong in Apache.
- Deleted URL with no better equivalent may redirect 301 to `/`, while the migration report should flag it as a fallback choice.
- No clickable or plain-text URL is allowed in user comments. Reject URL-like content server-side; store/render comments as safe text.
- External URLs on restaurant listings must not appear in public HTML/JSON-LD. Use an opaque server-side outbound mechanism initiated from a button and record only aggregate analytics when needed.
- AI-generated article provenance must always be stored internally. Public AI disclosure is configurable globally and per article; disclosure may be hidden.
- AI provider integration must use a provider abstraction. Do not hard-wire business logic to OpenAI. Initial adapters may include OpenAI, Anthropic, Google Gemini and Mistral when configured.
- Advertising must be native, lightweight, clearly labeled, and must not cause layout shift.
- Core Web Vitals and PageSpeed are release criteria, not cleanup tasks.

## Performance budgets
Target on representative preproduction pages:
- PageSpeed/Lighthouse Performance mobile >= 95; target 98–100 where technically realistic.
- SEO = 100 target.
- Best Practices = 100 target.
- Accessibility = 100 target where feasible.
- LCP < 1.8 s target.
- INP < 150 ms target.
- CLS < 0.05 target.
Avoid heavy frontend frameworks, unnecessary JS, third-party scripts, remote fonts and layout-unstable ad slots.

## UX rules
- Design must be simple, modern, mobile-first and obvious to use.
- Prefer native/semantic HTML and progressive enhancement.
- Every core journey must be usable without unnecessary JavaScript.
- Do not optimize aesthetics at the expense of accessibility, speed or clarity.

## Migration rules
- Never load the 155 MB decompressed SQL dump into model context.
- Import/query the legacy database and inspect only targeted results.
- Keep `legacy_wp_id` / `legacy_*_id` on migrated records for reconciliation.
- Never silently drop a legacy record. Produce anomaly reports.
- Do not migrate WordPress spam comments.
- Preserve approved/pending real comments, dates, parents/replies and authorship data needed for continuity.
- Migrate ListingPro reviews separately from editorial comments.
- Convert legacy Visual Composer/ListingPro shortcodes to clean supported HTML/blocks; report anything not converted.

## Testing rules
Every completed feature requires appropriate automated tests.
- Before starting a technical task, classify it as `MICRO`, `FAST` or `COMPLET`
  under the production-maintenance workflow below. Reclassify immediately if its
  actual scope reveals data, auth, security, dependency or transversal impact.
- PHP/business rules: Pest/PHPUnit.
- Browser flows: Playwright on preproduction when the classified risk and the
  affected user journey require it; it is not a default for a localised MICRO
  correction.
- Codex/browser validation: navigate as a real visitor/admin, fill forms, submit them, inspect visible results, console, network errors and relevant accessibility/performance issues.
- For authenticated back-office validation on preproduction, use only the dedicated test administrator supplied through local `PREPROD_ADMIN_EMAIL` and `PREPROD_ADMIN_PASSWORD` environment variables. Never use, modify, reset or make a fixture of the project owner's human administrator account. Tests requiring these variables must skip with an explicit reason when they are unavailable.
- If an E2E/browser test finds a defect, diagnose, fix, redeploy and rerun the failing flow before marking complete.
- Run targeted tests during development; run the full relevant suite before completing a milestone.

## NON-REGRESSION GATE
- For every significant functional change, run targeted tests during development and the complete regression suite before marking the work DONE.
- `composer test:regression` is the mandatory preproduction gate for a `COMPLET` change. It may be used once for a `FAST` change when its risk or perimeter justifies it, and is not required for a `MICRO` change. It verifies PHP sentinel coverage, database integrity, V2 media/storage, Playwright public sentinels, HTTP 500s, browser console/network failures and Laravel exceptions generated during the run.
- The regression gate must never pin real mutable business records as permanent fixtures. Capture `regression:snapshot` immediately before deployment and pass its run ID to the post-deployment gate; compare only changes made during that deployment, while retaining dynamic orphan/media/storage integrity checks.
- A new HTTP 500, lost media, unexpected lost relationship, unexpected count decrease, failed Playwright regression or new Laravel exception blocks DONE.
- Never repair data artificially, rerun a global migration, recreate relations/media, or change a valid fixture merely to make a regression test pass. Diagnose and correct the root cause in code first; restore data only afterwards when necessary.
- The regression suite protects the existing application as well as the changed feature. It is never optional for `COMPLET` work or a `FAST` task explicitly escalated to it; a genuinely isolated `MICRO` change follows its own targeted proof path.
- After a root-cause correction, rerun the complete regression suite. Inspect the complete Git diff before every functional commit; explain any unrelated or large change and verify that no secret, `.env`, or unintended deletion is staged.

## Documentation rules
Documentation is part of the deliverable.
Before changing behavior, read only the relevant spec under `docs/specifications/` plus `docs/PROJECT.md` and `docs/STATUS.md` as needed.
Update documentation after implementation.
Maintain:
- `docs/STATUS.md`
- relevant module specification
- `docs/DECISIONS.md` for architectural/product decisions
- `docs/CHANGELOG.md` for completed changes
- `docs/DEPLOYMENT.md` for deployment changes

## Token/context efficiency
- Do not scan the whole repository unless required.
- Do not read the SQL dump as text; query the imported legacy DB.
- Read only docs relevant to the current task.
- Prefer deterministic scripts for bulk transformations and audits.
- Prefer targeted grep/search over opening large files.
- Run targeted tests before full suites.
- Use browser inspection when it can prove behavior; do not browse the entire site after every small change.
- Do not regenerate working code unnecessarily.
- Summarize completed work in `STATUS.md` so later tasks do not need to reconstruct history.

## Security
- Never commit `.env`, credentials, API keys, SMTP secrets, SSH material, database dumps or user-sensitive exports.
- Legacy SQL and uploads stay local/private and are ignored by Git.
- Public app must use CSRF protection, output escaping, strict validation, safe uploads, rate limiting, secure cookies and appropriate security headers.

## Account safeguards
- The project owner's designated human administrator account is the sole human admin account. Never reset its password or change its role/status unless the project owner gives a specific request for that action.
- The WordPress migration is finalized. Do not query, use, or rerun migrations against the legacy WordPress database unless the project owner specifically authorizes a new legacy-data task.
- For development-only needs, Codex may use a separate provisional admin account when explicitly provisioned; never repurpose or alter the project owner's human administrator account for that purpose.
- The dedicated preproduction browser-test account is configured locally via `PREPROD_ADMIN_EMAIL` and `PREPROD_ADMIN_PASSWORD`; these values remain outside the repository and are never printed, committed or copied into test artifacts.

## Critical data-operation safeguard
- Never restore a database, rebuild/reseed V2 data, or import/reimport any data from the legacy database without **two distinct explicit confirmations** from the project owner in the current conversation.
- First present the exact target, scope, source, expected creates/updates/deletes, and rollback/backup plan; the first confirmation authorizes preparation only. Obtain the second confirmation immediately before the irreversible command or write begins.
- A past authorization, a broad request to "restore" or "import", or silence never counts as either confirmation. Read-only audits remain permitted when otherwise authorized.

## Production maintenance workflow
- Production is active. `main` is the stable, production branch; `develop` is the integration branch deployed to `dev.top-halal.fr` for preproduction validation.
- Classify every technical task before implementation and record the classification briefly when useful: `MICRO` is a tightly localised, presentation/SEO-only correction with no BDD, migration, business data, dependency, auth/permission, queue/job, transactional e-mail, upload/storage, sensitive business form, critical security or identified transversal impact. Typical cases are a link or external URL, `rel="nofollow"`, a small `robots.txt` or meta SEO rule, copy, one HTML attribute, isolated CSS, icon/label, share URL or presentation-only Blade correction. `FAST` covers a limited functional change such as small PHP/SEO/controller/route/reusable-component/frontend behaviour, without sensitive migration, auth/permission, massive mutation or critical transversal impact. `COMPLET` is mandatory for a migration or data mutation; auth, permissions, accounts, claims/ownership, security, sensitive business forms, restaurants, reviews/comments with business logic, queues/jobs, transactional e-mail, storage/media/upload, a dependency, a new feature, architecture, data deletion, significant route change, or any impact that cannot be clearly bounded.
- A technical correction starts from the stable `main` state on a focused `fix/...` or `hotfix/...` branch. Make only the intended change, inspect only the directly relevant files/components for `MICRO` (the limited functional perimeter for `FAST`), add or adapt the necessary targeted proof, then integrate that exact correction into `develop` for preproduction.
- `MICRO`: one clean commit is the objective (two only when genuinely justified); deploy once to preproduction, make one real verification directly tied to the change, wait for owner validation, selectively promote the validated commit, then make only change-related production checks. Do not run Playwright, `composer test:regression`, a regression snapshot, broad scans or unrelated desktop/mobile checks by default.
- `FAST`: use targeted PHP tests and one browser or Playwright check only when relevant, deploy once to preproduction and verify the affected flow. `composer test:regression` and its snapshot are conditional and run at most once when the risk or combined perimeter justifies them; they are not an iteration default.
- `COMPLET`: run targeted tests, relevant Playwright/browser validation, preproduction validation, the mandatory snapshot-backed `composer test:regression` gate, owner validation, selective promotion and the complete module-appropriate production checks.
- Several independent MICRO/FAST corrections may each receive their own targeted proof, then share one preproduction deployment, one lot check, one owner validation and one production deployment. A fully MICRO lot does not acquire a complete gate merely by having several items; reclassify it to `FAST` or `COMPLET` when the cumulative risk becomes transversal.
- If an expected test command is unavailable, never improvise a complex orchestration, concurrent executions, a manual reconstruction of the full gate, a background task to bypass terminal limits or repeated cache changes to force a MICRO/FAST pass. Continue with the declared targeted proof for `MICRO`/`FAST`; for `COMPLET`, stop as `BLOCKED` until the mandatory gate can run correctly. Never silently weaken a required gate.
- For environment-dependent `robots.txt`, validate the production branch logic with an automated or targeted check, verify preproduction's own intentional blocking/protection, then inspect the real production `/robots.txt` after deployment. Never alter preproduction temporarily to mimic production.
- Reclassify immediately rather than continuing as MICRO if implementation reveals BDD, auth, security, dependencies or transversal impact. Promotion to production always requires the project owner's explicit approval.
- Promote only the reviewed correction to `main` (for example by a reviewed merge or cherry-pick of the tested commit). Do not merge all of `develop` merely because it contains the correction: unfinished or unapproved work in `develop` must not reach production. Do not rewrite history or force-push.
- Deploy `main` to production only after that approval and perform change-appropriate post-deployment checks: deployed commit, site and `/health` where applicable, changed behavior, new HTTP 500s, relevant Laravel logs, and browser console/network for frontend changes. Check redirects (old URL, status, final destination), served `robots.txt`, generated sitemap, route/cache behavior, and real assets or desktop/mobile presentation when affected.
- A BO change intentionally exposed in Filament (content, settings, navigation, SEO metadata, a simple exact redirect, or other managed business value) is production data and may be made in the production BO through the normal administrative workflow. Validate broad or risky changes, including regex redirects, on preproduction first when useful. Do not replace it through a later code deployment.
- Blade, PHP/Laravel, routes, robots, sitemap, CSS/JS, schema, server behavior and new features are technical changes: they must use the branch -> `develop` -> preproduction -> tests -> explicit approval -> `main` -> production circuit. Direct server-side file editing is not a normal correction method. An exceptional emergency intervention must be immediately reproduced in Git and documented.
- Code, production database and production media storage are separate deployment concerns. Never copy, merge or synchronise preproduction database rows or storage into production during a normal deployment; never run `migrate:fresh`, a global reseed, restoration, legacy reimport or global reconstruction in production. Production is the source of truth for data administered after launch.
- A schema migration is deployed with the code and executed in production in a controlled way (normally `artisan migrate --force`), preserving production data; preproduction test data never moves to production. Before any migration or script that mutates existing data, identify scope and expected creates/updates/deletes, idempotence where relevant, BO-data interactions, backup/rollback plan, preproduction evidence and required human validation. Prefer create-if-absent defaults for new managed settings; never silently overwrite an existing production choice.
- Cache clearing/rebuilding is not a business-data reset. Use only project-appropriate cache commands and continue to respect the closure-route rule: clear route cache when routes change and do not route-cache closure routes.

## Git discipline
- Keep commits small and descriptive.
- Before committing: inspect `git diff`, run relevant tests, ensure no secret/private file is staged.
- Do not force-push or rewrite history unless explicitly requested.
- `main` is stable. Use focused feature branches when useful.
