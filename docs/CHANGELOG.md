# Changelog

## 2026-09-03

- Restaurant covers and galleries now accept only JPEG, PNG and WebP. The V2-only audited cleanup removed five video relations and five orphaned video assets/files across two restaurant records, promoted the remaining images, left no restaurant without a replacement and was idempotent on its second preproduction pass.
- After review of this authorized deletion, the preproduction regression baseline was refreshed to the resulting V2 counts, including `media_assets >= 1,459` and `restaurant_media >= 880`; all other count floors remain unchanged.

## 2026-09-02

- Simplified the restaurant location model: removed the twelve migration-only geocoding/qualification columns, while retaining the untouched legacy `address` field and the structured address/GPS contract. Nearby search now accepts only non-null coordinates within valid global ranges. Mandatory Géoplateforme selection, public structured-address rendering and GPS-only marker moves are covered by PHP tests.
- Refreshed only the two approved preproduction regression floors after a read-only audit of the intentional restaurant cleanup: `restaurants >= 7618` and `restaurant_media >= 885`. The update preserves strict decrease detection; no restaurant or media record was restored, imported, or otherwise changed.
- Reworked restaurant address entry around mandatory server-resolved Géoplateforme selections. The public proposal and owner-edit forms share the address selector/map; Filament creation also requires a selection. Public users no longer enter postcode, city, INSEE code or GPS manually. Marker refinement changes GPS only and does not reverse-geocode or rewrite structured/provider data. Restaurant pages and JSON-LD now prefer the structured address fields over the historical raw address. No existing restaurant was modified.

## 2026-09-01

- Les administrateurs actifs voient désormais un raccourci « Éditer » en haut des fiches restaurant, articles et pages publiques ; il ouvre directement le formulaire Filament correspondant, sans JavaScript ni surcharge des réponses des visiteurs.
- Public restaurant submissions now reject any cover or gallery image narrower than 800 px, with server-side validation and PHP/Playwright coverage.
- The required e-mail in a public restaurant submission now populates the restaurant contact field as well as the private contributor record, so moderators see it in the Contact tab.
- Added a permanent double-confirmation safeguard: no database restoration, V2 rebuild/reseed or legacy import/reimport may start without two distinct explicit owner confirmations in the current conversation, including a final confirmation immediately before writes.
- Refined public restaurant opening hours into a compact, responsive Blade card with semantic day/time rows, a subtle current-day state and no client-side dependency. A tested server-side Paris-time status now handles open services, breaks, closed days, the following day and overnight slots, while incomplete data never produces a guessed status.
- Made the Filament back-office container responsive and compacted the restaurant table on narrow screens. The list now shows a publication/addition date and defaults to the newest restaurant records.

## 2026-08-31
- Public restaurant reviews and editorial comments now display newest first, including front previews.
- Fixed media delivery cache collisions that made distinct article thumbnails render the same file. Public and regression-sentinel media URLs now contain the asset checksum, stale numeric URLs redirect safely, and feature coverage rejects a mismatched checksum.
- Gallery and back-office media rendering now select an available WebP variant and fall back to the original when none exists, so a missing 480 px derivative cannot cause a browser 404.
- Rewrote the remaining V2 editorial media URLs through an idempotent V2-only command, so existing inline images receive the same checksum-versioned cache key.
- Added the permanent V2 non-regression gate: approved database sentinels, media/storage and relation verification, protected minimum counts, dedicated Playwright HTTP/browser regression checks, and Laravel error-log validation for the preproduction run.
- Validated the gate on preproduction: 13 real V2 sentinels, 3 PHP regression tests and 98 desktop/mobile Playwright checks pass; no lost relation/media, HTTP 500, browser failure or new Laravel exception was found.
- Restored the pending-restaurant preview action for V2 proposals without a legacy ID. It now uses a temporary signed `noindex,nofollow` front URL and is included in the regression sentinels.
- Fixed the pending preview rendering and route-cache deployment gap discovered by its new test. Preproduction now clears its route cache on route changes; the validation passes with 17 PHP checks and 108 Playwright checks.

- Correction de la migration d’utilisateurs legacy : les comptes WordPress `administrator` conservent désormais le rôle V2 `admin`, sans abaisser un rôle V2 existant lors d’une reprise.
- Sécurisation du bootstrap PHPUnit : les tests refusent désormais de démarrer hors environnement `testing` avec SQLite, afin qu’un cache de configuration de préproduction ne puisse jamais orienter des migrations de test vers MariaDB.
- Ajout du parcours public non indexable « Ajouter un restaurant » : formulaire Blade SSR en cinq étapes, sans compte, avec progression, panneau desktop et ergonomie mobile. Les propositions créent exclusivement une fiche `pending` et un contexte déposant privé ; elles ne sont jamais publiées automatiquement.
- Réutilisation de la Géoplateforme pour l’adresse structurée, le code INSEE, le GPS, la carte à marqueur déplaçable et la détection informative de doublons. Les liens proposés restent des destinations sortantes inactives et n’apparaissent jamais dans le HTML public.
- Ajout des validations serveur des options halal, horaires, e-mail et fichiers ; une couverture est obligatoire et la galerie est limitée à dix images JPEG/PNG/WebP. Le parcours préproduction complet passe sur desktop et mobile.
- Les titres SEO des restaurants sont maintenant générés par défaut sans suffixe de marque : nom, ville et première spécialité ; un titre SEO renseigné manuellement garde priorité.
- Ajout des directives SEO `meta robots` configurables par fiche restaurant, avec options Google actives, limites d’extraits/aperçus et date d’expiration ; les fiches `noindex` ou `none` sont retirées du sitemap.

