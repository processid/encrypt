# Recette v1.3.1 en projet consommateur

Procédure à dérouler **dans chaque projet** qui utilise `processid/encrypt`, avant de
généraliser la mise à jour. Elle répond à une seule question :

> les données déjà chiffrées par ce projet restent-elles lisibles, et les dépréciations
> PHP 8.3 disparaissent-elles réellement ?

Le principe : **capturer des vecteurs avec la version actuellement installée**, mettre à
jour, puis vérifier que ces vecteurs se déchiffrent à l'identique. Sans la phase de
capture, la recette ne prouve rien sur l'interopérabilité — c'est pour cela que le script
refuse d'écraser un fichier de vecteurs existant.

Durée : environ 10 minutes par projet. Aucune dépendance, aucun accès réseau.

---

## Prérequis

| | |
|---|---|
| PHP | ≥ 8.0 (l'intérêt du correctif commence à 8.1) |
| Extension | `openssl` |
| Accès | un environnement où le projet tourne : poste, intégration ou préproduction |
| Droits | écriture dans le répertoire courant et dans `sys_get_temp_dir()` |

**Ne pas dérouler la recette en production.** Elle chiffre et déchiffre des fichiers
temporaires, mais l'étape de mise à jour (`composer require`) modifie `vendor/`.

---

## Phase 0 — préparer

```bash
cd /chemin/vers/le/projet
cp /chemin/vers/encrypt/fix_php83_functions_deprecated/recette_projet.php .
cp /chemin/vers/encrypt/fix_php83_functions_deprecated/recette_strict.php .

php -v
composer show processid/encrypt 2>/dev/null | head -3
```

Noter la version installée : elle sera reportée sur le PV de recette.

Si `composer show` ne renvoie rien, le projet embarque probablement une **copie
vendorisée** : voir l'[annexe](#annexe--copies-vendorisées-sans-composer).

---

## Phase 1 — capturer l'état AVANT la mise à jour

```bash
php recette_projet.php --capture
```

Produit `recette_vecteurs.json` (~43 Ko) contenant :

- des blobs chiffrés par la **version actuelle**, avec leur clair attendu — 7 contenus
  (vide, ASCII, accents, `'0'`, JSON, binaire avec octets nuls, long) × `aes-256-cbc` et
  `aes-128-cbc` ;
- trois fichiers chiffrés par la version actuelle (vide, 16 octets, petit texte) ;
- la **liste des dépréciations réellement émises** par ce projet sur les sept chemins
  `null` de l'API.

Sortie attendue sur une version antérieure à la 1.3.1, sous PHP ≥ 8.1 :

```
== Etat des chemins null AVANT mise a jour ==
  DEPREC encrypt_string(null) => aucune exception
         Deprecated: openssl_encrypt(): Passing null to parameter #1 ($data) ...
  propre decrypt_string(null) => aucune exception
  DEPREC encrypt_file(null, $out) => ValueError
         Deprecated: fopen(): Passing null to parameter #1 ($filename) ...
  ...
  6 diagnostic(s) releve(s) avant mise a jour.

RESULTAT : SUCCES -- vecteurs captures, la mise a jour peut etre appliquee
```

**Zéro diagnostic n'est pas un échec** : cela signifie que la version installée est déjà
corrigée, ou que PHP est en 8.0. La recette vérifiera alors surtout la non-régression
fonctionnelle.

En revanche, un `ECHEC` à cette phase signifie que **la version de départ est déjà cassée**
(un aller-retour ne se referme pas) : ne pas mettre à jour avant d'avoir compris pourquoi.

> `recette_vecteurs.json` contient les clés de chiffrement générées pour la recette.
> Ne pas le committer ; le supprimer à la fin (phase 6).

Pour couvrir en plus les fichiers multi-chunk (160 000 et 161 234 octets, soit les cas où
le chaînage d'IV entre blocs entre en jeu) :

```bash
php recette_projet.php --capture --complet     # fichier de vecteurs ~460 Ko
```

À réserver aux projets qui chiffrent effectivement des fichiers volumineux ; il faudra
alors passer `--complet` **aussi** en phase 3.

---

## Phase 2 — mettre à jour

```bash
composer require processid/encrypt:^1.3.1
```

Vérifier que la version installée a changé :

```bash
composer show processid/encrypt | head -3
```

---

## Phase 3 — vérifier

```bash
php recette_projet.php
# ou : php recette_projet.php --complet   (si la capture a été faite avec --complet)
```

Le script sort en **code 0** si tout passe, **1** en cas d'échec, **2** s'il ne peut pas
dérouler la recette (vecteurs absents, librairie introuvable).

Les huit groupes vérifiés :

| Groupe | Ce qui est prouvé |
|---|---|
| 1. Interopérabilité des chaînes | 14 blobs produits **avant** la mise à jour se déchiffrent à l'identique |
| 2. Interopérabilité des fichiers | les fichiers chiffrés **avant** se déchiffrent au sha1 près |
| 3. Chemins `null` | les 7 chemins n'émettent plus aucun diagnostic, et la classe d'exception n'a pas changé |
| 4. Aller-retour | chaînes et fichiers avec la version installée, plus « mauvais mot de passe ne restitue pas le clair » |
| 5. Appelant strict | 10 appels depuis un `declare(strict_types=1)` restent acceptés |
| 6. Membres typés | 9 appels lèvent bien `TypeError` — contrat inchangé, y compris `SetPassword(null)`/`SetMethod(null)` |
| 7. Différences documentées | `decrypt_string('0')` et `decrypt_string([])` renvoient `''` |
| 8. Audit des sources | recherche des appels dangereux dans le code du projet |

Toute dépréciation, tout warning ou notice émis pendant **n'importe quelle** étape la fait
échouer : le script installe un handler qui les collecte (tout en respectant `@` et
`error_reporting()`, pour ne pas transformer un `@unlink()` légitime en échec).

### Interpréter la sortie

```
51/51 etapes reussies

RESULTAT : SUCCES -- la v1.3.1 est validee sur ce projet
```

- **`ECHEC` en groupe 1 ou 2** — arrêt immédiat, rollback (phase 5). Cela signifierait un
  changement de format, ce que la 1.3.1 n'est pas censée faire. Conserver
  `recette_vecteurs.json` et le joindre au rapport.
- **`ECHEC` en groupe 3** — la mise à jour n'a pas pris effet (`vendor/` non régénéré,
  copie vendorisée encore chargée en priorité, autre version résolue). Vérifier avec
  `composer show processid/encrypt` et l'en-tête « Version lib » du rapport.
- **`ECHEC` en groupe 5** — rupture de compatibilité ascendante réelle : ne pas déployer,
  remonter le cas.
- **`avert` en groupe 7 ou 8** — n'empêche pas la mise en production, mais demande une
  décision (voir ci-dessous).

### Avertissements du groupe 8

Le script analyse les sources `*.php` du projet (hors `vendor/`, `node_modules/`, `.git/`,
`cache/`, `var/`, `storage/`) et signale :

- **`encrypt_file($p, $p)` / `decrypt_file($p, $p)`** — appel « en place ». **Destructif :
  le fichier est tronqué et la méthode retourne `true` quand même.** Ce n'est pas un
  problème introduit par la 1.3.1 (voir *Hors périmètre* dans `DEPLOIEMENT.md`), mais si le
  script en trouve un, c'est une perte de données déjà en cours dans ce projet : à corriger
  indépendamment de la mise à jour, en écrivant vers un chemin temporaire puis en
  renommant.
- **appels à `decrypt_file()` dont la valeur de retour est ignorée** — sans conséquence
  aujourd'hui, mais ce sont exactement les points d'appel qui changeront de comportement
  avec la **1.4.0**. Les recenser maintenant fait gagner du temps sur la release suivante.

L'analyse est purement lexicale, ligne par ligne : un appel réparti sur plusieurs lignes
n'est pas détecté. Elle ne remplace pas une revue, elle donne un point de départ. Pour la
désactiver : `--sans-audit`.

---

## Phase 4 — contrôler les logs applicatifs

Faire tourner le projet (parcours fonctionnel habituel, ou suite de tests du projet) avec
les dépréciations visibles, puis :

```bash
grep -iE 'openssl_encrypt.*Passing null|strlen.*Passing null|fopen.*Passing null' \
  /chemin/vers/php_errors.log
```

Attendu : plus aucune occurrence **postérieure** à la mise à jour. Les occurrences
antérieures restent dans le fichier, comparer les horodatages.

---

## Phase 5 — rollback

Le format de chiffrement n'a pas changé : le retour arrière est sûr à tout moment, sans
aucune donnée à restaurer.

```bash
composer require processid/encrypt:<version précédente>
```

Le fichier `recette_vecteurs.json` reste exploitable après rollback : le rejouer prouve que
l'ancienne version relit bien ses propres données.

---

## Phase 6 — clôturer

```bash
rm recette_vecteurs.json recette_projet.php recette_strict.php
```

### PV de recette

À reporter dans le ticket de déploiement, un bloc par projet :

```
Projet            :
Environnement     : poste / intégration / préproduction
Date              :
PHP               :
Version avant     :
Version après     : 1.3.1
Capture           : N diagnostic(s) relevé(s) avant mise à jour
Vérification      : N/N étapes réussies, N avertissement(s), code de sortie 0
--complet         : oui / non
Groupe 8          : N appel(s) en place, N retour(s) ignoré(s)
Logs (phase 4)    : aucune dépréciation postérieure à la mise à jour
Décision          : GO / NO-GO
Recetté par       :
```

---

## Annexe — copies vendorisées sans Composer

Les projets qui embarquent une copie des classes (souvent **antérieure à la 1.3.0** :
`1` au lieu de `OPENSSL_RAW_DATA`, repli `==` sur `hash_equals`) n'ont pas d'autoloader à
faire pointer. Le script se charge alors des fichiers directement :

```bash
# Phase 1 : capture avec la copie embarquée du projet
php recette_projet.php --capture --lib=/chemin/vers/la/copie

# Phase 2 : remplacer la copie par l'installation Composer
composer require processid/encrypt:^1.3.1
# ... puis supprimer les anciens fichiers et les require qui les chargent

# Phase 3 : vérification via l'autoloader
php recette_projet.php
```

Sur ces copies, attendre en phase 1 **deux** familles de dépréciations
(`openssl_encrypt()` et `strlen()`), et en phase 3 un `avert` sur
`decrypt_string('0')` : ces versions renvoient `false` là où la 1.3.x renvoie `''`
(court-circuit `empty()`). C'est la seule différence de comportement documentée de la
migration ; `"0"` n'est de toute façon pas un blob valide.

Les repérer dans le parc :

```bash
grep -rl 'La fonction hash_equals() n' --include='EncryptOpenSSL.php' .
```

---

## Annexe — options du script

```
--capture              phase 1 : produit le fichier de vecteurs
--force                autorise l'écrasement d'un fichier de vecteurs existant
--vecteurs=<fichier>   défaut : ./recette_vecteurs.json
--autoload=<fichier>   vendor/autoload.php à utiliser (sinon recherché en remontant)
--lib=<répertoire>     répertoire contenant EncryptOpenSSL.php et EncryptFileOpenSSL.php
--complet              ajoute les cas multi-chunk (fichier de vecteurs ~460 Ko)
--sans-audit           n'analyse pas les sources du projet
--help
```

Codes de sortie : `0` succès, `1` échec de recette, `2` recette impossible à dérouler.
