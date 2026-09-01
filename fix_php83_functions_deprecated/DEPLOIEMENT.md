# Déploiement v1.3.1 — branche `fix_php83_functions_deprecated`

Guide de mise en production de la version **1.3.1**.

Ce dossier porte le nom de la branche et contient tout ce qui la concerne :

| Fichier | Rôle |
|---|---|
| `DEPLOIEMENT.md` | ce document : merge, tag, risques, rollback |
| `RECETTE.md` | procédure de recette à dérouler **dans un projet consommateur** |
| `recette_projet.php` | script de recette autonome, à copier dans le projet |
| `recette_strict.php` | appelant en `declare(strict_types=1)` utilisé par le script |

La version **1.4.0** (`fix_decrypt_file_silent_data_loss`) est traitée séparément et
**volontairement** : la 1.3.1 est déployable sans réflexion là où la 1.4.0 demande un
arbitrage. Ne les fusionnez pas en une seule release. Voir [Suite : v1.4.0](#suite--v140)
en fin de document.

---

## Périmètre de la 1.3.1

| Contenu | Fichier |
|---|---|
| Normalisation `null` avant `openssl_encrypt()` | `EncryptOpenSSL::encrypt_string()` |
| Normalisation `null` avant `fopen()` | `EncryptFileOpenSSL::encrypt_file()`, `decrypt_file()` |
| Correction du mapping PSR-4 (`"/"` → `""`) | `composer.json` |
| PHPDoc des paramètres non typés, `public` explicite sur `$FILE_ENCRYPTION_BLOCKS` | les deux classes |
| Suite de tests autonome (135 assertions) | `tests/` |

> **Portée réelle du correctif PSR-4** — mesuré : `"processid\\encrypt\\": "/"` ne produit
> une map cassée (`array('/')`, `class_exists()` → `false`) que lorsque la librairie est le
> package **racine** — c'est-à-dire dans son propre dépôt, ce qui empêchait `tests/run.php`
> d'utiliser l'autoloader. Installée comme **dépendance**, Composer résout `"/"` relativement
> au répertoire du paquet (`array($vendorDir . '/processid/encrypt')`) et le namespace se
> charge correctement. **Aucun projet consommateur n'était affecté** : le correctif est réel,
> son impact est limité à l'environnement de développement de la librairie.

**Risque : nul — aucun changement observable.** Le format de chiffrement et la dérivation
de clé sont inchangés : `base64(iv . hmac_sha512 . ciphertext)` et
`substr(sha1($password, true), 0, 16)` sont identiques à toutes les versions 1.x. Aucune
migration, aucun re-chiffrement.

L'interopérabilité a été vérifiée dans les deux sens (chaînes, accents, fichiers
multi-blocs) avec les versions antérieures, **y compris les copies pré-1.3.0** encore
déployées dans certains projets. La recette de ce dossier rejoue cette vérification
sur les données réelles du projet cible.

---

## Avant de merger

Sur la branche :

```bash
php tests/run.php          # doit afficher SUCCES et sortir en code 0
composer validate
```

Vérifier qu'aucune dépréciation n'est émise :

```bash
php -d error_reporting=E_ALL -d display_errors=1 tests/run.php 2>&1 \
  | grep -iE 'deprecated|warning|notice' && echo "PROBLÈME" || echo "propre"
```

Attendu : `135/135`. Sans `vendor/` (clone frais), une assertion de moins : le test
d'autoload est ignoré.

> **Note pour les relecteurs** — `.gitattributes` contient `*.php -text`
> **volontairement**. Les sources sont historiquement en CRLF ; un `text eol=crlf` ferait
> renormaliser les blobs déjà commités et transformerait un diff de 12 lignes en réécriture
> de 240 lignes.

> **Ne jamais régénérer `tests/golden_vectors.php`.** Ces blobs ont été produits par la
> version *antérieure* : les régénérer depuis le code corrigé leur ferait perdre tout
> intérêt.

### Limites connues de la suite de tests

À corriger dans un lot ultérieur — à connaître pour ne pas surestimer un `SUCCES` :

- Sur les quatre normalisations `null` ajoutées, **une seule est couverte**
  (`encrypt_file(null, $out)`). Supprimer les `??=` de `decrypt_file()` laisse la suite
  verte. Les trois cas manquants lèvent tous `ValueError` aujourd'hui, donc ils sont
  testables à l'identique : `decrypt_file(null, $out)`, `decrypt_file($in, null)`,
  `encrypt_file($in, null)`.
