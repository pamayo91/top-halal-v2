# TOP-HALAL V2 — DOCUMENT MAÎTRE DE REPRISE

Dernière mise à jour : 30/08/2026

Ce document résume l'état du projet Top-Halal V2.
Il doit être lu avant toute nouvelle phase.

IMPORTANT :
- ne pas recommencer les phases déjà terminées ;
- Git + docs/STATUS.md restent la source technique de vérité ;
- inspecter l'état réel du repository avant toute modification ;
- ne jamais modifier la production ou les sources legacy sans autorisation explicite.

---

# 1. OBJECTIF DU PROJET

Reconstruction complète de https://top-halal.fr/ sans WordPress.

Le site est un annuaire de restaurants halal en France avec :
- fiches restaurants ;
- recherche géographique ;
- catégories/cuisines/features ;
- avis ;
- commentaires ;
- articles/pages éditoriaux ;
- utilisateurs ;
- revendication de restaurants ;
- espace propriétaire ;
- futur système publicitaire ;
- future génération éditoriale IA.

Objectifs principaux :
- architecture propre ;
- excellente UX ;
- SEO natif ;
- GEO/IA ready ;
- Core Web Vitals très élevés ;
- PageSpeed mobile >=95, objectif 98-100 ;
- aucune dépendance WordPress après migration.

---

# 2. STACK

Production cible :

- Laravel 13
- PHP 8.4
- MariaDB 11.4
- Apache
- Blade SSR
- JS minimal sur le front
- pas de SPA
- pas de Docker

Back-office :

- Filament 5
- Livewire
- Alpine
- stack moderne autorisée uniquement pour /admin

Le poids du back-office n'est pas une contrainte pour le front public.

Hébergement :
- o2switch/cPanel
- SSH disponible
- Cron disponible

Préproduction :
https://dev.top-halal.fr/

Application :
/home/meyo5199/top-halal-v2

DocumentRoot :
/home/meyo5199/top-halal-v2/public

GitHub privé :
pamayo91/top-halal-v2

Branches :
- main = stable / future production
- develop = préproduction

Déploiement :
GitHub → git pull develop sur préproduction.

---

# 3. RÈGLES DE SÉCURITÉ

Legacy WordPress :

BDD :
meyo5199_th

Préfixe :
tp_

Accès legacy :
STRICTEMENT READ-ONLY.

Uploads legacy :
- /top-halal.fr/wp-content/uploads
- /top-halal.fr/wp-contenu/uploads

Ces dossiers servent uniquement de sources historiques.

INTERDIT :
- écrire dedans ;
- supprimer ;
- renommer ;
- optimiser les fichiers source.

Production :
aucune modification sans validation humaine explicite.

DNS / Apache :
aucune modification sans validation explicite sauf tâche spécifiquement autorisée.

Préproduction :
Codex peut développer, tester, migrer et utiliser SSH conformément à AGENTS.md.

---

# 4. GIT / CODEX

Codex travaille sur le repository existant.

À chaque nouvelle conversation :

1. lire AGENTS.md ;
2. lire docs/STATUS.md ;
3. lire les specs pertinentes ;
4. vérifier git status ;
5. ne pas reset les modifications locales existantes ;
6. ne pas refaire les phases terminées.

Codex doit travailler de manière autonome jusqu'à :
- DONE ;
ou
- vrai blocage externe/métier nécessitant une décision.

Éviter les comptes rendus intermédiaires inutiles.

---

# 5. MIGRATION LEGACY → V2

STATUT : DONE.

Migration complète validée et idempotente.

Données finales migrées :

Restaurants :
7 704

Articles :
121 publiés + 1 draft

Pages :
90 publiées

Commentaires humains :
713 approved
15 pending

Avis :
77 migrés
7 anomalies documentées

Utilisateurs :
544 migrés
1 utilisateur sans email ignoré

Claims legacy :
52
0 migré
52 ignorés comme non attribuables

Règle claims :
ne jamais inventer user_id.

Médias :
migration indépendante du stockage WordPress.

Les médias référencés ont été récupérés depuis :
- wp-content
- wp-contenu

Seulement 3 médias historiques sont réellement perdus.

Les anciens shortcodes Visual Composer ont été transformés/nettoyés.

Aucune URL wp-content/wp-contenu ne doit subsister dans le HTML V2 public.

---

# 6. UTILISATEURS LEGACY

545 comptes WordPress historiques.

544 migrés.

Les anciens hashes WordPress ne sont PAS conservés.

