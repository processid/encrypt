<?php

// Recette de la v1.3.1 de processid/encrypt (branche fix_php83_functions_deprecated).
//
// A copier DANS un projet consommateur, avec recette_projet.php et
// recette_strict.php cote a cote. Aucune dependance.
//
//   php recette_projet.php --capture     # AVANT la mise a jour
//   composer require processid/encrypt:^1.3.1
//   php recette_projet.php               # APRES : code de sortie 0 attendu
//
// La phase --capture produit recette_vecteurs.json avec la version ACTUELLEMENT
// installee : des blobs chiffres, leur clair attendu, et la liste des
// deprecations emises sur les chemins `null`. La phase de verification rejoue
// tout avec la version installee apres mise a jour.
//
// Options :
//   --capture              phase 1 : produit le fichier de vecteurs
//   --force                autorise l'ecrasement d'un fichier de vecteurs existant
//   --vecteurs=<fichier>   defaut : ./recette_vecteurs.json
//   --autoload=<fichier>   vendor/autoload.php a utiliser
//   --lib=<repertoire>     repertoire contenant EncryptOpenSSL.php et
//                          EncryptFileOpenSSL.php (copies vendorisees sans Composer)
//   --complet              ajoute les cas multi-chunk (fichier de vecteurs ~460 Ko)
//   --sans-audit           n'analyse pas les sources du projet
//   --help
//
// ATTENTION : recette_vecteurs.json contient des cles de chiffrement generees
// pour la recette. Ne pas le committer, le supprimer apres la recette.

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------
$opt = [
    'capture'     => false,
    'force'       => false,
    'complet'     => false,
    'sans-audit'  => false,
    'vecteurs'    => getcwd() . '/recette_vecteurs.json',
    'autoload'    => '',
    'lib'         => '',
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $src = file(__FILE__);
        foreach (array_slice($src, 2, 27) as $l) {
            echo preg_replace('{^// ?}', '', rtrim($l)) . "\n";
        }
        exit(0);
    }
    if (preg_match('{^--([a-z-]+)(?:=(.*))?$}', $arg, $m)) {
        $name = $m[1];
        if (!array_key_exists($name, $opt)) {
            fwrite(STDERR, "Option inconnue : $arg (voir --help)\n");
            exit(2);
        }
        $opt[$name] = isset($m[2]) ? $m[2] : true;
        continue;
    }
    fwrite(STDERR, "Argument inattendu : $arg (voir --help)\n");
    exit(2);
}

// ---------------------------------------------------------------------------
// Collecte des diagnostics (deprecations, warnings, notices)
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');

$diagnostics = [];

set_error_handler(function (int $no, string $msg, string $file, int $line) use (&$diagnostics): bool {
    // Respecte @ et error_reporting() : sinon un @unlink() legitime ferait
    // echouer la recette.
    if (!(error_reporting() & $no)) {
        return false;
    }
    $diagnostics[] = niveau_nom($no) . ': ' . $msg . ' (' . basename($file) . ':' . $line . ')';
    return true;
});

function niveau_nom(int $no): string
{
    static $noms = [
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'Deprecated',
        E_WARNING           => 'Warning',
        E_USER_WARNING      => 'Warning',
        E_NOTICE            => 'Notice',
        E_USER_NOTICE       => 'Notice',
        E_RECOVERABLE_ERROR => 'Recoverable',
    ];
    return $noms[$no] ?? ('E_' . $no);
}

