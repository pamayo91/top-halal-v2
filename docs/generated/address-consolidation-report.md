# Consolidation adresses — contrôle de persistance

## Exécution initiale Phase 5

- APPROXIMATE interrogés : 5 030
- Réponses administratives complètes : 5 022
- Proximity rendus éligibles : 4 601

Ce chiffre de 5 022 était un compteur de réponses complètes du worker, et non un décompte par colonne réellement renseignée en BDD. La commande ne persistait pas `address_line1` et ses bornes d’ID se terminaient à 7 704 alors que les deux derniers enregistrements ont les IDs 7 708 et 7 709.

## Correctif de persistance

- Restaurants relus : 5 037
- Réponses Géoplateforme du cache : 5 030
- Fiches complétées : 5 029
- Résultats fournisseur incomplets : 8
- Erreurs fournisseur : 0
- GPS modifiés : 0
- Adresse historique modifiée : 0

Les écritures ne concernent que les champs structurés manquants. `APPROXIMATE` n’empêche pas le remplissage de `address_line1`, `postal_code`, `city_name`, `city_code` ou `country_code`.

## État BDD après correctif

| Champ | Compteur worker Phase 5 | Réel BDD |
|---|---:|---:|
| `postal_code` | 5 022 réponses complètes | 7 639 |
| `city_name` | 5 022 réponses complètes | 7 639 |
| `city_code` | 5 022 réponses complètes | 7 639 |
| `country_code` | 5 022 réponses complètes | 7 639 |
| `address_line1` | non mesuré / non persisté | 7 639 |

Le second passage n’a trouvé que huit réponses fournisseur incomplètes et n’a effectué aucune écriture : le correctif est idempotent.
