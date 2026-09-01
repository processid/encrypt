<?php

// Suite de tests autonome pour processid/encrypt.
// Aucune dependance : php tests/run.php
// Code de sortie 0 si tout passe, 1 sinon.
//
// Objectif principal : garantir la compatibilite PHP 8.3 (aucune deprecation
// emise) SANS regression fonctionnelle. Les vecteurs de tests/golden_vectors.php
// ont ete produits par la version anterieure de la librairie : ils doivent
// continuer a se dechiffrer a l'identique.

// -- Toute deprecation / notice / warning fait echouer le test --
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    throw new ErrorException($msg, 0, $no, $file, $line);
});

// -- Chargement de la librairie : via Composer si possible, sinon require direct --
$root = dirname(__DIR__);
$autoloadWorks = false;
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
    $autoloadWorks = class_exists('processid\encrypt\EncryptOpenSSL')
        && class_exists('processid\encrypt\EncryptFileOpenSSL');
}
if (!$autoloadWorks) {
    require_once $root . '/EncryptOpenSSL.php';
    require_once $root . '/EncryptFileOpenSSL.php';
}

use processid\encrypt\EncryptOpenSSL;
use processid\encrypt\EncryptFileOpenSSL;

// ---------------------------------------------------------------------------
// Micro-harnais
// ---------------------------------------------------------------------------
$passed = 0;
$failures = [];
$currentGroup = '';

function group(string $name): void
{
    global $currentGroup;
    $currentGroup = $name;
    echo "\n== $name ==\n";
}

function record(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failures, $currentGroup;
    if ($ok) {
        $passed++;
        echo "  ok   $label\n";
    } else {
        $failures[] = "$currentGroup / $label" . ($detail !== '' ? " -- $detail" : '');
        echo "  FAIL $label" . ($detail !== '' ? " -- $detail" : '') . "\n";
    }
}

function ok(bool $cond, string $label): void
{
    record($cond, $label);
}

function same($expected, $actual, string $label): void
{
    record(
        $expected === $actual,
        $label,
        $expected === $actual ? '' : 'attendu ' . describe($expected) . ', obtenu ' . describe($actual)
    );
}

function describe($v): string
{
    if (is_string($v)) {
        return strlen($v) > 40
            ? 'string(' . strlen($v) . ') sha1=' . sha1($v)
            : 'string(' . strlen($v) . ') ' . var_export($v, true);
    }
    return var_export($v, true);
}

// Execute un bloc de test ; toute exception (y compris ErrorException issue
// d'une deprecation) est convertie en echec plutot que d'arreter la suite.
function test(string $label, callable $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        record(false, $label, get_class($e) . ': ' . $e->getMessage()
            . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }
}

// ---------------------------------------------------------------------------
// Donnees
// ---------------------------------------------------------------------------
$golden = require __DIR__ . '/golden_vectors.php';

// Doit rester identique aux definitions utilisees pour produire les vecteurs.
$plaintexts = [
    'EMPTY'   => '',
    'ASCII'   => 'Hello, World!',
    'UTF8'    => "\u{c9}\u{e0}\u{fc} — accentu\u{e9} \u{20ac} \u{4e2d}\u{6587}",
    'ZERO'    => '0',
    'BINARY'  => "\x00\x01\x02\xff\xfe\x80\x7f\x00padding\x00",
    'BLOCK16' => str_repeat('A', 16),
];

$KEY_AES  = $golden['key_aes256'];
$KEY_HMAC = $golden['key_hash512'];

$tmpFiles = [];
function tmpfile_path(string $prefix): string
{
    global $tmpFiles;
    $p = tempnam(sys_get_temp_dir(), 'enctest_' . $prefix);
    $tmpFiles[] = $p;
    return $p;
}