// ---------------------------------------------------------------------------
// Chargement de la librairie
// ---------------------------------------------------------------------------
function charge_librairie(array $opt): string
{
    if ($opt['lib'] !== '') {
        $dir = rtrim($opt['lib'], '/');
        foreach (['EncryptOpenSSL.php', 'EncryptFileOpenSSL.php'] as $f) {
            if (!is_file("$dir/$f")) {
                echec_fatal("--lib=$dir : $f introuvable");
            }
            require_once "$dir/$f";
        }
        return "require direct depuis $dir";
    }

    if ($opt['autoload'] !== '') {
        if (!is_file($opt['autoload'])) {
            echec_fatal("--autoload={$opt['autoload']} : fichier introuvable");
        }
        require_once $opt['autoload'];
        return "autoload : {$opt['autoload']}";
    }

    // Recherche vendor/autoload.php en remontant depuis le repertoire courant.
    $dir = getcwd();
    for ($i = 0; $i < 6; $i++) {
        if (is_file("$dir/vendor/autoload.php")) {
            require_once "$dir/vendor/autoload.php";
            return "autoload : $dir/vendor/autoload.php";
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    echec_fatal(
        "Aucun vendor/autoload.php trouve en remontant depuis " . getcwd() . ".\n"
        . "  Projet Composer      : lancer la recette depuis la racine du projet,\n"
        . "                         ou passer --autoload=/chemin/vendor/autoload.php\n"
        . "  Copie vendorisee     : passer --lib=/chemin/vers/le/repertoire/des/classes"
    );
}

function echec_fatal(string $msg): void
{
    fwrite(STDERR, "\nRECETTE IMPOSSIBLE : $msg\n");
    exit(2);
}

$origine = charge_librairie($opt);

foreach (['processid\encrypt\EncryptOpenSSL', 'processid\encrypt\EncryptFileOpenSSL'] as $cls) {
    if (!class_exists($cls)) {
        echec_fatal(
            "classe $cls introuvable apres chargement ($origine).\n"
            . "  Un `composer require` resout correctement le namespace, y compris avec le\n"
            . "  mapping PSR-4 corrige en 1.3.1 (\"\" au lieu de \"/\") : ce mapping n'est\n"
            . "  litteralement casse que si la librairie est le package RACINE.\n"
            . "  Pistes : autoload non regenere (composer dump-autoload), copie vendorisee\n"
            . "  chargee par require, ou classe renommee.\n"
            . "  Contournement : --lib=vendor/processid/encrypt"
        );
    }
}

require_once __DIR__ . '/recette_strict.php';

use processid\encrypt\EncryptOpenSSL;
use processid\encrypt\EncryptFileOpenSSL;

function version_lib(): string
{
    if (class_exists('\Composer\InstalledVersions')) {
        try {
            $v = \Composer\InstalledVersions::getPrettyVersion('processid/encrypt');
            if ($v !== null) {
                $ref = \Composer\InstalledVersions::getReference('processid/encrypt');
                return $v . ($ref !== null ? ' (' . substr($ref, 0, 8) . ')' : '');
            }
        } catch (Throwable $e) {
            // paquet absent du lock : copie vendorisee ou chargement direct
        }
    }
    // Repli : la 1.3.1 est la premiere version a normaliser null dans encrypt_file().
    $f = (new ReflectionClass(EncryptFileOpenSSL::class))->getFileName();
    $src = $f !== false ? (string) file_get_contents($f) : '';
    if (strpos($src, 'hash_equals') !== false && strpos($src, 'OPENSSL_RAW_DATA') === false) {
        return 'inconnue (copie pre-1.3.0)';
    }
    return 'inconnue (chargement direct)';
}

// ---------------------------------------------------------------------------
// Harnais
// ---------------------------------------------------------------------------
$reussies = 0;
$echecs = [];
$avertissements = [];
$groupe = '';
$tmp = [];

function groupe(string $nom): void
{
    global $groupe;
    $groupe = $nom;
    echo "\n== $nom ==\n";
}

/**
 * $fn retourne true si l'etape passe, ou une chaine decrivant l'echec.
 * Toute deprecation / warning emis pendant l'etape la fait echouer.
 */
function etape(string $label, callable $fn): void
{
    global $reussies, $echecs, $avertissements, $groupe, $diagnostics;

    $avant = count($diagnostics);
    try {
        $res = $fn();
    } catch (Throwable $e) {
        $res = 'exception ' . get_class($e) . ' : ' . $e->getMessage();
    }
    $nouveaux = array_slice($diagnostics, $avant);

    if ($nouveaux !== []) {
        $echecs[] = "$groupe / $label -- diagnostic emis : " . implode(' | ', $nouveaux);
        echo "  ECHEC $label\n";
        foreach ($nouveaux as $d) {
            echo "        $d\n";
        }
        return;
    }
    if ($res === true) {
        $reussies++;
        echo "  ok    $label\n";
        return;
    }
    if (is_string($res) && strncmp($res, 'AVERTISSEMENT', 13) === 0) {
        $detail = ltrim(substr($res, 13), " :");
        $avertissements[] = "$groupe / $label -- " . $detail;
        echo "  avert $label -- " . $detail . "\n";
        return;
    }
    $echecs[] = "$groupe / $label -- " . (is_string($res) ? $res : 'echec');
    echo "  ECHEC $label -- " . (is_string($res) ? $res : 'echec') . "\n";
}

function tmp_fichier(string $prefixe): string
{
    global $tmp;
    $p = tempnam(sys_get_temp_dir(), 'recette_' . $prefixe . '_');
    if ($p === false) {
        echec_fatal('impossible de creer un fichier temporaire dans ' . sys_get_temp_dir());
    }
    $tmp[] = $p;
    return $p;
}

// ---------------------------------------------------------------------------
// Sondes des chemins `null` : jouees a l'identique avant et apres
// ---------------------------------------------------------------------------
/**
 * @return array<string,array{resultat:string,diagnostics:list<string>}>
 */
function sondes_null(string $keyAes, string $keyHash): array
{
    global $diagnostics;

    $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');
    $f = new EncryptFileOpenSSL('mot de passe recette', 'aes-256-cbc');
    $out = tmp_fichier('null');
    $in  = tmp_fichier('nullin');
    file_put_contents($in, 'contenu');

    $cas = [
        'encrypt_string(null)'        => static fn() => $o->encrypt_string(null),
        'decrypt_string(null)'        => static fn() => $o->decrypt_string(null),
        'encrypt_file(null, $out)'    => static fn() => $f->encrypt_file(null, $out),
        'encrypt_file($in, null)'     => static fn() => $f->encrypt_file($in, null),
        'decrypt_file(null, $out)'    => static fn() => $f->decrypt_file(null, $out),
        'decrypt_file($in, null)'     => static fn() => $f->decrypt_file($in, null),
        'encrypt_file(null, null)'    => static fn() => $f->encrypt_file(null, null),
    ];

    $res = [];
    foreach ($cas as $label => $fn) {
        $avant = count($diagnostics);
        try {
            $fn();
            $resultat = 'aucune exception';
        } catch (Throwable $e) {
            $resultat = get_class($e);
        }
        $res[$label] = [
            'resultat'    => $resultat,
            'diagnostics' => array_values(array_slice($diagnostics, $avant)),
        ];
    }
    return $res;
}

// ---------------------------------------------------------------------------
// Jeux de donnees
// ---------------------------------------------------------------------------
function clairs_chaines(): array
{
    return [
        'vide'      => '',
        'ascii'     => 'Bonjour le monde',
        'accents'   => "Éàçüñ — devis n°4212, 1 234,56 €",
        'zero'      => '0',
        'json'      => '{"id":42,"libelle":"café"}',
        'binaire'   => "\x00\x01\xff\xfe binaire \x00",
        'long'      => str_repeat('A quoi bon chiffrer sans recette. ', 200),
    ];
}

function clairs_fichiers(bool $complet): array
{
    $cas = [
        'petit texte'         => "Bonjour le monde\n",
        'un bloc exact (16o)' => str_repeat('B', 16),
        'vide'                => '',
    ];
    if ($complet) {
        $chunk = 16 * EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS;
        $cas['frontiere de chunk'] = str_repeat('C', $chunk);
        $cas['chunk + 1234']       = str_repeat('D', $chunk + 1234);
    }
    return $cas;
}

$methodes = ['aes-256-cbc', 'aes-128-cbc'];

echo "Recette processid/encrypt v1.3.1 -- branche fix_php83_functions_deprecated\n";
echo str_repeat('-', 72) . "\n";
echo "PHP           : " . PHP_VERSION . " (" . PHP_OS_FAMILY . ")\n";
echo "Projet        : " . getcwd() . "\n";
echo "Chargement    : $origine\n";
echo "Version lib   : " . version_lib() . "\n";

try {

// ===========================================================================
if ($opt['capture']) {
// ===========================================================================
    echo "Phase         : CAPTURE (avant mise a jour)\n";

    if (is_file($opt['vecteurs']) && $opt['force'] !== true) {
        echec_fatal(
            "{$opt['vecteurs']} existe deja.\n"
            . "  Ce fichier est la preuve de l'etat AVANT mise a jour : l'ecraser apres\n"
            . "  la mise a jour rendrait la recette sans valeur.\n"
            . "  Pour repartir de zero volontairement : --force"
        );
    }

    $keyAes  = EncryptOpenSSL::generate_key_aes256();
    $keyHash = EncryptOpenSSL::generate_key_hash512();
    $password = 'recette-' . bin2hex(random_bytes(8));

    $vecteurs = [
        'recette' => 'processid/encrypt v1.3.1',
        'format'  => 1,
        'capture' => [
            'date'        => date('c'),
            'php'         => PHP_VERSION,
            'version_lib' => version_lib(),
            'projet'      => getcwd(),
            'chargement'  => $origine,
            'complet'     => $opt['complet'] === true,
        ],
        'cles' => [
            'aes256'   => $keyAes,
            'hash512'  => $keyHash,
            'password' => $password,
        ],
        'chaines'  => [],
        'fichiers' => [],
    ];

    groupe('Capture des vecteurs chaines');
    foreach ($methodes as $methode) {
        $o = new EncryptOpenSSL($keyAes, $keyHash, $methode);
        foreach (clairs_chaines() as $nom => $clair) {
            etape("$methode / $nom", function () use ($o, $methode, $nom, $clair, &$vecteurs) {
                $blob = $o->encrypt_string($clair);
                if ($blob === '' || $o->decrypt_string($blob) !== $clair) {
                    return 'aller-retour rompu des la capture : version de depart deja cassee';
                }
                $vecteurs['chaines'][] = [
                    'nom'       => $nom,
                    'methode'   => $methode,
                    'clair_b64' => base64_encode($clair),
                    'blob'      => $blob,
                ];
                return true;
            });
        }
    }

    groupe('Capture des vecteurs fichiers');
    foreach (clairs_fichiers($opt['complet'] === true) as $nom => $contenu) {
        etape("aes-256-cbc / $nom", function () use ($nom, $contenu, $password, &$vecteurs) {
            $in  = tmp_fichier('in');
            $enc = tmp_fichier('enc');
            file_put_contents($in, $contenu);

            $f = new EncryptFileOpenSSL($password, 'aes-256-cbc');
            if ($f->encrypt_file($in, $enc) !== true) {
                return 'encrypt_file a retourne false des la capture';
            }
            $chiffre = file_get_contents($enc);
            if ($chiffre === false) {
                return 'fichier chiffre illisible';
            }
            $vecteurs['fichiers'][] = [
                'nom'         => $nom,
                'methode'     => 'aes-256-cbc',
                'sha1_clair'  => sha1($contenu),
                'taille'      => strlen($contenu),
                'chiffre_b64' => base64_encode($chiffre),
                'blocs'       => EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS,
            ];
            return true;
        });
    }

    groupe('Etat des chemins null AVANT mise a jour');
    $vecteurs['sondes_null'] = sondes_null($keyAes, $keyHash);
    $nbDeprecations = 0;
    foreach ($vecteurs['sondes_null'] as $label => $sonde) {
        $n = count($sonde['diagnostics']);
        $nbDeprecations += $n;
        echo "  " . ($n > 0 ? 'DEPREC' : 'propre') . " $label => {$sonde['resultat']}\n";
        foreach ($sonde['diagnostics'] as $d) {
            echo "         $d\n";
        }
    }
    echo "\n  $nbDeprecations diagnostic(s) releve(s) avant mise a jour.\n";
    if ($nbDeprecations === 0) {
        echo "  (0 = la version installee est deja corrigee, ou PHP < 8.1 :\n";
        echo "   la recette verifiera alors surtout la non-regression fonctionnelle.)\n";
    }

    if ($echecs === []) {
        $json = json_encode($vecteurs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($opt['vecteurs'], $json) === false) {
            echec_fatal("ecriture de {$opt['vecteurs']} impossible");
        }
        echo "\nVecteurs ecrits : {$opt['vecteurs']} ("
            . number_format((float) filesize($opt['vecteurs']) / 1024, 1, ',', ' ') . " Ko)\n";
        echo "Ce fichier contient des cles de chiffrement : ne pas le committer.\n";
    }

// ===========================================================================
} else {
// ===========================================================================
    echo "Phase         : VERIFICATION (apres mise a jour)\n";

    if (!is_file($opt['vecteurs'])) {
        echec_fatal(
            "{$opt['vecteurs']} introuvable.\n"
            . "  La phase de capture doit etre jouee AVANT la mise a jour :\n"
            . "      php recette_projet.php --capture\n"
            . "  Si la mise a jour est deja faite, revenir a la version precedente le\n"
            . "  temps de la capture (composer require processid/encrypt:<ancienne>)."
        );
    }
    $vecteurs = json_decode((string) file_get_contents($opt['vecteurs']), true);
    if (!is_array($vecteurs) || ($vecteurs['format'] ?? null) !== 1) {
        echec_fatal("{$opt['vecteurs']} illisible ou format inattendu");
    }

    echo "Vecteurs      : {$opt['vecteurs']}\n";
    echo "  capture du  : {$vecteurs['capture']['date']}\n";
    echo "  version lib : {$vecteurs['capture']['version_lib']} -> " . version_lib() . "\n";
    echo "  PHP         : {$vecteurs['capture']['php']} -> " . PHP_VERSION . "\n";

    $keyAes   = $vecteurs['cles']['aes256'];
    $keyHash  = $vecteurs['cles']['hash512'];
    $password = $vecteurs['cles']['password'];

    // -----------------------------------------------------------------------
    groupe('1. Interoperabilite des chaines (blobs produits avant la mise a jour)');
    // -----------------------------------------------------------------------
    foreach ($vecteurs['chaines'] as $v) {
        etape("{$v['methode']} / {$v['nom']}", function () use ($v, $keyAes, $keyHash) {
            $o = new EncryptOpenSSL($keyAes, $keyHash, $v['methode']);
            $attendu = base64_decode($v['clair_b64'], true);
            $obtenu = $o->decrypt_string($v['blob']);
            if ($obtenu === $attendu) {
                return true;
            }
            if ($attendu === '' && $obtenu === '') {
                return true;
            }
            return 'clair non restitue : attendu ' . strlen((string) $attendu)
                . ' octets, obtenu ' . var_export($obtenu, true);
        });
    }

    // -----------------------------------------------------------------------
    groupe('2. Interoperabilite des fichiers (chiffres avant la mise a jour)');
    // -----------------------------------------------------------------------
    foreach ($vecteurs['fichiers'] as $v) {
        etape("{$v['methode']} / {$v['nom']} ({$v['taille']} o)", function () use ($v, $password) {
            if ((int) $v['blocs'] !== EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS) {
                return '$FILE_ENCRYPTION_BLOCKS a change depuis la capture ('
                    . $v['blocs'] . ' -> ' . EncryptFileOpenSSL::$FILE_ENCRYPTION_BLOCKS
                    . ') : les fichiers chiffres avec l\'ancienne valeur sont illisibles';
            }
            $enc = tmp_fichier('enc');
            $out = tmp_fichier('out');
            file_put_contents($enc, base64_decode($v['chiffre_b64'], true));

            $f = new EncryptFileOpenSSL($password, $v['methode']);
            if ($f->decrypt_file($enc, $out) !== true) {
                return 'decrypt_file a retourne false';
            }
            $sha = sha1_file($out);
            return $sha === $v['sha1_clair']
                ? true
                : "contenu different : sha1 attendu {$v['sha1_clair']}, obtenu $sha";
        });
    }

    // -----------------------------------------------------------------------
    groupe('3. Chemins null : aucune deprecation ne doit subsister');
    // -----------------------------------------------------------------------
    $apres = sondes_null($keyAes, $keyHash);
    foreach ($apres as $label => $sonde) {
        $avantSonde = $vecteurs['sondes_null'][$label] ?? null;
        etape($label, function () use ($sonde, $avantSonde) {
            if ($sonde['diagnostics'] !== []) {
                return 'diagnostic toujours emis : ' . implode(' | ', $sonde['diagnostics']);
            }
            if ($avantSonde !== null && $avantSonde['resultat'] !== $sonde['resultat']) {
                return "classe de resultat modifiee : {$avantSonde['resultat']} -> {$sonde['resultat']}";
            }
            return true;
        });
        if ($avantSonde !== null && $avantSonde['diagnostics'] !== []) {
            echo "        (avant : " . implode(' | ', $avantSonde['diagnostics']) . ")\n";
        }
    }

    // -----------------------------------------------------------------------
    groupe('4. Aller-retour avec la version installee');
    // -----------------------------------------------------------------------
    foreach ($methodes as $methode) {
        etape("chaines / $methode", function () use ($methode, $keyAes, $keyHash) {
            $o = new EncryptOpenSSL($keyAes, $keyHash, $methode);
            foreach (clairs_chaines() as $nom => $clair) {
                if ($o->decrypt_string($o->encrypt_string($clair)) !== $clair) {
                    return "aller-retour rompu sur '$nom'";
                }
            }
            return true;
        });
    }
    foreach (clairs_fichiers($opt['complet'] === true) as $nom => $contenu) {
        etape("fichier / $nom", function () use ($contenu, $password) {
            $in  = tmp_fichier('in');
            $enc = tmp_fichier('enc');
            $out = tmp_fichier('out');
            file_put_contents($in, $contenu);
            $f = new EncryptFileOpenSSL($password, 'aes-256-cbc');
            if ($f->encrypt_file($in, $enc) !== true) {
                return 'encrypt_file => false';
            }
            if ($f->decrypt_file($enc, $out) !== true) {
                return 'decrypt_file => false';
            }
            return sha1_file($out) === sha1($contenu) ? true : 'contenu non restitue';
        });
    }
    etape('mauvais mot de passe ne restitue pas le clair', function () use ($password) {
        $in  = tmp_fichier('in');
        $enc = tmp_fichier('enc');
        $out = tmp_fichier('out');
        $contenu = str_repeat("secret\n", 50);
        file_put_contents($in, $contenu);
        (new EncryptFileOpenSSL($password, 'aes-256-cbc'))->encrypt_file($in, $enc);
        (new EncryptFileOpenSSL($password . '-faux', 'aes-256-cbc'))->decrypt_file($enc, $out);
        return file_get_contents($out) !== $contenu
            ? true
            : 'un mot de passe errone a restitue le clair';
    });

    // -----------------------------------------------------------------------
    groupe('5. Contrat d\'un appelant en declare(strict_types=1)');
    // -----------------------------------------------------------------------
    foreach (recette_strict_probe($keyAes, $keyHash) as $label => $resultat) {
        etape($label, static fn() => $resultat === 'OK'
            ? true
            : "doit rester accepte, mais a leve $resultat");
    }

    // -----------------------------------------------------------------------
    groupe('6. Membres deja types : TypeError attendue (contrat inchange)');
    // -----------------------------------------------------------------------
    foreach (recette_strict_typed_probe($keyAes, $keyHash) as $label => $resultat) {
        etape($label, static fn() => $resultat === 'TypeError'
            ? true
            : "TypeError attendue, obtenu : $resultat");
    }

    // -----------------------------------------------------------------------
    groupe('7. Differences documentees (informatif)');
    // -----------------------------------------------------------------------
    etape("decrypt_string('0') => '' (au lieu de false avant la 1.3.0)",
        function () use ($keyAes, $keyHash) {
            $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');
            $r = $o->decrypt_string('0');
            if ($r === '') {
                return true;
            }
            return 'AVERTISSEMENT : ' . var_export($r, true)
                . " au lieu de '' : comportement anterieur a la 1.3.0";
        });
    etape('decrypt_string([]) => \'\' (court-circuit empty())',
        function () use ($keyAes, $keyHash) {
            $o = new EncryptOpenSSL($keyAes, $keyHash, 'aes-256-cbc');
            return $o->decrypt_string([]) === ''
                ? true
                : 'AVERTISSEMENT : comportement modifie sur tableau vide';
        });

    // -----------------------------------------------------------------------
    if ($opt['sans-audit'] !== true) {
        groupe('8. Audit des points d\'appel du projet (informatif)');
        etape('appels en place (en|de)crypt_file($p, $p) : aucun attendu', function () {
            $trouves = audit_appels_en_place(getcwd());
            if ($trouves === []) {
                return true;
            }
            return 'AVERTISSEMENT : ' . count($trouves)
                . " appel(s) avec le meme chemin en entree et en sortie -- DESTRUCTIF :\n"
                . '           ' . implode("\n           ", $trouves);
        });
        etape('appels a decrypt_file() dont le retour est ignore', function () {
            $trouves = audit_retour_ignore(getcwd());
            if ($trouves === []) {
                return true;
            }
            return 'AVERTISSEMENT : ' . count($trouves)
                . " appel(s) a verifier avant la 1.4.0 :\n"
                . '           ' . implode("\n           ", $trouves);
        });
    }
}

} finally {
    foreach ($tmp as $p) {
        if (is_file($p)) {
            @unlink($p);
        }
    }
}

// ---------------------------------------------------------------------------
// Audit statique des sources du projet
// ---------------------------------------------------------------------------
function sources_projet(string $racine): Generator
{
    $exclus = ['vendor', 'node_modules', '.git', 'cache', 'var', 'storage'];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $f) use ($exclus): bool {
                return !$f->isDir() || !in_array($f->getFilename(), $exclus, true);
            }
        )
    );
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            yield $f->getPathname();
        }
    }
}

