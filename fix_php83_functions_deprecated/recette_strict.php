<?php

declare(strict_types=1);

// Recette v1.3.1 -- appelant en typage strict.
//
// Le typage strict s'applique au SITE D'APPEL, pas a la librairie : ce fichier
// reproduit le pire cas d'un projet consommateur en declare(strict_types=1).
// Si la librairie typait ses parametres, tous les appels de
// recette_strict_probe() leveraient une TypeError.
//
// Fichier compagnon de recette_projet.php -- ne s'execute pas seul.

use processid\encrypt\EncryptOpenSSL;
use processid\encrypt\EncryptFileOpenSSL;

/**
 * Execute une sonde et renvoie 'OK' ou le nom de la classe d'exception.
 * Factorise volontairement : les deux sondes ci-dessous partagent ce contrat.
 *
 * @param array<string,string> $res
 */
function recette_strict_sonde(array &$res, string $label, callable $fn): void
{
    try {
        $fn();
        $res[$label] = 'OK';
    } catch (Throwable $e) {
        $res[$label] = get_class($e);
    }
}

/**
 * Appels qui DOIVENT rester valides depuis un appelant strict.
 * Tout resultat different de 'OK' est une rupture de compatibilite ascendante.
 *
 * @return array<string,string> libelle => 'OK' ou classe d'exception
 */
function recette_strict_probe(string $keyAes, string $keyHash): array
{
    $res = [];
    $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');

    recette_strict_sonde($res, 'encrypt_string(int)',   static fn() => $o->encrypt_string(123));
    recette_strict_sonde($res, 'encrypt_string(float)', static fn() => $o->encrypt_string(1.5));
    recette_strict_sonde($res, 'encrypt_string(bool)',  static fn() => $o->encrypt_string(true));
    recette_strict_sonde($res, 'encrypt_string(null)',  static fn() => $o->encrypt_string(null));
    recette_strict_sonde($res, 'decrypt_string(int)',   static fn() => $o->decrypt_string(123));
    recette_strict_sonde($res, 'decrypt_string(null)',  static fn() => $o->decrypt_string(null));

    $f = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    recette_strict_sonde($res, 'SetPassword(int)',      static fn() => $f->SetPassword(1234));
    recette_strict_sonde($res, 'SetPassword(float)',    static fn() => $f->SetPassword(1.5));
    recette_strict_sonde($res, 'SetMethod(string)',     static fn() => $f->SetMethod('aes-256-cbc'));

    recette_strict_sonde($res, 'aller-retour scalaire', static function () use ($o): void {
        if ($o->decrypt_string($o->encrypt_string(123)) !== '123') {
            throw new RuntimeException('aller-retour scalaire rompu');
        }
    });

    return $res;
}

/**
 * Appels qui levent DEJA une TypeError, avant comme apres la 1.3.1.
 * Le contrat doit etre verifie et connu, pas suppose.
 *
 * Deux familles :
 *  - membres types `string` depuis la 1.3.0 (constructeurs, setters de EncryptOpenSSL) ;
 *  - EncryptFileOpenSSL::SetPassword()/SetMethod(), dont le parametre est NON type
 *    mais dont la propriete de destination est typee `string` : elles levent donc
 *    pour null et array meme depuis un appelant NON strict.
 *
 * @return array<string,string> libelle => classe d'exception attendue, ou 'OK'
 */
function recette_strict_typed_probe(string $keyAes, string $keyHash): array
{
    $res = [];

    recette_strict_sonde($res, 'EncryptOpenSSL::__construct(method=int)',
        static fn() => new EncryptOpenSSL($keyAes, $keyHash, 123));
    recette_strict_sonde($res, 'EncryptFileOpenSSL::__construct(password=int)',
        static fn() => new EncryptFileOpenSSL(1234, 'aes-256-cbc'));

    $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');
    recette_strict_sonde($res, 'EncryptOpenSSL::SetMethod(int)',      static fn() => $o->SetMethod(123));
    recette_strict_sonde($res, 'EncryptOpenSSL::SetKey_aes256(int)',  static fn() => $o->SetKey_aes256(123));
    recette_strict_sonde($res, 'EncryptOpenSSL::SetKey_hash512(int)', static fn() => $o->SetKey_hash512(123));

    // Propriete de destination typee : leve aussi depuis un appelant non strict.
    $f = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    recette_strict_sonde($res, 'EncryptFileOpenSSL::SetPassword(null)', static fn() => $f->SetPassword(null));
    recette_strict_sonde($res, 'EncryptFileOpenSSL::SetMethod(null)',   static fn() => $f->SetMethod(null));
    recette_strict_sonde($res, 'EncryptFileOpenSSL::SetPassword(array)', static fn() => $f->SetPassword([]));
    recette_strict_sonde($res, 'EncryptOpenSSL::encrypt_string(array)', static fn() => $o->encrypt_string([]));

    return $res;
}
