# Préparation production — clôture P1 (2026-10-12)

## Périmètre et méthode

Audit réalisé exclusivement sur la préproduction `https://dev.top-halal.fr`, sans accès à WordPress legacy, sans suppression de donnée métier et sans opération sur la production. Les mesures navigateur utilisent Chromium/Playwright mobile (390 × 844, cache froid, user-agent mobile usuel), sans limitation réseau artificielle.

## Headers de sécurité

Le middleware applicatif `SecurityHeaders` est actif : CSP restrictive (`default-src 'self'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'none'`, `object-src 'none'`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` et `Permissions-Policy: camera=(), geolocation=(), microphone=()`. Leaflet CDN n'est permis que dans l'administration et les tuiles OpenStreetMap sont explicitement limitées. HSTS ne s'ajoute qu'en environnement Laravel `production` sur HTTPS (`max-age=31536000; includeSubDomains`) et est donc absent de préproduction.

Les scripts/styles inline existants imposent temporairement `unsafe-inline`; aucun `unsafe-eval` n'est autorisé. Le test PHP `SecurityHeadersTest`, le contrôle HTTP et les navigations Playwright de l'accueil, annuaire, Paris et Quick sont passants, sans erreur console/page.

## OPcache PHP 8.4

Le module `/opt/alt/php84/usr/lib64/php/modules/opcache.so` est présent, mais le CLI approuvé `/opt/alt/php84/usr/bin/php` ne le charge pas (`php -m` et `php -i`). Le compte SSH ne peut ni lire ni modifier la configuration Apache/PHP-FPM : l'état du SAPI web ne peut donc pas être inféré. Aucune activation risquée n'a été tentée. Avant la bascule production, l'administrateur d'hébergement doit activer/vérifier OPcache pour le handler PHP 8.4 du domaine via une méthode non publique.

## Données candidates au nettoyage — lecture seule

La recherche de marqueurs (`playwright`, `fixture`, `regression`, `example`) trouve 62 `restaurant_submissions` utilisant `example.invalid`, domaine réservé non distribuable : candidats forts, sans suppression ni altération.

| Création UTC | IDs | Statut |
| --- | --- | --- |
| 2026-09-02 | 4, 5 | `pending_admin_review` |
| 2026-09-11 | 6–14 | `pending_admin_review` |
| 2026-09-12 | 26, 27 | `pending_email_verification` |
| 2026-09-13 | 29–34 | `pending_email_verification` |
| 2026-09-17 | 36–62 | `pending_email_verification` |
| 2026-09-18 | 68–71 | `pending_email_verification` |
| 2026-09-29 | 75–86 | `pending_email_verification` |

Les mêmes marqueurs ne produisent aucun candidat dans `contact_messages`. Les neuf autres soumissions, sur domaines non réservés, ne sont pas qualifiées comme données de test.

## Références dev.top-halal.fr

Le code ne contient que les références attendues de préproduction (scripts/tests/configuration). La base a été auditée pour réglages, templates e-mail, contenus HTML et canonicals. La page publiée `pages.id=11` / `quick-hallal` contenait deux liens internes absolus vers la préproduction (média V2 et recherche Quick) ; ils sont devenus des chemins relatifs. Le contrôle final est à zéro occurrence dans les contenus, réglages, templates, articles/pages et canonicals audités. `APP_URL=https://dev.top-halal.fr` reste correct pour la préproduction et devra être renseigné avec l'URL de production lors de sa création.

## Mesures représentatives

Les scores Lighthouse ne sont pas rapportés : Lighthouse n'est pas installé localement et aucun score n'a été inventé. Ces métriques Playwright/Performance API sont cache froid, non bridées ; elles ne remplacent pas une recette Lighthouse mobile avec throttling en production.

| URL | HTTP | TTFB | FCP | LCP | CLS | Ressources transférées |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| `/` | 200 | 364 ms | 456 ms | 456 ms | 0 | 2.67 MB |
| `/restaurants` | 200 | 370 ms | 452 ms | 540 ms | 0 | 163 KB |
| `/restos/paris` | 200 | 380 ms | 472 ms | 508 ms | 0 | 98 KB |
| `/quick-hallal` | 200 | 1.09 s | 1.22 s | 1.22 s | 0 | 2.95 MB |

Les LCP observés sont sous la cible de 1.8 s dans ce laboratoire. Les poids de l'accueil et de `quick-hallal` doivent néanmoins être optimisés avant la mesure Lighthouse de production.