## 2026-08-30

- Changed restaurant web enrichment to a Codex-researched workflow: Laravel reserves checkpoints and validates/applies structured evidence, while normal web navigation supplies the sources. No API key is required for normal batches.
- Ran the first 10-restaurant web-research pilot on preproduction. All restaurant writes were safely refused: confirmed/potential closures and conflicting evidence were retained in the review queue, with the complete internal CSV audit.
- Added field-level web-evidence confidence and `--retry-insufficient`, preserving the previous status in the comparative CSV so incomplete records can be revisited under improved search rules without advancing the batch cursor.
- Revisited the 43 insufficient batch records under the field-specific confidence policy: six placeholder descriptions were safely replaced from exact, recently crawled local pages; 37 records remain unchanged with explicit low-confidence reasons.
- Fixed web-enrichment CSV snapshots: persisted description before/after values and the applied flag now agree with the database, with regression coverage for the generated CSV.

- Added resumable restaurant web-enrichment checkpoints, CSV batch reports, conservative source matching and strict write guards for missing hours and placeholder descriptions. Closure/radiation signals are retained only for human review; an optional official Google Places adapter is disabled until credentials and budget are approved.

- Ajout de l’onglet « Médias » sur la fiche restaurant et de l’action « Prévisualiser » pour les restaurants en attente, qui utilise désormais le véritable gabarit front en `noindex,nofollow`, sans changement de statut ni exposition dans les parcours publiés.
- Simplification de l’onglet Localisation Filament : seules l’adresse structurée, la carte et les alertes utiles sont affichées. Les adresses/GPS historiques et les métadonnées techniques restent conservés sans modifier les règles de géocodage, proximité ou audit.
- Repasse après suppression manuelle des restaurants sans GPS : 7 683 restaurants actifs, aucun GPS manquant, aucun nouveau géocodage ni GPS existant modifié, et aucune référence restaurant orpheline détectée.
- Correction de la carte de localisation Filament : son initialisation attend désormais l’onglet visible, sa taille est recalculée à chaque affichage et son canevas est préservé lors des mises à jour Livewire. L’édition d’adresse ne nécessite plus d’enregistrement ni d’actualisation pour voir la carte.
- Clarification de la gestion des restaurants supprimés : onglets distincts « Restaurants » et « Corbeille », actions en masse cohérentes avec l’onglet courant, et restauration disponible uniquement dans la Corbeille.
- Remplacement de l’archivage des restaurants par une Corbeille : « Supprimer » réalise un soft delete réversible, la Corbeille permet la restauration, la suppression définitive individuelle ou en masse et son vidage après confirmation. Toutes ces opérations sont auditées ; les anciennes fiches archivées y sont transférées.
- Phase 6A.2 : géocodage massif idempotent des adresses structurées sans GPS. 13 coordonnées ajoutées automatiquement (10 housenumber, 3 street), `proximity_status=ELIGIBLE` lorsque cohérent, aucun GPS préexistant modifié.
- Phase 6A.1 : un déplacement de marqueur validé par un administrateur reste de précision `MANUAL` mais devient `ELIGIBLE` quand aucune anomalie ne subsiste. Une incompatibilité Geography reste `REVIEW_REQUIRED`. La provenance `admin_map` est maintenant distincte de la future proposition publique `public_map`.

## 2026-08-30

- Standardized public listing thumbnails: article cards, the three homepage restaurant cards and the three-column restaurant directory use fixed, responsive image frames with cropped images, so portrait source media can no longer change card height.
- Restored article-page featured media rendering from the existing V2 featured-media relation. The selected image is now visible above the article body and included in Article structured data; no media record or source file was changed.
- Corrected restaurant-detail hero delivery: it now selects the best available media variant and falls back to the original asset instead of requesting a non-existent 960 px variant.

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

## 2026-09-04

- Added the native V2 restaurant specialties Burger, Brunch and Grillades. They are available in directory filters, public submissions and administration; search suggestions no longer hide a specialty solely because it has no published restaurant yet.
- Added the specialty-image pipeline: normalized 1200×800 WebP sources are attached to V2 specialties and may be assigned as card-only fallback thumbnails to restaurants with no raster image.
- Removed the unused Mauricienne specialty after a V2-only relation audit confirmed zero associated restaurants.

## 2026-09-01

