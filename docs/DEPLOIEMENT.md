# Deploiement — Preproduction

Ce document resume la strategie de deploiement demandee pour Top-Halal V2. Le document technique canonique reste `docs/DEPLOYMENT.md`.

## Strategie Git
- Depot distant: repository GitHub prive.
- `main`: branche stable, future production.
- `develop`: branche de preproduction.
- `feature/...`: branche temporaire pour les fonctionnalites importantes, puis fusion dans `develop` apres validation.

## Flux souhaite
1. Codex travaille sur le code.
2. Codex lance les tests locaux cibles.
3. Codex commit dans Git.
4. Codex push vers le repository GitHub prive.
5. Codex se connecte en SSH a la preproduction.
6. Le serveur fait un `git pull` de `develop`.
7. `composer install` est lance si necessaire.
8. Les migrations Laravel sont lancees si necessaire.
9. Les assets sont compiles si necessaire.
10. Les caches/optimisations Laravel sont appliques.
11. Les workers sont redemarres si necessaire.
12. La preproduction est testee avec navigateur/Playwright.

## Contraintes
- Pas de rsync comme methode principale.
- Le repository ne doit pas etre clone dans un repertoire publiquement accessible.
- L'application doit etre hors du DocumentRoot public.
- Seul le dossier Laravel `public/` doit etre expose par Apache.
- `.env`, dumps SQL, cles SSH, cles API, mots de passe et autres secrets ne doivent jamais etre commites.
- Rien ne doit etre deploye en production sans validation explicite.

## Actions manuelles attendues
- Ajouter la cle publique de deploiement preproduction dans GitHub comme deploy key en lecture seule.
- Confirmer les protections de branches souhaitees.
- Confirmer explicitement tout passage futur de `develop` vers `main`.

## Etat GitHub
- Repository: `git@github.com:pamayo91/top-halal-v2.git`.
- URL utilisee depuis le poste Codex: `git@github-tophalal-codex:pamayo91/top-halal-v2.git`.
- URL a utiliser depuis le serveur de preproduction: `git@github-tophalal:pamayo91/top-halal-v2.git`.
- Cle privee de deploiement: conservee uniquement sur le serveur de preproduction dans `~/.ssh/top-halal-v2-github-deploy`.
- Acces GitHub depuis la preproduction: authentification SSH testee avec succes.
- Push depuis le poste Codex: authentification SSH testee avec succes via `github-tophalal-codex`.

## Audit preproduction
- Audit consolide: `docs/generated/server-audit.txt`.
- PHP par defaut: 8.1.34.
- PHP 8.4 detecte: `/opt/alt/php84/usr/bin/php`.
- Composer fonctionne avec PHP 8.4 explicite.
- Extensions PHP 8.4 requises pour Laravel: OK.
- `opcache` reste a activer avant validation performance.
- Node/npm absents du PATH SSH.

## Validation actuelle
- Laravel 13 genere officiellement avec Composer sur la preproduction.
- Branche `develop` clonee dans `/home/meyo5199/top-halal-v2`.
- Dependances Composer installees avec PHP 8.4 explicite.
- Fichier `.env` preproduction cree uniquement sur le serveur avec permissions `600`; aucun secret dans Git ou la documentation.
- Connexion V2 `meyo5199_top_halal_v2` verifiee en lecture/ecriture.
- Connexion legacy `meyo5199_th` verifiee en lecture seule: SELECT OK, INSERT/UPDATE/DELETE/ALTER/DROP refuses.
- Migrations Laravel initiales lancees uniquement sur la base V2.
- Tests PHP passes dans un repertoire temporaire isole avec `/opt/alt/php84/usr/bin/php artisan test`.
- Verification serveur OK: `artisan about`, `migrate:status`, `route:list` et PHPUnit.
- Commande `legacy:inventory` enregistree dans Artisan.
- Test navigateur/Playwright HTTP preproduction OK en desktop et mobile.
- HTTPS bloque par un certificat non approuve/self-signed sur `https://dev.top-halal.fr.meyo5199.odns.fr/`.
- Chemins sensibles non lisibles publiquement: `.env`, `composer.json`, `artisan`, `storage/`, `vendor/`, `.git/`.

## Chemins confirmes avant clone/deploiement
- Chemin applicatif confirme: `/home/meyo5199/top-halal-v2`.
- DocumentRoot Apache futur confirme: `/home/meyo5199/top-halal-v2/public`.
- Le DocumentRoot n'a pas encore ete modifie.
- Dossier actuel detecte pour le sous-domaine: `/home/meyo5199/dev.top-halal.fr.meyo5199.odns.fr`.

## Automatisation future
Un script `scripts/deploy-preprod.sh` sera prepare apres l'audit serveur, quand les chemins, binaires, permissions, workers et besoins Node/Composer seront confirmes.
