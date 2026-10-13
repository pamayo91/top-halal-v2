# Registre opérationnel — Production Top-Halal V2

## Objet et règles de sécurité

Ce registre est la référence opérationnelle avant tout déploiement, diagnostic
ou intervention sur la production. Créé le 2026-10-13 (Europe/Paris), il a été
complété par un audit strictement en lecture seule de la surface HTTP, du
référentiel Git et du checkout de production. Les constats internes ci-dessous
proviennent exclusivement de commandes de lecture; aucune tâche applicative,
Cron, cache, fichier, média ou donnée n'a été modifié.

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
| Accès SSH production | CONFIRMÉ | L'alias local recommandé `top-halal-prod` atteint le compte `meyo5199` sur `belette.o2switch.net` et permet la lecture du checkout production. |

## Accès et application

| Élément | État | Registre |
| --- | --- | --- |
| SSH production, utilisateur et méthode d'accès | CONFIRMÉ | Utiliser `top-halal-prod`. L'ancien alias `top-halal-preprod` atteint techniquement le même compte, mais ne doit plus être l'alias normal des opérations production afin d'éviter les erreurs humaines. |
| Checkout Git production | CONFIRMÉ | `/home/meyo5199/top-halal-v2-prod`, avec répertoire `.git`. |
| Branche déployée | CONFIRMÉ | `main`, HEAD `8f7f189cad983093bec123b98aba325d5f8186e7`, correspondant au tag/référence de production documenté. Le working tree est propre. |
| Remote Git du checkout | CONFIRMÉ | `origin` utilise l'alias GitHub de déploiement documenté; aucune clé ni credential n'est enregistrée dans ce registre. |
| DocumentRoot Apache et `public/` servi | CONFIRMÉ | Le propriétaire confirme `/home/meyo5199/top-halal-v2-prod/public`, cohérent avec le checkout Laravel et son répertoire `public`. |
| `.env` et logs Laravel | CONFIRMÉ | `.env` existe à la racine du checkout, permissions `600`; logs sous `storage/logs`. Son contenu sensible n'a pas été affiché. |
| Storage/médias | CONFIRMÉ | Les originaux V2 sont sous `storage/app/private/media/originals`; `storage/app/public` existe mais aucun lien `public/storage` n'est présent. Les médias sont délivrés par la route Laravel versionnée `/media/{asset}/v/{checksum}` depuis le disque `local`. |
| BDD Laravel production | CONFIRMÉ | Connexion `mariadb`, BDD `meyo5199_top_halal_prod`, MariaDB serveur `11.4.13`. Aucun credential n'a été lu ni enregistré. |

La BDD production est la source de vérité pour les données administrées dans son
BO. Elle ne doit jamais être remplacée, fusionnée ou synchronisée depuis la BDD
préproduction lors d'un déploiement standard. Les médias/stockage production
suivent la même séparation : aucun rsync ou synchronisation globale depuis la
préproduction, et un déploiement Git ne doit ni supprimer ni réinitialiser les
uploads.

## Runtime et assets

| Élément | État | Registre |
| --- | --- | --- |
| Laravel réellement chargé | CONFIRMÉ | `Laravel Framework 13.29.0`. |
| PHP CLI | CONFIRMÉ | Le `php` par défaut et `/opt/alt/php84/usr/bin/php` sont tous deux PHP 8.4.26; ce dernier reste le chemin explicite à employer pour Artisan/Cron. |
| Composer | CONFIRMÉ | Composer 2.10.2, fonctionnel via `/opt/alt/php84/usr/bin/php /usr/local/bin/composer`. |
| Git | CONFIRMÉ | Git 2.56.0 dans le checkout production. |
| MariaDB | CONFIRMÉ | Client et serveur MariaDB 11.4.13. |
| Node/npm en production | NON APPLICABLE | Les exécutables ne sont pas présents sur le serveur; le build ne doit pas y être lancé. |
| Vite et build réellement déployé | CONFIRMÉ | `public/build/`, `assets/` et `manifest.json` existent (manifest du 2026-10-12 23:02); les assets Vite ciblés de la page d'accueil répondent HTTP 200. |
| Filament assets | CONFIRMÉ (procédure) | Après une modification de dépendances, `artisan filament:assets` est requis avant recette BO; les assets ne sont pas remplacés par Vite. |

Le projet prévoit le disque Laravel `public` sous `storage/app/public`, publié
par le lien `public/storage`; son existence et la configuration active du
filesystem sur la production restent à vérifier. Les assets Vite peuvent être
construits hors serveur puis copiés dans le `public/build/` réellement confirmé,
contenu et `manifest.json` inclus, sans copier les sources ni secrets.

## Apache, queue, scheduler et caches

