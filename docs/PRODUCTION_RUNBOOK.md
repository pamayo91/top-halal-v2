# Registre opérationnel — Production Top-Halal V2

## Objet et règles de sécurité

Ce registre est la référence opérationnelle avant tout déploiement, diagnostic
ou intervention sur la production. Il a été créé le 2026-10-13 (Europe/Paris)
à partir d'un audit strictement en lecture seule : surface HTTP publique,
référentiel Git et documentation versionnée. Aucun accès SSH de production
configuré localement n'était disponible pendant cet audit; aucune valeur interne
non vérifiée n'est donc présentée comme un fait.

Ne jamais inscrire ici de `.env`, mot de passe, clé privée SSH, clé API, secret
SMTP, credential/DSN BDD, token, cookie ou donnée personnelle. Les secrets
restent dans la configuration serveur ou les réglages chiffrés de l'application,
hors Git et hors journaux de déploiement.

Production est active. `main` est la branche stable destinée à la production;
`develop` reste l'intégration validée sur `dev.top-halal.fr`. D045 et
`AGENTS.md` s'appliquent intégralement : code, BDD et médias sont indépendants;
la BDD et le storage de préproduction ne sont jamais synchronisés vers la
production pendant un déploiement standard.

## État de l'audit

| Élément | État | Observation vérifiée |
| --- | --- | --- |
| Domaine public principal | CONFIRMÉ | `https://top-halal.fr/` répond HTTP 200 en HTTPS. |
| Hébergement HTTP observé | CONFIRMÉ | La réponse publique annonce `o2switch-PowerBoost-v3`. |
| Domaine utile secondaire | CONFIRMÉ | `https://prod-preview.top-halal.fr/` répond 301 vers le domaine principal. |
| Santé Laravel | CONFIRMÉ | `https://top-halal.fr/health` répond 200 avec le service `top-halal-v2`. |
| Robots et sitemap | CONFIRMÉ | `robots.txt` autorise le crawl et référence le sitemap public; `/sitemap.xml` répond 200. |
| Sécurité HTTP | CONFIRMÉ | HTTPS, HSTS, CSP, `nosniff`, Referrer-Policy et Permissions-Policy sont servis sur les réponses contrôlées. |
| Exposition de fichiers sensibles | CONFIRMÉ | `/.env` et `/.git/HEAD` répondent 403; `/artisan` et `/composer.json` répondent 404. |
| Référence Git | CONFIRMÉ | Le tag `v2.0.0-production` et `origin/main`/`origin/develop` pointent sur `8f7f189` à l'audit. |
| Alias SSH production | À CONFIGURER | Seul `top-halal-preprod` est présent dans la configuration SSH locale. |

## Accès et application

| Élément | État | Registre |
| --- | --- | --- |
| SSH production, utilisateur et méthode d'accès | À CONFIRMER | Créer ou valider un alias dédié, sans en copier les secrets ici. |
| Checkout Git production | À CONFIRMER | Une documentation historique mentionne `/home/meyo5199/top-halal-v2-prod`; ne pas l'utiliser avant vérification SSH. |
| Branche déployée | CONFIRMÉ (politique) | `main`. Confirmer le commit réel sur le serveur avant toute intervention. |
| Remote Git du checkout | À CONFIRMER | Vérifier `git remote -v` sur la production, sans publier de clé ni de token. |
| DocumentRoot Apache et `public/` servi | À CONFIRMER | Vérifier la configuration réellement active; ne pas déduire celle-ci de la préproduction. |
| `.env`, logs Laravel et storage | À CONFIRMER | Vérifier seulement existence, propriétaire et chemin; ne jamais afficher le contenu de `.env`. |
| BDD Laravel production | À CONFIRMER | Identifier moteur/version/connexion et, si validé comme non sensible, identifiant logique; jamais le DSN ni les credentials. |

La BDD production est la source de vérité pour les données administrées dans son
BO. Elle ne doit jamais être remplacée, fusionnée ou synchronisée depuis la BDD
préproduction lors d'un déploiement standard. Les médias/stockage production
suivent la même séparation : aucun rsync ou synchronisation globale depuis la
préproduction, et un déploiement Git ne doit ni supprimer ni réinitialiser les
uploads.

## Runtime et assets

| Élément | État | Registre |
| --- | --- | --- |
| Laravel du dépôt | CONFIRMÉ | `composer.lock` référence `laravel/framework` `v13.29.0`. La version réellement chargée en production reste à contrôler par SSH. |
| PHP CLI, Composer, MariaDB, Git et extensions | À CONFIRMER | L'audit versionné disponible concerne la préproduction, pas la production. Vérifier versions, chemin PHP explicite, Composer, Git, MariaDB et extensions requises sans modifier le serveur. |
| PHP par défaut | À CONFIRMER | Contrôler séparément `php -v` et le binaire explicitement utilisé par Artisan. |
| Node/npm en production | À CONFIRMER | Ne pas supposer l'absence observée en préproduction. |
| Vite | CONFIRMÉ (code) | Les layouts Blade utilisent `@vite`; le build attendu est `public/build/` avec `manifest.json`. |
| Build réellement déployé | À CONFIRMER | Vérifier présence/horodatage du manifest et la méthode de transfert sans remplacer d'asset. |
| Filament assets | CONFIRMÉ (procédure) | Après une modification de dépendances, `artisan filament:assets` est requis avant recette BO; les assets ne sont pas remplacés par Vite. |

