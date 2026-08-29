# Pilote Géocodage / Reverse-géocodage

Mode : `pilote sans aucune écriture restaurants, coordonnées ou Geography`.
Provider : `Géoplateforme / Base Adresse Nationale`.
Cache : Laravel cache, 30 jours par requête normalisée ; timeout 10 s ; 2 retries ; 4 requêtes/s maximum hors cache.

## Synthèse

- Restaurants testés : **100**.
- Requêtes directes : **97** ; succès : **97** ; échecs : **0**.
- Reverse : **84** ; succès : **84** ; échecs : **0**.
- Distance GPS historique → résultat direct : médiane **14 m** ; min 0 m ; max 6625 m.
- Classes GPS : excellent 55, bon 21, approximatif 6, conflit 2.
- CP extrait identique : 67 / 68; ville identique : 48 / 68.

## Résultats détaillés

### V2 #1 / legacy #13453 — COMPLETE_WITH_GPS
- Adresse brute : `Rue Jean de Ponthieu 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1066316, 1.8328573`; direct : {"label":"Rue Jean de Ponthieu 80100 Abbeville","score":0.9640736363636363,"type":"street","id":"80001_1038","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.106446,"longitude":1.83279}; reverse : {"label":"10 Rue Jean de Ponthieu 80100 Abbeville","score":0.9993,"type":"housenumber","id":"80001_1038_00010","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.106642,"longitude":1.832958}.
- Distance : 21 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #4 / legacy #13454 — COMPLETE_WITH_GPS
- Adresse brute : `28 chaussee du bois 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1080799, 1.8370869`; direct : {"label":"28 Chaussée du Bois 80100 Abbeville","score":0.9694890909090909,"type":"housenumber","id":"80001_0210_00028","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.10808,"longitude":1.837087}; reverse : {"label":"28 Chaussée du Bois 80100 Abbeville","score":1,"type":"housenumber","id":"80001_0210_00028","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.10808,"longitude":1.837087}.
- Distance : 0 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #8 / legacy #13455 — COMPLETE_WITH_GPS
- Adresse brute : `15 boulevard de la republique 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1095196, 1.8391408`; direct : {"label":"15 Boulevard de la République 80100 Abbeville","score":0.9732572727272727,"type":"housenumber","id":"80001_1810_00015","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.109559,"longitude":1.839254}; reverse : {"label":"15 Boulevard de la République 80100 Abbeville","score":0.9991,"type":"housenumber","id":"80001_1810_00015","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.109559,"longitude":1.839254}.
- Distance : 9 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #9 / legacy #13456 — COMPLETE_WITH_GPS
- Adresse brute : `29 rue du maréchal Foch 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1049676, 1.8351987`; direct : {"label":"29 Rue du Maréchal Foch 80100 Abbeville","score":0.9695463636363635,"type":"housenumber","id":"80001_1260_00029","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.104961,"longitude":1.835153}; reverse : {"label":"29 Rue du Maréchal Foch 80100 Abbeville","score":0.9997,"type":"housenumber","id":"80001_1260_00029","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.104961,"longitude":1.835153}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #10 / legacy #13457 — COMPLETE_WITH_GPS
- Adresse brute : `17 avenue de la gare 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1026486, 1.8252937`; direct : {"label":"17 Avenue de la Gare 80100 Abbeville","score":0.96388,"type":"housenumber","id":"80001_0800_00017","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.102768,"longitude":1.82529}; reverse : {"label":"17 Avenue de la Gare 80100 Abbeville","score":0.9987,"type":"housenumber","id":"80001_0800_00017","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.102768,"longitude":1.82529}.
- Distance : 13 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #29 / legacy #13458 — COMPLETE_WITH_GPS
- Adresse brute : `Route Boucher De Perthes 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1059393, 1.8358482`; direct : {"label":"Rue Boucher de Perthes 80100 Abbeville","score":0.7575188026607538,"type":"street","id":"80001_0240","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.105956,"longitude":1.835926}; reverse : {"label":"31 Rue Boucher de Perthes 80100 Abbeville","score":0.9996,"type":"housenumber","id":"80001_0240_00031","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.105947,"longitude":1.835795}.
- Distance : 6 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #30 / legacy #13459 — COMPLETE_WITH_GPS
- Adresse brute : `1 Boulevard De La République 80100 Abbeville`; CP/ville extraits : `80100` / `Abbeville`; Geography : `Abbeville`.
- GPS historique : `50.1091881, 1.8392561`; direct : {"label":"1 Boulevard de la République 80100 Abbeville","score":0.9732572727272727,"type":"housenumber","id":"80001_1810_00001","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.109206,"longitude":1.839311}; reverse : {"label":"1 Boulevard de la République 80100 Abbeville","score":0.9996,"type":"housenumber","id":"80001_1810_00001","postcode":"80100","city":"Abbeville","citycode":"80001","latitude":50.109206,"longitude":1.839311}.
- Distance : 4 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #31 / legacy #13460 — COMPLETE_WITH_GPS
- Adresse brute : `24 avenue des Droits de L'homme 78260 Acheres`; CP/ville extraits : `78260` / `Acheres`; Geography : `Acheres`.
- GPS historique : `48.9505103, 2.0642055`; direct : {"label":"24 Avenue des Droits de l’Homme 78260 Achères","score":0.9650563636363635,"type":"housenumber","id":"78005_0138_00024","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.950662,"longitude":2.064406}; reverse : {"label":"24 Avenue des Droits de l’Homme 78260 Achères","score":0.9978,"type":"housenumber","id":"78005_0138_00024","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.950662,"longitude":2.064406}.
- Distance : 22 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #32 / legacy #13461 — COMPLETE_WITH_GPS
- Adresse brute : `43 Avenue de Stalingrad 78260 Achères`; CP/ville extraits : `78260` / `Achères`; Geography : `Acheres`.
- GPS historique : `48.9626060, 2.0709515`; direct : {"label":"43 Avenue Stalingrad 78260 Achères","score":0.8090399999999999,"type":"housenumber","id":"78005_0480_00043","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.962606,"longitude":2.070952}; reverse : {"label":"43 Avenue Stalingrad 78260 Achères","score":1,"type":"housenumber","id":"78005_0480_00043","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.962606,"longitude":2.070952}.
- Distance : 0 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #33 / legacy #13462 — COMPLETE_WITH_GPS
- Adresse brute : `2 Rue Paquet 78260 Achères`; CP/ville extraits : `78260` / `Achères`; Geography : `Acheres`.
- GPS historique : `48.9618719, 2.0709798`; direct : {"label":"2 Avenue Paquet 78260 Achères","score":0.6820309090909091,"type":"housenumber","id":"78005_0341_00002","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.96231,"longitude":2.07152}; reverse : {"label":"2 Rue Maurice Berteaux 78260 Achères","score":0.9954,"type":"housenumber","id":"78005_0308_00002","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.961464,"longitude":2.070891}.
- Distance : 63 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #34 / legacy #13463 — COMPLETE_WITH_GPS
- Adresse brute : `24 Avenue des Droits de l'Homme 78260 Achères`; CP/ville extraits : `78260` / `Achères`; Geography : `Acheres`.
- GPS historique : `48.9505103, 2.0642055`; direct : {"label":"24 Avenue des Droits de l’Homme 78260 Achères","score":0.9650563636363635,"type":"housenumber","id":"78005_0138_00024","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.950662,"longitude":2.064406}; reverse : {"label":"24 Avenue des Droits de l’Homme 78260 Achères","score":0.9978,"type":"housenumber","id":"78005_0138_00024","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.950662,"longitude":2.064406}.
- Distance : 22 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #35 / legacy #13464 — COMPLETE_WITH_GPS
- Adresse brute : `Avenue de Stalingrad 78260 Achères`; CP/ville extraits : `78260` / `Achères`; Geography : `Acheres`.
- GPS historique : `48.9613947, 2.0694236`; direct : {"label":"Avenue Stalingrad 78260 Achères","score":0.8028416528925618,"type":"street","id":"78005_0480","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.961633,"longitude":2.069746}; reverse : {"label":"47 Avenue Stalingrad 78260 Achères","score":0.9998,"type":"housenumber","id":"78005_0480_00047","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.961381,"longitude":2.069424}.
- Distance : 35 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #2 / legacy #13465 — COMPLETE_WITH_GPS
- Adresse brute : `42 Avenue de Stalingrad 78260 Achères`; CP/ville extraits : `78260` / `Achères`; Geography : `Acheres`.
- GPS historique : `48.9622243, 2.0708556`; direct : {"label":"42 Avenue Stalingrad 78260 Achères","score":0.8090399999999999,"type":"housenumber","id":"78005_0480_00042","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.96223,"longitude":2.070534}; reverse : {"label":"44 Avenue Stalingrad 78260 Achères","score":0.9981,"type":"housenumber","id":"78005_0480_00044","postcode":"78260","city":"Achères","citycode":"78005","latitude":48.962386,"longitude":2.070793}.
- Distance : 23 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #36 / legacy #13466 — COMPLETE_WITH_GPS
- Adresse brute : `1 Rue des Tamaris 34300 Agde`; CP/ville extraits : `34300` / `Agde`; Geography : `Agde`.
- GPS historique : `43.3035160, 3.4842790`; direct : {"label":"1 Rue des Tamaris 34300 Agde","score":0.9690790909090908,"type":"housenumber","id":"34003_2177_00001","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.303762,"longitude":3.483944}; reverse : {"label":"3 Rue des Tamaris 34300 Agde","score":0.9983,"type":"housenumber","id":"34003_2177_00003","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.303405,"longitude":3.484133}.
- Distance : 39 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #37 / legacy #13467 — COMPLETE_WITH_GPS
- Adresse brute : `3 Impasse du Globe 34300 Agde`; CP/ville extraits : `34300` / `Agde`; Geography : `Agde`.
- GPS historique : `43.2818978, 3.5151981`; direct : {"label":"3 Impasse du Globe 34300 Agde","score":0.9640854545454545,"type":"housenumber","id":"34003_0865_00003","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.281898,"longitude":3.515198}; reverse : {"label":"3 Impasse du Globe 34300 Agde","score":1,"type":"housenumber","id":"34003_0865_00003","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.281898,"longitude":3.515198}.
- Distance : 0 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #38 / legacy #13468 — COMPLETE_WITH_GPS
- Adresse brute : `6 parking du temps libre 34300 Agde`; CP/ville extraits : `34300` / `Agde`; Geography : `Agde`.
- GPS historique : `43.2769271, 3.5045279`; direct : {"label":"6 Pkg du Temps Libre 34300 Agde","score":0.6876718181818182,"type":"housenumber","id":"34003_2185_00006","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.277142,"longitude":3.504123}; reverse : {"label":"3 Passage des Noctambules 34300 Agde","score":0.9968,"type":"housenumber","id":"34003_1636_00003","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.277195,"longitude":3.504666}.
- Distance : 41 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #39 / legacy #13469 — COMPLETE_WITH_GPS
- Adresse brute : `2 rue de la plage 34300 Agde`; CP/ville extraits : `34300` / `Agde`; Geography : `Agde`.
- GPS historique : `43.2831575, 3.4478074`; direct : {"label":"2 Rue de la Plage 34300 Agde","score":0.9582627272727272,"type":"housenumber","id":"34003_1778_00002","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.283137,"longitude":3.447782}; reverse : {"label":"2 Rue de la Plage 34300 Agde","score":0.9997,"type":"housenumber","id":"34003_1778_00002","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.283137,"longitude":3.447782}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #40 / legacy #13470 — COMPLETE_WITH_GPS
- Adresse brute : `18 Chemin de Janin 34300 Agde`; CP/ville extraits : `34300` / `Agde`; Geography : `Agde`.
- GPS historique : `43.3086700, 3.4758262`; direct : {"label":"18 Chemin de Janin 34300 Agde","score":0.9699909090909091,"type":"housenumber","id":"34003_1240_00018","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.308697,"longitude":3.475924}; reverse : {"label":"18 Chemin de Janin 34300 Agde","score":0.9992,"type":"housenumber","id":"34003_1240_00018","postcode":"34300","city":"Agde","citycode":"34003","latitude":43.308697,"longitude":3.475924}.
- Distance : 8 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #41 / legacy #13471 — COMPLETE_WITH_GPS
- Adresse brute : `78 boulevard carnot 47000 Agen`; CP/ville extraits : `47000` / `Agen`; Geography : `Agen`.
- GPS historique : `44.2042054, 0.6207664`; direct : {"label":"78 Boulevard Carnot 47000 Agen","score":0.974430909090909,"type":"housenumber","id":"47001_3128_00078","postcode":"47000","city":"Agen","citycode":"47001","latitude":44.204122,"longitude":0.620663}; reverse : {"label":"78bis Boulevard Carnot 47000 Agen","score":0.9992,"type":"housenumber","id":"47001_3128_00078_bis","postcode":"47000","city":"Agen","citycode":"47001","latitude":44.204186,"longitude":0.62067}.
- Distance : 12 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #42 / legacy #13472 — COMPLETE_WITH_GPS
- Adresse brute : `45 Rue Augustins 47000 Agen`; CP/ville extraits : `47000` / `Agen`; Geography : `Agen`.
- GPS historique : `44.2065728, 0.6187344`; direct : {"label":"45 Rue des Augustins 47000 Agen","score":0.7690527272727272,"type":"housenumber","id":"47001_0220_00045","postcode":"47000","city":"Agen","citycode":"47001","latitude":44.206572,"longitude":0.618769}; reverse : {"label":"45 Rue des Augustins 47000 Agen","score":0.9997,"type":"housenumber","id":"47001_0220_00045","postcode":"47000","city":"Agen","citycode":"47001","latitude":44.206572,"longitude":0.618769}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #1299 / legacy #14733 — COMPLETE_NO_GPS
- Adresse brute : `36 rue de lHorloge 11400 Castelnaudary`; CP/ville extraits : `11400` / `Castelnaudary`; Geography : `Castelnaudary`.
- GPS historique : `, `; direct : {"label":"36 Rue de l'Horloge 11400 Castelnaudary","score":0.8091638461538462,"type":"housenumber","id":"11076_1490_00036","postcode":"11400","city":"Castelnaudary","citycode":"11076","latitude":43.32043,"longitude":1.954195}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #1897 / legacy #15335 — COMPLETE_NO_GPS
- Adresse brute : `1-3 Avenue de LEurope 60100 Creil`; CP/ville extraits : `60100` / `Creil`; Geography : `Creil`.
- GPS historique : `, `; direct : {"label":"3 Avenue de l'Europe 60100 Creil","score":0.757472780748663,"type":"housenumber","id":"60175_0352_00003","postcode":"60100","city":"Creil","citycode":"60175","latitude":49.268179,"longitude":2.483748}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #4023 / legacy #17463 — COMPLETE_NO_GPS
- Adresse brute : `32 Avenue de lEcole dAgriculture 34000 Montpellier`; CP/ville extraits : `34000` / `Montpellier`; Geography : `Montpellier`.
- GPS historique : `, `; direct : {"label":"32 Avenue de l'Ecole d'Agriculture-Gabriel Buchet 34000 Montpellier","score":0.637795294117647,"type":"housenumber","id":"34172_1871_00032","postcode":"34000","city":"Montpellier","citycode":"34172","latitude":43.612853,"longitude":3.859612}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #4073 / legacy #17513 — COMPLETE_NO_GPS
- Adresse brute : `55 Boulevard Rouget de LIsle 93100 Montreuil`; CP/ville extraits : `93100` / `Montreuil`; Geography : `Montreuil`.
- GPS historique : `, `; direct : {"label":"55 Boulevard Rouget de Lisle 93100 Montreuil","score":0.9749881818181817,"type":"housenumber","id":"93048_8270_00055","postcode":"93100","city":"Montreuil","citycode":"93048","latitude":48.858592,"longitude":2.437448}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #4231 / legacy #17671 — COMPLETE_NO_GPS
- Adresse brute : `11 rue de lArmée Patton 54000 Nancy`; CP/ville extraits : `54000` / `Nancy`; Geography : `Nancy`.
- GPS historique : `, `; direct : {"label":"11 Rue de l'Armée Patton 54000 Nancy","score":0.8150981818181817,"type":"housenumber","id":"54395_0260_00011","postcode":"54000","city":"Nancy","citycode":"54395","latitude":48.690628,"longitude":6.171195}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #4983 / legacy #18436 — COMPLETE_NO_GPS
- Adresse brute : `29 rue dEnghien 75010 Paris`; CP/ville extraits : `75010` / `Paris`; Geography : `Paris`.
- GPS historique : `, `; direct : {"label":"29 Rue d'Enghien 75010 Paris","score":0.7951894805194805,"type":"housenumber","id":"75110_3284_00029","postcode":"75010","city":"Paris","citycode":"75110","latitude":48.872005,"longitude":2.35051}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #7638 / legacy #22256 — COMPLETE_NO_GPS
- Adresse brute : `13 Terr. de l'Université, 92000 Nanterre`; CP/ville extraits : `92000` / `Nanterre`; Geography : `Nanterre`.
- GPS historique : `, `; direct : {"label":"13 Terrasse de l'Université 92000 Nanterre","score":0.7655241558441558,"type":"housenumber","id":"92050_9556_00013","postcode":"92000","city":"Nanterre","citycode":"92050","latitude":48.899837,"longitude":2.2122}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #7698 / legacy #22737 — COMPLETE_NO_GPS
- Adresse brute : `111 avenue du Rosny 93250 Villemomble`; CP/ville extraits : `93250` / `Villemomble`; Geography : `Villemomble`.
- GPS historique : `, `; direct : {"label":"111 Avenue de Rosny 93250 Villemomble","score":0.794591052631579,"type":"housenumber","id":"93077_8155_00111","postcode":"93250","city":"Villemomble","citycode":"93077","latitude":48.881973,"longitude":2.496961}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : true; ville match : true; reverse ville match : null.

### V2 #136 / legacy #13566 — PARTIAL_WITH_GPS
- Adresse brute : `31 rue charles de gaulle Alfortville`; CP/ville extraits : `` / ``; Geography : `Alfortville`.
- GPS historique : `48.8148283, 2.4158333`; direct : {"label":"31 Rue Charles de Gaulle 94140 Alfortville","score":0.9688818181818182,"type":"housenumber","id":"94002_1446_00031","postcode":"94140","city":"Alfortville","citycode":"94002","latitude":48.814684,"longitude":2.415916}; reverse : {"label":"31 Rue Charles de Gaulle 94140 Alfortville","score":0.9983,"type":"housenumber","id":"94002_1446_00031","postcode":"94140","city":"Alfortville","citycode":"94002","latitude":48.814684,"longitude":2.415916}.
- Distance : 17 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #400 / legacy #13831 — PARTIAL_WITH_GPS
- Adresse brute : `280 - 336 Route d'Enghien Argenteuil 95100`; CP/ville extraits : `` / ``; Geography : `Argenteuil`.
- GPS historique : `48.9565544, 2.2831453`; direct : {"label":"336 Route d’Enghien 95100 Argenteuil","score":0.7998339160839161,"type":"housenumber","id":"95018_1920_00336","postcode":"95100","city":"Argenteuil","citycode":"95018","latitude":48.957623,"longitude":2.288169}; reverse : {"label":"68 Rue de Champguerin 95100 Argenteuil","score":0.9995,"type":"housenumber","id":"95018_1130_00068","postcode":"95100","city":"Argenteuil","citycode":"95018","latitude":48.956524,"longitude":2.283086}.
- Distance : 386 m; classe : `MATCH_APPROXIMATE`; CP match : null; ville match : null; reverse ville match : null.

### V2 #615 / legacy #14049 — PARTIAL_WITH_GPS
- Adresse brute : `Galerie de l'ibis 97122 Baie-Mahault`; CP/ville extraits : `` / ``; Geography : `Baie-Mahault`.
- GPS historique : `16.2414330, -61.5648977`; direct : {"label":"Impasse des ibis rouges 97122 Baie-Mahault","score":0.47911727272727267,"type":"street","id":"97103_1587","postcode":"97122","city":"Baie-Mahault","citycode":"97103","latitude":16.258015,"longitude":-61.571802}; reverse : {"label":"20 Rue Henri Becquerel 97122 Baie-Mahault","score":0.997,"type":"housenumber","id":"97103_n0lkus_00020","postcode":"97122","city":"Baie-Mahault","citycode":"97103","latitude":16.24164,"longitude":-61.565084}.
- Distance : 1986 m; classe : `CONFLICT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #616 / legacy #14050 — PARTIAL_WITH_GPS
- Adresse brute : `Rue Nobel - Z.I Jarry 97122 Baie-Mahault`; CP/ville extraits : `` / ``; Geography : `Baie-Mahault`.
- GPS historique : `16.2387064, -61.5690621`; direct : {"label":"Rue Nobel 97122 Baie-Mahault","score":0.6572706879606879,"type":"street","id":"97103_0626","postcode":"97122","city":"Baie-Mahault","citycode":"97103","latitude":16.239056,"longitude":-61.565125}; reverse : {"label":"89 Rue Nobel 97122 Baie-Mahault","score":0.9973,"type":"housenumber","id":"97103_0626_00089","postcode":"97122","city":"Baie-Mahault","citycode":"97103","latitude":16.238533,"longitude":-61.569233}.
- Distance : 422 m; classe : `MATCH_APPROXIMATE`; CP match : null; ville match : null; reverse ville match : null.

### V2 #622 / legacy #14056 — PARTIAL_WITH_GPS
- Adresse brute : `1 Rue de Rosporden, 29380 Bannalec, France`; CP/ville extraits : `` / ``; Geography : `Bannalec`.
- GPS historique : `47.9322550, -3.6987838`; direct : {"label":"1 Rue de Rosporden 29380 Bannalec","score":0.7234495804195804,"type":"housenumber","id":"29004_0776_00001","postcode":"29380","city":"Bannalec","citycode":"29004","latitude":47.932283,"longitude":-3.698793}; reverse : {"label":"1 Rue de Rosporden 29380 Bannalec","score":0.9997,"type":"housenumber","id":"29004_0776_00001","postcode":"29380","city":"Bannalec","citycode":"29004","latitude":47.932283,"longitude":-3.698793}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #625 / legacy #14059 — PARTIAL_WITH_GPS
- Adresse brute : `3 Rue de Couchot, 55000 Bar-le-Duc, France`; CP/ville extraits : `` / ``; Geography : `Bar-le-Duc`.
- GPS historique : `48.7767844, 5.1603221`; direct : {"label":"3 Rue de Couchot 55000 Bar-le-Duc","score":0.7243695804195803,"type":"housenumber","id":"55029_0200_00003","postcode":"55000","city":"Bar-le-Duc","citycode":"55029","latitude":48.776872,"longitude":5.16035}; reverse : {"label":"1 Rue de Couchot 55000 Bar-le-Duc","score":0.9994,"type":"housenumber","id":"55029_0200_00001","postcode":"55000","city":"Bar-le-Duc","citycode":"55029","latitude":48.776827,"longitude":5.160262}.
- Distance : 10 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #630 / legacy #14064 — PARTIAL_WITH_GPS
- Adresse brute : `5 Rue du Général Nismes, 47230 Barbaste, France`; CP/ville extraits : `` / ``; Geography : `Barbaste`.
- GPS historique : `44.1707541, 0.2872479`; direct : {"label":"5 Rue du Général Nismes 47230 Barbaste","score":0.7350701652892562,"type":"housenumber","id":"47021_0055_00005","postcode":"47230","city":"Barbaste","citycode":"47021","latitude":44.170727,"longitude":0.287236}; reverse : {"label":"5 Rue du Général Nismes 47230 Barbaste","score":0.9997,"type":"housenumber","id":"47021_0055_00005","postcode":"47230","city":"Barbaste","citycode":"47021","latitude":44.170727,"longitude":0.287236}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #631 / legacy #14065 — PARTIAL_WITH_GPS
- Adresse brute : `16 Rue Jules Béraud, 04400 Barcelonnette, France`; CP/ville extraits : `` / ``; Geography : `Barcelonnette`.
- GPS historique : `44.3868246, 6.6517854`; direct : {"label":"16 Rue Jules Beraud 04400 Barcelonnette","score":0.7379309090909091,"type":"housenumber","id":"04019_0280_00016","postcode":"04400","city":"Barcelonnette","citycode":"04019","latitude":44.386791,"longitude":6.651857}; reverse : {"label":"18 Rue Jules Beraud 04400 Barcelonnette","score":0.9995,"type":"housenumber","id":"04019_0280_00018","postcode":"04400","city":"Barcelonnette","citycode":"04019","latitude":44.386778,"longitude":6.651796}.
- Distance : 7 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #632 / legacy #14066 — PARTIAL_WITH_GPS
- Adresse brute : `BARLIN KEBAB, 2 Rue Albert Legrand, 62620 Barlin, France`; CP/ville extraits : `` / ``; Geography : `Barlin`.
- GPS historique : `50.4588869, 2.6154784`; direct : {"label":"2 Rue Legrand 62620 Barlin","score":0.4430711888111888,"type":"housenumber","id":"62083_0010_00002","postcode":"62620","city":"Barlin","citycode":"62083","latitude":50.458947,"longitude":2.615457}; reverse : {"label":"2 Rue Legrand 62620 Barlin","score":0.9993,"type":"housenumber","id":"62083_0010_00002","postcode":"62620","city":"Barlin","citycode":"62083","latitude":50.458947,"longitude":2.615457}.
- Distance : 7 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #636 / legacy #14070 — PARTIAL_WITH_GPS
- Adresse brute : `Rue Delrieu 97100 Basse terre`; CP/ville extraits : `` / ``; Geography : `Basse-terre`.
- GPS historique : `16.0002310, -61.7317059`; direct : {"label":"Rue Delrieu 97100 Basse-Terre","score":0.9653763636363636,"type":"street","id":"97105_0260","postcode":"97100","city":"Basse-Terre","citycode":"97105","latitude":16.000165,"longitude":-61.731758}; reverse : {"label":"Rue Delrieu 97100 Basse-Terre","score":0.9991,"type":"street","id":"97105_0260","postcode":"97100","city":"Basse-Terre","citycode":"97105","latitude":16.000165,"longitude":-61.731758}.
- Distance : 9 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #637 / legacy #14071 — PARTIAL_WITH_GPS
- Adresse brute : `2 place Saint François 97100 Basse terre`; CP/ville extraits : `` / ``; Geography : `Basse-terre`.
- GPS historique : `15.9966210, -61.7309880`; direct : {"label":"2 Place Saint Francois 97100 Basse-Terre","score":0.9538354545454544,"type":"housenumber","id":"97105_0790_00002","postcode":"97100","city":"Basse-Terre","citycode":"97105","latitude":15.995084,"longitude":-61.730162}; reverse : {"label":"26 Rue du Docteur Cabre 97100 Basse-Terre","score":0.9991,"type":"housenumber","id":"97105_0290_00026","postcode":"97100","city":"Basse-Terre","citycode":"97105","latitude":15.996581,"longitude":-61.731058}.
- Distance : 192 m; classe : `MATCH_APPROXIMATE`; CP match : null; ville match : null; reverse ville match : null.

### V2 #639 / legacy #14073 — PARTIAL_WITH_GPS
- Adresse brute : `63 Boulevard du Général Graziani, 20200 Bastia, France`; CP/ville extraits : `` / ``; Geography : `Bastia`.
- GPS historique : `42.7052495, 9.4516245`; direct : {"label":"63 Boulevard du Général Graziani 20200 Bastia","score":0.7633923529411765,"type":"housenumber","id":"2b033_0830_00063","postcode":"20200","city":"Bastia","citycode":"2B033","latitude":42.705145,"longitude":9.451719}; reverse : {"label":"1 Rue Henri Tomasi 20200 Bastia","score":0.9992,"type":"housenumber","id":"2b033_2023_00001","postcode":"20200","city":"Bastia","citycode":"2B033","latitude":42.705205,"longitude":9.451543}.
- Distance : 14 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #646 / legacy #14080 — PARTIAL_WITH_GPS
- Adresse brute : `3 Rue des Armuriers, 25110 Baume-les-Dames, France`; CP/ville extraits : `` / ``; Geography : `Baume-les-Dames`.
- GPS historique : `47.3520667, 6.3605297`; direct : {"label":"3 Rue des Armuriers 25110 Baume-les-Dames","score":0.7421831334622824,"type":"housenumber","id":"25047_0100_00003","postcode":"25110","city":"Baume-les-Dames","citycode":"25047","latitude":47.351991,"longitude":6.360506}; reverse : {"label":"3 Rue des Armuriers 25110 Baume-les-Dames","score":0.9991,"type":"housenumber","id":"25047_0100_00003","postcode":"25110","city":"Baume-les-Dames","citycode":"25047","latitude":47.351991,"longitude":6.360506}.
- Distance : 9 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #648 / legacy #14082 — PARTIAL_WITH_GPS
- Adresse brute : `Rue Saint-Jean, 14400 Bayeux, France`; CP/ville extraits : `` / ``; Geography : `Bayeux`.
- GPS historique : `49.2753979, -0.6966617`; direct : {"label":"Rue Saint-Jean 14400 Bayeux","score":0.7048353719008263,"type":"street","id":"14047_1060","postcode":"14400","city":"Bayeux","citycode":"14047","latitude":49.275755,"longitude":-0.697721}; reverse : {"label":"95 Rue Saint-Jean 14400 Bayeux","score":0.9997,"type":"housenumber","id":"14047_1060_00095","postcode":"14400","city":"Bayeux","citycode":"14047","latitude":49.275412,"longitude":-0.696626}.
- Distance : 87 m; classe : `MATCH_GOOD`; CP match : null; ville match : null; reverse ville match : null.

### V2 #659 / legacy #14093 — PARTIAL_WITH_GPS
- Adresse brute : `32 Rue d'Espagne, 64100 Bayonne, France`; CP/ville extraits : `` / ``; Geography : `Bayonne`.
- GPS historique : `43.4891370, -1.4772169`; direct : {"label":"32 Rue d'Espagne 64100 Bayonne","score":0.7233390909090909,"type":"housenumber","id":"64102_0790_00032","postcode":"64100","city":"Bayonne","citycode":"64102","latitude":43.489212,"longitude":-1.477279}; reverse : {"label":"24 Rue Lagréou 64100 Bayonne","score":0.9995,"type":"housenumber","id":"64102_1290_00024","postcode":"64100","city":"Bayonne","citycode":"64102","latitude":43.489098,"longitude":-1.477234}.
- Distance : 10 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #662 / legacy #14096 — PARTIAL_WITH_GPS
- Adresse brute : `1 Avenue Voltaire, 95250 Beauchamp, France`; CP/ville extraits : `` / ``; Geography : `Beauchamp`.
- GPS historique : `49.0112129, 2.2018440`; direct : {"label":"1 Avenue Voltaire 95250 Beauchamp","score":0.7268177622377623,"type":"housenumber","id":"95051_0890_00001","postcode":"95250","city":"Beauchamp","citycode":"95051","latitude":49.01121,"longitude":2.201856}; reverse : {"label":"1 Avenue Voltaire 95250 Beauchamp","score":0.9999,"type":"housenumber","id":"95051_0890_00001","postcode":"95250","city":"Beauchamp","citycode":"95051","latitude":49.01121,"longitude":2.201856}.
- Distance : 1 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #674 / legacy #14108 — PARTIAL_WITH_GPS
- Adresse brute : `46 Rue du Faubourg Madeleine, 21200 Beaune, France`; CP/ville extraits : `` / ``; Geography : `Beaune`.
- GPS historique : `47.0207285, 4.8424318`; direct : {"label":"46 Rue du Faubourg Madeleine 21200 Beaune","score":0.756880406189555,"type":"housenumber","id":"21054_1698_00046","postcode":"21200","city":"Beaune","citycode":"21054","latitude":47.02078,"longitude":4.842475}; reverse : {"label":"46 Rue du Faubourg Madeleine 21200 Beaune","score":0.9993,"type":"housenumber","id":"21054_1698_00046","postcode":"21200","city":"Beaune","citycode":"21054","latitude":47.02078,"longitude":4.842475}.
- Distance : 7 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #677 / legacy #14111 — PARTIAL_WITH_GPS
- Adresse brute : `44 Rue de la République, 38270 Beaurepaire, France`; CP/ville extraits : `` / ``; Geography : `Beaurepaire`.
- GPS historique : `45.3387161, 5.0536367`; direct : {"label":"44 Rue de la République 38270 Beaurepaire","score":0.7496213152804642,"type":"housenumber","id":"38034_rttjei_00044","postcode":"38270","city":"Beaurepaire","citycode":"38034","latitude":45.338658,"longitude":5.053678}; reverse : {"label":"2 Rue Champollion 38270 Beaurepaire","score":0.9995,"type":"housenumber","id":"38034_0240_00002","postcode":"38270","city":"Beaurepaire","citycode":"38034","latitude":45.33876,"longitude":5.053616}.
- Distance : 7 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #683 / legacy #14117 — PARTIAL_WITH_GPS
- Adresse brute : `49 Rue Carnot, 60000 Beauvais, France`; CP/ville extraits : `` / ``; Geography : `Beauvais`.
- GPS historique : `49.4322299, 2.0841555`; direct : {"label":"49 Rue Carnot 60000 Beauvais","score":0.7118989839572192,"type":"housenumber","id":"60057_0700_00049","postcode":"60000","city":"Beauvais","citycode":"60057","latitude":49.432234,"longitude":2.084133}; reverse : {"label":"49 Rue Carnot 60000 Beauvais","score":0.9998,"type":"housenumber","id":"60057_0700_00049","postcode":"60000","city":"Beauvais","citycode":"60057","latitude":49.432234,"longitude":2.084133}.
- Distance : 2 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #684 / legacy #14118 — PARTIAL_WITH_GPS
- Adresse brute : `15 Boulevard de l'Assaut, 60000 Beauvais, France`; CP/ville extraits : `` / ``; Geography : `Beauvais`.
- GPS historique : `49.4350360, 2.0881035`; direct : {"label":"15 Boulevard de l'Assaut 60000 Beauvais","score":0.7503845454545454,"type":"housenumber","id":"60057_0300_00015","postcode":"60000","city":"Beauvais","citycode":"60057","latitude":49.435132,"longitude":2.088247}; reverse : {"label":"15 Boulevard de l'Assaut 60000 Beauvais","score":0.9985,"type":"housenumber","id":"60057_0300_00015","postcode":"60000","city":"Beauvais","citycode":"60057","latitude":49.435132,"longitude":2.088247}.
- Distance : 15 m; classe : `MATCH_EXCELLENT`; CP match : null; ville match : null; reverse ville match : null.

### V2 #2462 / legacy #15901 — PARTIAL_NO_GPS
- Adresse brute : ``; CP/ville extraits : `` / ``; Geography : `Grenoble`.
- GPS historique : `, `; direct : null; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7509 / legacy #21350 — PARTIAL_NO_GPS
- Adresse brute : ``; CP/ville extraits : `` / ``; Geography : ``.
- GPS historique : `, `; direct : null; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7544 / legacy #21641 — PARTIAL_NO_GPS
- Adresse brute : ``; CP/ville extraits : `` / ``; Geography : `Argenteuil`.
- GPS historique : `, `; direct : null; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7648 / legacy #22343 — PARTIAL_NO_GPS
- Adresse brute : `45 Place Drouet d'Erlon, Reims, France`; CP/ville extraits : `` / ``; Geography : `Reims`.
- GPS historique : `, `; direct : {"label":"45 Place Drouet d'Erlon 51100 Reims","score":0.7253827272727273,"type":"housenumber","id":"51454_3040_00045","postcode":"51100","city":"Reims","citycode":"51454","latitude":49.255412,"longitude":4.02679}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7649 / legacy #22352 — PARTIAL_NO_GPS
- Adresse brute : `100 Avenue Willy Brandt, Lille, France`; CP/ville extraits : `` / ``; Geography : `Lille`.
- GPS historique : `, `; direct : {"label":"Avenue Willy Brandt 59777 Lille","score":0.624318961038961,"type":"street","id":"59350_9337","postcode":"59777","city":"Lille","citycode":"59350","latitude":50.636198,"longitude":3.075819}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7650 / legacy #22358 — PARTIAL_NO_GPS
- Adresse brute : `59 avenue Marcel Mérieux`; CP/ville extraits : `` / ``; Geography : `Tours`.
- GPS historique : `, `; direct : {"label":"59 Avenue Marcel Mérieux 37200 Tours","score":0.9713181818181816,"type":"housenumber","id":"37261_3309_00059","postcode":"37200","city":"Tours","citycode":"37261","latitude":47.365624,"longitude":0.679694}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7651 / legacy #22364 — PARTIAL_NO_GPS
- Adresse brute : `31 Boulevard Lafayette, Clermont-Ferrand, France`; CP/ville extraits : `` / ``; Geography : `Clermont-Ferrand`.
- GPS historique : `, `; direct : {"label":"31 boulevard Lafayette 63000 Clermont-Ferrand","score":0.7638518181818181,"type":"housenumber","id":"63113_2570_00031","postcode":"63000","city":"Clermont-Ferrand","citycode":"63113","latitude":45.773491,"longitude":3.091908}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #7652 / legacy #22370 — PARTIAL_NO_GPS
- Adresse brute : `32 Rue Michel Ange, Sainte-Marie, La Réunion`; CP/ville extraits : `` / ``; Geography : `Sainte-Marie`.
- GPS historique : `, `; direct : {"label":"32 Rue Michel Ange 97438 Sainte-Marie","score":0.6543778048780488,"type":"housenumber","id":"97418_0443_00032","postcode":"97438","city":"Sainte-Marie","citycode":"97418","latitude":-20.899712,"longitude":55.517255}; reverse : null.
- Distance : n/a; classe : `n/a`; CP match : null; ville match : null; reverse ville match : null.

### V2 #159 / legacy #13590 — CONFLICT
- Adresse brute : `11 Avenue du Général De Gaulle 66110 Amelie Les Bains`; CP/ville extraits : `66110` / `Amelie Les Bains`; Geography : `Amelie-les-Bains-Palalda`.
- GPS historique : `42.4726479, 2.6713280`; direct : {"label":"11 Avenue General de Gaulle 66110 Amélie-les-Bains-Palalda","score":0.7154690909090908,"type":"housenumber","id":"66003_0046_00011","postcode":"66110","city":"Amélie-les-Bains-Palalda","citycode":"66003","latitude":42.472699,"longitude":2.671332}; reverse : {"label":"8 Avenue General de Gaulle 66110 Amélie-les-Bains-Palalda","score":0.9998,"type":"housenumber","id":"66003_0046_00008","postcode":"66110","city":"Amélie-les-Bains-Palalda","citycode":"66003","latitude":42.472629,"longitude":2.671337}.
- Distance : 6 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #540 / legacy #13974 — CONFLICT
- Adresse brute : `53 Rue de la commune de Paris 93300 Auvervilliers`; CP/ville extraits : `93300` / `Auvervilliers`; Geography : `Aubervilliers`.
- GPS historique : `48.9104452, 2.3806020`; direct : {"label":"53 Rue de la Commune de Paris 93300 Aubervilliers","score":0.8182627272727272,"type":"housenumber","id":"93001_1483_00053","postcode":"93300","city":"Aubervilliers","citycode":"93001","latitude":48.910453,"longitude":2.380155}; reverse : {"label":"53 Rue de la Commune de Paris 93300 Aubervilliers","score":0.9967,"type":"housenumber","id":"93001_1483_00053","postcode":"93300","city":"Aubervilliers","citycode":"93001","latitude":48.910453,"longitude":2.380155}.
- Distance : 33 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #2816 / legacy #16255 — CONFLICT
- Adresse brute : `La Rosiere 73700 La Rosiere 1850`; CP/ville extraits : `73700` / `La Rosiere 1850`; Geography : `La Rosiere`.
- GPS historique : `45.6272900, 6.8494640`; direct : {"label":"Rue de la Rosière 73700 Bourg-Saint-Maurice","score":0.43956999999999996,"type":"street","id":"73054_0229","postcode":"73700","city":"Bourg-Saint-Maurice","citycode":"73054","latitude":45.619873,"longitude":6.764936}; reverse : {"label":"806 Route du Col du Petit Saint Bernard 73700 Montvalezan","score":0.9969,"type":"housenumber","id":"73176_0180_00806","postcode":"73700","city":"Montvalezan","citycode":"73176","latitude":45.627568,"longitude":6.849481}.
- Distance : 6625 m; classe : `CONFLICT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #2870 / legacy #16309 — CONFLICT
- Adresse brute : `13 bis general patton 54410 Laneuville devant nancy`; CP/ville extraits : `54410` / `Laneuville devant nancy`; Geography : `Laneuveville-devant-Nancy`.
- GPS historique : `48.6574483, 6.2307548`; direct : {"label":"13 Rue du Général Patton 54410 Laneuveville-devant-Nancy","score":0.6954681047765794,"type":"housenumber","id":"54300_0150_00013","postcode":"54410","city":"Laneuveville-devant-Nancy","citycode":"54300","latitude":48.657383,"longitude":6.23072}; reverse : {"label":"13 Rue du Général Patton 54410 Laneuveville-devant-Nancy","score":0.9992,"type":"housenumber","id":"54300_0150_00013","postcode":"54410","city":"Laneuveville-devant-Nancy","citycode":"54300","latitude":48.657383,"longitude":6.23072}.
- Distance : 8 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #3119 / legacy #16558 — CONFLICT
- Adresse brute : `173 Rue Rivay 92300 Levallois`; CP/ville extraits : `92300` / `Levallois`; Geography : `Levallois-Perret`.
- GPS historique : `48.9007345, 2.2844768`; direct : {"label":"Rue Rivay 92300 Levallois-Perret","score":0.628198051948052,"type":"street","id":"92044_8150","postcode":"92300","city":"Levallois-Perret","citycode":"92044","latitude":48.895486,"longitude":2.288575}; reverse : {"label":"54 Quai Charles Pasqua 92300 Levallois-Perret","score":0.9982,"type":"housenumber","id":"92044_1458_00054","postcode":"92300","city":"Levallois-Perret","citycode":"92044","latitude":48.900829,"longitude":2.284672}.
- Distance : 656 m; classe : `MATCH_APPROXIMATE`; CP match : true; ville match : false; reverse ville match : false.

### V2 #4594 / legacy #18034 — CONFLICT
- Adresse brute : `Ozoir-la-Ferrière, 8 Place Roger Nicolas, 77330 France`; CP/ville extraits : `77330` / `France`; Geography : `Ozoir-la-Ferriere`.
- GPS historique : `48.7708094, 2.6893067`; direct : {"label":"8 Place Roger Nicolas 77330 Ozoir-la-Ferrière","score":0.727768881118881,"type":"housenumber","id":"77350_0702_00008","postcode":"77330","city":"Ozoir-la-Ferrière","citycode":"77350","latitude":48.770502,"longitude":2.689739}; reverse : {"label":"1 Place Roger Nicolas 77330 Ozoir-la-Ferrière","score":0.9987,"type":"housenumber","id":"77350_0702_00001","postcode":"77330","city":"Ozoir-la-Ferrière","citycode":"77350","latitude":48.770727,"longitude":2.689426}.
- Distance : 47 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6434 / legacy #19890 — CONFLICT
- Adresse brute : `6 rue du cdt cousteau 33240 St Andre de Cubzac`; CP/ville extraits : `33240` / `St Andre de Cubzac`; Geography : `Saint-Andre-de-Cubzac`.
- GPS historique : `44.9927008, -0.4485538`; direct : {"label":"6 Rue du Commandant Cousteau 33240 Saint-André-de-Cubzac","score":0.6504946708463949,"type":"housenumber","id":"33366_0197_00006","postcode":"33240","city":"Saint-André-de-Cubzac","citycode":"33366","latitude":44.992718,"longitude":-0.448629}; reverse : {"label":"4 Rue du Commandant Cousteau 33240 Saint-André-de-Cubzac","score":0.9995,"type":"housenumber","id":"33366_0197_00004","postcode":"33240","city":"Saint-André-de-Cubzac","citycode":"33366","latitude":44.99274,"longitude":-0.448531}.
- Distance : 6 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6440 / legacy #19896 — CONFLICT
- Adresse brute : `21 Porte Carrée 35140 St Aubin du Cormier`; CP/ville extraits : `35140` / `St Aubin du Cormier`; Geography : `Saint-Aubin-du-Cormier`.
- GPS historique : `48.2610847, -1.3987224`; direct : {"label":"21 Rue Porte Carree 35140 Saint-Aubin-du-Cormier","score":0.713600909090909,"type":"housenumber","id":"35253_0540_00021","postcode":"35140","city":"Saint-Aubin-du-Cormier","citycode":"35253","latitude":48.26106,"longitude":-1.39875}; reverse : {"label":"23 Rue Porte Carree 35140 Saint-Aubin-du-Cormier","score":0.9998,"type":"housenumber","id":"35253_0540_00023","postcode":"35140","city":"Saint-Aubin-du-Cormier","citycode":"35253","latitude":48.261086,"longitude":-1.398689}.
- Distance : 3 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6441 / legacy #19897 — CONFLICT
- Adresse brute : `90 avenue Lucien Boeuf 83370 St Aygulf`; CP/ville extraits : `83370` / `St Aygulf`; Geography : `Saint-Aygulf`.
- GPS historique : `43.3836031, 6.7218468`; direct : {"label":"90 Avenue Lucien BOEUF 83370 Fréjus","score":0.6549243243243242,"type":"housenumber","id":"83061_0313_00090","postcode":"83370","city":"Fréjus","citycode":"83061","latitude":43.383488,"longitude":6.722084}; reverse : {"label":"76 Avenue Lucien BOEUF 83370 Fréjus","score":0.9981,"type":"housenumber","id":"83061_0313_00076","postcode":"83370","city":"Fréjus","citycode":"83061","latitude":43.383643,"longitude":6.72208}.
- Distance : 23 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6442 / legacy #19898 — CONFLICT
- Adresse brute : `montée Château 69720 St Bonnet de Mure`; CP/ville extraits : `69720` / `St Bonnet de Mure`; Geography : `Saint-Bonnet-de-Mure`.
- GPS historique : `45.6902980, 5.0282720`; direct : {"label":"Montée du Château 69720 Saint-Bonnet-de-Mure","score":0.7182805785123968,"type":"street","id":"69287_0676","postcode":"69720","city":"Saint-Bonnet-de-Mure","citycode":"69287","latitude":45.690907,"longitude":5.028456}; reverse : {"label":"10 Montée du Château 69720 Saint-Bonnet-de-Mure","score":0.9997,"type":"housenumber","id":"69287_0676_00010","postcode":"69720","city":"Saint-Bonnet-de-Mure","citycode":"69287","latitude":45.690294,"longitude":5.028232}.
- Distance : 69 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6445 / legacy #19901 — CONFLICT
- Adresse brute : `8 Place de Martray 22000 St Brieuc`; CP/ville extraits : `22000` / `St Brieuc`; Geography : `Saint-Brieuc`.
- GPS historique : `48.5146105, -2.7637742`; direct : {"label":"8 Place du Martray 22000 Saint-Brieuc","score":0.6850097202797203,"type":"housenumber","id":"22278_3090_00008","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.514652,"longitude":-2.763934}; reverse : {"label":"Place du Martray 22000 Saint-Brieuc","score":0.9992,"type":"street","id":"22278_3090","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.514538,"longitude":-2.763782}.
- Distance : 13 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6451 / legacy #19907 — CONFLICT
- Adresse brute : `1 Place Haute du Chai 22000 St Brieuc`; CP/ville extraits : `22000` / `St Brieuc`; Geography : `Saint-Brieuc`.
- GPS historique : `48.5151879, -2.7622810`; direct : {"label":"1 Place du Chai 22000 Saint-Brieuc","score":0.6477254545454545,"type":"housenumber","id":"22278_0802_00001","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.514693,"longitude":-2.762703}; reverse : {"label":"11 Rue Houvenagle 22000 Saint-Brieuc","score":0.9991,"type":"housenumber","id":"22278_2330_00011","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.515161,"longitude":-2.762396}.
- Distance : 63 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6455 / legacy #19911 — CONFLICT
- Adresse brute : `27 Rue De L Abbé Josselin 22000 St Brieuc`; CP/ville extraits : `22000` / `St Brieuc`; Geography : `Saint-Brieuc`.
- GPS historique : `48.5174040, -2.7536190`; direct : {"label":"Rue Abbé Josselin 22000 Saint-Brieuc","score":0.6355499173553719,"type":"street","id":"22278_0030","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.517011,"longitude":-2.754783}; reverse : {"label":"24 Rue Jobert de Lamballe 22000 Saint-Brieuc","score":0.9989,"type":"housenumber","id":"22278_2560_00024","postcode":"22000","city":"Saint-Brieuc","citycode":"22278","latitude":48.517474,"longitude":-2.753511}.
- Distance : 96 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6460 / legacy #19916 — CONFLICT
- Adresse brute : `1 Rue Sadi carnot 72120 St Calais`; CP/ville extraits : `72120` / `St Calais`; Geography : `Saint-Calais`.
- GPS historique : `47.9200717, 0.7454624`; direct : {"label":"1 Rue Sadi Carnot 72120 Saint-Calais","score":0.7453218181818182,"type":"housenumber","id":"72269_1380_00001","postcode":"72120","city":"Saint-Calais","citycode":"72269","latitude":47.920029,"longitude":0.745279}; reverse : {"label":"Grande rue 72120 Saint-Calais","score":0.9993,"type":"street","id":"72269_1360","postcode":"72120","city":"Saint-Calais","citycode":"72269","latitude":47.920138,"longitude":0.745465}.
- Distance : 14 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6464 / legacy #19920 — CONFLICT
- Adresse brute : `21 cours a. de montgolfier 42400 St Chamond`; CP/ville extraits : `42400` / `St Chamond`; Geography : `Saint-Chamond`.
- GPS historique : `45.4715502, 4.5105114`; direct : {"label":"21 Cours Adrien de Montgolfier 42400 Saint-Chamond","score":0.6859139037433155,"type":"housenumber","id":"42207_0030_00021","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.471564,"longitude":4.510402}; reverse : {"label":"21 Cours Adrien de Montgolfier 42400 Saint-Chamond","score":0.9991,"type":"housenumber","id":"42207_0030_00021","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.471564,"longitude":4.510402}.
- Distance : 9 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6465 / legacy #19921 — CONFLICT
- Adresse brute : `7 rue des trois frères 42400 St Chamond`; CP/ville extraits : `42400` / `St Chamond`; Geography : `Saint-Chamond`.
- GPS historique : `45.4738073, 4.5091148`; direct : {"label":"7 Rue des trois Frères 42400 Saint-Chamond","score":0.7757383116883116,"type":"housenumber","id":"42207_3230_00007","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.473802,"longitude":4.509062}; reverse : {"label":"7 Rue des trois Frères 42400 Saint-Chamond","score":0.9996,"type":"housenumber","id":"42207_3230_00007","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.473802,"longitude":4.509062}.
- Distance : 4 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6469 / legacy #19925 — CONFLICT
- Adresse brute : `14 Place Germain Morel 42400 St Chamond`; CP/ville extraits : `42400` / `St Chamond`; Geography : `Saint-Chamond`.
- GPS historique : `45.4723185, 4.5100849`; direct : {"label":"14 Place Germain Morel 42400 Saint-Chamond","score":0.7697301298701297,"type":"housenumber","id":"42207_1135_00014","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.471984,"longitude":4.510203}; reverse : {"label":"10 Rue du pré château 42400 Saint-Chamond","score":0.997,"type":"housenumber","id":"42207_2650_00010","postcode":"42400","city":"Saint-Chamond","citycode":"42207","latitude":45.472056,"longitude":4.50998}.
- Distance : 38 m; classe : `MATCH_GOOD`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6476 / legacy #19932 — CONFLICT
- Adresse brute : `1277 Avenue Trevoux 01000 St Denis lès Bourg`; CP/ville extraits : `01000` / `St Denis lès Bourg`; Geography : `Saint-Denis-les-Bourg`.
- GPS historique : `46.1996948, 5.1909306`; direct : {"label":"1277 Avenue de Trévoux 01000 Saint-Denis-lès-Bourg","score":0.7434318181818181,"type":"housenumber","id":"01344_0476_01277","postcode":"01000","city":"Saint-Denis-lès-Bourg","citycode":"01344","latitude":46.199882,"longitude":5.191091}; reverse : {"label":"1277 Avenue de Trévoux 01000 Saint-Denis-lès-Bourg","score":0.9976,"type":"housenumber","id":"01344_0476_01277","postcode":"01000","city":"Saint-Denis-lès-Bourg","citycode":"01344","latitude":46.199882,"longitude":5.191091}.
- Distance : 24 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6486 / legacy #19942 — CONFLICT
- Adresse brute : `10 rue d'Amérique 88100 St Dié des Vosges`; CP/ville extraits : `88100` / `St Dié des Vosges`; Geography : `Saint-Die-des-Vosges`.
- GPS historique : `48.2890000, 6.9484770`; direct : {"label":"10 Rue d'Amerique 88100 Saint-Dié-des-Vosges","score":0.7812166115702478,"type":"housenumber","id":"88413_0070_00010","postcode":"88100","city":"Saint-Dié-des-Vosges","citycode":"88413","latitude":48.288753,"longitude":6.948478}; reverse : {"label":"10 Rue d'Amerique 88100 Saint-Dié-des-Vosges","score":0.9973,"type":"housenumber","id":"88413_0070_00010","postcode":"88100","city":"Saint-Dié-des-Vosges","citycode":"88413","latitude":48.288753,"longitude":6.948478}.
- Distance : 27 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #6490 / legacy #19946 — CONFLICT
- Adresse brute : `16 rue de st robert 38120 St Egrève`; CP/ville extraits : `38120` / `St Egrève`; Geography : `Saint-Egreve`.
- GPS historique : `45.2346864, 5.6776601`; direct : {"label":"16 rue de saint robert 38120 Saint-Égrève","score":0.6808775324675324,"type":"housenumber","id":"38382_0570_00016","postcode":"38120","city":"Saint-Égrève","citycode":"38382","latitude":45.234626,"longitude":5.677523}; reverse : {"label":"18bis rue de saint robert 38120 Saint-Égrève","score":0.9989,"type":"housenumber","id":"38382_0570_00018_bis","postcode":"38120","city":"Saint-Égrève","citycode":"38382","latitude":45.234667,"longitude":5.677525}.
- Distance : 13 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : false; reverse ville match : false.

### V2 #331 / legacy #13762 — COMPLETE_WITH_GPS
- Adresse brute : `Avenue des Platanes 66700 Argelès sur Mer`; CP/ville extraits : `66700` / `Argelès sur Mer`; Geography : `Argeles-sur-Mer`.
- GPS historique : `42.5520848, 3.0459592`; direct : {"label":"Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9567018181818182,"type":"street","id":"66008_0052","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.552443,"longitude":3.045766}; reverse : {"label":"10 Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9989,"type":"housenumber","id":"66008_0052_00010","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.55218,"longitude":3.045995}.
- Distance : 43 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #333 / legacy #13764 — COMPLETE_WITH_GPS
- Adresse brute : `Avenue des Platanes 66700 Argelès sur Mer`; CP/ville extraits : `66700` / `Argelès sur Mer`; Geography : `Argeles-sur-Mer`.
- GPS historique : `42.5520848, 3.0459592`; direct : {"label":"Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9567018181818182,"type":"street","id":"66008_0052","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.552443,"longitude":3.045766}; reverse : {"label":"10 Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9989,"type":"housenumber","id":"66008_0052_00010","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.55218,"longitude":3.045995}.
- Distance : 43 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #335 / legacy #13766 — COMPLETE_WITH_GPS
- Adresse brute : `Avenue des Platanes 66700 Argelès sur Mer`; CP/ville extraits : `66700` / `Argelès sur Mer`; Geography : `Argeles-sur-Mer`.
- GPS historique : `42.5520848, 3.0459592`; direct : {"label":"Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9567018181818182,"type":"street","id":"66008_0052","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.552443,"longitude":3.045766}; reverse : {"label":"10 Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9989,"type":"housenumber","id":"66008_0052_00010","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.55218,"longitude":3.045995}.
- Distance : 43 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #337 / legacy #13768 — COMPLETE_WITH_GPS
- Adresse brute : `Avenue des Platanes 66700 Argelès sur Mer`; CP/ville extraits : `66700` / `Argelès sur Mer`; Geography : `Argeles-sur-Mer`.
- GPS historique : `42.5520848, 3.0459592`; direct : {"label":"Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9567018181818182,"type":"street","id":"66008_0052","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.552443,"longitude":3.045766}; reverse : {"label":"10 Avenue des Platanes 66700 Argelès-sur-Mer","score":0.9989,"type":"housenumber","id":"66008_0052_00010","postcode":"66700","city":"Argelès-sur-Mer","citycode":"66008","latitude":42.55218,"longitude":3.045995}.
- Distance : 43 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #864 / legacy #14298 — PARTIAL_WITH_GPS
- Adresse brute : `Bois Colombes Distribution, 207 Avenue d'Argenteuil, 92270 Bois-Colombes, France`; CP/ville extraits : `` / ``; Geography : `Bois-Colombes`.
- GPS historique : `48.9218094, 2.2774733`; direct : {"label":"207 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.5139358373205741,"type":"housenumber","id":"92009_0050_00207","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.919971,"longitude":2.279578}; reverse : {"label":"253 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.9995,"type":"housenumber","id":"92009_0050_00253","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.921811,"longitude":2.277537}.
- Distance : 256 m; classe : `MATCH_APPROXIMATE`; CP match : null; ville match : null; reverse ville match : null.

### V2 #865 / legacy #14299 — PARTIAL_WITH_GPS
- Adresse brute : `Bois Colombes Distribution, 229 Avenue d'Argenteuil, 92270 Bois-Colombes, France`; CP/ville extraits : `` / ``; Geography : `Bois-Colombes`.
- GPS historique : `48.9218094, 2.2774733`; direct : {"label":"229 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.5139358373205741,"type":"housenumber","id":"92009_0050_00229","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.920899,"longitude":2.278593}; reverse : {"label":"253 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.9995,"type":"housenumber","id":"92009_0050_00253","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.921811,"longitude":2.277537}.
- Distance : 130 m; classe : `MATCH_GOOD`; CP match : null; ville match : null; reverse ville match : null.

### V2 #867 / legacy #14301 — PARTIAL_WITH_GPS
- Adresse brute : `Bois Colombes Distribution, 229 Avenue d'Argenteuil, 92270 Bois-Colombes, France`; CP/ville extraits : `` / ``; Geography : `Bois-Colombes`.
- GPS historique : `48.9218094, 2.2774733`; direct : {"label":"229 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.5139358373205741,"type":"housenumber","id":"92009_0050_00229","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.920899,"longitude":2.278593}; reverse : {"label":"253 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.9995,"type":"housenumber","id":"92009_0050_00253","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.921811,"longitude":2.277537}.
- Distance : 130 m; classe : `MATCH_GOOD`; CP match : null; ville match : null; reverse ville match : null.

### V2 #870 / legacy #14304 — PARTIAL_WITH_GPS
- Adresse brute : `Bois Colombes Distribution, 215 Avenue d'Argenteuil, 92270 Bois-Colombes, France`; CP/ville extraits : `` / ``; Geography : `Bois-Colombes`.
- GPS historique : `48.9218094, 2.2774733`; direct : {"label":"215 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.5139358373205741,"type":"housenumber","id":"92009_0050_00215","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.920381,"longitude":2.279144}; reverse : {"label":"253 Avenue d'Argenteuil 92270 Bois-Colombes","score":0.9995,"type":"housenumber","id":"92009_0050_00253","postcode":"92270","city":"Bois-Colombes","citycode":"92009","latitude":48.921811,"longitude":2.277537}.
- Distance : 200 m; classe : `MATCH_APPROXIMATE`; CP match : null; ville match : null; reverse ville match : null.

### V2 #1327 / legacy #14761 — COMPLETE_WITH_GPS
- Adresse brute : `Parvis de la préfecture 95000 Cergy`; CP/ville extraits : `95000` / `Cergy`; Geography : `Cergy`.
- GPS historique : `49.0351736, 2.0763492`; direct : {"label":"Parvis de la Préfecture 95000 Cergy","score":0.9580163636363634,"type":"street","id":"95127_A931","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.036274,"longitude":2.076804}; reverse : {"label":"3 Rue du Chemin Dupuis Vert 95000 Cergy","score":0.9878,"type":"housenumber","id":"95127_e54y6h_00003","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.035949,"longitude":2.075156}.
- Distance : 127 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #1330 / legacy #14764 — COMPLETE_WITH_GPS
- Adresse brute : `Parvis de la Préfecture 95000 Cergy`; CP/ville extraits : `95000` / `Cergy`; Geography : `Cergy`.
- GPS historique : `49.0351736, 2.0763492`; direct : {"label":"Parvis de la Préfecture 95000 Cergy","score":0.9580163636363634,"type":"street","id":"95127_A931","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.036274,"longitude":2.076804}; reverse : {"label":"3 Rue du Chemin Dupuis Vert 95000 Cergy","score":0.9878,"type":"housenumber","id":"95127_e54y6h_00003","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.035949,"longitude":2.075156}.
- Distance : 127 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #1331 / legacy #14765 — COMPLETE_WITH_GPS
- Adresse brute : `parvis de la préfecture 95000 Cergy`; CP/ville extraits : `95000` / `Cergy`; Geography : `Cergy`.
- GPS historique : `49.0351736, 2.0763492`; direct : {"label":"Parvis de la Préfecture 95000 Cergy","score":0.9580163636363634,"type":"street","id":"95127_A931","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.036274,"longitude":2.076804}; reverse : {"label":"3 Rue du Chemin Dupuis Vert 95000 Cergy","score":0.9878,"type":"housenumber","id":"95127_e54y6h_00003","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.035949,"longitude":2.075156}.
- Distance : 127 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #1332 / legacy #14766 — COMPLETE_WITH_GPS
- Adresse brute : `Parvis de la préfecture 95000 Cergy`; CP/ville extraits : `95000` / `Cergy`; Geography : `Cergy`.
- GPS historique : `49.0351736, 2.0763492`; direct : {"label":"Parvis de la Préfecture 95000 Cergy","score":0.9580163636363634,"type":"street","id":"95127_A931","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.036274,"longitude":2.076804}; reverse : {"label":"3 Rue du Chemin Dupuis Vert 95000 Cergy","score":0.9878,"type":"housenumber","id":"95127_e54y6h_00003","postcode":"95000","city":"Cergy","citycode":"95127","latitude":49.035949,"longitude":2.075156}.
- Distance : 127 m; classe : `MATCH_GOOD`; CP match : true; ville match : true; reverse ville match : true.

### V2 #223 / legacy #13654 — COMPLETE_WITH_GPS
- Adresse brute : `6 rue beaurepaire 49100 Angers`; CP/ville extraits : `49100` / `Angers`; Geography : `Angers`.
- GPS historique : `47.4737972, -0.5591289`; direct : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9769145454545454,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}; reverse : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9996,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}.
- Distance : 4 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #234 / legacy #13665 — COMPLETE_WITH_GPS
- Adresse brute : `6 Rue Beaurepaire 49100 Angers`; CP/ville extraits : `49100` / `Angers`; Geography : `Angers`.
- GPS historique : `47.4737972, -0.5591289`; direct : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9769145454545454,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}; reverse : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9996,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}.
- Distance : 4 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #235 / legacy #13666 — COMPLETE_WITH_GPS
- Adresse brute : `6 rue beaurepaire 49100 Angers`; CP/ville extraits : `49100` / `Angers`; Geography : `Angers`.
- GPS historique : `47.4737972, -0.5591289`; direct : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9769145454545454,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}; reverse : {"label":"6 Rue Beaurepaire 49100 Angers","score":0.9996,"type":"housenumber","id":"49007_0695_00006","postcode":"49100","city":"Angers","citycode":"49007","latitude":47.473762,"longitude":-0.559139}.
- Distance : 4 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #311 / legacy #13742 — COMPLETE_WITH_GPS
- Adresse brute : `210 rue Adolphe Pajeaud 92160 Antony`; CP/ville extraits : `92160` / `Antony`; Geography : `Antony`.
- GPS historique : `48.7408031, 2.2812801`; direct : {"label":"210 Rue Adolphe Pajeaud 92160 Antony","score":0.9777363636363635,"type":"housenumber","id":"92002_0020_00210","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}; reverse : {"label":"200 Rue Adolphe Pajeaud 92160 Antony","score":0.9979,"type":"housenumber","id":"92002_0020_00200","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}.
- Distance : 21 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #312 / legacy #13743 — COMPLETE_WITH_GPS
- Adresse brute : `210 rue Adolphe pajeaud 92160 Antony`; CP/ville extraits : `92160` / `Antony`; Geography : `Antony`.
- GPS historique : `48.7408031, 2.2812801`; direct : {"label":"210 Rue Adolphe Pajeaud 92160 Antony","score":0.9777363636363635,"type":"housenumber","id":"92002_0020_00210","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}; reverse : {"label":"200 Rue Adolphe Pajeaud 92160 Antony","score":0.9979,"type":"housenumber","id":"92002_0020_00200","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}.
- Distance : 21 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #313 / legacy #13744 — COMPLETE_WITH_GPS
- Adresse brute : `210 rue Adolphe Pajeaud 92160 Antony`; CP/ville extraits : `92160` / `Antony`; Geography : `Antony`.
- GPS historique : `48.7408031, 2.2812801`; direct : {"label":"210 Rue Adolphe Pajeaud 92160 Antony","score":0.9777363636363635,"type":"housenumber","id":"92002_0020_00210","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}; reverse : {"label":"200 Rue Adolphe Pajeaud 92160 Antony","score":0.9979,"type":"housenumber","id":"92002_0020_00200","postcode":"92160","city":"Antony","citycode":"92002","latitude":48.740753,"longitude":2.281552}.
- Distance : 21 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #584 / legacy #14018 — COMPLETE_WITH_GPS
- Adresse brute : `175 avenue henri ravera 92220 Bagneux`; CP/ville extraits : `92220` / `Bagneux`; Geography : `Bagneux`.
- GPS historique : `48.8039272, 2.3118813`; direct : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9754090909090909,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}; reverse : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}.
- Distance : 5 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #592 / legacy #14026 — COMPLETE_WITH_GPS
- Adresse brute : `175 Avenue Henri Ravera 92220 Bagneux`; CP/ville extraits : `92220` / `Bagneux`; Geography : `Bagneux`.
- GPS historique : `48.8039272, 2.3118813`; direct : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9754090909090909,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}; reverse : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}.
- Distance : 5 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #594 / legacy #14028 — COMPLETE_WITH_GPS
- Adresse brute : `175 avenue Henri Ravera 92220 Bagneux`; CP/ville extraits : `92220` / `Bagneux`; Geography : `Bagneux`.
- GPS historique : `48.8039272, 2.3118813`; direct : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9754090909090909,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}; reverse : {"label":"175 Avenue Henri Ravera 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_4451_00175","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.803895,"longitude":2.31184}.
- Distance : 5 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #595 / legacy #14029 — COMPLETE_WITH_GPS
- Adresse brute : `2 rue des bas Longchamps 92220 Bagneux`; CP/ville extraits : `92220` / `Bagneux`; Geography : `Bagneux`.
- GPS historique : `48.7891523, 2.3188435`; direct : {"label":"2 Rue des Bas Longchamps 92220 Bagneux","score":0.9682163636363635,"type":"housenumber","id":"92007_0620_00002","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789068,"longitude":2.318903}; reverse : {"label":"288 Avenue Aristide Briand 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_0070_00288","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789146,"longitude":2.31891}.
- Distance : 10 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #597 / legacy #14031 — COMPLETE_WITH_GPS
- Adresse brute : `288 av aristide Briand 92220 Bagneux`; CP/ville extraits : `92220` / `Bagneux`; Geography : `Bagneux`.
- GPS historique : `48.7891523, 2.3188435`; direct : {"label":"288 Avenue Aristide Briand 92220 Bagneux","score":0.7604436363636363,"type":"housenumber","id":"92007_0070_00288","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789146,"longitude":2.31891}; reverse : {"label":"288 Avenue Aristide Briand 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_0070_00288","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789146,"longitude":2.31891}.
- Distance : 5 m; classe : `MATCH_EXCELLENT`; CP match : true; ville match : true; reverse ville match : true.

### V2 #598 / legacy #14032 — COMPLETE_WITH_GPS
- Adresse brute : `288 avenue aristide briand 92290 bagneux`; CP/ville extraits : `92290` / `bagneux`; Geography : `Bagneux`.
- GPS historique : `48.7891523, 2.3188435`; direct : {"label":"288 Avenue Aristide Briand 92220 Bagneux","score":0.8033482926829268,"type":"housenumber","id":"92007_0070_00288","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789146,"longitude":2.31891}; reverse : {"label":"288 Avenue Aristide Briand 92220 Bagneux","score":0.9995,"type":"housenumber","id":"92007_0070_00288","postcode":"92220","city":"Bagneux","citycode":"92007","latitude":48.789146,"longitude":2.31891}.
- Distance : 5 m; classe : `MATCH_EXCELLENT`; CP match : false; ville match : true; reverse ville match : true.

## Estimation prudente sur 7 704

- Ne pas extrapoler automatiquement les résultats du pilote : la phase 3 devra réutiliser les seuils observés, avec journalisation et validation humaine des conflits.
- Les GPS participant à « autour de moi » devront être limités aux classes `VERIFIED`/`HIGH_CONFIDENCE`; exclure les clusters artificiels, approximatifs et conflits.
