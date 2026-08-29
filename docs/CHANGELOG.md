# Changelog

## 2026-08-29

- Added independent address-confidence, GPS-precision and precise-proximity qualification. The proximity search now uses `proximity_status`; no historical coordinate or raw address was changed.
- Added controlled full address enrichment: structured address provenance, confidence statuses, Filament visibility and precise-radius eligibility. The full preproduction pass processed 7,704 restaurants without replacing historical addresses or existing GPS.
- Added a deterministic plain-text normalizer for HTML entities and recognised UTF-8/Windows-1252 mojibake. Rich editorial HTML remains untouched; the migration paths and an idempotent audit command use the same normalizer.
- Added environment-aware public URL resolution for Filament actions, including published content only and media previews, plus targeted search indexes for administrative lookups.

## 2026-08-26
- Started the public frontend milestone: replaced technical public previews with a shared Blade design system, responsive header/navigation, homepage, blog index, directory search, taxonomy collections, restaurant detail/gallery, editorial comments and public review submission. Search uses only V2 fields and geolocation is initiated only after a visitor action.
- Added an opaque, aggregate-counting restaurant outbound route (`/sortie/{token}`) and private destination table. Public templates never render external restaurant destinations.
- Preproduction migration and full PHP suite pass (43 tests, 205 assertions). Desktop/mobile browser smoke and public directory journeys pass with no console, failed-network or legacy WordPress requests.
- Confirmed the distinct retained-page policy: `/blog` is indexable and listed in the sitemap; `/mon-compte` remains available without redirect but is `noindex,follow` and excluded from the sitemap. Sitemap generation now excludes every editorial record carrying a `noindex` directive.
- Applied the approved non-restaurant sitemap cleanup without deleting migration records: retained `/blog` and `/mon-compte`; marked nine technical/duplicate paths as redirected, excluded them from sitemap eligibility and added exact 301 rules (`/blog-2` → `/blog`, all other approved removals → `/`).
- Validated the structural SEO release on preproduction: migration/import/audits passed (454 application rules, 3 Apache-owned infrastructure rules, no persisted conflicts/loops/chains; 121 articles, 90 pages and 7,633 restaurants mapped with no editorial slug collision). Full PHP suite passes: 40 tests, 186 assertions. Browser checks passed for exact, regex and query redirects, 404, canonical restaurant markup and console errors.
- Added the structural SEO foundation: canonical route contract, trailing-slash normalization, public 404/410 responses, sitemap/robots, visible breadcrumbs and native JSON-LD. Restaurant JSON-LD emits a single approved-review AggregateRating only when visible.
- Added the database-backed redirect engine, deterministic `.htaccess` inventory importer, cache invalidation, exact/regex/query matching, priority/activation/hit metrics and duplicate/conflict/loop/chain audit command. Host, HTTPS and legacy-domain redirects remain Apache-owned.
- Removed all legacy Google AdSense content from migrated articles/pages, including direct `<ins class="adsbygoogle">` slots and the fragmented Base64/URL-encoded legacy payload. The rescan finds zero `adsbygoogle`, `googlesyndication` or `ca-pub-` markers in V2 editorial HTML.
- Completed the full legacy-to-V2 migration: final idempotence run `ab6713de-164a-4216-b320-4cb143e1b184` created no entity. The PHP suite is green (31 tests, 158 assertions), all V2 media originals/variants exist, and no migrated HTML references `wp-content` or `wp-contenu`.
- Reclassified the 415 apparent inline-media losses: 412 were recovered read-only from the additional legacy `wp-contenu/uploads` tree; three URLs return 404 locally and publicly and remain explicit anomalies.
- Fixed the full-migration inline-media regression: the migration had stripped editorial images before its media phase and only considered WordPress-standard relations. It now rescans complete editorial HTML, including root-relative legacy upload paths, copies/reuses readable sources, creates V2 content-media relations and writes safe V2 `src`, `srcset`, dimensions and lazy-loading attributes.
- Reconciled 695 inline references: 280 readable references are represented by 152 idempotent V2 content-media links; 415 unavailable physical sources remain explicit migration anomalies. No migrated article or page retains a public `wp-content` or `wp-contenu` upload reference.
- Investigated legacy post 27: all four expected inline images have neither a surviving attachment row nor a readable legacy file. They are reported as `missing_physical_source`, not rendered through legacy WordPress.
- Added sanitizer coverage for absolute and relative legacy upload images, and replaced hard-coded pilot preview media with generic featured-media rendering.

