# Exceptions adresses — Phase 5.1

## Bilan

- Fiches initiales sans structure : **65**.
- Résolues automatiquement : **34**.
- Enrichies administrativement sans ajout de GPS : **34**.
- GPS ajoutés : **0**.
- Restantes : **31**.

Les 34 fiches résolues sont : `1185, 1263, 1879, 1897, 2436, 2496, 2625, 2861, 3281, 3632, 3875, 3948, 4023, 4616, 4707, 4983, 5125, 6226, 6654, 6899, 7120, 7596, 7598, 7612, 7638, 7648, 7649, 7651, 7652, 7653, 7654, 7698, 7713, 7714`.

## Restantes — raison précise

| V2 | Restaurant | Classification | Raison |
|---:|---|---|---|
| 2462 | Miam's | inutilisable | adresse historique et GPS absents |
| 7509 | Les deux rives : Restaurant créteil | inutilisable | adresse historique et GPS absents |
| 7544 | Sushi Time's | inutilisable | adresse historique et GPS absents |
| 2501 | La Friterie | REVIEW_REQUIRED | résultat direct complet mais Geography incompatible |
| 2958 | Antalya | REVIEW_REQUIRED | résultat direct complet mais Geography incompatible |
| 7089 | Kebab Les Nations | REVIEW_REQUIRED | résultat direct complet mais Geography incompatible |
| 3608 | Montecristo Marrakech | hors France | Maroc : BAN/Géoplateforme France non appropriée |
| 3609 | Restaurant riad Marrakech | hors France | Maroc : BAN/Géoplateforme France non appropriée |
| 3870 | L'En-K Snacking Monaco | hors France | Monaco : ne pas écrire `country_code=FR` |
| 3871 | Piznkeb | hors France | Monaco : ne pas écrire `country_code=FR` |
| 3930 | Marché Royal | hors France | Monaco : ne pas écrire `country_code=FR` |
| 7677–7696 | Mr. (20 fiches) | suspect/test | même nom générique, même adresse américaine `3137 Laguna Street`, aucun GPS ; recommandation : revue manuelle et ne pas publier automatiquement |

## Garanties

- Aucun `address` historique modifié.
- Aucun GPS historique remplacé.
- Les réponses sont écrites seulement lorsque CP, ville, code INSEE et un type `housenumber`/`street` concordent avec Geography quand celle-ci existe.
- Les 20 fiches « Mr. » ont été inspectées côté legacy (auteur/date/métadonnées, catégories et relations) : aucune information exploitable ne permet de les qualifier comme restaurants France ; elles sont conservées sans suppression.