/** @return list<string> */
function audit_appels_en_place(string $racine): array
{
    $trouves = [];
    foreach (sources_projet($racine) as $chemin) {
        if (basename($chemin) === 'recette_projet.php') {
            continue;
        }
        $lignes = file($chemin, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lignes as $i => $ligne) {
            if (preg_match('{(?:en|de)crypt_file\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_]*)\s*,\s*\1\s*\)}', $ligne)) {
                $trouves[] = chemin_relatif($racine, $chemin) . ':' . ($i + 1) . ' ' . trim($ligne);
            }
        }
    }
    return $trouves;
}

/** @return list<string> */
function audit_retour_ignore(string $racine): array
{
    $trouves = [];
    foreach (sources_projet($racine) as $chemin) {
        if (basename($chemin) === 'recette_projet.php') {
            continue;
        }
        $lignes = file($chemin, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lignes as $i => $ligne) {
            if (strpos($ligne, 'decrypt_file(') === false) {
                continue;
            }
            // Retour manifestement exploite : affectation, condition, return, operateur logique.
            if (preg_match('{(if\s*\(|=\s*[^=]|return\s|&&|\|\||!\s*\$|\?)}', $ligne)) {
                continue;
            }
            $trouves[] = chemin_relatif($racine, $chemin) . ':' . ($i + 1) . ' ' . trim($ligne);
        }
    }
    return $trouves;
}