## 2026-08-25
- Completed the controlled Media pilot: audited 2,239 legacy attachments, copied four reviewed assets into V2 storage, generated eight WebP variants and confirmed idempotence on a second apply.
- Added safe media delivery (exact MIME, immutable cache, `nosniff`, unavailable width = 404), PHP coverage and desktop/mobile preproduction validation. The `wp-content` and `wp-contenu` spellings are both removed from migrated pilot HTML, so previews no longer request legacy uploads.
- Recorded 415 missing physical attachment sources, 135 duplicate checksums and 535 inline-media debt records for later reconciliation; no full media import was inferred from the pilot.
- Added queued Laravel transactional email notifications for verification, reset/password changes and claim status; SMTP/API transport remains environment-configured.
- Added preproduction email test command, database-queue operating guidance and read-only SPF/DKIM/DMARC audit. No legacy-user campaign or real-recipient send occurred.
- Added and validated the controlled Users/Auth/Claims pilot: registration, login/logout, password reset request/reset, CSRF/session protection and email/IP rate limiting.
- Added mandatory password replacement for migrated legacy users; old WordPress hashes are never imported and the server-only temporary secret is never documented or logged.
- Added pending/approve/reject restaurant claims, backend restaurant ownership Policy and minimal technical moderation views. E2E accounts, claims and restaurants were deleted after validation.
- Migrated only ten deterministic legacy user identities and confirmed a second run creates no duplicates or password rewrites.
- Validated the complete flow on HTTPS preproduction with 23 PHP tests / 135 assertions and Playwright desktop/mobile.
- Added and validated the controlled ListingPro restaurant-reviews pilot: audit, V2 table/model, migration command, moderation command, approved-only aggregate and technical restaurant preview.
- Audited 84 legacy reviews; migrated only eight valid deterministic reviews. Three records without a valid listing relation and four without ratings remain reported and skipped.
- Added the controlled WordPress editorial-comments pilot: aggregate read-only audit, V2 `comments` table/model, idempotent `legacy:migrate-comments`, temporary moderation command and protected preview form.
- Audited 20,196 legacy comments and migrated only ten deterministic comments (nine approved, one pending) to the existing article/page pilot. Spam, pingbacks, review comments and all non-pilot records remain untouched.
- Added comments migration reports and inline legacy-media debt reports. The three removed inline legacy images remain traceable; no physical media was copied.
- Verified the comments pilot with 15 PHP tests and 28 desktop/mobile Playwright tests. The browser test data was deleted after validation.
- Added the V2 editorial pilot schema, reader/transformer/sanitizer pipeline, technical preview route and controlled `legacy:migrate-content` command.
- Migrated only five articles and five pages; legacy scripts/non-allowlisted iframes are removed and Visual Composer is converted to clean HTML.
- Restored Playwright by using Codex's bundled Node runtime and validated all ten editorial previews on desktop and mobile.
- Audited all 7,704 ListingPro restaurant option rows for opening hours. No usable legacy schedule exists; no complementary hours migration was run.
- Added `legacy:audit-opening-hours` and a sanitized report with format counts, examples and the hostile time-like email anomaly.
- Added the controlled Restaurant/ListingPro pilot schema, models and read-only `legacy:migrate-restaurants` command.
- Ran the deterministic ten-restaurant dry-run and applied it twice on preproduction; no duplicate restaurant, slug or relationship was created.
- Recorded the sanitized restaurant migration report and the selected legacy IDs. No articles, pages, comments, reviews, users, advertising or AI data was migrated.
- Added automated coverage for complete and incomplete listings, multi-relations, UTF-8, status, idempotence, read-only legacy access and transaction rollback.
- Documented the Git branch strategy and SSH/Git-based preproduction deployment workflow.
- Added French deployment summary in `docs/DEPLOIEMENT.md`.
- Updated project status for partial SSH access and pending private GitHub repository setup.
- Configured local Git `origin` for the private GitHub repository and generated a dedicated preproduction GitHub deploy key.
- Created local `main` and `develop` branches; remote push is pending GitHub SSH authorization for this workstation.
- Ran the real preproduction server audit and saved the consolidated result to `docs/generated/server-audit.txt`.
- Recorded PHP 8.4/Composer extension blockers and the preproduction GitHub SSH alias status.
- Generated a dedicated Codex workstation SSH key and configured the local Git remote through the `github-tophalal-codex` alias.
- Pushed local `main` and `develop` branches to GitHub after write access was enabled.
- Re-ran the PHP 8.4/Composer audit after extension changes and confirmed required Laravel bootstrap extensions are available.
- Generated the Laravel 13 application with the official Composer `create-project` workflow on preproduction using explicit PHP 8.4.
- Added MariaDB and read-only legacy WordPress connection placeholders, `/health`, a neutral Blade homepage, PHPUnit coverage and Playwright smoke-test scaffolding.
- Added `legacy:inventory` for safe aggregate legacy WordPress inventory reports.
- Re-ran the `.htaccess` inventory and confirmed the parser matches all active rule lines in the supplied file.
- Cloned `develop` to the confirmed preproduction application path and installed Composer dependencies with explicit PHP 8.4, without changing DocumentRoot or creating `.env`.
- Updated the legacy database plan to use the existing `meyo5199_th` database through a dedicated SELECT-only user with WordPress prefix `tp_`.
- Configured preproduction `.env` server-side only, generated `APP_KEY`, verified V2 DB read/write, verified legacy SELECT-only enforcement, and ran initial Laravel migrations only on the V2 database.
- Verified preproduction Laravel with `artisan about`, `migrate:status`, `route:list` and PHPUnit under explicit PHP 8.4.
- Expanded Playwright smoke coverage for desktop/mobile, 404 and sensitive public paths.
- Validated the exposed preproduction app over HTTP and recorded the HTTPS certificate blocker.
- Validated the exposed preproduction app over HTTPS after certificate/domain setup.
- Added preproduction noindex protections through `X-Robots-Tag` and `robots.txt`.
- Ran the read-only legacy inventory against `meyo5199_th` and added inventory/analysis reports.
- Drafted the initial WordPress/ListingPro mapping, business schema proposal and limited sample migration plan.