- Le handler d'erreurs de `tests/run.php` ne vérifie pas `error_reporting()` : le
  `@unlink()` du bloc de nettoyage peut lever et tuer le rapport (code de sortie 255 sans
  décompte). Correctif : `if (!(error_reporting() & $no)) { return false; }` en tête du
  handler.
- Plusieurs initialisations tournent hors du `try/catch` de `test()` (chargement/autoload,
  construction des jeux de données) : une dépréciation à cet endroit produit un fatal error
  au lieu d'un `FAIL` nommé.
- Le test « `$FILE_ENCRYPTION_BLOCKS` pilote le découpage » passe à l'identique avec la
  valeur par défaut : il ne démontre rien. L'assertion probante serait de chiffrer avec
  `blocks = 2` puis déchiffrer avec `blocks = 10000` et de constater la **non**-restitution.

La recette de ce dossier (`RECETTE.md`) couvre les quatre cas `null` et le contrat des
membres typés, y compris ceux que la suite du dépôt n'exerce pas.

---

## Merge

```bash
git push -u origin fix_php83_functions_deprecated
gh pr create --base master --head fix_php83_functions_deprecated \
  --title "fix: compatibilité PHP 8.3 sans rupture de compatibilité"
```

## Tag

Sans tag, aucun `composer update` ne verra le correctif : `composer.json` n'a pas de champ
`version`, la résolution passe uniquement par les tags git.

```bash
git checkout master && git pull
git tag v1.3.1
git push --tags
```

---

## Risque — matrice de compatibilité

Les paramètres modifiés ici sont restés **non typés délibérément**. Les typer casserait
tout projet appelant en mode strict qui transmet un scalaire (`encrypt_string(123)`,
`SetPassword(1234)`…), car le typage strict s'applique au site d'appel et non à la
librairie.

Ce qui est **vérifié par la suite de tests** (`tests/strict_caller.php`), depuis un
appelant en `declare(strict_types=1)` :

| Appel | Avant | 1.3.1 |
|---|---|---|
| `encrypt_string(int\|float\|bool)` | OK | OK (inchangé) |
| `encrypt_string(null)` | `Deprecated` + `''` | `''` sans dépréciation |
| `decrypt_string(int)` | OK | OK (inchangé) |
| `decrypt_string(null)` | `''` | `''` (inchangé) |
| `EncryptFileOpenSSL::SetPassword(int)` | OK | OK (inchangé) |
| `encrypt_file(null, $out)` | `Deprecated` + `ValueError` | `ValueError` sans dépréciation |

Ce qui **lève une exception, avant comme après** — non couvert par la suite, vérifié
manuellement :

