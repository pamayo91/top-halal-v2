# Architectural & Product Decisions

## 2026-08-24

### D001 — Leave WordPress entirely
WordPress is legacy storage only. Top-Halal V2 is not a WordPress theme/plugin project and must not require WordPress at runtime.

### D002 — Laravel monolith
Use a Laravel 13 monolith with server-rendered Blade for public pages to minimize operational and frontend complexity.

### D003 — Apache + MariaDB + no Docker
Use the existing dedicated-server stack: Apache, PHP 8.4, MariaDB 11.4.x, SSH and Cron. Do not introduce Docker.

### D004 — Application-managed SEO redirects
Migrate content-path redirects from the old `.htaccess` into the application. Keep host/protocol/infrastructure redirects in Apache. Support exact and advanced/pattern redirects with validation and caching.

### D005 — Comment URLs forbidden
New comments cannot contain URLs/links. Enforce server-side and safely render plain text.

### D006 — Restaurant external links obfuscated
External URLs must not be exposed in public HTML/JSON-LD. Use server-side opaque outbound actions from buttons.

### D007 — AI provenance internal, disclosure configurable
Always keep generation provenance internally. Public disclosure may be globally/per-article enabled or hidden.

### D008 — AI vendor abstraction
AI workflows depend on an internal provider interface rather than one vendor SDK/API.

### D009 — CWV is a release gate
Performance must be designed in from the first templates/components and validated on preproduction.

### D010 — Living specification
Codex must update project status/specifications as it develops and tests features.

### D011 — Controlled V2 media pipeline
Media is reconciled through explicit, idempotent attachment pilots. V2 stores originals privately by checksum, generates local responsive WebP variants for supported raster formats, and never renders a legacy upload URL. Missing files and unselected inline media remain auditable debt rather than inferred or silently discarded.