| Élément | État | Registre |
| --- | --- | --- |
| HTTPS, certificat et redirection preview | CONFIRMÉ | HTTPS public est actif, le certificat de `top-halal.fr` est confirmé actif par le propriétaire, et `prod-preview` redirige 301 vers le domaine principal. Autorité, renouvellement et configuration ne sont pas documentés ici. |
| HTTP -> HTTPS | CONFIRMÉ | `http://top-halal.fr/` redirige 301 vers HTTPS. |
| VirtualHost et configuration détaillée du certificat | À CONFIRMER | La configuration Apache/cPanel active n'est pas lisible avec cet accès. Le DocumentRoot est confirmé séparément; le répertoire historique `/home/meyo5199/top-halal.fr` ne doit pas être pris comme référence V2. |
| Règles Apache | CONFIRMÉ (principe) | Domaine/protocole et infrastructure relèvent d'Apache; les redirections de contenu relèvent de Laravel. |
| Queue utilisée par le code | CONFIRMÉ | Le worker lance `queue:work database --queue=default --stop-when-empty` avec `flock`, timeout 75 s et 4 essais. |
| Worker/Cron réellement actifs en production | CONFIRMÉ | Crontab : scheduler chaque minute, script worker chaque minute et purge e-mails à 03:05, tous sous `/home/meyo5199/top-halal-v2-prod`. |
| Scheduler applicatif défini | CONFIRMÉ (code) | `contributions:purge-verifications` est planifié à 03:10, protégé contre les chevauchements. |
| Scheduler/Cron réel et purge e-mails | CONFIRMÉ | Scheduler : `schedule:run` chaque minute; purge `emails:purge-delivery-logs --days=60 --no-interaction` à 03:05, avec logs sous `storage/logs`. |
| Cache de routes | CONFIRMÉ (règle) | Des closure routes existent : vider le cache de routes lors d'un changement de routes, sans utiliser `route:cache`. |

Le script versionné `scripts/run-queue-worker-cron.sh` détermine le répertoire
de l'application depuis son propre emplacement, reconcilie les historiques de
livraison, puis prend un verrou dans `storage/framework/queue-worker.lock`.
La crontab vérifiée appelle ce script chaque minute et les logs scheduler/worker
ont été modifiés pendant l'audit. Il emploie `/usr/bin/flock -n`, le driver
`database`, la file `default`, quatre essais, un timeout de 75 secondes et le
`retry_after` configuré à 90 secondes.

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

## Diagnostic production read-only

Les commandes suivantes ont été employées sans mutation lors de l'audit :

```bash
git status --short --branch
git branch --show-current
git rev-parse HEAD
git remote -v
php -v
/opt/alt/php84/usr/bin/php artisan --version
/opt/alt/php84/usr/bin/php /usr/local/bin/composer --version
crontab -l
ls -ld .env storage storage/app/public storage/logs public/build
readlink public/storage
```

Une interrogation BDD éventuelle doit rester limitée à `SELECT VERSION()` et
`SELECT DATABASE()` et ne doit jamais afficher de credential ni de DSN.

## Rollback non destructif

**Code.** Identifier le dernier commit déployé sain, créer un correctif Git
traçable (revert ou promotion contrôlée du commit précédent) et le faire passer
par le circuit de validation. Ne pas réécrire l'historique ni forcer un push.

**Migrations.** Un rollback automatique n'est jamais présumé sûr. Certaines
migrations sont irréversibles ou risquées; analyser la migration, la sauvegarde
vérifiée et une restauration ciblée éventuelle, puis obtenir la validation
humaine nécessaire. Les protections à deux confirmations pour restauration,
rebuild/reseed et réimport legacy restent obligatoires.

Pour une migration de schéma habituelle, appliquer le workflow de déploiement
documenté. Avant une migration ou un script susceptible de supprimer, réécrire
ou transformer massivement des données, de modifier/supprimer une colonne avec
données ou de rendre l'opération difficilement réversible, vérifier dans
JetBackup 5 la présence d'un point de restauration approprié. Cette vérification
ne remplace aucune des protections, analyses ou validations exigées ailleurs.

**Assets.** Conserver le build précédemment validé jusqu'aux health checks. Si
nécessaire, restaurer explicitement ce build et son `manifest.json` cohérent,
puis vérifier les pages concernées.

**Données métier.** Un rollback de code ne restaure jamais automatiquement les
données créées ou modifiées entre-temps dans le BO production.

## Sauvegardes

| Élément | État | Action avant changement risqué |
| --- | --- | --- |
| Mécanisme de sauvegarde o2switch | CONFIRMÉ | JetBackup 5 est disponible côté o2switch/cPanel, selon le propriétaire. |
| Sauvegarde BDD production | À CONFIRMER DANS JETBACKUP 5 | Vérifier point de restauration, fréquence, contenu, rétention et restauration avant une opération sensible. |
| Sauvegarde storage/médias | À CONFIRMER DANS JETBACKUP 5 | Vérifier point de restauration, périmètre, rétention et procédure de restauration. |

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
- Checkout production, `main`/HEAD propre, remote Git, PHP 8.4.26, Composer
  2.10.2, Laravel 13.29.0, Git 2.56.0, MariaDB 11.4.13 et BDD V2 confirmés.
- Crontab scheduler/worker/purge, build Vite servi, storage interne et route de
  diffusion média versionnée contrôlés en lecture seule.

### À confirmer

- VirtualHost et paramètres détaillés du certificat, faute de visibilité sur la
  configuration Apache/cPanel active.
- JetBackup 5 : fréquence, points disponibles, périmètre, rétention et
  procédure de restauration.

### Non applicable

- Synchronisation BDD préproduction -> production et synchronisation globale de
  storage préproduction -> production : interdites par l'architecture.
- Docker et Node/npm sur le serveur : non utilisés par l'architecture active;
  ne pas les introduire sans décision explicite.
