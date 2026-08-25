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
- PHP/business rules: Pest/PHPUnit.
- Browser flows: Playwright on preproduction.
- Codex/browser validation: navigate as a real visitor/admin, fill forms, submit them, inspect visible results, console, network errors and relevant accessibility/performance issues.
- If an E2E/browser test finds a defect, diagnose, fix, redeploy and rerun the failing flow before marking complete.
- Run targeted tests during development; run the full relevant suite before completing a milestone.

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

## Git discipline
- Keep commits small and descriptive.
- Before committing: inspect `git diff`, run relevant tests, ensure no secret/private file is staged.
- Do not force-push or rewrite history unless explicitly requested.
- `main` is stable. Use focused feature branches when useful.
