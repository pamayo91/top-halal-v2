# Legacy WordPress / ListingPro Migration

## Source handling
- Use the existing live Top-Halal MariaDB database `meyo5199_th` as the legacy source through a dedicated read-only database user.
- The legacy application connection must never use the current WordPress credentials because they may have write permissions.
- The dedicated legacy user `meyo5199_top_halal_legacy_readonly` must have only `SELECT` on `meyo5199_th`.*.
- The WordPress table prefix is `tp_`.
- `meyo5199_th.sql.gz` remains under `legacy/` only as a safety/reference snapshot for now.
- The dump is Git-ignored and must never be committed.
- Do not import or duplicate the dump unless the migration plan is explicitly changed.
- Migration code queries legacy tables; it must never parse the whole SQL dump into AI/model context.
- No Laravel migration may target the legacy connection.
- No business command may write to the legacy database.

## Read-only verification
Before any legacy inventory or migration planning command uses the legacy connection, verify:
- `SELECT` succeeds;
- `INSERT` is refused;
- `UPDATE` is refused;
- `DELETE` is refused;
- `ALTER` is refused;
- `DROP` is refused.

Preproduction verification on 2026-08-25 confirmed all of the above. No WordPress import command has been run.

## Core mapping
- WordPress posts of ListingPro listing type -> restaurants.
- ListingPro listing meta/options -> restaurant details.
- listing categories/features/locations -> normalized V2 taxonomies/geography.
- `lp-reviews` and rating metadata -> restaurant reviews.
- legitimate WordPress comments -> editorial comments; exclude spam.
- WordPress users -> V2 users as applicable.
- posts/articles -> articles.
- pages -> pages.
- attachments/media -> media records and copied/reprocessed media.
- Yoast/AIOSEO/current permalink data -> SEO fields and URL reconciliation.
- old redirects -> redirect manager.

## Reconciliation requirements
Every migration command must report:
- source eligible count;
- inserted count;
- updated/skipped count;
- anomaly count;
- examples/IDs for anomalies.
No silent data loss.

## Content cleanup
Detect and convert Visual Composer/ListingPro shortcodes. Unsupported fragments go to an anomaly report and must not silently render raw shortcode syntax in V2.

## Cutover
Perform full migration to staging, then a delta migration immediately before production cutover so late comments/articles/reviews/users are not lost.
