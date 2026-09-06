# SEO

## Core rules
- Preserve useful current URLs.
- Build deterministic URL mapping from WordPress -> V2.
- Generate canonical, robots directives, sitemaps, breadcrumbs and correct status codes natively.
- Avoid indexable combinations of arbitrary filters; most query/filter combinations should be noindex unless explicitly promoted to a quality landing page.
- Structured data derives from visible, factual content.

## Frozen public URL contract (2026-08-26)
- Canonical public URLs are HTTPS, on the canonical host, with no trailing slash except `/`.
- Restaurants use `/resto/{slug}`; category and service pages use `/specialites/{slug}` and `/service/{slug}`. Published editorial content uses its root slug: `/{slug}`. The legacy Geography tables do not resolve city pages; legacy city slugs without an identical structured-city slug redirect through the application redirect engine.
- A page/article slug collision is a migration anomaly to resolve before cutover; it must never produce two indexable URLs.
- `/sitemap.xml` contains only indexable canonical URLs. `robots.txt` exposes it in production only; preproduction remains disallow-all/noindex.
- Arbitrary query parameters canonicalize to the parameter-free URL and are `noindex,follow`. Pagination is `?page=N`; page 1 is canonicalized to the root and pages above 1 are `noindex,follow` until a curated collection is approved.

## Structured data and facets

### Facettes ville + spécialité

Une facette SEO est une configuration sparse identifiée par le `city_code` INSEE canonique d'une commune et une spécialité existante. La table `city_specialty_seo_pages` ne contient jamais de matrice ville × spécialité : une ligne est créée seulement lorsqu'un administrateur ouvre la facette ou lui donne un override. Toute combinaison non configurée, et toute configuration `closed`, reste fermée par défaut : elle ne produit pas de landing publique, de lien de maillage ni d'entrée sitemap. Les filtres de l'annuaire restent utilisables indépendamment de cet état et leurs URLs conservent `noindex,follow`.

Une configuration `open` publie exclusivement `/restos/{slug-ville-précis}/{slug-specialite}`. Le slug ville provient du resolver actuel : un homonyme emploie donc son suffixe départemental (`/restos/saint-denis-93/burger`). Le listing joint les relations `restaurant_category` existantes et ne duplique aucune relation. Sa canonical est elle-même, ses pages paginées sont `noindex,follow`, et l'URL principale est `index,follow` et sitemapable.

Sans override, H1, title et meta description sont générés à partir de la ville, de la spécialité et du nombre réel de restaurants. Les contenus haut/bas restent vides tant qu'ils ne sont pas saisis. Le fil d'Ariane visible et JSON-LD est `Accueil > Restaurants > région > département > ville > spécialité`, en réutilisant les règles de niveaux mutualisés Paris/DOM. Une page ville peut afficher un bloc SSR léger de ses seules facettes ouvertes ; il ne remplace jamais les filtres UX ni le bloc « Villes aux alentours ». Dans le BO, le badge « Ouverte » est le lien vers cette URL publique ; une facette fermée n’a pas de lien front.

## Pages villes SEO

L'identité technique d'une commune est son `city_code` INSEE canonique ; `city_name` reste le seul nom d'affichage. Paris, Lyon et Marseille absorbent leurs codes d'arrondissements dans leur code communal. Une page ville dont le nom est unique utilise `/restos/{Str::slug(city_name)}`. Pour chaque groupe de communes distinctes partageant ce slug, chaque page précise utilise `/restos/{slug}-{code_departement}` ; le slug non qualifié devient une page de désambiguïsation HTTP 200, `noindex,follow`, canonique sur elle-même et absente du sitemap. Elle ne mélange jamais les restaurants et présente seulement les communes, département, région et liens précis.

`city_seo_pages` est identifié par `city_code` et ne contient que les overrides éditoriaux/SEO. Les éventuels overrides historiques sont migrés vers leur code canonique sans créer d'override par défaut. Une page est indexable automatiquement au seuil global configurable, sauf override `forced_open` ou `forced_closed`; les pages fermées restent accessibles, `noindex,follow`, canoniques sur elles-mêmes et hors sitemap. Tous les liens internes générés vers une ville utilisent le slug précis.

