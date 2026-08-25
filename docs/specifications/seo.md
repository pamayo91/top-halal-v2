# SEO

## Core rules
- Preserve useful current URLs.
- Build deterministic URL mapping from WordPress -> V2.
- Generate canonical, robots directives, sitemaps, breadcrumbs and correct status codes natively.
- Avoid indexable combinations of arbitrary filters; most query/filter combinations should be noindex unless explicitly promoted to a quality landing page.
- Structured data derives from visible, factual content.

## Restaurant links
External website/social/reservation URLs must not be present in public HTML or JSON-LD. Render buttons that use an opaque server-side outbound route/token and then redirect. Do not leak destination URLs through data attributes, inline scripts, JSON blobs or accessible markup.

## Redirect testing
Before cutover, crawl/check all known historical URLs and redirect rules. Report unexpected 404/5xx, chains, loops and destination mismatches.