// ---------------------------------------------------------------------------
try {

// ===========================================================================
group('Chargement / autoload');
// ===========================================================================
ok(class_exists('processid\encrypt\EncryptOpenSSL'), 'classe EncryptOpenSSL disponible');
ok(class_exists('processid\encrypt\EncryptFileOpenSSL'), 'classe EncryptFileOpenSSL disponible');
// vendor/ est gitignore : sur un clone frais il est absent, ce qui n'est pas une
// erreur. On ne verifie le mapping PSR-4 que si l'autoloader est reellement present.
if (is_file($root . '/vendor/autoload.php')) {
    record($autoloadWorks, 'autoload Composer resout le namespace processid\\encrypt',
        $autoloadWorks ? '' : 'mapping PSR-4 casse dans composer.json');
} else {
    echo "  skip vendor/ absent : test d'autoload ignore (lancer composer dump-autoload)\n";
}

// ===========================================================================
group('Regression : vecteurs produits par la version anterieure');
// ===========================================================================
foreach ($golden['strings'] as $v) {
    test("golden {$v['method']} / {$v['name']}", function () use ($v, $plaintexts, $KEY_AES, $KEY_HMAC) {
        if (!isset($plaintexts[$v['name']])) {
            record(false, "golden {$v['method']} / {$v['name']}", 'plaintext de reference manquant');
            return;
        }
        $expected = $plaintexts[$v['name']];
        // Garde-fou : la definition locale doit correspondre a celle du vecteur.
        same($v['plain_sha1'], sha1($expected), "golden {$v['name']} : plaintext de reference intact");

        $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, $v['method']);
        same($expected, $obj->decrypt_string($v['blob']), "golden {$v['method']} / {$v['name']} dechiffre a l'identique");
    });
}

test('golden fichier : dechiffrement d\'un fichier produit par la version anterieure', function () use ($golden) {
    $f = $golden['file'];
    $expected = base64_decode($f['plain_b64']);
    same($f['plain_sha1'], sha1($expected), 'golden fichier : plaintext de reference intact');
    same($f['plain_len'], strlen($expected), 'golden fichier : longueur de reference intacte');

    $encPath = tmpfile_path('gold_in');
    $outPath = tmpfile_path('gold_out');
    file_put_contents($encPath, base64_decode($f['cipher_b64']));

    $obj = new EncryptFileOpenSSL($golden['file_password'], $f['method']);
    ok($obj->decrypt_file($encPath, $outPath), 'golden fichier : decrypt_file retourne true');
    same($expected, file_get_contents($outPath), 'golden fichier : contenu dechiffre a l\'identique');
});

// ===========================================================================
group('Aller-retour chaines');
// ===========================================================================
$roundtrips = $plaintexts + [
    'RANDOM_1K' => random_bytes(1024),
    'LARGE_1MB' => str_repeat('L', 1024 * 1024),
    'NEWLINES'  => "ligne1\r\nligne2\nligne3\r",
];

foreach (['aes-256-cbc', 'aes-128-cbc', 'aes-256-ctr'] as $method) {
    foreach ($roundtrips as $name => $plain) {
        test("aller-retour $method / $name", function () use ($method, $name, $plain, $KEY_AES, $KEY_HMAC) {
            $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, $method);
            same($plain, $obj->decrypt_string($obj->encrypt_string($plain)), "aller-retour $method / $name");
        });
    }
}

test('deux chiffrements de la meme donnee different (IV aleatoire)', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    ok($obj->encrypt_string('meme donnee') !== $obj->encrypt_string('meme donnee'), 'IV aleatoire par appel');
});

test('format de sortie : base64(iv . hmac512 . ciphertext)', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $raw = base64_decode($obj->encrypt_string('abc'), true);
    ok($raw !== false, 'sortie base64 stricte valide');
    // 16 (IV aes-cbc) + 64 (sha512) + 16 (un bloc chiffre)
    same(96, strlen($raw), 'longueur brute attendue pour 3 octets de donnee');
});