Stratégie :
- mot de passe temporaire configuré hors Git ;
- must_change_password = true ;
- premier login → changement obligatoire.

373 comptes n'ont aucune activité métier détectée mais existent réellement dans WordPress.

Décision :
NE PAS LES SUPPRIMER.

L'absence d'activité n'est pas un critère de suppression.

La stratégie d'emailing de ces comptes sera décidée plus tard.

---

# 7. MÉDIAS

STATUT : DONE.

Stockage V2 indépendant.

Fonctionnalités :
- originaux V2 ;
- variantes WebP ;
- checksum ;
- MIME ;
- dimensions ;
- srcset ;
- sizes ;
- width/height ;
- lazy-loading ;
- conservation des dimensions historiques inline.

Ne pas créer toutes les miniatures WordPress historiques.

Les petites images historiques ne doivent pas être agrandies artificiellement.

Exemple validé :
post legacy 27.

Les images de 300px restent affichées à ~300px sur desktop et responsive sur mobile.

---

# 8. SEO / ROUTES / REDIRECTIONS

STATUT : DONE.

Routes V2 et SEO structurel figés.

Moteur de redirections en base :

- exact
- regex
- query strings
- priorité
- activation
- 301
- 410
- compteur hits
- dernière utilisation
- cache
- audit boucles/chaînes/conflits

Import legacy :

454 règles applicatives actives.

3 règles infrastructure restent Apache.

Fallback métier demandé :

1. équivalent exact ;
2. catégorie/ville/parent pertinent ;
3. destination sémantiquement proche ;
4. fallback ultime 301 homepage.

Cette règle homepage est volontaire.

Canonical, trailing slash, sitemap, robots, breadcrumbs, JSON-LD et 404/410 sont implémentés.

AggregateRating :
une seule structure cohérente par restaurant.

---

# 9. SITEMAP

Nettoyage effectué.

Conservé :
/blog

/mon-compte :
- accessible
- canonical self
- noindex,follow
- hors sitemap

Anciennes pages techniques supprimées du sitemap et redirigées :

/home → /
/payment-success-2 → /
/blog-2 → /blog
/erreur-paiement → /
/payment-checkout → /
/payment-fail → /
/payment-success → /
/submit-listing → /
/hello → /

---

# 10. FRONT PUBLIC

STATUT : première version fonctionnelle terminée.

Comprend notamment :

- homepage
- navigation
- recherche restaurants
- listings
- filtres
- fiches restaurants
- articles
- pages
- avis
- commentaires
- auth
- compte
- recherche par proximité

Stack front :
Blade SSR + JS minimal.

La finition visuelle/performance globale sera faite à la fin des fonctionnalités.

---

# 11. BACK-OFFICE

STATUT : Filament 5 opérationnel.

URL :
/admin

Ancien back-office Blade supprimé.

Filament est le seul back-office.

Modules :

- Dashboard
- Restaurants
- Articles
- Pages
- Médias
- Avis
- Commentaires
- Claims
- Utilisateurs
- Catégories
- Features
- Géographie
- Redirections
- Réglages
- Journal d'audit

Fonctionnalités :
- tables
- recherche
- filtres
- pagination
- actions
- badges
- modération
- audit log
- permissions admin

Les redirections legacy ne peuvent pas intercepter /admin.

Actions "Voir sur le site" ajoutées lorsque pertinente.

Charset :
normaliseur déterministe en place.
Ne jamais appliquer une conversion globale aveugle.

---

# 12. DONNÉES PARASITES LEGACY

Des valeurs malveillantes historiques ont été trouvées dans Geography :
- PG_SLEEP
- DBMS_PIPE
- SELECT/XOR
- payloads scanners

Elles provenaient des données historiques, pas d'une vulnérabilité Laravel actuelle.

Le référentiel Geography a été nettoyé.

Les autres taxonomies ont également été auditées.

---

# 13. DATES HISTORIQUES

Les dates legacy doivent être conservées.

Utiliser les vraies dates métier et non la date de migration.

Articles/pages :
dates WordPress historiques.

Commentaires :
tp_comments.comment_date / comment_date_gmt.

Avis :
date historique ListingPro.

Utilisateurs :
user_registered.

Les dates sont affichées dans Filament lorsque pertinentes.

---

# 14. ADRESSES / GÉOLOCALISATION

C'est le chantier le plus récemment terminé.

Les 7 704 restaurants ont été audités et enrichis.

