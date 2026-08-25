# WordPress / ListingPro Mapping

## Inventory Snapshot
- Source database: `meyo5199_th`
- WordPress prefix: `tp_`
- Access mode: dedicated `legacy_wp` connection, SELECT-only
- Tables: 41
- Listings: 7,633 published, 70 pending, 1 reported
- Articles: 121 published
- Pages: 90 published, 1 trash
- Attachments: 2,239
- Users: 545
- ListingPro reviews: 84 `lp-reviews` posts plus 1 review comment
- Legitimate editorial comments: 713 approved, 15 pending
- Spam comments: 19,466, excluded from migration

## Source To Target Mapping
- `tp_posts.post_type = listing` -> `restaurants`
- `tp_postmeta.lp_listingpro_options` and `lp_listingpro_options_fields` -> restaurant detail fields after parser-specific normalization
- `tp_terms` + `tp_term_taxonomy.taxonomy = listing-category` -> restaurant categories
- `tp_terms` + `tp_term_taxonomy.taxonomy = features` -> restaurant features
- `tp_terms` + `tp_term_taxonomy.taxonomy = location` -> normalized geography candidates
- `tp_posts.post_type = attachment` -> media records and file reconciliation

### Restaurant pilot implementation
- V2 uses compact `categories`, `features` and hierarchical `locations` tables rather than separate region/department/city tables. A location parent preserves legacy WordPress hierarchy where available.
- `restaurant_category`, `restaurant_feature` and `restaurant_location` preserve many-to-many ListingPro terms.
- `restaurant_opening_hours` stores recognized ListingPro schedules and retains only the technical legacy key alongside parsed times; no raw contact data is exported in reports.
- `restaurant_media` records gallery attachment IDs and legacy paths as pending reconciliation, without copying uploads or exposing legacy source URLs.
- The validated pilot selection is `13453`, `13454`, `13455`, `13456`, `13457`, `13465`, `13567`, `21293`, `21333`, `22184`. It covers published, pending, claimed, multiple-category, multiple-feature, gallery, GPS and unusual ListingPro metadata cases.
- The selected legacy records did not contain enabled/parseable business hours. This is an explicit pilot anomaly, not an inferred schedule.
- The complete opening-hours audit found `business_hours = null` on 3,168 ListingPro option rows and an empty string on 4,536; there are no non-empty schedules in the 7,704 legacy listings. A single time-like value is in an email field and is classified as hostile data, never as an opening hour. No complementary hours sample exists to migrate.
- `tp_posts.post_type = post` -> articles
- `tp_posts.post_type = page` -> pages

### Editorial pilot implementation
- Pilot articles: `27`, `104`, `295`, `10697`, `11461`; pilot pages: `4`, `5`, `38`, `10430`, `11554`.
- Yoast metadata has precedence when present; AIOSEO is reported for later reconciliation. Legacy JSON-LD is not imported.
- Visual Composer rows/columns are removed, column text retained, messages become semantic asides, sidebars are removed and raw HTML is decoded then sanitized. Scripts and non-allowlisted iframes are removed.
- approved/pending `tp_comments.comment_type = comment` -> editorial comments
- `tp_posts.post_type = lp-reviews` -> restaurant reviews
- ListingPro claim posts `lp-claims` -> future claim records
- Yoast postmeta/indexable tables -> SEO metadata candidates
- AIOSEO postmeta rows -> secondary SEO metadata candidates

## Initial Business Schema Proposal
- `restaurants`: identity, slug, status, legacy IDs, owner/user link, address fields, geo coordinates, contact fields, halal/certification fields, moderation and publication timestamps.
- `restaurant_details`: parsed ListingPro options, hours, pricing, private external URLs, social/contact metadata not exposed directly in public HTML.
- `taxonomies`: category, feature and location vocabularies with `legacy_term_id`.
- `restaurant_taxonomy`: restaurant to category/feature/location relationships with legacy relationship references when useful.
- `media`: attachment metadata, legacy attachment IDs, source paths, processing status and alt text.
- `articles`: title, slug, status, dates, author link, clean content, excerpt, SEO fields, source/provenance.
- `pages`: slug, status, dates, clean content or constrained blocks, SEO fields.
- `comments`: editorial comment tree, moderation status, private email, safe text content, `legacy_wp_comment_id`.
- `reviews`: ListingPro review records separate from comments, rating, moderation status, author/user reference, legacy review ID.
- `review_responses`: owner/admin replies when present or added later.
- `redirects`: application-managed imported redirects, separate from Apache infrastructure redirects.
- `migration_runs` and `migration_anomalies`: deterministic reconciliation counts, skipped records and examples.

## Anomalies And Special Data
- 63 posts contain Visual Composer shortcodes and need conversion or anomaly reporting.
- 14,423 comments contain URL-like content; legacy comments may be preserved as safe text, but new comments must reject URLs.
- 19,466 spam comments must not migrate.
- ListingPro stores important structured data in serialized/meta option fields, especially `lp_listingpro_options`.
- Yoast data is present in postmeta and dedicated Yoast tables.
- A small amount of AIOSEO postmeta exists and should be reconciled after Yoast precedence is defined.
- No orphan postmeta or orphan term relationship rows were detected in the inventory.

## Limited Test Migration Plan
The first migration implementation must support a dry-run/report mode and then a tiny executable sample. Do not run a massive import before sample validation.

Sample candidates:
- Restaurants: legacy post IDs `13453`, `13454`, `13455`, `13456`, `13457`, `13458`, `13459`, `13460`, `13461`, `13462`
- Articles: legacy post IDs `27`, `104`, `217`, `295`, `10697`
- Pages: legacy post IDs `4`, `5`, `6`, `7`, `8`
- Comments with reply coverage candidates: legacy comment IDs `2`, `3`, `4`, `5`, `6`, `7`, `8`, `9`, `11`, `13`
- Restaurant reviews: legacy review post IDs `21098`, `21099`, `21100`, `21101`, `21102`, `21103`, `21104`, `21105`, `21106`, `21107`

Validation criteria:
- preserve `legacy_wp_id` / `legacy_wp_comment_id`;
- keep legacy source read-only;
- report source, inserted, updated/skipped and anomaly counts;
- verify URL preservation/redirect decisions separately;
- verify comments are rendered safely and remain URL-free for new submissions;
- verify reviews are not mixed with editorial comments.