// ===========================================================================
group('Entrees nulles et limites (compatibilite PHP 8.3)');
// ===========================================================================
// Ces tests echouent sur le code pre-correctif : encrypt_string(null) declenche
// "Passing null to parameter #1 ($data) of type string is deprecated", que le
// gestionnaire d'erreurs transforme en ErrorException.
test('encrypt_string(null) sans deprecation', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $blob = $obj->encrypt_string(null);
    ok(is_string($blob) && $blob !== '', 'encrypt_string(null) retourne une chaine');
    same('', $obj->decrypt_string($blob), 'encrypt_string(null) equivaut a chiffrer une chaine vide');
});

test('decrypt_string sur entrees vides', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    same('', $obj->decrypt_string(null), 'decrypt_string(null) === \'\'');
    same('', $obj->decrypt_string(''), 'decrypt_string(\'\') === \'\'');
    same('', $obj->decrypt_string('0'), 'decrypt_string(\'0\') === \'\' (comportement empty() historique)');
});

test('encrypt_string accepte les scalaires par coercition', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    same('123', $obj->decrypt_string($obj->encrypt_string(123)), 'entier coerce en chaine');
    same('1.5', $obj->decrypt_string($obj->encrypt_string(1.5)), 'flottant coerce en chaine');
});

// ===========================================================================
group('Detection d\'alteration');
// ===========================================================================
test('HMAC altere rejete', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $raw = base64_decode($obj->encrypt_string('donnee sensible'));
    $raw[20] = chr(ord($raw[20]) ^ 0xff);   // octet dans la zone HMAC (offset 16..79)
    same(false, $obj->decrypt_string(base64_encode($raw)), 'octet HMAC inverse => false');
});

test('ciphertext altere rejete', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $raw = base64_decode($obj->encrypt_string('donnee sensible'));
    $raw[85] = chr(ord($raw[85]) ^ 0xff);   // octet dans la zone ciphertext (offset >= 80)
    same(false, $obj->decrypt_string(base64_encode($raw)), 'octet ciphertext inverse => false');
});

test('donnees tronquees rejetees', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $raw = base64_decode($obj->encrypt_string('donnee sensible'));
    same(false, $obj->decrypt_string(base64_encode(substr($raw, 0, 79))), 'longueur < iv+64 => false');
    same(false, $obj->decrypt_string(base64_encode(substr($raw, 0, 90))), 'ciphertext tronque => false');
});

test('mauvaises cles rejetees', function () use ($KEY_AES, $KEY_HMAC) {
    $obj  = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    $blob = $obj->encrypt_string('donnee sensible');

    $wrongHmac = new EncryptOpenSSL($KEY_AES, EncryptOpenSSL::generate_key_hash512(), 'aes-256-cbc');
    same(false, $wrongHmac->decrypt_string($blob), 'mauvaise cle HMAC => false');

    $wrongAes = new EncryptOpenSSL(EncryptOpenSSL::generate_key_aes256(), $KEY_HMAC, 'aes-256-cbc');
    // La cle AES n'entre pas dans le HMAC : le HMAC passe, le dechiffrement echoue.
    $r = $wrongAes->decrypt_string($blob);
    ok($r !== 'donnee sensible', 'mauvaise cle AES ne restitue pas le clair');
});

test('entree non-base64 rejetee', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    same(false, $obj->decrypt_string('!!!pas du base64!!!'), 'garbage => false');
    same(false, $obj->decrypt_string(str_repeat('A', 200)), 'base64 valide mais non authentifie => false');
});

// ===========================================================================
group('Generation de cles');
// ===========================================================================
test('generate_key_aes256 / generate_key_hash512', function () {
    $a = EncryptOpenSSL::generate_key_aes256();
    $h = EncryptOpenSSL::generate_key_hash512();
    same(32, strlen(base64_decode($a, true)), 'cle AES = 32 octets');
    same(64, strlen(base64_decode($h, true)), 'cle HMAC = 64 octets');
    ok($a !== EncryptOpenSSL::generate_key_aes256(), 'cles AES successives differentes');
    ok($h !== EncryptOpenSSL::generate_key_hash512(), 'cles HMAC successives differentes');
});