### Villes aux alentours

Les pages villes peuvent rendre, après la pagination, un bloc SSR « Villes aux alentours ». Il relie exclusivement les pages villes SEO ouvertes, hors commune courante, triées par distance géographique croissante et limitées par les réglages globaux `city_nearby_radius_km` (30 km par défaut, borné à 250 km) et `city_nearby_maximum` (15 par défaut, borné à 30). Le département et la région n'interviennent pas dans le calcul ; un homonyme utilise toujours son slug précis et aucune désambiguïsation ne peut être liée. Si aucune commune éligible ne relève du rayon, le bloc est absent.

Les points de référence sont stockés localement, un point officiel par `city_code` canonique dans `city_reference_points`. La commande de maintenance explicite `city-reference-points:sync` récupère le champ `centre` de l'API française `geo.api.gouv.fr/communes`, ne conserve que les communes ayant une page ville publiée et n'est jamais appelée par une requête publique. Les coordonnées de restaurants ne servent pas de centre de commune. Les résultats sont mis en cache douze heures avec une génération invalidée à chaque modification de rayon, de maximum, de statut SEO ou de commune publiée.

## Pages administratives

Le référentiel administratif versionné résout localement `city_code → département → région`, sans champ géographique supplémentaire sur `restaurants`, appel distant, legacy Geography ni filtrage PHP massif. Il gère les codes 2A/2B, DOM/COM et les arrondissements municipaux. Régions et départements utilisent aussi `/restos/{slug}` et sont indexables/sitemapables au même seuil global que les villes ; une pagination reste `noindex,follow`.

Un département garde son slug court, sauf si ce slug est déjà la page d'une commune distincte : la commune conserve l'URL existante et le département reçoit automatiquement `-{code_departement}` (par exemple `/restos/indre-36`). Paris reste une page unique parce que commune et département y correspondent au même territoire. Les collectivités ultramarines où région et département partagent le même territoire ne produisent qu'une URL canonique.

Chaque breadcrumb visible commence par `Accueil > Restaurants` (`/restaurants`) et le JSON-LD `BreadcrumbList` reproduit exactement ces mêmes niveaux. Une ville précise ajoute région, département puis ville, sans doublon pour Paris ni les collectivités territoriales fusionnées. Une fiche restaurant réutilise strictement ce même résultat des resolvers : ses niveaux géographiques pointent vers les pages canoniques existantes et son restaurant est l’unique élément courant non cliquable ; son `BreadcrumbList` conserve exactement les mêmes niveaux et URLs.
- JSON-LD is native and derives only from visible facts. A restaurant emits exactly one `AggregateRating`, inside its `Restaurant` object, only when approved reviews are visible. External restaurant URLs never appear in public HTML or JSON-LD.
- Search, sort, distance, price, amenity and multi-filter URLs are discovery controls, not indexable landing pages. They remain noindex and stay out of sitemaps unless explicitly promoted with a stable canonical path and unique content.

## Restaurant links
External website/social/reservation URLs must not be present in public HTML or JSON-LD. Render buttons that use an opaque server-side outbound route/token and then redirect. Do not leak destination URLs through data attributes, inline scripts, JSON blobs or accessible markup.

## Redirect testing
Before cutover, crawl/check all known historical URLs and redirect rules. Report unexpected 404/5xx, chains, loops and destination mismatches.

Run `seo:audit-legacy-urls` after the full migration to write the deterministic content/listing URL mapping and fail on published page/article slug collisions. Run `redirects:audit` after importing historical rules, then crawl the combined report on preproduction before cutover.

## Approved technical-page cleanup (2026-08-26)
`/blog` remains published, indexable and in the sitemap. `/mon-compte` remains published and accessible, but is explicitly `noindex,follow` and excluded from the sitemap; neither is redirected. The migrated records for `/home`, `/payment-success-2`, `/blog-2`, `/erreur-paiement`, `/payment-checkout`, `/payment-fail`, `/payment-success`, `/submit-listing` and `/hello` are retained for reconciliation but marked `redirected`, excluded from the sitemap and covered by an exact 301. `/blog-2` redirects to `/blog`; all remaining paths redirect to `/` under the approved ultimate fallback policy.