Le projet prévoit le disque Laravel `public` sous `storage/app/public`, publié
par le lien `public/storage`; son existence et la configuration active du
filesystem sur la production restent à vérifier. Les assets Vite peuvent être
construits hors serveur puis copiés dans le `public/build/` réellement confirmé,
contenu et `manifest.json` inclus, sans copier les sources ni secrets.

## Apache, queue, scheduler et caches

| Élément | État | Registre |
| --- | --- | --- |
| HTTPS et redirection preview | CONFIRMÉ | HTTPS public est actif; `prod-preview` redirige 301 vers le domaine principal. |
| HTTP -> HTTPS, VirtualHost, DocumentRoot, certificat | À CONFIRMER | Contrôler en lecture seule avec l'accès serveur/hébergement; aucune modification Apache/DNS/certificat dans ce workflow. |
| Règles Apache | CONFIRMÉ (principe) | Domaine/protocole et infrastructure relèvent d'Apache; les redirections de contenu relèvent de Laravel. |
| Queue utilisée par le code | CONFIRMÉ | Le worker lance `queue:work database --queue=default --stop-when-empty` avec `flock`, timeout 75 s et 4 essais. |
| Worker/Cron réellement actifs en production | À CONFIRMER | Lire la crontab production et, si utile, l'état/logs sans lancer ni arrêter de worker. |
| Scheduler applicatif défini | CONFIRMÉ (code) | `contributions:purge-verifications` est planifié à 03:10, protégé contre les chevauchements. |
| Scheduler/Cron réel et purge e-mails | À CONFIRMER | Vérifier fréquence, commande PHP, logs et éventuelle purge `emails:purge-delivery-logs`; ne rien créer/modifier. |
| Cache de routes | CONFIRMÉ (règle) | Des closure routes existent : vider le cache de routes lors d'un changement de routes, sans utiliser `route:cache`. |

Le script versionné `scripts/run-queue-worker-cron.sh` détermine le répertoire
de l'application depuis son propre emplacement, reconcilie les historiques de
livraison, puis prend un verrou dans `storage/framework/queue-worker.lock`.
Il décrit le mécanisme applicatif; il ne prouve pas que la crontab production le
déclenche. Lors d'un futur audit SSH, relever sans secret les entrées Top-Halal,
leur fréquence, le binaire PHP et les fichiers de log associés.

Commandes à évaluer pour un futur déploiement, jamais à exécuter pendant un
audit : `artisan optimize:clear`, `artisan config:cache` et `artisan view:cache`.
N'utiliser `artisan route:clear` qu'en cas de changement de routes; ne pas
exécuter `route:cache` tant que des routes closure sont présentes.

## Procédure standard de déploiement — à exécuter seulement après autorisation

1. Vérifier l'autorisation du propriétaire, la branche `main`, le commit exact,
   son diff et l'absence de fonctionnalités non approuvées.
2. Vérifier que ce même correctif a été validé sur `develop`/`dev.top-halal.fr`,
   avec tests ciblés, recette pertinente et `composer test:regression` vert.
3. Déterminer si une migration de données est impliquée. Une migration de schéma
   peut être exécutée avec le code; une mutation de données exige analyse de
   portée, volumes, idempotence, interaction BO, sauvegarde/rollback et
   validation humaine adaptée.
4. Se connecter via l'alias SSH production confirmé, relever le commit courant,
   les chemins et l'état de travail; arrêter si le checkout n'est pas propre ou
   si une information du registre n'est pas confirmée.
5. Mettre à jour de manière traçable vers le commit `main` autorisé. Ne pas
   éditer directement des fichiers applicatifs sur le serveur.
6. Exécuter `composer install` seulement si le lockfile/dépendances changent,
   avec les options production validées lors de l'audit runtime. Publier les
   assets Filament si requis par cette mise à jour.
7. Exécuter `artisan migrate --force` seulement si des migrations approuvées
   sont présentes; ne jamais employer `migrate:fresh`, reseed global, restore,
   import/réimport legacy ou synchronisation de BDD.
8. Livrer un build Vite validé seulement s'il a changé, dans le répertoire
   `public/build/` confirmé avec son `manifest.json`; préserver le build
   précédent jusqu'à validation.
9. Appliquer uniquement les caches nécessaires et traiter la queue seulement
   conformément au mécanisme de production vérifié. Ne pas démarrer/arrêter un
   service par hypothèse.
