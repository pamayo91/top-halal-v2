# Médias

## Objectif

Les médias WordPress sont une source de migration uniquement. Une ressource affichée par V2 est copiée dans le stockage V2 et ne déclenche jamais une requête vers WordPress ni vers son répertoire `uploads`.

## Migration contrôlée

- `legacy:audit-media` lit exclusivement la connexion `legacy_wp` et produit des comptes, types MIME, références, doublons de contenu et anomalies sans exposer de chemin ou de donnée personnelle dans le rapport.
- `legacy:migrate-media` exige de un à dix identifiants d’attachments explicitement revus. `--dry-run` ne copie rien; `--apply` est idempotent et écrit un rapport de réconciliation.
- Chaque attachment garde son `legacy_attachment_id`. Les sources absentes, non rasterisables et les doublons sont signalés; rien n’est inventé ni servi depuis l’héritage.
- Les fichiers sont stockés sous leur checksum SHA-256. Deux attachments identiques conservent chacun leur `legacy_attachment_id` tout en partageant le même fichier physique.

## Diffusion et performance

- V2 sert uniquement les originaux copiés ou des variantes WebP générées localement; les chemins internes ne sont jamais publics.
- Les images raster reçoivent des variantes WebP 480, 960 et 1440 px, sans agrandissement. Les dimensions intrinsèques sont conservées pour réserver l’espace et éviter le CLS.
- Une largeur demandée qui n’existe pas répond 404; elle ne retombe jamais silencieusement sur un original avec un type MIME erroné.
- Les réponses ont un type MIME exact, `X-Content-Type-Options: nosniff` et un cache immuable. Les images sous le pli sont `loading=lazy`; l’image LCP ne l’est pas.
- Les PDF, vidéos, HEIC et GIF ne sont pas convertis automatiquement. Ils restent traçables dans l’audit et ne sont exposés qu’après une décision produit dédiée.

## Critères de fin de phase

- Audit préproduction exécuté et archivé.
- Pilote explicitement relié aux contenus/listing déjà migrés, appliqué deux fois sans doublon.
- Original et variantes V2 vérifiés par tests PHP et navigateur desktop/mobile, sans requête `wp-content` ni erreur console/réseau.
- Spécification, mapping, statut et changelog mis à jour.
