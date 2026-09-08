# Articles & Pages

## Articles
Store clean HTML/content structure, title/slug/excerpt/status/author/media/SEO metadata/publication dates and source type (`manual`, `ai`, `imported`).
Approved public comments are displayed from newest to oldest.

## Pages
No page-builder dependency. Use a constrained set of lightweight content blocks when structured layout is required.

## Migration
- Preserve useful URLs and publication dates.
- Convert/remove legacy shortcodes.
- Preserve SEO metadata where relevant.
- Report unsupported content fragments.

## AI visibility
AI source/provenance stays internal even when public disclosure is disabled.

## Sidebar éditoriale partagée

Les Articles et Pages utilisent le même moteur Blade SSR de sidebar. Elle est active par défaut pour un Article et inactive par défaut pour une Page ; une Page doit l’activer explicitement dans Filament. Les deux configurations globales (`editorial_sidebar_articles`, `editorial_sidebar_pages`) sont séparées et les overrides par contenu restent sparse.

Les blocs disponibles sont : sommaire, recherche restaurant, restaurants liés, articles liés, signalement, proximité, restos à la une, explorer aussi, partage et contact. Aucun bloc sans données exploitables n’est rendu. Le sommaire construit des ancres déterministes H2/H3. Sur mobile, seul le sommaire précède le contenu ; les autres compléments le suivent.

La présentation publique utilise une colonne éditoriale principale et une rail secondaire d’environ 70/30 sur desktop. Les contenus liés utilisent les variantes média V2 compactes lorsqu’elles existent, avec un fallback de hauteur stable. Le CTA de proximité ne duplique pas le formulaire de recherche : sa géolocalisation navigateur reste volontaire, déclenchée uniquement après clic, et son fallback mène au parcours de recherche existant.
## Inline legacy media debt

During the controlled editorial pilot, direct `top-halal.fr/wp-content` and `top-halal.fr/wp-contenu` inline images are removed from stored V2 HTML rather than being rendered from WordPress. `legacy:audit-inline-media` records the legacy source URL/path, content type and ID, ordinal position, nearby context and resolved attachment ID when available. This is a media-reconciliation backlog only: no physical file is copied in this phase.
