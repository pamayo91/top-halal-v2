# Audit intégrité des données migrées

Généré le : `2026-08-28T21:45:52+00:00`
Mode : `correction appliquée`

## Dates
### Articles
- Legacy : 123 enregistrements ; création disponible 121 ; modification disponible 122.
- V2 avant : 122 enregistrements ; identités legacy 121 ; modification legacy 122.
- Publication V2 avant : 0.
- Publication V2 après : 121.

### Pages
- Legacy : 91 enregistrements ; création disponible 91 ; modification disponible 91.
- V2 avant : 90 enregistrements ; identités legacy 90 ; modification legacy 90.
- Publication V2 avant : 0.
- Publication V2 après : 90.

### Comments
- Legacy : 728 enregistrements ; création disponible 728 ; modification disponible 728.
- V2 avant : 730 enregistrements ; identités legacy 728.

### Reviews
- Legacy : 84 enregistrements ; création disponible 83 ; modification disponible 84.
- V2 avant : 79 enregistrements ; identités legacy 77.

### Restaurants
- Legacy : 7704 enregistrements ; création disponible 7634 ; modification disponible 7636.
- V2 avant : 7704 enregistrements ; identités legacy 7634 ; modification legacy 7636.

### Users
- Legacy : 545 enregistrements ; création disponible 545.
- V2 avant : 544 enregistrements ; identités legacy 544.

### Media
- Legacy : 2239 enregistrements ; création disponible 2239 ; modification disponible 2239.
- V2 avant : 1459 enregistrements ; identités legacy 1048.

Les `created_at`/`updated_at` V2 restent des traces V2 lorsqu’ils ne sont pas déjà historiques. Les dates WordPress sont conservées dans les champs `legacy_*`; les articles/pages utilisent aussi `published_at` pour la publication historique.

## Géographie
- Total avant : 2003 ; utilisées : 1961 ; inutilisées : 42.
- Valides : 1971 ; suspectes : 0 ; manifestement malveillantes : 32 ; vides : 0.
- Doublons potentiels (non fusionnés automatiquement) : 1.
- Supprimées : 32.
- Supprimée V2 #1970 / legacy term #2703 : `-1 OR 2+228-228-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1971 / legacy term #2704 : `-1 OR 2+151-151-1=0+0+0+1` (aucun restaurant associé).
- Supprimée V2 #1972 / legacy term #2705 : `-1' OR 2+170-170-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1973 / legacy term #2706 : `-1' OR 2+478-478-1=0+0+0+1 or 'iBp5VbPs'='` (aucun restaurant associé).
- Supprimée V2 #1974 / legacy term #2707 : `-1" OR 2+745-745-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1975 / legacy term #2708 : `-1; waitfor delay '0:0:15' --` (aucun restaurant associé).
- Supprimée V2 #1976 / legacy term #2709 : `j3mFaN92'; waitfor delay '0:0:15' --` (aucun restaurant associé).
- Supprimée V2 #1977 / legacy term #2710 : `-5 OR 748=(SELECT 748 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1978 / legacy term #2711 : `-5) OR 738=(SELECT 738 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1979 / legacy term #2712 : `-1)) OR 147=(SELECT 147 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1980 / legacy term #2713 : `gmG59xgC' OR 715=(SELECT 715 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1981 / legacy term #2714 : `3ygNNNGQ') OR 127=(SELECT 127 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1982 / legacy term #2715 : `n92mCQhU')) OR 411=(SELECT 411 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1983 / legacy term #2716 : `1*DBMS_PIPE.RECEIVE_MESSAGE(CHR(99)||CHR(99)||CHR(99),15)` (aucun restaurant associé).
- Supprimée V2 #1984 / legacy term #2717 : `1'||DBMS_PIPE.RECEIVE_MESSAGE(CHR(98)||CHR(98)||CHR(98),15)||'` (aucun restaurant associé).
- Supprimée V2 #1985 / legacy term #2718 : `if(now()=sysdate(),sleep(15),0)` (aucun restaurant associé).
- Supprimée V2 #1986 / legacy term #2719 : `0'XOR(if(now()=sysdate(),sleep(15),0))XOR'Z` (aucun restaurant associé).
- Supprimée V2 #1987 / legacy term #2720 : `(select(0)from(select(sleep(15)))v)/*'+(select(0)from(select(sleep(15)))v)+'"+(select(0)from(select(sleep(15)))v)+"*/` (aucun restaurant associé).
- Supprimée V2 #1988 / legacy term #2721 : `@@aCecp` (aucun restaurant associé).
- Supprimée V2 #1990 / legacy term #2723 : `-1 OR 2+834-834-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1991 / legacy term #2724 : `-1 OR 2+47-47-1=0+0+0+1` (aucun restaurant associé).
- Supprimée V2 #1992 / legacy term #2725 : `-1' OR 2+760-760-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1993 / legacy term #2726 : `-1' OR 2+968-968-1=0+0+0+1 or 'Rfy2Pr42'='` (aucun restaurant associé).
- Supprimée V2 #1994 / legacy term #2727 : `-1" OR 2+790-790-1=0+0+0+1 --` (aucun restaurant associé).
- Supprimée V2 #1995 / legacy term #2728 : `Oj58QfQs'; waitfor delay '0:0:15' --` (aucun restaurant associé).
- Supprimée V2 #1996 / legacy term #2729 : `-5 OR 927=(SELECT 927 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1997 / legacy term #2730 : `-5) OR 191=(SELECT 191 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1998 / legacy term #2731 : `-1)) OR 16=(SELECT 16 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #1999 / legacy term #2732 : `zz1YJNCI' OR 405=(SELECT 405 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #2000 / legacy term #2733 : `fiknrRpf') OR 914=(SELECT 914 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #2001 / legacy term #2734 : `aKkqPham')) OR 453=(SELECT 453 FROM PG_SLEEP(15))--` (aucun restaurant associé).
- Supprimée V2 #2002 / legacy term #2735 : `@@tZu7Z` (aucun restaurant associé).

## Autres taxonomies
- categories : 22 entrées, 0 anomalie(s) détectée(s).
- features : 13 entrées, 0 anomalie(s) détectée(s).
- editorial_categories : 3 entrées, 0 anomalie(s) détectée(s).
- editorial_tags : 615 entrées, 0 anomalie(s) détectée(s).

## Corrections idempotentes
- Articles synchronisés : 122 ; pages : 90 ; restaurants : 0 ; médias : 1048.
