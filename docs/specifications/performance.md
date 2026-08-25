# Performance / Core Web Vitals

## Release targets
Representative mobile PageSpeed/Lighthouse Performance >= 95; target 98–100 where realistic.
Internal CWV budgets: LCP < 1.8s, INP < 150ms, CLS < 0.05.

## Architecture principles
- Server-rendered HTML via Blade.
- Minimal JS; progressive enhancement.
- No heavy SPA framework for public site.
- Local/system fonts preferred; avoid remote font render blocking.
- AVIF/WebP responsive images with correct dimensions.
- Lazy-load below-the-fold media, never the LCP image.
- Preload/priority only when measured and justified.
- Strong browser/server caching.
- Brotli/Gzip as server supports.
- OPcache enabled and Laravel optimized in deployed environments.
- Stable reserved dimensions for ads/media to prevent CLS.
- Avoid third-party scripts by default.

## Validation pages
At minimum:
- homepage;
- representative city/listing page;
- representative restaurant page;
- representative article;
- search/filter flow.

Performance regression must be diagnosed before milestone completion.
