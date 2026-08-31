# Non-régression permanente

## But

Cette suite est le verrou obligatoire avant de déclarer une évolution fonctionnelle importante terminée. Elle protège l'état V2 réellement déployé ; elle ne lit jamais la base WordPress ni les uploads legacy.

Elle détecte les pertes silencieuses de relations, de médias ou de comptes, les chemins `wp-content` / `wp-contenu` réintroduits, les baisses inattendues de volumes, les erreurs HTTP 500, les erreurs console/réseau et les nouvelles exceptions Laravel pendant le parcours de validation.

## Sentinelles V2

La table privée `regression_sentinels` conserve uniquement les identifiants techniques V2/legacy sûrs, slugs, chemins V2, relations et compteurs approuvés. Elle ne contient ni adresse e-mail, ni mot de passe, ni URL sortante privée.

`regression:sentinels` sélectionne des données migrées réelles et représentatives : galerie, photo unique si disponible, fiche sans photo, catégories, services, avis, adresse/GPS structurés, prévisualisation signée d'une fiche `pending`, article avec image à la une, média inline si disponible, article sans image, page éditoriale et redirection exacte. Les catégories sans donnée disponible restent explicitement absentes du registre plutôt que d'être remplacées par une fausse donnée de test.

Les relations de chaque sentinelle sont comparées exactement : médias/asset/variantes, catégories, services, géographie, avis, horaires, taxonomies éditoriales, adresse et GPS. Les volumes globaux ont un seuil minimal : une addition légitime est acceptée, une baisse exige une investigation et une mise à jour volontaire du baseline.

Le baseline est créé ou remplacé seulement par l'action explicite suivante, après revue d'une évolution de données légitime :

```powershell
ssh top-halal-preprod "cd /home/meyo5199/top-halal-v2 && /opt/alt/php84/usr/bin/php artisan regression:sentinels --refresh-baseline"
```

Cette commande ne doit jamais servir à masquer un échec : la cause racine doit être corrigée avant toute régénération.

## Commandes

Pendant le développement, lancer les tests PHP ciblés concernés. La validation complète préproduction se lance depuis le poste de travail avec :

```powershell
composer test:regression
```

Elle exécute les tests PHP de sentinelles, récupère la vérification V2 de préproduction par SSH, lance `tests/e2e/regression/` contre `https://dev.top-halal.fr`, puis relance les vérifications et contrôle les nouvelles erreurs Laravel depuis le début de la suite.

Variables optionnelles, sans secret dans Git : `PREPROD_SSH_HOST`, `PREPROD_APP_PATH` et `PREPROD_BASE_URL`. Le back-office est couvert par les tests PHP de conservation des relations. Le parcours navigateur admin requiert en plus un compte administrateur de test explicitement provisionné ; aucun compte humain réel ne doit être utilisé.

## Ajouter une fonctionnalité critique

1. Ajouter un test PHP ciblé qui protège sa règle métier et sa conservation de données.
2. Ajouter une sentinelle dans `SentinelRegistry` si le parcours utilise un nouveau template, une relation ou un type de média critique.
3. Ajouter le parcours public dans `tests/e2e/regression/` lorsqu'il doit être vérifié sur préproduction.
4. Déployer, lancer la suite complète et corriger toute régression à la source.
5. Mettre à jour ce document et les specs concernées si le périmètre protégé évolue.

## Diagnostic

`php artisan regression:verify` affiche les relations ou fichiers exacts en échec. Vérifier ensuite le diff de l'évolution, les opérations Eloquent (`sync`, `detach`, `delete`, collections remplacées et batchs) et les logs Laravel. Il est interdit de résoudre un échec en restaurant aveuglément les données, en relançant une migration globale ou en modifiant la fixture : corriger le chemin de code responsable, restaurer seulement si nécessaire, puis rejouer toute la suite.

Les batchs continuent d'utiliser `chunkById`, `cursor`, checkpoints réels ou `legacy_wp_id`; une borne artificielle `ID <= count()` est interdite.
