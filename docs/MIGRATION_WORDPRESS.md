# Legacy WordPress / ListingPro Migration

## Source handling
- Place `meyo5199_th.sql.gz` under `legacy/` locally/on authorized preprod storage.
- The dump is Git-ignored and must never be committed.
- Import it into a separate legacy MariaDB database/user with read-only application credentials after import.
- Migration code queries legacy tables; it must never parse the whole SQL dump into AI/model context.

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
