# Enrichissement web des restaurants

## Portée et sécurité

`restaurants:web-enrich` ne modifie jamais l’identité, l’adresse, les coordonnées, les taxonomies, la publication, les claims, les avis ou les médias. Il peut uniquement ajouter des horaires absents et remplacer une description vide ou commençant (casse/espaces insensibles) par `Kebab frites de` ou `Description de votre restaurant unique`.

Les fermetures et radiations sont des files de revue humaine : aucune de ces alertes ne modifie une fiche.

## État persistant

La table `restaurant_web_enrichments` contient un unique checkpoint par restaurant : `PENDING`, `PROCESSING`, `UPDATED`, `UNCHANGED`, `CLOSED_CONFIRMED_REVIEW`, `CLOSED_POSSIBLE_REVIEW`, `CLOSURE_CONFLICT`, `SOURCE_CONFLICT`, `INSUFFICIENT_DATA` ou `ERROR`. Elle conserve les sources internes, snapshots avant/après, confiance, motif et erreur technique.

La sélection est ordonnée par ID réel sans hypothèse sur la continuité des IDs. Les statuts terminaux ne sont jamais repris automatiquement. `--retry-errors` reprend les erreurs et les `PROCESSING` vieux de plus de trente minutes.

## Source web

L’interface `RestaurantWebSourceProvider` sépare l’enrichissement du fournisseur. L’adaptateur initial est l’API officielle Google Places, optionnelle : `RESTAURANT_WEB_PROVIDER=google_places` et `GOOGLE_PLACES_API_KEY` doivent être configurés uniquement après approbation du budget. Par défaut aucun appel web ne part et les fiches sont classées `ERROR` (configuration), donc reprenables avec `--retry-errors`, sans modification.

L’adaptateur exige un score combinant nom (60 %) et adresse (40 %) d’au moins 78 %. Il applique timeout, un retry, cache Laravel quatorze jours et une pause configurable. Aucun HTML Google n’est scrapé. Pour une radiation SIRENE/RNE, un fournisseur officiel supplémentaire doit être implémenté : cette phase ne déduit jamais une radiation à partir d’un simple résultat Places.

## Exploitation

```text
php artisan restaurants:web-enrich --limit=50
php artisan restaurants:web-enrich --limit=50 --dry-run
php artisan restaurants:web-enrich --retry-errors --limit=50
```

Chaque batch écrit `docs/generated/web-enrichment/batch-AAAAMMJJ-HHMMSS.csv`. Les statuts `CLOSED_*`, `CLOSURE_CONFLICT`, `SOURCE_CONFLICT` et `INSUFFICIENT_DATA` s’y filtrent directement; ils sont aussi filtrables par `restaurant_web_enrichments.status`.