- Repaired only the 812 strictly verified V2 `address_line1` values that duplicated a matching historical `postcode + city` suffix. The raw address, structured postcode/city/code, GPS and geocoding/location status fields were not changed; 184 conflicting records remain untouched. The second execution corrected zero records.
- Centralized deterministic address-line parsing in `AddressLineParser`, used by consolidation, exception resolution and address suggestions. The repair command writes a persistent candidate report, refuses writes unless the expected cohort is found, processes safe ID chunks and verifies protected fields after every update.
- Following manual verification, removed only the explicit historical final postcode/city suffix from 180 additional `address_line1` values whose structured locality differed. Four malformed lines without such a visible suffix remain untouched.
- Completed 1,087 previously fully missing structured addresses from Géoplateforme using the raw historical address as query input. Existing raw addresses, GPS and qualification/status fields were preserved; 73 incomplete or imprecise provider cases remain unmodified.

## 2026-08-30

- Added a dependency-free local SVG icon set for restaurant services. All listed services now have a semantic visual marker on public restaurant cards and detail pages; the three domain-specific icons use the same 24px rounded-stroke language as the Lucide-derived set, and every icon remains paired with accessible text.

## 2026-08-29

- Added the targeted Phase 5.1 exception resolver and Filament “Adresse à traiter” filter. It processes explicit/non-contiguous IDs, preserves historical addresses and existing GPS, and records per-record reasons. Thirty-four of the 65 incomplete records were safely structured; unresolved foreign, ambiguous, empty and suspected-test records remain reviewable.
- Corrected the Phase 5 structured-address persistence path: administrative data is now written for `APPROXIMATE` records when available, `address_line1` is populated safely, and the batch is idempotent and targets only incomplete structured fields. Historical addresses and coordinates remain immutable. Added regression coverage for the O Sha-style forward/reverse street-number disagreement.
- Added the read-only Géoplateforme/BAN geocoding pilot, provider abstraction, bounded cache/rate limit, response parsing and no-write coverage. The representative 100-restaurant preproduction pilot produced no restaurant or geography changes.
- Added `data:audit-addresses`, a deterministic, read-only address/GPS audit with a test proving it does not modify V2 or legacy data. The preproduction run audited all 7,704 restaurants and produced the address/GPS report plus a 100-record sample; no geocoding, address/GPS correction, or geography change was made.

## 2026-08-27

- Audited and repaired migrated-data integrity on preproduction: restored 121 historical article and 90 page publication dates, added legacy attachment dates, retained V2 audit timestamps, and added Filament date displays for content, moderation, users, media and restaurants.
- Classified all 2,003 migrated geographic terms. Removed 32 manifest SQL-injection/fuzzing payloads that had no restaurant relationship; 1,961 valid location relationships were retained. Added idempotent reporting, future-import guards, admin validation and the geography “Sans restaurant” filter. Categories, features and editorial taxonomies had no matching anomaly.

- Completed the Filament 5 `/admin` migration: legacy `/bo` routes, controllers and Blade views are removed; settings, audit log, moderation, media, redirect, taxonomy and user modules are in the panel. Preproduction PHP suite (51 tests, 229 assertions) and Filament desktop/mobile Playwright validation pass.
- Added the secured V2 administration area, audit log and daily operational management screens.
- Added admin feature coverage for authorization, restaurant CRUD, moderation, editorial sanitization and sensitive audit filtering.
# 2026-08-30 — Phase 6A : composant d’adresse administrateur

- Ajout d’un service réutilisable de suggestions et de sélection d’adresse via l’abstraction Géoplateforme, avec cache fournisseur existant, minimum trois caractères et endpoint Laravel protégé/limité.
- Refonte de la localisation Filament : adresse historique consultable, adresse administrative structurée, qualité non éditable, carte Leaflet chargée à la demande et coordonnées protégées de la saisie brute.
- Les corrections manuelles de marqueur, les sélections et les entrées manuelles conservent la provenance, recalculent le statut de proximité et sont tracées dans l’audit existant. Les zones Geography et les doublons ne sont jamais modifiés automatiquement.
## 2026-08-31

- Restored 892 missing V2 `restaurant_media.media_asset_id` links through an idempotent, conflict-aware repair command that reads only V2 tables. No source file or legacy connection was used; O Sha’s six images and the no-op second pass were validated on preproduction.
- Ajout de l’audit de recherche web des enrichissements restaurants : nombre de recherches, requêtes et sources rejetées, exportés dans le CSV interne.
- Ajout de `restaurants:web-enrich --ids=…` pour reprendre exactement des checkpoints non continus sans sélectionner un nouveau batch.
- Reprise préproduction du lot existant de 98 restaurants : écritures limitées aux descriptions éligibles et aux horaires absents; les alertes de fermeture restent en revue humaine.
- Clôture du batch web-enrichment réservé des restaurants 172 à 271 et génération de son rapport CSV consolidé privé.
# 2026-09-02

- Refonte de la recherche publique en composant SSR réutilisable à deux champs : villes issues de `city_name`, autocomplete limité de villes/spécialités/restaurants, priorité de ville, navigation directe vers la fiche et proximité déclenchée uniquement après choix volontaire.
- Les recherches combinées conservent le moteur de filtres et la politique `noindex,follow`; une ville seule mène à la page `/restos/{slug}` correspondante.
