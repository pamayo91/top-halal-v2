# Audit — édition d’une fiche restaurant (2026-09-18)

## Sources auditées

| Surface | État avant cette livraison | Décision |
| --- | --- | --- |
| Filament `RestaurantResource` | Données générales, adresse/GPS, catégories, services, téléphone, `contact_email`, horaires, médias, slug/statut, SEO, signaux de modération et identifiants legacy. | Les données métier sont exposées au gérant ; statut, slug, SEO, modération, doublons, identifiants et techniques restent administratifs. `contact_email` est exclu car il dépend de l’unique adresse du compte. |
| Proposition publique | Nom, halal, adresse vérifiée, catégories/services, horaires, téléphone, liens, description, couverture/galerie et contexte de dépôt. | Elle reste un flux de création/modération : son e-mail et son contexte déclaratif ne deviennent pas un second champ éditable de fiche. |
| Éditeur déposant / owner / historien | Une route et une policy communes existaient déjà, mais le formulaire ne proposait que nom, description, téléphone et localisation. | Un même écran et un même `ManagedRestaurantUpdater` servent maintenant toutes les identités encore autorisées. |

## Droits vérifiés

`RestaurantPolicy::manage` et son unique `representedRestaurantsQuery` déterminent la route, l’écran et l’écriture : claim approuvé, paternité historique exacte ou dépôt encore gérable sans claim approuvé concurrent. L’approbation d’un claim retire bien le droit temporaire du déposant, sans supprimer ses snapshots ni relations historiques.

## Contrat de l’éditeur compte

- Éditable : nom, description sûre, halal viande/poulet, catégories, services, sept jours d’horaires et leurs plages, adresse sélectionnée/GPS, téléphone, quatre destinations sortantes opaques, couverture et galerie (ajout, retrait, ordre).
- Non éditable : e-mail, statut, slug, SEO, modération, données de doublon, relations/snapshots de claim ou de dépôt, identifiants legacy et champs techniques.
- Persistance : catégories/services/horaires/médias passent par les services métier existants ; un PATCH partiel ne synchronise pas les rubriques absentes. La transaction ne touche donc pas aux champs et relations non demandés.

## Couverture automatisée

`ManagedRestaurantEditorTest` exerce les trois profils, une modification complète (horaires multi-plages, ajout/retrait média, GPS, liens), la persistance, et la conservation du statut, slug et e-mail. Les tests existants vérifient le transfert du déposant, la paternité historique et la non-duplication de compte lors du changement d’adresse.

La préproduction a exécuté les huit parcours Playwright correspondants en Chromium desktop et Pixel 7 : ouverture par déposant, claimant et auteur historique, puis sauvegarde réelle de créneaux et de médias pour chaque format. Les cinq fixtures non humaines créées pour cette validation ont été supprimées après le contrôle. Le gate complet de régression a ensuite confirmé les sentinelles V2, les médias, les relations, les réponses HTTP, le navigateur et les logs Laravel.
