<?php

declare(strict_types=1);

// Simule un projet appelant qui active le typage strict. Le typage strict
// s'applique au SITE D'APPEL : si la librairie typait ses parametres, tous les
// appels ci-dessous leveraient une TypeError. Ils doivent rester valides.

use processid\encrypt\EncryptOpenSSL;
use processid\encrypt\EncryptFileOpenSSL;

/**
 * @return array<string,string> libelle => resultat ('OK' ou la classe d'exception)
 */
function strict_caller_probe(string $keyAes, string $keyHash): array
{
    $res = [];
    $probe = static function (string $label, callable $fn) use (&$res): void {
        try {
            $fn();
            $res[$label] = 'OK';
        } catch (Throwable $e) {
            $res[$label] = get_class($e);
        }
    };

    $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');

    $probe('encrypt_string(int)',    static fn() => $o->encrypt_string(123));
    $probe('encrypt_string(float)',  static fn() => $o->encrypt_string(1.5));
    $probe('encrypt_string(bool)',   static fn() => $o->encrypt_string(true));
    $probe('encrypt_string(null)',   static fn() => $o->encrypt_string(null));
    $probe('decrypt_string(int)',    static fn() => $o->decrypt_string(123));
    $probe('decrypt_string(null)',   static fn() => $o->decrypt_string(null));

    $f = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    $probe('SetPassword(int)',       static fn() => $f->SetPassword(1234));
    $probe('SetMethod(string)',      static fn() => $f->SetMethod('aes-256-cbc'));

    // Aller-retour complet depuis un contexte strict.
    $probe('aller-retour scalaire', static function () use ($o): void {
        if ($o->decrypt_string($o->encrypt_string(123)) !== '123') {
            throw new RuntimeException('aller-retour scalaire rompu');
        }
    });

    return $res;
}

/**
 * Affecte la propriete statique depuis ce fichier, qui est en strict_types=1.
 */
function strict_caller_set_blocks($value): void
{
    EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS = $value;
}

/**
 * Membres DEJA types `string` avant cette version (constructeurs et setters de
 * EncryptOpenSSL, constructeur de EncryptFileOpenSSL). Ils levent TypeError
 * depuis un appelant strict : ce n'est pas une regression introduite ici, mais
 * le contrat doit etre verifie et documente plutot qu'affirme a l'aveugle.
 *
 * @return array<string,string> libelle => classe d'exception, ou 'OK'
 */
function strict_caller_typed_members_probe(string $keyAes, string $keyHash): array
{
    $res = [];
    $probe = static function (string $label, callable $fn) use (&$res): void {
        try {
            $fn();
            $res[$label] = 'OK';
        } catch (Throwable $e) {
            $res[$label] = get_class($e);
        }
    };

    $probe('EncryptOpenSSL::__construct(method=int)',
        static fn() => new EncryptOpenSSL($keyAes, $keyHash, 123));
    $probe('EncryptFileOpenSSL::__construct(password=int)',
        static fn() => new EncryptFileOpenSSL(1234, 'aes-256-cbc'));

    $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');
    $probe('EncryptOpenSSL::SetMethod(int)',      static fn() => $o->SetMethod(123));
    $probe('EncryptOpenSSL::SetKey_aes256(int)',  static fn() => $o->SetKey_aes256(123));
    $probe('EncryptOpenSSL::SetKey_hash512(int)', static fn() => $o->SetKey_hash512(123));

    return $res;
}
