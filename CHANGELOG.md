## [1.3.1] - 2026-09-01

### ⚡ Compatibilité PHP 8.3
- `EncryptOpenSSL::encrypt_string()` : `null` est désormais normalisé en chaîne vide
  avant l'appel à `openssl_encrypt()`. Corrige la dépréciation PHP 8.1+
  « openssl_encrypt(): Passing null to parameter #1 ($data) of type string is deprecated ».
  Le blob produit est strictement identique à celui de la version 1.3.0.
- `EncryptFileOpenSSL::encrypt_file()` / `decrypt_file()` : `null` normalisé en chaîne vide
  avant `fopen()`, même dépréciation supprimée. `fopen('')` lève la même `ValueError`
  qu'auparavant : la classe d'exception est inchangée.
- `EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS` : visibilité `public` rendue explicite
  (elle l'était déjà implicitement — aucun changement de comportement).
- Ajout de PHPDoc `@param` sur les paramètres non typés, pour l'analyse statique et l'IDE.

### ✅ Compatibilité — aucune rupture
- **Les paramètres modifiés ici restent volontairement non typés.** Les typer
  (`string` / `?string`) casserait tout appelant en `declare(strict_types=1)` transmettant
  un scalaire : `encrypt_string(123)`, `SetPassword(1234)`… Le typage strict s'applique au
  site d'appel, pas à la librairie. Vérifié : 14 appels régressaient avec des paramètres typés.
- ⚠️ **Ne concerne pas les membres déjà typés avant cette version.**
  `EncryptOpenSSL::__construct()`, `SetKey_aes256()`, `SetKey_hash512()`, `SetMethod()` et
  `EncryptFileOpenSSL::__construct()` sont typés `string` depuis la 1.3.0 : un appelant strict
  qui leur transmet un scalaire obtient déjà une `TypeError`. Comportement inchangé ici, mais
  désormais couvert par les tests plutôt qu'affirmé.
- **Format et dérivation de clé inchangés** : `base64(iv . hmac_sha512 . ciphertext)` et
  `substr(sha1($password, true), 0, 16)`.
- Compatibilité des données vérifiée **dans les deux sens** entre 1.3.0 et 1.3.1
  (chaînes et fichiers multi-blocs). Aucune migration, aucun re-chiffrement, rollback sûr.
- Matrice de compatibilité vérifiée sur `null`, `int`, `float` et `bool`, avec et sans
  `declare(strict_types=1)` : **aucun changement de comportement**. Les cas `array` lèvent
  une `TypeError` avant comme après (`encrypt_string([])`, `decrypt_string([1])`) ; seul le
  tableau vide passé à `decrypt_string()` est court-circuité par `empty()` et renvoie `''`.
- `EncryptFileOpenSSL::SetPassword()` et `SetMethod()` ont des paramètres non typés mais
  affectent des propriétés typées `string` : elles lèvent `TypeError` pour `null` et `array`
  quel que soit le mode de l'appelant, strict ou non. Contrat inchangé ici, mais plus étroit
  que ce que la signature laisse croire.

### 🧪 Tests
- Suite de tests autonome, sans dépendance : `php tests/run.php` (ou `composer test`).
- Vecteurs de non-régression (`tests/golden_vectors.php`) produits par la version
  antérieure : les données chiffrées par les versions 1.x se déchiffrent à l'identique.
- `tests/strict_caller.php` : vérifie qu'un appelant en `declare(strict_types=1)` peut
  toujours transmettre des scalaires.
- Toute dépréciation, notice ou warning PHP fait échouer la suite.
- La suite fonctionne sans `vendor/` (le test d'autoload est alors ignoré, pas en échec).

### 🔧 Divers
- `composer.json` : correction du mapping PSR-4 qui pointait sur `"/"`. Mesuré : la map
  générée ne contenait le littéral `array('/')` — donc un autoload inopérant — que lorsque
  la librairie est le package **racine** (son propre dépôt, ce qui empêchait la suite de
  tests d'utiliser l'autoloader). Installée comme **dépendance**, Composer résolvait `"/"`
  relativement au répertoire du paquet : **aucun projet consommateur n'était affecté.**
- `composer.json` : ajout de `license` (MIT, conforme au fichier LICENSE), `type`,
  et du script `composer test`.
- Ajout de `.gitignore` et `.gitattributes` (les sources sont en CRLF : normalisation désactivée).
- Correction du commentaire d'entête de `EncryptFileOpenSSL.php` qui documentait un
  constructeur à 3 arguments inexistant.

### ⚠️ Problèmes connus, non corrigés dans cette version
- `decrypt_file()` retourne `true` et écrit un fichier vide ou tronqué lorsque le mot de
  passe ou la méthode sont incorrects (`openssl_decrypt()` retourne `false` silencieusement).
- Une méthode de chiffrement inconnue produit une `ValueError` peu explicite.
- `fread()` étant plafonné à 8192 octets sur les flux utilisateur (stream wrappers), le
  découpage chiffrement/déchiffrement se désynchronise. Les fichiers locaux ne sont pas affectés.

## [1.3.0] - 2026-02-24

### 🔐 Sécurité
- Renforcement de la validation des données dans `decrypt_string()`.
- Ajout d’un retour anticipé lorsque la donnée d’entrée est vide afin d’éviter un traitement inutile.
- Amélioration de la robustesse face aux données chiffrées corrompues ou malformées.
- Réduction des comportements indéfinis possibles lors du décodage Base64.

### 🛠 Modifications
- Typage strict
- Remplacement du test `strlen($data)` par un contrôle explicite `empty($data)` avec retour immédiat.
- Amélioration de la lisibilité et de la programmation défensive de la fonction.

### ⚠️ Rupture de compatibilité
- `decrypt_string()` retourne désormais `false` (au lieu d'une chaîne vide) en cas de données corrompues ou de HMAC invalide.

### ♻️ Compatibilité
- Compatible PHP 8.0 et versions ultérieures.