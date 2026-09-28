# Recherche restaurants

`/restaurants` est une page de découverte SSR. Les paramètres `q`, `ville`, `categories[]`, `features[]`, `lat` et `lng` servent exclusivement à la recherche et restent `noindex,follow`, conformément à la politique SEO.

- La recherche textuelle porte sur le nom et la ville réellement migrés.
- La localisation sélectionnée est toujours un `city_code` INSEE. Elle est résolue depuis `commune_references`, le référentiel local versionné de communes officielles ; les restaurants publiés ne limitent jamais les suggestions.
- `city_name` ne sert qu'au libellé. Les homonymes indiquent leur département, et la recherche normalise accents, casse, espaces, tirets et apostrophes usuelles de façon déterministe côté serveur.
- Les catégories et services utilisent les relations V2 ; plusieurs choix sont combinés de façon restrictive.
- La pagination conserve les filtres mais reste non indexable au-delà de la première page.
- L’état de recherche est normalisé avant la requête : `localisation + spécialités + services + texte + pagination`. La localisation reste active quand un autre critère change. Une ville (`city_code`) et la proximité (`lat`/`lng`) sont mutuellement exclusives : le choix explicite d’« Autour de moi » retire la ville, et la sélection explicite d’une ville retire les coordonnées.
- « Autour de moi » applique côté serveur un rayon fixe de 30 km, défini une seule fois dans `config/restaurant-search.php`. Une boîte englobante réduit la requête avant le calcul exact de distance ; aucun résultat hors rayon n’est présenté, ni remplacé par Paris ou l’annuaire global.
- « Autour de moi » demande explicitement la géolocalisation navigateur après clic, transmet les coordonnées par POST puis redirige vers une URL de résultat. Aucune demande de position n’est faite au chargement.
- La distance est calculée côté MariaDB avec les coordonnées réellement renseignées ; les restaurants sans coordonnées ne sont pas présentés dans ce résultat.

## Recherche publique à deux champs

L’accueil et l’annuaire réutilisent le même composant Blade SSR : une localisation et une spécialité ou un restaurant. L’annuaire s’ouvre sans ville sélectionnée (`Ville ou localisation`) ; l’accueil conserve son raccourci explicite Paris (`75056`). Lorsqu’un visiteur modifie le libellé, le code INSEE caché est immédiatement effacé : un texte non sélectionné ne peut donc jamais réutiliser Paris. L’autocomplete interroge l’index local après deux caractères ; aucune API externe n’est appelée pendant la recherche.

Une commune officielle sans restaurant mène à `/restaurants?city_code=…`, en `noindex,follow`, avec un état vide explicite. Elle ne crée ni `city_seo_pages`, ni sitemap, ni landing `/restos/*`. Une commune disposant déjà d’une page ville continue vers cette page. Un texte non résolu est refusé avec une erreur claire et ne lance aucune recherche géographique.

## Référentiel local des communes

`database/data/communes-france-2026.tsv` est un export versionné du référentiel officiel de communes exposé par `geo.api.gouv.fr/communes` (source, date et licence dans son en-tête). La migration crée seulement la table indexée ; le déploiement charge ou actualise le contenu avec `php artisan communes:sync-reference`. Pour actualiser le millésime, remplacer le TSV à partir de la source officielle, conserver son en-tête de provenance, puis exécuter `communes:sync-reference --fresh` en environnement contrôlé. Cette maintenance ne lit jamais WordPress et ne modifie jamais les restaurants ou les pages SEO.

Tous les listings publics de restaurants hors « Autour de moi » sont ordonnés par date canonique de publication décroissante (`legacy_published_at`, puis `created_at` pour les créations V2), avec `id DESC` comme départage déterministe. Cela couvre annuaire, recherches, villes, départements, régions, spécialités, services et facettes ville. « Autour de moi » reste volontairement trié par distance. Les filtres de spécialités et services restent les seules entrées de la sidebar : sur desktop elles sont repliables après huit valeurs et sticky sans dépasser le viewport ; sur mobile elles sont présentées dans un drawer léger et accessible. Les filtres actifs restent toujours visibles.

Toutes ces grilles de résultats utilisent le même cadre média responsive : la vignette a une hauteur réservée commune, y compris sans image, et les photos sont recadrées avec `object-fit: cover`. Le ratio intrinsèque d’un média ne peut donc ni agrandir une carte ni décaler son contenu par rapport aux cartes voisines.

Les suggestions, limitées et déclenchées après deux caractères, distinguent toutes les spécialités V2 et les restaurants publiés. Une spécialité est donc proposée dès sa création, même avant d’être associée à une fiche publiée. Les restaurants de la ville sélectionnée sont proposés en premier, sans exclure les autres villes. La sélection explicite d’un restaurant ouvre directement sa fiche.

La sélection d’une suggestion de spécialité ne crée qu’un filtre `categories[]` V2 : le libellé peut rester visible dans le champ pour l’UX, mais n’est jamais soumis comme `q`. Une saisie libre sans suggestion reste au contraire une recherche `q` sans spécialité inventée. Toute modification manuelle du champ après une sélection réactive `q` et invalide uniquement la spécialité issue de cette sélection ; les cases de filtre choisies volontairement dans l’annuaire restent synchronisées séparément.

« Autour de moi » ne demande la position qu’après le clic volontaire correspondant. Refus, indisponibilité et délai affichent « Impossible d’obtenir votre position. Choisissez une ville. » sans empêcher une recherche par ville. Les recherches et combinaisons de filtres restent sur `/restaurants` en `noindex,follow`; elles ne créent aucune nouvelle landing page SEO ou facette indexable.

Les landings ville `/restos/{ville}` conservent H1, contenu, fil d’Ariane, canonical et stratégie d’indexation. Elles affichent aussi le composant SSR commun de recherche et les mêmes filtres. Une combinaison filtrée rejoint `/restaurants?city_code=…&…` en `noindex,follow`, sans ouvrir de facette SEO implicite ; la landing non filtrée reste canonique selon sa règle existante.

Sur desktop, le changement d’une case spécialité/service soumet immédiatement le formulaire SSR unique ; son CTA de secours reste disponible sans JavaScript. Sur mobile, le drawer conserve la sélection groupée et le CTA « Afficher les résultats ». Dans tous les cas, le bouton principal recherche synchronise les cases actives avant soumission.
