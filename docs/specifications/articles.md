# Articles & Pages

## Articles
Store clean HTML/content structure, title/slug/excerpt/status/author/media/SEO metadata/publication dates and source type (`manual`, `ai`, `imported`).
Approved public comments are displayed from newest to oldest.

Les tableaux HTML présents dans le contenu public des Articles et Pages sont ciblés uniquement sous `.prose` : bordures fines séparées, cellules espacées, séparateurs internes et deux coins supérieurs arrondis via les tokens existants. Sur mobile, les tableaux larges défilent horizontalement dans leur propre conteneur sans élargir la page.

Chaque Article et Page possède le booléen `comments_enabled`, activé par défaut. Lorsqu’il est fermé, les commentaires publiés restent rendus, mais tous les CTA et formulaires de nouvelle contribution (y compris les réponses) disparaissent ; le serveur refuse aussi toute soumission directe ou contribution différée. Si aucun commentaire publié n’est rendu, le bloc commentaires entier est absent.

The back-office RichEditor supports legitimate nested editorial lists. Livewire accepts property paths up to 20 levels deep for this content; its other payload limits remain in force. Articles and Pages share the editorial RichEditor extension: its link dialogue supports combinable `nofollow`, `sponsored` and `ugc` `rel` tokens, plus the native `_blank` option. Existing non-SEO `rel` tokens are retained when a link is edited, while no automatic rewrite is applied to untouched legacy links. The toolbar is CSS-sticky below the measured Filament top bar, with an opaque surface and responsive wrapping.

Articles and Pages also share the `Code source` toolbar action. It opens the current unsaved TipTap HTML in a monospace textarea; `Annuler` leaves the editor unchanged and `Appliquer` replaces the editor immediately through the same `ContentSanitizer` used at save time. The action therefore cannot introduce scripts, event handlers or `javascript:` URLs that the normal editorial save path would reject.

Les catégories et tags éditoriaux migrés restent des métadonnées internes/de-conciliation et peuvent alimenter le petit libellé non cliquable d’une carte d’article. Le listing public `/blog` ne rend aucune navigation ni filtre de catégories ; il présente directement sa grille d’articles. Les données et relations de taxonomie ne sont pas supprimées pour cette règle de présentation.

## Pages
No page-builder dependency. Use a constrained set of lightweight content blocks when structured layout is required.

For Pages and Articles, the slug is generated from the title only while creating a new record. A later title change never changes the slug or public URL. Administrators may still explicitly change a slug in the back-office: the application creates an exact 301 from the previous URL to the new URL, visible in `Redirections`. Cross-content duplicates, redirect-source conflicts and redirect loops are rejected. Existing stored URLs are never rewritten by automatic generation.

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

Les libellés des cartes de sidebar ne sont pas des titres H2 afin de préserver le plan sémantique du contenu éditorial. Dans le sommaire, seuls les H2 reçoivent une numérotation continue ; les H3 restent des liens indentés sans numéro.

Sur desktop, un sommaire réellement long conserve sa liste SSR complète en haut de lecture, limitée à 68 vh avec défilement interne si nécessaire et sans contrôle supplémentaire. Après le début de lecture, une amélioration JavaScript légère le compacte en indiquant la section en cours et un bouton `Afficher le sommaire`; ce bouton redéploie la liste. Dans cet état sticky déployé, le seul contrôle est le petit `Réduire ↑` aligné à droite du titre. Les petits sommaires et le rendu mobile gardent leur présentation existante, y compris sans JavaScript.
## Inline legacy media debt

During the controlled editorial pilot, direct `top-halal.fr/wp-content` and `top-halal.fr/wp-contenu` inline images are removed from stored V2 HTML rather than being rendered from WordPress. `legacy:audit-inline-media` records the legacy source URL/path, content type and ID, ordinal position, nearby context and resolved attachment ID when available. This is a media-reconciliation backlog only: no physical file is copied in this phase.

## Corbeille éditoriale

Articles et Pages utilisent `SoftDeletes` et une corbeille Filament dédiée. Lorsqu’un contenu publié est déplacé vers la corbeille, l’administrateur choisit le comportement de son ancienne URL : 301/302 avec destination, ou 404/410 sans destination. La règle automatique est identifiée par `origin=editorial_deletion`, `related_type` et `related_id`. Une restauration désactive uniquement cette règle automatique après vérification des collisions de slug et des règles concurrentes ; une suppression définitive conserve la règle SEO. Les brouillons jamais publiés n’obtiennent aucune règle.