GPS historiques :

7 663 restaurants avaient déjà un GPS.

Qualité observée lors du pilote :
- distance médiane ancien GPS ↔ Géoplateforme : 14m
- GPS historiques globalement très fiables

Décision fondamentale :
NE PAS remplacer massivement les GPS historiques.

Les GPS existants sont conservés sauf correction manuelle/justifiée.

Phase 6A.2 : les 36 fiches dont le GPS était incomplet ont été retraitées automatiquement à partir de leur adresse structurée. 13 coordonnées ont été ajoutées (10 `housenumber`, 3 `street`), sans remplacer une seule coordonnée existante ; 23 fiches restent sans GPS faute d’adresse structurée exploitable.

---

# 15. MODÈLE D'ADRESSE

L'adresse historique est conservée.

Elle ne doit jamais être écrasée silencieusement.

Données structurées V2 :

- address / address_raw historique
- address_line1
- address_line2
- postal_code
- city_name
- city_code (code INSEE)
- country_code
- latitude
- longitude
- provider
- source ID
- score
- précision
- statut de confiance
- geocoded_at
- manually_verified_at si nécessaire

Référence administrative principale :
city_code / code INSEE.

Ne pas considérer les simples variations de texte comme des conflits :

- St / Saint
- accents
- tirets
- casse
- quartier vs commune
- nom usuel vs nom administratif

---

# 16. GÉOCODAGE

Provider principal France :

Géoplateforme / Base Adresse Nationale.

Architecture :

GeocodingService
→ GeoPlateformeProvider

Le système doit pouvoir accueillir d'autres providers plus tard.

Fonctionnalités déjà présentes :
- géocodage direct
- reverse geocoding
- cache
- rate limit
- timeout/retry
- score
- calcul distance
- qualification
- auto-géocodage batch idempotent des adresses structurées sans GPS (`data:autogeocode-missing-gps`)

---

# 17. QUALIFICATION GPS / ADRESSE

La confiance dans l'adresse et la confiance dans le GPS sont séparées.

Un restaurant peut avoir :

adresse APPROXIMATE

mais

GPS suffisamment fiable pour "autour de moi".

La recherche de proximité utilise :
proximity_status

et non uniquement geocoding_status.

Statuts de proximité :

- ELIGIBLE
- REVIEW_REQUIRED
- EXCLUDED

Les restaurants approximatifs peuvent rester visibles dans les recherches ville/catégorie même s'ils sont exclus d'un rayon précis.

---

# 18. ENRICHISSEMENT FINAL DES ADRESSES

Après plusieurs phases d'audit, géocodage et consolidation :

7 673 / 7 704 restaurants possèdent désormais :

- address_line1
- postal_code
- city_name
- city_code
- country_code

31 exceptions seulement restent.

Aucun GPS historique n'a été remplacé.

Aucune adresse historique n'a été modifiée.

---

# 19. 31 EXCEPTIONS ADRESSES

3 réellement inutilisables :
- Miam's
- Les deux rives : Restaurant Créteil
- Sushi Time's

Cause :
aucune adresse historique et aucun GPS.

3 REVIEW_REQUIRED pour Geography incompatible :
- La Friterie
- Antalya
- Kebab Les Nations

5 hors France :
- Montecristo Marrakech
- Restaurant riad Marrakech
- L'En-K Snacking Monaco
- Piznkeb
- Marché Royal

Ne pas attribuer FR artificiellement.

20 fiches "Mr." :
- même nom générique
- même adresse américaine 3137 Laguna Street
- aucun GPS
- probablement test/spam/import parasite
- conservées pour revue manuelle
- ne pas publier automatiquement.

Filament possède un filtre :
"Adresse à traiter".

---

# 20. BUG DE BATCH CORRIGÉ

Un ancien batch supposait que :

ID <= nombre de lignes.

Erreur :
les IDs restaurants ne sont pas continus et dépassent 7704.

Exemple :
O Sha possède des IDs V2 7708/7709.

Toutes les commandes batch concernées ont été auditées.

Les traitements doivent utiliser :
- chunkById
- cursor
- checkpoints réels
- legacy_wp_id
ou stratégie équivalente.

Test ajouté avec ID 9000.

Ne jamais revenir à un traitement basé sur ID <= count().

---

# 21. CAS O SHA

Cas de référence pour le système d'adresse.

Adresse historique :
46 Boulevard du Temple, Paris, France

Après correction :

address_line1 :
46 Boulevard du Temple