// ===========================================================================
group('Setters fluides');
// ===========================================================================
test('setters retournent $this et sont effectifs', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    ok($obj->SetKey_aes256($KEY_AES) === $obj, 'SetKey_aes256 fluide');
    ok($obj->SetKey_hash512($KEY_HMAC) === $obj, 'SetKey_hash512 fluide');
    ok($obj->SetMethod('aes-128-cbc') === $obj, 'SetMethod fluide');
    same('aes-128-cbc-ok', $obj->decrypt_string($obj->encrypt_string('aes-128-cbc-ok')), 'methode changee prise en compte');

    $f = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    ok($f->SetPassword('pw2') === $f, 'SetPassword fluide');
    ok($f->SetMethod('aes-128-cbc') === $f, 'SetMethod (fichier) fluide');
});

// ===========================================================================
group('Aller-retour fichiers');
// ===========================================================================
$blockSize = 16 * EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS;   // 160000
$fileCases = [
    'vide'                => '',
    'petit texte'         => "Bonjour le monde\n",
    'un bloc exact (16o)' => str_repeat('B', 16),
    'frontiere de chunk'  => str_repeat('C', $blockSize),
    'chunk + 1234'        => str_repeat('D', $blockSize + 1234),
    'multi-chunk 1 Mo'    => random_bytes(1024 * 1024),
];

foreach ($fileCases as $name => $content) {
    test("fichier aller-retour : $name", function () use ($name, $content) {
        $in   = tmpfile_path('in');
        $enc  = tmpfile_path('enc');
        $out  = tmpfile_path('out');
        file_put_contents($in, $content);

        $obj = new EncryptFileOpenSSL('mot de passe de test', 'aes-256-cbc');
        ok($obj->encrypt_file($in, $enc), "encrypt_file true : $name");
        ok($obj->decrypt_file($enc, $out), "decrypt_file true : $name");
        same(sha1($content), sha1_file($out), "contenu restitue : $name");
        ok(file_get_contents($enc) !== $content || $content === '', "fichier chiffre != clair : $name");
    });
}

test('fichier : mauvais mot de passe ne restitue pas le clair', function () {
    $in  = tmpfile_path('in');
    $enc = tmpfile_path('enc');
    $out = tmpfile_path('out');
    $content = str_repeat("secret\n", 50);
    file_put_contents($in, $content);

    (new EncryptFileOpenSSL('bon mot de passe', 'aes-256-cbc'))->encrypt_file($in, $enc);
    (new EncryptFileOpenSSL('mauvais mot de passe', 'aes-256-cbc'))->decrypt_file($enc, $out);
    ok(file_get_contents($out) !== $content, 'mauvais mot de passe => contenu different');
});

test('fichier source inexistant => false', function () {
    $out = tmpfile_path('out');
    $obj = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    // fopen() emet un warning sur fichier absent : attendu, on le neutralise ici.
    set_error_handler(fn() => true);
    try {
        $r1 = $obj->encrypt_file('/chemin/absolument/inexistant/xyz', $out);
        $r2 = $obj->decrypt_file('/chemin/absolument/inexistant/xyz', $out);
    } finally {
        // Sans finally, un throw laisserait ce handler avaleur actif pour tout le
        // reste de la suite et masquerait silencieusement chaque deprecation.
        restore_error_handler();
    }
    same(false, $r1, 'encrypt_file sur source absente => false');
    same(false, $r2, 'decrypt_file sur source absente => false');
});

