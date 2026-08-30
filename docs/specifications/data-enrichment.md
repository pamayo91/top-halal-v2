# Enrichissement web des restaurants

## Portée et sécurité

`restaurants:web-enrich` ne modifie jamais l’identité, l’adresse, les coordonnées, les taxonomies, la publication, les claims, les avis ou les médias. Il peut uniquement ajouter des horaires absents et remplacer une description vide ou commençant (casse/espaces insensibles) par `Kebab frites de` ou `Description de votre restaurant unique`.

Les fermetures et radiations sont des files de revue humaine : aucune de ces alertes ne modifie une fiche.

## État persistant

La table `restaurant_web_enrichments` contient un unique checkpoint par restaurant : `PENDING`, `PROCESSING`, `UPDATED`, `UNCHANGED`, `CLOSED_CONFIRMED_REVIEW`, `CLOSED_POSSIBLE_REVIEW`, `CLOSURE_CONFLICT`, `SOURCE_CONFLICT`, `INSUFFICIENT_DATA` ou `ERROR`. Elle conserve les sources internes, snapshots avant/après, confiance, motif et erreur technique.

La sélection est ordonnée par ID réel sans hypothèse sur la continuité des IDs. Les statuts terminaux ne sont jamais repris automatiquement. `--retry-errors` reprend les erreurs et les `PROCESSING` vieux de plus de trente minutes.

## Recherche web par Codex

Codex prépare et réserve un batch, recherche chaque établissement via la navigation web normale, puis remet ses preuves structurées au writer Laravel. Aucune clé API ni scraping de SERP n’est requis. Chaque preuve conserve le matching nom/adresse, le statut d’activité, les sources, faits, horaires et niveau de confiance.

L’adaptateur Google Places reste optionnel et désactivé par défaut ; il n’est plus le chemin normal. Codex privilégie site officiel, réseaux officiels, sources d’entreprise, plateformes reconnues et presse locale. Une information insuffisante ou contradictoire ne produit aucune modification.

## Exploitation

```text
php artisan restaurants:web-enrich --limit=50
php artisan restaurants:web-enrich --prepare --limit=50
php artisan restaurants:web-enrich --apply=/chemin/prive/preuves.json
php artisan restaurants:web-enrich --limit=50 --dry-run
php artisan restaurants:web-enrich --retry-errors --limit=50
php artisan restaurants:web-enrich --restaurant=9000
```

La commande sans `--apply` réserve et exporte le prochain batch dans `storage/app/private/web-enrichment/`; Codex y ajoute les preuves avant l’application. Chaque application écrit `docs/generated/web-enrichment/batch-AAAAMMJJ-HHMMSS.csv`. Les statuts `CLOSED_*`, `CLOSURE_CONFLICT`, `SOURCE_CONFLICT` et `INSUFFICIENT_DATA` s’y filtrent directement; ils sont aussi filtrables par `restaurant_web_enrichments.status`.
