# Articles & Pages

## Articles
Store clean HTML/content structure, title/slug/excerpt/status/author/media/SEO metadata/publication dates and source type (`manual`, `ai`, `imported`).

## Pages
No page-builder dependency. Use a constrained set of lightweight content blocks when structured layout is required.

## Migration
- Preserve useful URLs and publication dates.
- Convert/remove legacy shortcodes.
- Preserve SEO metadata where relevant.
- Report unsupported content fragments.

## AI visibility
AI source/provenance stays internal even when public disclosure is disabled.
## Inline legacy media debt

During the controlled editorial pilot, direct `top-halal.fr/wp-content` and `top-halal.fr/wp-contenu` inline images are removed from stored V2 HTML rather than being rendered from WordPress. `legacy:audit-inline-media` records the legacy source URL/path, content type and ID, ordinal position, nearby context and resolved attachment ID when available. This is a media-reconciliation backlog only: no physical file is copied in this phase.