function chemin_relatif(string $racine, string $chemin): string
{
    return strncmp($chemin, $racine, strlen($racine)) === 0
        ? ltrim(substr($chemin, strlen($racine)), '/\\')
        : $chemin;
}

// ---------------------------------------------------------------------------
// Verdict
// ---------------------------------------------------------------------------
restore_error_handler();

$total = $reussies + count($echecs);
echo "\n" . str_repeat('-', 72) . "\n";
echo "$reussies/$total etapes reussies";
echo $avertissements !== [] ? ', ' . count($avertissements) . " avertissement(s)\n" : "\n";

if ($avertissements !== []) {
    echo "\nAVERTISSEMENTS (n'empechent pas la mise en production) :\n";
    foreach ($avertissements as $a) {
        echo "  - $a\n";
    }
}

if ($echecs !== []) {
    echo "\nECHECS (" . count($echecs) . ") :\n";
    foreach ($echecs as $e) {
        echo "  - $e\n";
    }
    echo "\nRESULTAT : ECHEC -- ne pas deployer, voir DEPLOIEMENT.md / Rollback\n";
    exit(1);
}

echo "\nRESULTAT : SUCCES";
echo $opt['capture'] ? " -- vecteurs captures, la mise a jour peut etre appliquee\n"
                     : " -- la v1.3.1 est validee sur ce projet\n";
exit(0);