| Appel | Résultat | Depuis un appelant non strict aussi ? |
|---|---|---|
| `encrypt_string(array)` | `TypeError` (`openssl_encrypt()`, arg #1) | oui |
| `decrypt_string(array non vide)` | `TypeError` (`base64_decode()`, arg #1) | oui |
| `EncryptFileOpenSSL::SetPassword(null\|array)` | `TypeError` | **oui** |
| `EncryptFileOpenSSL::SetMethod(null\|array)` | `TypeError` | **oui** |

> ⚠️ `EncryptFileOpenSSL::SetPassword()` et `SetMethod()` ont des paramètres **non typés**
> mais assignent dans des propriétés typées `string`. Elles lèvent donc `TypeError` pour
> `null` et `array` **quel que soit le mode de l'appelant**, strict ou non. Ce n'est pas une
> régression de cette version, mais le contrat réel est plus étroit que ce que la signature
> laisse croire.

> ⚠️ Cela ne s'applique pas non plus aux **membres déjà typés avant cette version** :
> `EncryptOpenSSL::__construct()`, `SetKey_aes256()`, `SetKey_hash512()`, `SetMethod()` et
> `EncryptFileOpenSSL::__construct()` sont typés `string` depuis la 1.3.0. Un appelant en
> `declare(strict_types=1)` qui leur transmet un scalaire obtient déjà une `TypeError`.
> Comportement inchangé par cette version, mais à connaître avant de migrer un projet
> strict.

**Seule différence de comportement observable, héritée de la 1.3.0 :**
`decrypt_string('0')` renvoie `''` au lieu de `false` (court-circuit `empty()`). Cas
limite — `"0"` n'est de toute façon pas un blob valide.

---

## Recette en projet consommateur

Avant de déployer dans un projet, dérouler **`RECETTE.md`** : capture de vecteurs avec la
version actuellement installée, mise à jour, puis vérification que ces vecteurs se
déchiffrent à l'identique et qu'aucune dépréciation ne subsiste.

```bash
cp fix_php83_functions_deprecated/recette_projet.php \
   fix_php83_functions_deprecated/recette_strict.php /chemin/vers/le/projet/
cd /chemin/vers/le/projet
php recette_projet.php --capture      # AVANT la mise à jour
composer require processid/encrypt:^1.3.1
php recette_projet.php                # APRÈS : doit sortir en code 0
```

---

## Après déploiement

Les dépréciations doivent disparaître des logs :

```bash
grep -iE 'openssl_encrypt.*Passing null|strlen.*Passing null|fopen.*Passing null' \
  /chemin/vers/php_errors.log
```

---

## Projets consommateurs

```bash
composer require processid/encrypt:^1.3.1
```

### Copies vendorisées obsolètes

Certains projets embarquent une copie **antérieure à la 1.3.0** (reconnaissable au code de
compatibilité PHP 5.3/5.6 : `1` au lieu de `OPENSSL_RAW_DATA`, et un repli `==` sur
`hash_equals`, vulnérable au timing).

Ces copies ont **deux** dépréciations PHP 8.3, pas une :

| Appel | Copie pré-1.3.0 | 1.3.1 |
|---|---|---|
| `encrypt_string(null)` | `Deprecated: openssl_encrypt()…` | OK |
| `decrypt_string(null)` | `Deprecated: strlen()…` | `''` |
| `decrypt_string('0')` | `false` | `''` |

Les repérer :

```bash
grep -rl 'La fonction hash_equals() n' --include='EncryptOpenSSL.php' .
```

La rupture annoncée au CHANGELOG 1.3.0 (`false` sur données corrompues) ne se matérialise
pas : ces versions renvoient déjà `false`.

Pour ces projets sans Composer, la recette s'utilise avec `--lib=` : voir
`RECETTE.md`, annexe « copies vendorisées ».

---

## Rollback

Le format n'ayant pas changé, **le retour arrière est sûr à tout moment** : les données
chiffrées avant, pendant ou après le déploiement restent lisibles par toutes les versions.

```bash
composer require processid/encrypt:1.3.0     # ou la version précédemment installée
```

Aucune donnée à restaurer, aucune migration à annuler.

---

## Hors périmètre

Problèmes connus, **non corrigés** par la 1.3.1. Aucun n'est introduit par cette version,
mais tous sont dans le code qu'elle touche : à connaître avant de déployer.

### Perte de données silencieuse

- **Chiffrement/déchiffrement « en place ».** `encrypt_file($p, $p)` et
  `decrypt_file($p, $p)` **détruisent le fichier et retournent `true`** : le fichier de
  destination est ouvert en mode `'w'` (donc tronqué) *avant* l'ouverture de la source, qui
  est ensuite lue vide. Vérifié : 2000 octets → 48 octets, retour `true`. **Ne jamais
  appeler ces méthodes avec le même chemin en entrée et en sortie.** À auditer dans les
  projets consommateurs :

  ```bash
  grep -rn --include='*.php' -E '(en|de)crypt_file\(\s*(\$[A-Za-z_]+)\s*,\s*\2\s*\)' .
  ```

- **`encrypt_file()` retourne `true` après un échec de lecture ou de chiffrement.** Seuls
  les échecs de `fopen()` positionnent l'indicateur d'erreur ; ceux de `fread()` et
  `openssl_encrypt()` sont ignorés. Vérifié : `encrypt_file('/tmp', $out)` — `fopen()` sur
  un répertoire réussit, `fread()` échoue — retourne `true` en n'écrivant que l'IV.
  La 1.4.0 corrige ce défaut **pour `decrypt_file()` uniquement** : `encrypt_file()` a le
  même trou et n'est pas au programme.

### Contrats

- Une **méthode de chiffrement inconnue** produit une `ValueError` peu explicite sur le
  chemin *chiffrement* (remontée depuis `openssl_random_pseudo_bytes(false)`), et un
  `E_WARNING` (`openssl_cipher_iv_length(): Unknown cipher algorithm`) sur le chemin
  *déchiffrement*. En PHP nu, `decrypt_string()` retourne `false` comme documenté ; **sous
  un framework qui convertit les warnings en exceptions** (handlers de debug Symfony,
  Laravel), c'est une `ErrorException` non rattrapée. Se déclenche à la configuration,
  jamais en cours d'exploitation avec une méthode valide.

- `$FILE_ENCRYPTION_BLOCKS` est un **état global mutable qui conditionne la lisibilité des
  fichiers** : la taille de chunk doit être identique entre le processus qui chiffre et
  celui qui déchiffre (l'IV d'un chunk est dérivé du chiffré du précédent). Un projet qui
  l'ajuste rend indéchiffrables ses fichiers déjà produits — et `decrypt_file()` retournera
  quand même `true`. La 1.3.1 se contente de rendre le `public` explicite ; la cible reste
  une `const`, ou une propriété d'instance avec la taille inscrite dans un en-tête de
  fichier versionné (donc une 2.0).

### Flux et cryptographie

- `fread()` est plafonné à 8192 octets sur les flux utilisateur (stream wrappers `s3://`,
  `gs://`, wrappers maison) : le découpage chiffrement/déchiffrement se désynchronise et
  corrompt silencieusement. **Les fichiers locaux ne sont pas affectés.** Les fichiers déjà
  produits via un wrapper sont déjà illisibles ; corriger ne les récupérera pas.

- La dérivation de clé fichier (`sha1` non salé, clé de 128 bits même pour `aes-256-*`,
  aucune authentification du fichier produit) est faible. La changer rendrait tous les
  fichiers existants irrécupérables : cela suppose un en-tête de fichier versionné, donc
  une 2.0 accompagnée d'un outil de migration.

### Distribution

- `tests/` est livré dans l'archive de distribution : chaque `vendor/processid/encrypt/`
  embarque la suite et ses clés de test en dur. La justification historique
  (« le script `composer test` casserait sinon chez les consommateurs ») est **fausse** :
  Composer n'exécute jamais les scripts d'une dépendance, seulement ceux du package racine.
  Si l'on veut alléger le dist, `/tests/ export-ignore` est sans effet de bord.

---

## Suite : v1.4.0

Branche `fix_decrypt_file_silent_data_loss`, à traiter **après** le tag `v1.3.1`. Elle
aura son propre dossier `fix_decrypt_file_silent_data_loss/` sur le même modèle
(déploiement + recette).

### Ce qui change

`decrypt_file()` retournait `true` **tout en écrivant un fichier vide ou tronqué** quand
`openssl_decrypt()` échouait (mot de passe erroné, méthode erronée, fichier corrompu).

```
                     retour   sortie
Avant                true     0 octet   ← perte silencieuse
Après                false    0 octet
Bon mot de passe     true     intact    (inchangé)
```

**Aucun déchiffrement légitime n'est affecté.** Cas vérifiés retournant toujours `true` :
fichier vide, 1 / 15 / 16 / 17 octets, taille de chunk exacte, chunk ×2 exact, chunk ×2 + 1,
fichier chiffré vide, fichier réduit à l'IV.

Attendu de la suite sur cette branche : `168/168`.

### Merge

La PR de la 1.4.0 doit cibler la branche de la 1.3.1, **pas `master`** — sinon les deux
commits apparaissent dans la même diff et la séparation est perdue.

```bash
git push -u origin fix_decrypt_file_silent_data_loss
gh pr create --base fix_php83_functions_deprecated \
  --head fix_decrypt_file_silent_data_loss \
  --title "fix: decrypt_file() signale l'échec au lieu d'écrire un fichier vide"
```

Après merge de la PR 1.3.1, GitHub rebase automatiquement celle de la 1.4.0 sur `master`.

```bash
git checkout master && git pull
git tag v1.4.0
git push --tags
```

### Risque — à surveiller

Les appelants qui **testent** la valeur de retour verront remonter des échecs jusque-là
invisibles. C'est l'objectif du correctif, mais cela peut révéler des documents corrompus
ou chiffrés avec une autre clé, restés silencieux jusqu'ici.

Les appelants qui **ignorent** la valeur de retour ne constatent aucun changement — et
restent donc exposés au problème d'origine.

Déploiement recommandé : **un projet à la fois, avec observation des logs**, plutôt qu'un
push simultané partout.

Avant de déployer, repérer les points d'appel sensibles dans chaque projet consommateur :

```bash
# Appels dont l'échec déclenche une suppression ou un renommage
grep -rn -A5 'decrypt_file(' --include='*.php' . | grep -B3 -E 'unlink|rename'

# Appels qui ignorent la valeur de retour
grep -rn --include='*.php' 'decrypt_file(' . \
  | grep -vE 'if\s*\(|if\(|=\s*\$|return |&&|\|\||!\$'
```

En cas d'échec, `decrypt_file()` laisse le fichier de destination partiellement écrit :
il n'est volontairement pas supprimé, le chemin étant fourni par l'appelant.

La 1.4.0 corrige la librairie, mais **ne protège pas un appelant qui ignore la valeur de
retour** de `decrypt_file()`. Les scripts de migration ou de reprise de données qui
suppriment la source après déchiffrement doivent être audités séparément.
