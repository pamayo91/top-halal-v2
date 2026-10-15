# Audit des restaurants sans ville résolue

- Exécuté : 2026-10-13T19:09:23+02:00
- Sélection : Published restaurants for which CityPageResolver::cityForCode() returns null.
- Garantie : lecture seule V2 ; aucun accès legacy, aucune redirection ou donnée métier modifiée.
- Vérification de sécurité : le test dédié intercepte les requêtes SQL d’écriture ; aucun INSERT, UPDATE, DELETE ni création de restaurant/référentiel n’est autorisé par cette commande.

## Synthèse

- Total analysé : 63
- Cas normaux / acceptables : 0
- Corrections de données potentielles : 63
- Bugs resolver potentiels : 0
- Décisions métier nécessaires : 0
- Cas ambigus : 7745, 7775, 7797, 7883

### Répartition par catégorie

- A — city_code absent : 59
- B — city_code invalide ou inconnu : 0
- C — city_name / city_code incohérents : 0
- D — Code arrondissement / ancienne représentation territoriale : 0
- E — Commune étrangère / hors référentiel France : 0
- F — Données structurées incomplètes : 0
- G — Valeur manifestement anormale ou donnée parasite : 4
- H — Bug probable du resolver : 0

## Détail

| ID | Nom | Ville | Code ville | Catégorie | Échec resolver | Action recommandée |
| ---: | --- | --- | --- | --- | --- | --- |
| 7711 | Quick Etampes | Etampes |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7714 | Quick Chelles | Chelles |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7718 | Quick Reims Cormontreuil | Cormontreuil |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7719 | Quick Oyonnax Arbent | Arbent |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7727 | Quick Douai Férin | Ferin |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7731 | Quick Aulnay CC | Aulnay-Sous-Bois |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7733 | Quick La Courneuve 2 | La-Courneuve |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7735 | Quick Center Parcs Poitiers | Morton |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7737 | Quick Chambly | Chambly |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7740 | Quick Flins | Flins-Sur-Seine |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7741 | Quick Le Puy-en-Velay | Le-Puy-En-Velay |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7742 | Quick Gonfreville | Gonfreville-L'orcher |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7743 | Quick Center Parcs Normandie (Bois Francs) | Verneuil-Sur-Avre |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7745 | Quick Toulouse Mirail | Toulouse-Mirail |  | G — Valeur manifestement anormale ou donnée parasite | city_code_absent_with_non_administrative_city_value | Vérification manuelle de la fiche et de la source avant toute correction ; aucun code proposé. |
| 7746 | Quick Center Parcs Moselle | Hattigny |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7747 | Quick Bailleul | Bailleul |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7748 | Quick Rouen Saint-Sever | Rouen |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7755 | Quick Rouen Barentin | Barentin |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7773 | Quick Roques-sur-Garonne | Roques-Sur-Garonne |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7775 | Quick Lab 997 | Auber Lab |  | G — Valeur manifestement anormale ou donnée parasite | city_code_absent_with_non_administrative_city_value | Vérification manuelle de la fiche et de la source avant toute correction ; aucun code proposé. |
| 7778 | Quick Ecully | Ecully |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7783 | Quick Saint Brice sous Forêt | Saint Brice Sous Forêt |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7784 | Quick Dorlisheim | Dorlisheim |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7787 | Quick Epinay sur Seine | Epinay Sur Seine |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7789 | Quick Cabries - Plan de Campagne | Cabries |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7795 | Quick Grand Quevilly | Grand Quevilly |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7796 | Quick Villefranche sur Saône | Villefranche Sur Saône |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7797 | Quick Westfield Les 4 temps - La Défense | Paris La Défense Cedex |  | G — Valeur manifestement anormale ou donnée parasite | city_code_absent_with_non_administrative_city_value | Vérification manuelle de la fiche et de la source avant toute correction ; aucun code proposé. |
| 7799 | Quick Valenciennes - Petite fôret | Petite Foret |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7807 | Quick Le Havre Montivilliers | Montivilliers |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7815 | Quick Marignane Saint-Victoret | Saint-Victoret |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7817 | Quick Romans sur Isère | Romans Sur Isère |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7819 | Quick Faches Thumesnil | Faches-Thumesnil |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7822 | Quick Chambray les Tours | Chambray Les Tours |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7823 | Quick Bry sur Marne | Bry Sur Marne |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7824 | Quick Les Ulis | Les Ulis |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7829 | Quick Chalon-sur-Saône | Chalon-Sur-Saone |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7831 | Quick Bordeaux Rives d'Arcins | Villenave D'ornon |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7832 | Quick Saint Quentin | Fayet |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7836 | Quick Belle Epine | Thiais |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7843 | Quick Thionville | Commune De Thionville |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7846 | Quick Saint Martin d'Hères | Saint Martin D'hères |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7848 | Quick Caen Mondeville | Mondeville |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7849 | Quick Les Clayes sous Bois | Les Clayes Sous Bois |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7851 | Quick Nantes Orvault | Orvault |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7856 | Quick Margencel - Thonon | Margencel |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7857 | Quick Belfort | Botans |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7858 | Quick Mougins | Mougins |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7867 | Quick Geispolsheim | Geispolsheim |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7869 | Quick Agen | Boé |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7870 | Quick Lomme | Ennetières-En-Weppes |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7872 | Quick Etrembières | Etrembières |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7873 | Quick Evreux | Evreux |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7877 | Quick Troyes Barberey | Barberey-Saint-Sulpice |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7878 | Quick Toul Dommartin A31 | Chaudeney-Sur-Moselle |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7880 | Quick Lampertheim Vendenheim | Vendenheim |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7882 | Quick Nevers Marzy | Marzy |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7883 | Quick Lab 999 | Auber Lab |  | G — Valeur manifestement anormale ou donnée parasite | city_code_absent_with_non_administrative_city_value | Vérification manuelle de la fiche et de la source avant toute correction ; aucun code proposé. |
| 7885 | Quick Trégueux St Brieuc | Tregeux |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7887 | Quick Olivet | Olivet |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7889 | Quick Aire de Fabrègues | Fabregues |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7890 | Quick Nancy Houdemont | Houdemont |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |
| 7891 | Quick Brétigny-sur-Orge | Bretigny Sur Orge |  | A — city_code absent | city_code_absent | Compléter le code INSEE après validation administrative de la source ; aucune commune homonyme n’est choisie automatiquement. |

Le CSV compagnon contient les champs structurés complets de chaque ligne. Aucune recommandation ne constitue une correction automatique.
