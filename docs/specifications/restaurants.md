# Restaurants

## Core entity
Restaurant records migrate from ListingPro listings and keep `legacy_wp_id`.
Suggested core data includes name/slug/description/status/owner, address/postcode/city/geolocation, contact info, pricing, halal/certification information, social/website URLs (private from crawlable public markup), media, hours, categories/features, verification/claim status and SEO fields.

## Geography
Normalize legacy flat locations into useful region/department/city/postcode relationships where data allows. Keep geocoordinates and support spatial/distance queries using MariaDB capabilities.

## Controlled legacy migration
- The pilot schema uses `restaurants`, `categories`, `features`, hierarchical `locations`, their pivots, `restaurant_opening_hours` and `restaurant_media`.
- Each imported entity keeps its WordPress identifier (`legacy_wp_id`, `legacy_term_id` or `legacy_attachment_id`).
- `legacy:migrate-restaurants` is read-only on `legacy_wp`; its default and `--dry-run` modes write no V2 data. `--apply --limit=10` is the only currently supported write mode.
- Missing gallery, hours, coordinates or location terms are reported record-by-record. Hours are only persisted when ListingPro contains an enabled, parseable value; no schedule is inferred.
- The 2026-08-25 legacy audit found no enabled, parseable ListingPro schedule across 7,704 listings. `restaurant_opening_hours` remains ready for future/admin-entered schedules, but no legacy record is inserted until a real source format exists.

## Public page
- Fast server-rendered content.
- Core info and opening hours.
- categories/features.
- Services are rendered as local inline SVG icons plus their text labels. The icon set has no external dependency; three domain-specific icons (halal certification, prayer room and decor) follow the same 24px rounded-stroke style as the Lucide-derived icons.
- reviews/aggregate where valid.
- map/geographic JS loaded only when needed.
- external outbound actions obfuscated server-side.

## Admin/owner
Create/edit/moderate records, media, hours, categories/features and ownership/claims with clear auditability.

## SEO par fiche
Le panneau SEO permet de sélectionner les directives `robots` actives (`all`, `noindex`, `nofollow`, `none`, `nosnippet`, `indexifembedded`, `noimageindex`, `notranslate`) et de paramétrer les valeurs qui nécessitent un argument : longueur d’extrait, aperçu image, aperçu vidéo et date `unavailable_after`. Les directives sont rendues dans la balise `meta name="robots"` de la fiche ; une fiche `noindex` ou `none` est exclue du sitemap.
