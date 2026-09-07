# Enrichissement web des restaurants

## Audit Quick (lecture seule)

`restaurants:audit-quick` interroge uniquement le répertoire officiel `https://www.quick.fr/tous-les-quicks`, dont les données de restaurants sont rendues dans le payload Next.js `__NEXT_DATA__`. La commande n'effectue aucune écriture dans `restaurants` (un contrôle avant/après sur le nombre et le dernier `updated_at` bloque le run si ce contrat est rompu). Elle enregistre seulement le snapshot privé `storage/app/audits/quick/quick-restaurants.json` et les synthèses versionnables `docs/generated/quick-restaurants-audit.csv` / `.md`.

Le rapprochement combine nom sans marque Quick, adresse, CP, ville, téléphone et distance GPS. Les statuts `MATCH_EXACT`, `MATCH_UPDATE`, `MATCH_PROBABLE`, `MISSING_TOP_HALAL`, `DUPLICATE_TOP_HALAL` et `HALAL_NOT_CONFIRMED` restent des propositions de revue : la commande ne crée, ne met à jour ni ne supprime jamais de fiche. Le halal n'est confirmé que lorsque les indicateurs officiels par restaurant (`halal` ou `certifHalal`) le sont; l'absence de certificateur est conservée comme telle.

`restaurants:sync-quick --apply` is the explicit, idempotent write phase. It uses the private snapshot plus reviewed CSV, snapshots existing affected rows privately before changes, creates only audited `MISSING_TOP_HALAL` rows, refreshes the reviewed matches, and never creates taxonomy records. It maps only existing equivalent features and stores Quick hours under source-owned keys.

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
php artisan restaurants:web-enrich --retry-insufficient --limit=50
php artisan restaurants:web-enrich --restaurant=9000
php artisan restaurants:web-enrich --retry-insufficient --ids=73,75,76 --limit=3
```

La commande sans `--apply` réserve et exporte le prochain batch dans `storage/app/private/web-enrichment/`; Codex y ajoute les preuves avant l’application. Chaque application écrit `docs/generated/web-enrichment/batch-AAAAMMJJ-HHMMSS.csv`. Les statuts `CLOSED_*`, `CLOSURE_CONFLICT`, `SOURCE_CONFLICT` et `INSUFFICIENT_DATA` s’y filtrent directement; ils sont aussi filtrables par `restaurant_web_enrichments.status`.

La confiance est indépendante par champ (`matching`, activité, horaires et description) avec les niveaux `HIGH`, `MEDIUM` ou `LOW`. Une source unique, récente, d’un site officiel ou d’une plateforme précise avec nom et adresse concordants peut suffire pour des horaires absents ou une description factuelle. Une fermeture reste soumise à une preuve plus stricte et à la revue humaine.

Chaque checkpoint conserve aussi `research_count`, les requêtes effectuées et les sources rejetées avec leur motif. Ces données sont internes : elles figurent dans le CSV de contrôle, jamais dans le HTML public ou le JSON-LD. L’option `--ids` sert notamment à reprendre exactement une liste connue d’IDs non continus, sans réserver un nouveau lot.
