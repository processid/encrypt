## [1.2.4] - 2026-02-24

### 🔐 Sécurité
- Renforcement de la validation des données dans `decrypt_string()`.
- Renforcement de la validation des données dans `decrypt_string()`.
- Ajout d’un retour anticipé lorsque la donnée d’entrée est vide afin d’éviter un traitement inutile.
- Amélioration de la robustesse face aux données chiffrées corrompues ou malformées.
- Réduction des comportements indéfinis possibles lors du décodage Base64.

### 🛠 Modifications
- Typage strict
- Remplacement du test `strlen($data)` par un contrôle explicite `empty($data)` avec retour immédiat.
- Amélioration de la lisibilité et de la programmation défensive de la fonction.

### ♻️ Compatibilité
- Compatible PHP 8.0 et versions ultérieures.