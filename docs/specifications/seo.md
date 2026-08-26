# SEO

## Core rules
- Preserve useful current URLs.
- Build deterministic URL mapping from WordPress -> V2.
- Generate canonical, robots directives, sitemaps, breadcrumbs and correct status codes natively.
- Avoid indexable combinations of arbitrary filters; most query/filter combinations should be noindex unless explicitly promoted to a quality landing page.
- Structured data derives from visible, factual content.

## Frozen public URL contract (2026-08-26)
- Canonical public URLs are HTTPS, on the canonical host, with no trailing slash except `/`.
- Restaurants use `/resto/{slug}`; geographic pages use `/restos/{location}`; category and service pages use `/specialites/{slug}` and `/service/{slug}`. Published editorial content uses its root slug: `/{slug}`.
- A page/article slug collision is a migration anomaly to resolve before cutover; it must never produce two indexable URLs.
- `/sitemap.xml` contains only indexable canonical URLs. `robots.txt` exposes it in production only; preproduction remains disallow-all/noindex.
- Arbitrary query parameters canonicalize to the parameter-free URL and are `noindex,follow`. Pagination is `?page=N`; page 1 is canonicalized to the root and pages above 1 are `noindex,follow` until a curated collection is approved.

## Structured data and facets
- JSON-LD is native and derives only from visible facts. A restaurant emits exactly one `AggregateRating`, inside its `Restaurant` object, only when approved reviews are visible. External restaurant URLs never appear in public HTML or JSON-LD.
- Search, sort, distance, price, amenity and multi-filter URLs are discovery controls, not indexable landing pages. They remain noindex and stay out of sitemaps unless explicitly promoted with a stable canonical path and unique content.

## Restaurant links
External website/social/reservation URLs must not be present in public HTML or JSON-LD. Render buttons that use an opaque server-side outbound route/token and then redirect. Do not leak destination URLs through data attributes, inline scripts, JSON blobs or accessible markup.

## Redirect testing
Before cutover, crawl/check all known historical URLs and redirect rules. Report unexpected 404/5xx, chains, loops and destination mismatches.

Run `seo:audit-legacy-urls` after the full migration to write the deterministic content/listing URL mapping and fail on published page/article slug collisions. Run `redirects:audit` after importing historical rules, then crawl the combined report on preproduction before cutover.