postal_code :
75011

city_name :
Paris

city_code :
75111

country_code :
FR

GPS historique conservé.

Le reverse GPS tombe sur le n°48 mais :
- adresse legacy
- source ID Géoplateforme enregistré

pointent vers le n°46.

Décision :
conserver 46.

Ce cas illustre qu'un reverse GPS ne doit jamais écraser aveuglément une adresse historique cohérente.

---

# 22. EMAILS

Couche transactionnelle déjà développée :

- verification email
- reset password
- changement password
- claims
- notifications futures

Queue + retry.

Préproduction :
MAIL_MAILER=log.

Avant production :
- vrai provider
- SPF
- DKIM
- DMARC

Pas encore de campagne réelle.

---

# 23. COMMENTAIRES

Nouveaux commentaires :

INTERDICTION de liens.

Refus serveur-side :
- URL
- domaine
- HTML link

Commentaires historiques peuvent contenir du texte ancien, mais aucun nouveau lien autorisé.

Modération admin disponible.

---

# 24. AVIS

77 avis historiques migrés.

Avis publics :
approved uniquement.

Un propriétaire ne peut pas :
- supprimer lui-même un avis
- approuver ses avis
- modifier l'avis utilisateur

AggregateRating calculé uniquement depuis les avis valides/visibles.

---

# 25. LIENS EXTERNES RESTAURANTS

Règle SEO importante :

AUCUNE URL externe restaurant dans le HTML public.

Pour site web / réseaux sociaux :

bouton public
→ endpoint interne V2
→ URL récupérée côté serveur
→ 302 externe.

Ne jamais exposer l'URL externe dans :
- HTML
- data attributes
- JSON-LD
- JS embarqué

si l'obfuscation est requise.

---

# 26. PROCHAINES GRANDES FONCTIONNALITÉS

À développer après la finalisation du système d'adresse :

1. Ajout public de restaurant
2. Espace propriétaire / modifications
3. Claims finalisés
4. Publicité / monétisation
5. IA éditoriale multi-provider
6. Emails réels / notifications / campagne legacy
7. Finition globale UX
8. Accessibilité
9. CWV / PageSpeed
10. Crawl SEO final
11. Playwright E2E complet
12. Sécurité
13. préparation production

---

# 27. FUTUR SYSTÈME PUBLICITAIRE

Prévu mais NON développé complètement.

Architecture souhaitée :

- advertisers
- campaigns
- creatives
- placements
- targeting
- stats agrégées par jour

Règles :

- aucun emplacement vide si pas de campagne
- publicité clairement sponsorisée
- vente directe d'encarts
- éviter scripts tiers lourds
- préserver CWV

---

# 28. FUTURE IA ÉDITORIALE

Architecture prévue :

multi-provider abstrait :

- OpenAI
- Anthropic
- Gemini
- Mistral
- autres

Pipeline :

sources réelles
→ déduplication
→ génération grounded
→ contrôles
→ draft / publication

Conserver :

- provider
- modèle
- prompt version
- sources
- dates
- tokens
- coût

Disclosure IA :

- configurable globalement
- override par article

Le backend doit toujours savoir si un contenu est généré par IA.

---

# 29. RÈGLE DE DÉVELOPPEMENT

Pour chaque nouvelle phase :

1. lire ce document ;
2. lire STATUS.md ;
3. lire les specs concernées ;
4. inspecter Git ;
5. implémenter ;
6. tester ;
7. corriger ;
8. tester préproduction ;
9. documenter ;
10. commit ;
11. push develop ;
12. déployer.

Ne pas revenir avec un simple état d'avancement.

Retour uniquement :
- DONE ;
ou
- vrai blocage nécessitant une décision.

---

# 30. PHASE SUIVANTE

PHASE 6A :

COMPOSANT D'ADRESSE INTELLIGENT RÉUTILISABLE

Objectif :

créer une UX unique réutilisable par :

- Filament admin
- futur ajout public restaurant
- future modification propriétaire

Fonctions attendues :

- autocomplete Géoplateforme
- sélection adresse
- CP/ville/code INSEE automatiques
- GPS automatique
- carte
- marqueur
- déplacement manuel
- fallback "adresse introuvable"
- niveaux de confiance
- audit
- détection doublon
- aucune saisie manuelle latitude/longitude pour utilisateur normal

Commencer par l'intégration Filament.

Une fois le composant validé en admin :
réutiliser le même service/composant pour le front public et l'espace propriétaire.
