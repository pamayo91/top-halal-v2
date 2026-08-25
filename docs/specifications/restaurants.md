# Restaurants

## Core entity
Restaurant records migrate from ListingPro listings and keep `legacy_wp_id`.
Suggested core data includes name/slug/description/status/owner, address/postcode/city/geolocation, contact info, pricing, halal/certification information, social/website URLs (private from crawlable public markup), media, hours, categories/features, verification/claim status and SEO fields.

## Geography
Normalize legacy flat locations into useful region/department/city/postcode relationships where data allows. Keep geocoordinates and support spatial/distance queries using MariaDB capabilities.

## Public page
- Fast server-rendered content.
- Core info and opening hours.
- categories/features.
- reviews/aggregate where valid.
- map/geographic JS loaded only when needed.
- external outbound actions obfuscated server-side.

## Admin/owner
Create/edit/moderate records, media, hours, categories/features and ownership/claims with clear auditability.