$original = EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS;
test('propriete statique $FILE_ENCRYPTION_BLOCKS pilote le decoupage', function () use ($original) {
    try {
        EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS = 2;   // chunks de 32 octets
        $in  = tmpfile_path('in');
        $enc = tmpfile_path('enc');
        $out = tmpfile_path('out');
        $content = str_repeat('E', 500);                   // force ~16 chunks
        file_put_contents($in, $content);

        $obj = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
        ok($obj->encrypt_file($in, $enc), 'encrypt_file avec petits chunks');
        ok($obj->decrypt_file($enc, $out), 'decrypt_file avec petits chunks');
        same(sha1($content), sha1_file($out), 'contenu restitue avec petits chunks');
    } finally {
        EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS = $original;
    }
    same($original, EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS, 'valeur initiale restauree');
});

// ===========================================================================
group('Compatibilite ascendante des signatures');
// ===========================================================================
// Les parametres sont volontairement NON types. Les typer casserait tout
// appelant en declare(strict_types=1) transmettant un scalaire : le typage
// strict s'applique au site d'appel, pas a la librairie.
require_once __DIR__ . '/strict_caller.php';

test('appelant en declare(strict_types=1) : scalaires toujours acceptes', function () use ($KEY_AES, $KEY_HMAC) {
    foreach (strict_caller_probe($KEY_AES, $KEY_HMAC) as $label => $result) {
        same('OK', $result, "strict_types appelant : $label");
    }
});

test('membres deja types : contrat reel depuis un appelant strict', function () use ($KEY_AES, $KEY_HMAC) {
    // EncryptOpenSSL::__construct/SetMethod/SetKey_* et EncryptFileOpenSSL::__construct
    // sont types `string` depuis la 1.3.0. Un appelant en strict_types=1 qui leur
    // transmet un scalaire obtient une TypeError -- comportement anterieur a cette
    // version, mais qui doit etre connu et non presente comme "aucun typage".
    foreach (strict_caller_typed_members_probe($KEY_AES, $KEY_HMAC) as $label => $result) {
        same('TypeError', $result, "deja type (inchange ici) : $label");
    }
});

test('BC : decrypt_string(array) retourne \'\' (empty()) sans TypeError', function () use ($KEY_AES, $KEY_HMAC) {
    $obj = new EncryptOpenSSL($KEY_AES, $KEY_HMAC, 'aes-256-cbc');
    same('', $obj->decrypt_string([]), 'tableau vide => \'\'');
});

test('BC : encrypt_file(null, ...) leve ValueError comme avant (et non TypeError)', function () {
    $obj = new EncryptFileOpenSSL('pw', 'aes-256-cbc');
    $out = tmpfile_path('bc');
    $class = 'aucune exception';
    try {
        $obj->encrypt_file(null, $out);
    } catch (Throwable $e) {
        $class = get_class($e);
    }
    same('ValueError', $class, 'chemin null => ValueError (classe inchangee)');
});

test('$FILE_ENCRYPTION_BLOCKS reste non typee (assignable depuis un contexte strict)', function () {
    $rp = new ReflectionProperty(EncryptFileOpenSSL::class, 'FILE_ENCRYPTION_BLOCKS');
    ok(!$rp->hasType(), 'propriete non typee : aucune TypeError possible a l\'affectation');

    $original = EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS;
    try {
        // Affectation depuis strict_caller.php, qui est en declare(strict_types=1).
        strict_caller_set_blocks('5000');
        same('5000', EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS,
            'valeur conservee telle quelle depuis un appelant strict');
    } finally {
        EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS = $original;
    }
});

} finally {
    foreach ($tmpFiles as $p) {
        if (is_file($p)) {
            @unlink($p);
        }
    }
}

// ---------------------------------------------------------------------------
restore_error_handler();
$total = $passed + count($failures);
echo "\n" . str_repeat('-', 60) . "\n";
echo "PHP " . PHP_VERSION . " -- $passed/$total assertions reussies\n";
if ($failures) {
    echo "\nECHECS (" . count($failures) . ") :\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    echo "\nRESULTAT : ECHEC\n";
    exit(1);
}
echo "RESULTAT : SUCCES\n";
exit(0);
