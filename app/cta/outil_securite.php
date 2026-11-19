<?php
// CTA — durcissement MySQL : crée un utilisateur dédié « cta_app » (droits limités à la base cta) avec un mot de passe aléatoire,
// l'écrit dans config.php et propose de définir le mot de passe de root.  Lancé par durcir_mysql.bat.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$cfgf = __DIR__ . '/config.php';
$c = require $cfgf;
$rootpw = $argv[1] ?? '';   // mot de passe actuel de root (vide par défaut dans XAMPP)
try {
    $pdo = new PDO('mysql:host=' . $c['hote'] . ';port=' . $c['port'], 'root', $rootpw, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) { fwrite(STDERR, "Connexion root impossible : " . $e->getMessage() . "\n"); exit(1); }
$pw = rtrim(strtr(base64_encode(random_bytes(24)), '+/', 'Ab'), '=');
$base = preg_replace('/[^A-Za-z0-9_]/', '', $c['base']);
$pdo->exec("CREATE USER IF NOT EXISTS 'cta_app'@'localhost' IDENTIFIED BY " . $pdo->quote($pw));
$pdo->exec("ALTER USER 'cta_app'@'localhost' IDENTIFIED BY " . $pdo->quote($pw));
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON `$base`.* TO 'cta_app'@'localhost'");
$pdo->exec('FLUSH PRIVILEGES');
$t = file_get_contents($cfgf);
$t = preg_replace("/('utilisateur'\s*=>\s*)'[^']*'/", "\$1'cta_app'", $t);
$t = preg_replace("/('mot_de_passe'\s*=>\s*)'[^']*'/", "\$1" . var_export($pw, true), $t);
file_put_contents($cfgf, $t);
echo "Utilisateur MySQL « cta_app » créé et config.php mis à jour.\n";
// mot de passe de root
$nouveau = $argv[2] ?? '';
if ($nouveau !== '') {
    $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY " . $pdo->quote($nouveau)); $pdo->exec('FLUSH PRIVILEGES');
    echo "Mot de passe de root défini. phpMyAdmin demandera désormais ce mot de passe (réglez config.inc.php : auth_type = 'cookie').\n";
}