10. Exécuter les contrôles post-déploiement, consulter les logs pertinents,
    consigner le commit et compléter le journal ci-dessous.

## Matrice de changement

| Changement | Actions après mise à jour du code | À ne pas faire automatiquement |
| --- | --- | --- |
| Documentation seule | Git et revue du commit seulement. | Composer, migration, build, changement BDD. |
| PHP/Blade sans dépendance | Caches adaptés, vérification fonctionnelle. | Migration sans fichier réellement présent. |
| Composer | `composer install` avec runtime/options confirmés; caches; Filament assets si requis. | Supposer que le PHP par défaut convient. |
| Migration BDD | Audit, sauvegarde adaptée, `artisan migrate --force`, contrôles post-migration. | Copier la BDD préprod, `migrate:fresh`, reseed. |
| CSS/JS/Vite | Build validé, `public/build/` et `manifest.json`, contrôle navigateur desktop/mobile. | Écraser le build précédent avant validation. |
| Routes | Gestion du cache de routes adaptée et test de l'URL réelle. | `route:cache` avec les closure routes. |
| Queue/jobs | Contrôle du mécanisme confirmé et de `failed_jobs`. | Redémarrer/dupliquer un worker par défaut. |
| Robots/sitemap | Vérifier directement les réponses publiques. | Déduire le rendu depuis les fichiers source. |

## Health checks post-déploiement

- Confirmer le commit réellement déployé et le domaine principal en HTTPS.
- Vérifier `/health`, l'accueil, une fiche restaurant, une page ville, un
  article, `/robots.txt` et `/sitemap.xml`.
- Tester directement la fonctionnalité modifiée; vérifier le BO si concerné.
- Examiner les nouvelles erreurs HTTP 500, les logs Laravel pertinents et,
  pour le frontend, console et réseau navigateur.
- Ajouter les contrôles spécifiques : ancienne URL/code/destination pour une
  redirection; assets et formats desktop/mobile pour le frontend.

## Rollback non destructif

**Code.** Identifier le dernier commit déployé sain, créer un correctif Git
traçable (revert ou promotion contrôlée du commit précédent) et le faire passer
par le circuit de validation. Ne pas réécrire l'historique ni forcer un push.

**Migrations.** Un rollback automatique n'est jamais présumé sûr. Certaines
migrations sont irréversibles ou risquées; analyser la migration, la sauvegarde
vérifiée et une restauration ciblée éventuelle, puis obtenir la validation
humaine nécessaire. Les protections à deux confirmations pour restauration,
rebuild/reseed et réimport legacy restent obligatoires.

**Assets.** Conserver le build précédemment validé jusqu'aux health checks. Si
nécessaire, restaurer explicitement ce build et son `manifest.json` cohérent,
puis vérifier les pages concernées.

**Données métier.** Un rollback de code ne restaure jamais automatiquement les
données créées ou modifiées entre-temps dans le BO production.

## Sauvegardes

| Élément | État | Action avant changement risqué |
| --- | --- | --- |
| Sauvegarde BDD production | À CONFIRMER | Vérifier mécanisme, fréquence, emplacement privé, restauration et rétention. |
| Sauvegarde storage/médias | À CONFIRMER | Vérifier mécanisme, fréquence, restauration et périmètre. |
| Sauvegardes hébergeur | À CONFIRMER DANS O2SWITCH/CPANEL | Ne jamais présumer leur disponibilité ni leur configuration. |

## Journal des futurs déploiements

Ne pas inventer d'historique. Ajouter une ligne uniquement après un déploiement
réel et autorisé.

| Date/heure Europe/Paris | Commit | Branche/source | Objet | Migration | Build | Exécutant | Health checks | Rollback | Remarque |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |

## Synthèse de preuve

### Confirmé

- Surface publique `top-halal.fr`, HTTPS, santé, robots, sitemap, headers de
  sécurité et protection des chemins sensibles contrôlés le 2026-10-13.
- Redirection 301 de `prod-preview.top-halal.fr` vers le domaine principal.
- Politique Git `main`/`develop`, tag de production et mécanismes applicatifs
  versionnés (Vite, queue, scheduler, cache de routes) contrôlés dans Git.

### À confirmer

- Alias SSH, utilisateur, checkout, remote, DocumentRoot, `.env`, logs,
  storage/lien symbolique, runtime réellement chargé et BDD de production.
- Crontab active, queue/scheduler/purge réels, Node/npm, build/manifeste actifs,
  sauvegardes et leurs procédures de restauration.
- Réglages Apache non visibles publiquement : HTTP -> HTTPS, VirtualHost,
  certificat et règles d'infrastructure précises.

### Non applicable

- Synchronisation BDD préproduction -> production et synchronisation globale de
  storage préproduction -> production : interdites par l'architecture.
- Docker et worker permanent Supervisor/systemd : non prévus par l'architecture
  documentée; ne pas les introduire sans décision explicite.