## 2026-08-24
- Created initial Codex handoff/starter documentation.
- Added project-level agent constraints and token-efficiency rules.
- Added server audit utility.
- Added legacy `.htaccess` redirect inventory utility and source file.
- Recorded the preproduction audit and official Composer bootstrap as hard prerequisites before Laravel initialization.
# Changelog

## 2026-08-29

- Added the read-only Géoplateforme/BAN geocoding pilot, provider abstraction, bounded cache/rate limit, response parsing and no-write coverage. The representative 100-restaurant preproduction pilot produced no restaurant or geography changes.
- Added `data:audit-addresses`, a deterministic, read-only address/GPS audit with a test proving it does not modify V2 or legacy data. The preproduction run audited all 7,704 restaurants and produced the address/GPS report plus a 100-record sample; no geocoding, address/GPS correction, or geography change was made.

## 2026-08-27

- Audited and repaired migrated-data integrity on preproduction: restored 121 historical article and 90 page publication dates, added legacy attachment dates, retained V2 audit timestamps, and added Filament date displays for content, moderation, users, media and restaurants.
- Classified all 2,003 migrated geographic terms. Removed 32 manifest SQL-injection/fuzzing payloads that had no restaurant relationship; 1,961 valid location relationships were retained. Added idempotent reporting, future-import guards, admin validation and the geography “Sans restaurant” filter. Categories, features and editorial taxonomies had no matching anomaly.

- Completed the Filament 5 `/admin` migration: legacy `/bo` routes, controllers and Blade views are removed; settings, audit log, moderation, media, redirect, taxonomy and user modules are in the panel. Preproduction PHP suite (51 tests, 229 assertions) and Filament desktop/mobile Playwright validation pass.
- Added the secured V2 administration area, audit log and daily operational management screens.
- Added admin feature coverage for authorization, restaurant CRUD, moderation, editorial sanitization and sensitive audit filtering.
