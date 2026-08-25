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
- Cle privee de deploiement: conservee uniquement sur le serveur de preproduction dans `~/.ssh/top-halal-v2-github-deploy`.
- Acces GitHub depuis la preproduction: en attente de l'ajout manuel de la cle publique dans GitHub.

## Automatisation future
Un script `scripts/deploy-preprod.sh` sera prepare apres l'audit serveur, quand les chemins, binaires, permissions, workers et besoins Node/Composer seront confirmes.
