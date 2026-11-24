<?php
// CTA Lambaréné — fonctions communes : configuration, base de données, migrations, session, droits, journal
date_default_timezone_set('Africa/Libreville');
const CTA_VERSION = '15-preview-2026-11-24';
const CTA_SCHEMA = 2;
const CTA_CLES = '/^cta\.(shell|caisse|pharma|rh|ptme|audit)\.v1$/';
const CTA_ROLES = ['Médecin-chef', 'Administrateur', 'Caisse', 'IDEP', 'Pharmacie', 'RH', 'CNAMGS', 'PTME', 'Laboratoire'];
const CTA_POSTES = ['Caisse', 'IDEP', 'Pharmacie', 'RH', 'CNAMGS', 'PTME', 'Laboratoire']; // postes gérés par le médecin-chef
// Documents que chaque rôle peut lire / écrire (le serveur applique ces règles, pas le navigateur)
const CTA_DROITS = [
    'Médecin-chef'  => ['shell', 'caisse', 'pharma', 'rh', 'ptme', 'audit'],
    'Administrateur'=> ['shell', 'audit'],
    'Caisse'        => ['shell', 'caisse'],
    'IDEP'          => ['shell', 'caisse'],
    'Pharmacie'     => ['shell', 'pharma'],
    'RH'            => ['shell', 'rh'],
    'CNAMGS'        => ['shell', 'rh'],
    'PTME'          => ['shell', 'ptme', 'pharma'],
    'Laboratoire'   => ['shell', 'pharma'],
];
const CTA_UTILISATEURS_DEFAUT = [
    [1, 'Médecin-chef', 'Médecin-chef'], [2, 'Responsable caisse', 'Caisse'], [3, 'Responsable pharmacie', 'Pharmacie'],
    [4, 'Agent RH', 'RH'], [5, 'Agent PTME', 'PTME'], [6, 'Administrateur', 'Administrateur'],
    [7, 'Agent CNAMGS', 'CNAMGS'], [8, 'Agent IDEP', 'IDEP'], [9, 'Agent laboratoire', 'Laboratoire'],
];

function cta_config() {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/config.php'; }
    if (getenv('CTA_SQLITE')) { $c['pilote'] = 'sqlite'; $c['sqlite_fichier'] = getenv('CTA_SQLITE'); } // tests automatiques uniquement
    return $c;
}

// ---------- journal d'erreurs du serveur (dossier logs/, fermé au web)
function cta_log($msg) {
    $d = __DIR__ . '/logs';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    @file_put_contents($d . '/cta_erreurs.log', '[' . date('Y-m-d H:i:s') . '] [' . $ip . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function cta_acces_autorise() {
    $ok = cta_config()['reseaux_autorises'] ?? [];
    if (!$ok) return true;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($ok as $p) { if ($p !== '' && strpos($ip, $p) === 0) return true; }
    return false;
}

function cta_en_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function cta_en_local() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return $ip === '127.0.0.1' || $ip === '::1';
}

function cta_entetes_securite() {
    // CTA_APERCU=1 : aperçu de démonstration seulement (autorise l'affichage dans la fenêtre d'aperçu).
    // En service normal, la variable n'est pas définie : aucun site ne peut afficher l'application.
    $apercu = (bool)getenv('CTA_APERCU');
    header('X-Content-Type-Options: nosniff');
    if (!$apercu) header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-src 'self' about: data: blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors " . ($apercu ? "'self' https:" : "'none'"));
    if (cta_en_https()) header('Strict-Transport-Security: max-age=31536000');
}

function cta_db() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = cta_config();
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    if (($c['pilote'] ?? 'mysql') === 'sqlite') {
        @mkdir(dirname($c['sqlite_fichier']), 0775, true);
        $pdo = new PDO('sqlite:' . $c['sqlite_fichier'], null, null, $opt);
        $pdo->exec('PRAGMA busy_timeout = 8000');
    } else {
        $dsn = 'mysql:host=' . $c['hote'] . ';port=' . $c['port'] . ';dbname=' . $c['base'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['utilisateur'], $c['mot_de_passe'], $opt);
    }
    cta_migrer($pdo);
    cta_initialiser($pdo);
    return $pdo;
}

function cta_est_mysql($pdo) { return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'; }

// Transaction qui verrouille en écriture dès le début (aucune lecture ne peut s'intercaler)
function cta_tx($pdo, callable $f) {
    if (cta_est_mysql($pdo)) { $pdo->beginTransaction(); } else { $pdo->exec('BEGIN IMMEDIATE'); }
    try {
        $r = $f();
        if (cta_est_mysql($pdo)) $pdo->commit(); else $pdo->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        try { if (cta_est_mysql($pdo)) { if ($pdo->inTransaction()) $pdo->rollBack(); } else { $pdo->exec('ROLLBACK'); } } catch (Throwable $x) {}
        throw $e;
    }
}

// ---------- schéma versionné : chaque mise à jour ne fait qu'ajouter ce qui manque, sans toucher aux données
function cta_migrer($pdo) {
    $mysql = cta_est_mysql($pdo);
    $eng = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $big = $mysql ? 'LONGTEXT' : 'TEXT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS cta_meta (cle VARCHAR(60) NOT NULL PRIMARY KEY, valeur VARCHAR(255) NOT NULL)$eng");
    $v = 0;
    $r = $pdo->query("SELECT valeur FROM cta_meta WHERE cle = 'schema_version'")->fetch();
    if ($r) $v = (int)$r['valeur'];
    if ($v >= CTA_SCHEMA) return;
    // v1 : documents et historique journalier
    $pdo->exec("CREATE TABLE IF NOT EXISTS cta_kv (cle VARCHAR(80) NOT NULL PRIMARY KEY, valeur $big NOT NULL, version INT NOT NULL DEFAULT 1, maj DATETIME NOT NULL, poste VARCHAR(120) NULL)$eng");
    if ($mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cta_histo (id INT NOT NULL AUTO_INCREMENT, cle VARCHAR(80) NOT NULL, jour DATE NOT NULL, valeur LONGTEXT NOT NULL, version INT NOT NULL, maj DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY uq_histo (cle, jour))$eng");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cta_histo (id INTEGER PRIMARY KEY AUTOINCREMENT, cle VARCHAR(80) NOT NULL, jour DATE NOT NULL, valeur TEXT NOT NULL, version INT NOT NULL, maj DATETIME NOT NULL)");
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_histo ON cta_histo (cle, jour)');
    }
    // v2 : comptes, journal d'audit tenu par le serveur, compteurs
    $pdo->exec("CREATE TABLE IF NOT EXISTS cta_user (id INT NOT NULL PRIMARY KEY, nom VARCHAR(120) NOT NULL, role VARCHAR(40) NOT NULL, actif TINYINT NOT NULL DEFAULT 1, hash VARCHAR(255) NULL, legacy_salt VARCHAR(64) NULL, legacy_hash VARCHAR(100) NULL, must TINYINT NOT NULL DEFAULT 1, fails INT NOT NULL DEFAULT 0, lock_until BIGINT NOT NULL DEFAULT 0, maj DATETIME NOT NULL)$eng");
    if ($mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cta_audit (n INT NOT NULL AUTO_INCREMENT, t VARCHAR(30) NOT NULL, u VARCHAR(120) NOT NULL, r VARCHAR(40) NOT NULL, m VARCHAR(60) NOT NULL, a VARCHAR(200) NOT NULL, d TEXT NOT NULL, ip VARCHAR(60) NULL, h CHAR(64) NOT NULL, PRIMARY KEY (n))$eng");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cta_audit (n INTEGER PRIMARY KEY AUTOINCREMENT, t VARCHAR(30) NOT NULL, u VARCHAR(120) NOT NULL, r VARCHAR(40) NOT NULL, m VARCHAR(60) NOT NULL, a VARCHAR(200) NOT NULL, d TEXT NOT NULL, ip VARCHAR(60) NULL, h CHAR(64) NOT NULL)");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS cta_seq (nom VARCHAR(40) NOT NULL PRIMARY KEY, valeur INT NOT NULL)$eng");
    $pdo->prepare($mysql ? "REPLACE INTO cta_meta (cle, valeur) VALUES ('schema_version', ?)" : "INSERT OR REPLACE INTO cta_meta (cle, valeur) VALUES ('schema_version', ?)")->execute([(string)CTA_SCHEMA]);
    $pdo->prepare($mysql ? "REPLACE INTO cta_meta (cle, valeur) VALUES ('schema_maj', ?)" : "INSERT OR REPLACE INTO cta_meta (cle, valeur) VALUES ('schema_maj', ?)")->execute([date('Y-m-d H:i:s')]);
    cta_log('Schéma de base mis à jour : version ' . $v . ' -> ' . CTA_SCHEMA);
}

function cta_json($a, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// ---------- session
function cta_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $c = cta_config();
    $h = max(1, (int)($c['session_heures'] ?? 24));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)($h * 3600));
    session_name('CTASESSID');
    // CTA_APERCU=1 : l'application est affichée dans une fenêtre d'aperçu (site parent différent).
    // Le cookie doit alors être SameSite=None; Secure, sinon le navigateur refuse de le conserver
    // et la session est perdue juste après la connexion. En service normal : Strict.
    $apercu = (bool)getenv('CTA_APERCU') && cta_en_https();
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => cta_en_https(), 'httponly' => true, 'samesite' => $apercu ? 'None' : 'Strict']);
    $d = __DIR__ . '/logs/sessions';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    if (is_dir($d) && is_writable($d)) session_save_path($d);
    session_start();
    // expiration par inactivité (par défaut 24 h) ; renouvelée à chaque requête
    if (isset($_SESSION['dern']) && time() - $_SESSION['dern'] > $h * 3600) { $_SESSION = []; session_regenerate_id(true); }
    if (!empty($_SESSION['uid'])) $_SESSION['dern'] = time();
}

// Utilisateur de la session (relu en base : un compte désactivé perd l'accès immédiatement)
function cta_utilisateur($db) {
    cta_session();
    if (empty($_SESSION['uid'])) return null;
    $s = $db->prepare('SELECT id, nom, role, actif, must FROM cta_user WHERE id = ?');
    $s->execute([(int)$_SESSION['uid']]);
    $u = $s->fetch();
    if (!$u || !(int)$u['actif']) { $_SESSION = []; return null; }
    return ['id' => (int)$u['id'], 'nom' => $u['nom'], 'role' => $u['role'], 'must' => (int)$u['must']];
}

function cta_peut($user, $doc) {
    return $user && in_array($doc, CTA_DROITS[$user['role']] ?? [], true);
}

function cta_cle_doc($k) { return preg_replace('/^cta\.|\.v1$/', '', $k); }

function cta_prive($user) { return $user && in_array($user['role'], ['Médecin-chef', 'Administrateur'], true); }

// ---------- comptes
function cta_mdp_initial() { return (string)(cta_config()['mot_de_passe_initial'] ?? 'cta2026'); }

function cta_regle_mdp($n) {
    if (strlen($n) < 8 || !preg_match('/[A-Za-z]/', $n) || !preg_match('/\d/', $n)) return 'Au moins 8 caractères, avec des lettres et des chiffres.';
    return '';
}

function cta_hash_legacy($salt, $pw) { // ancien format du navigateur (v13) : 1500 × SHA-256
    $x = $salt . ':' . $pw;
    for ($i = 0; $i < 1500; $i++) $x = hash('sha256', $x . $salt);
    return $x;
}

// Crée les comptes à partir des données existantes (v13) ou de la liste par défaut ; retire les empreintes du document
function cta_semer_utilisateurs($db) {
    $n = (int)$db->query('SELECT COUNT(*) AS n FROM cta_user')->fetch()['n'];
    if ($n > 0) return;
    $now = date('Y-m-d H:i:s');
    $r = $db->query("SELECT valeur FROM cta_kv WHERE cle = 'cta.shell.v1'")->fetch();
    $doc = $r ? json_decode($r['valeur'], true) : null;
    $ins = $db->prepare('INSERT INTO cta_user (id, nom, role, actif, hash, legacy_salt, legacy_hash, must, fails, lock_until, maj) VALUES (?,?,?,?,?,?,?,?,0,0,?)');
    $init = password_hash(cta_mdp_initial(), PASSWORD_DEFAULT);
    $vus = 0;
    if (is_array($doc) && !empty($doc['users']) && is_array($doc['users'])) {
        foreach ($doc['users'] as $u) {
            if (!isset($u['id'], $u['nom'], $u['role']) || !in_array($u['role'], CTA_ROLES, true)) continue;
            $leg = !empty($u['salt']) && !empty($u['hash']);
            $ins->execute([(int)$u['id'], (string)$u['nom'], $u['role'], ($u['actif'] ?? true) === false ? 0 : 1,
                $leg ? null : $init, $leg ? (string)$u['salt'] : null, $leg ? (string)$u['hash'] : null, ($u['must'] ?? !$leg) ? 1 : 0, $now]);
            $vus++;
        }
    }
    if (!$vus) {
        foreach (CTA_UTILISATEURS_DEFAUT as $u) $ins->execute([$u[0], $u[1], $u[2], 1, $init, null, null, 1, $now]);
    }
    // purge des anciennes empreintes dans le document et dans l'historique
    foreach (['cta_kv' => 'cle', 'cta_histo' => 'id'] as $t => $pk) {
        $rows = $db->query("SELECT $pk AS k, cle AS cle, valeur FROM $t WHERE cle = 'cta.shell.v1'")->fetchAll();
        foreach ($rows as $row) {
            $d = json_decode($row['valeur'], true);
            if (!is_array($d)) continue;
            $d = cta_nettoyer_shell($d);
            $db->prepare("UPDATE $t SET valeur = ? WHERE $pk = ?")->execute([json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['k']]);
        }
    }
    cta_ajouter_audit($db, 'Système', 'Système', 'Comptes', 'Comptes créés sur le serveur', $vus ? ($vus . ' comptes repris') : 'comptes par défaut', '');
}

function cta_nettoyer_shell($d) {
    if (!empty($d['users']) && is_array($d['users'])) {
        foreach ($d['users'] as &$u) { foreach (['salt', 'hash', 'fails', 'lock'] as $f) unset($u[$f]); }
        unset($u);
    }
    return $d;
}

function cta_roles_gerables($user) { // rôles que l'utilisateur peut créer / modifier
    if ($user['role'] === 'Administrateur') return CTA_ROLES;
    if ($user['role'] === 'Médecin-chef') return CTA_POSTES;
    return [];
}
function cta_peut_gerer($user, $cible) { return in_array($cible['role'], cta_roles_gerables($user), true); }
function cta_peut_reinit($user, $cible) { return cta_peut_gerer($user, $cible) || ($user['role'] === 'Médecin-chef' && $cible['role'] === 'Administrateur'); }

// Document « shell » tel que le navigateur le reçoit : comptes issus de la table, aucune empreinte
function cta_shell_sortie($db, $valeur) {
    $d = json_decode($valeur, true);
    if (!is_array($d)) $d = [];
    $prof = [];
    foreach (($d['users'] ?? []) as $u) { if (isset($u['id'])) $prof[(int)$u['id']] = $u; }
    $out = [];
    foreach ($db->query('SELECT id, nom, role, actif, must FROM cta_user ORDER BY id')->fetchAll() as $r) {
        $p = $prof[(int)$r['id']] ?? [];
        $u = ['id' => (int)$r['id'], 'nom' => $r['nom'], 'last' => $p['last'] ?? $r['nom'], 'first' => $p['first'] ?? '', 'role' => $r['role'], 'actif' => (bool)(int)$r['actif']];
        if ((int)$r['must']) $u['must'] = true;
        $out[] = $u;
    }
    $d['users'] = $out;
    $d['ver'] = 2;
    if (!isset($d['uid'])) $d['uid'] = count($out) ? max(array_column($out, 'id')) + 1 : 1;
    $d['uid'] = max((int)$d['uid'], count($out) ? max(array_column($out, 'id')) + 1 : 1);
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// Document « shell » reçu du navigateur : seuls les profils (nom, prénom, état) permis par les droits sont retenus
function cta_shell_entree($db, $user, $valeur) {
    $d = json_decode($valeur, true);
    if (!is_array($d)) return $valeur;
    $actuels = [];
    foreach ($db->query('SELECT id, nom, role, actif FROM cta_user')->fetchAll() as $r) $actuels[(int)$r['id']] = $r;
    $prof = [];
    $ex = $db->query("SELECT valeur FROM cta_kv WHERE cle = 'cta.shell.v1'")->fetch();
    $exd = $ex ? json_decode($ex['valeur'], true) : null;
    foreach ((is_array($exd) ? ($exd['users'] ?? []) : []) as $u) { if (isset($u['id'])) $prof[(int)$u['id']] = ['id' => (int)$u['id'], 'last' => (string)($u['last'] ?? ''), 'first' => (string)($u['first'] ?? '')]; }
    if (cta_prive($user)) {
        $now = date('Y-m-d H:i:s');
        foreach (($d['users'] ?? []) as $u) {
            $id = (int)($u['id'] ?? 0);
            if (!isset($actuels[$id])) continue; // un compte ne se crée que par l'API (user_add)
            $c = $actuels[$id];
            if (!cta_peut_gerer($user, $c)) continue;
            $nom = trim((string)($u['nom'] ?? $c['nom']));
            if ($nom !== $c['nom']) {
                $dup = $db->prepare('SELECT 1 FROM cta_user WHERE id <> ? AND LOWER(nom) = LOWER(?)'); $dup->execute([$id, $nom]);
                if ($nom === '' || $dup->fetch()) $nom = $c['nom'];
            }
            $role = (string)($u['role'] ?? $c['role']);
            if (!in_array($role, cta_roles_gerables($user), true)) $role = $c['role'];
            $actif = ($u['actif'] ?? true) === false ? 0 : 1;
            if ($id === $user['id']) { $actif = 1; $role = $c['role']; }
            if (!$actif && in_array($c['role'], ['Administrateur', 'Médecin-chef'], true)) {
                $n = $db->prepare('SELECT COUNT(*) AS n FROM cta_user WHERE role = ? AND actif = 1 AND id <> ?'); $n->execute([$c['role'], $id]);
                if ((int)$n->fetch()['n'] < 1) $actif = 1;
            }
            if ($nom !== $c['nom'] || $role !== $c['role'] || $actif !== (int)$c['actif']) {
                $db->prepare('UPDATE cta_user SET nom = ?, role = ?, actif = ?, maj = ? WHERE id = ?')->execute([$nom, $role, $actif, $now, $id]);
            }
            $prof[$id] = ['id' => $id, 'last' => (string)($u['last'] ?? $nom), 'first' => (string)($u['first'] ?? '')];
        }
    }
    $d['users'] = array_values($prof);
    unset($d['uid']);
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// ---------- journal d'audit : ajout seul, chaînage SHA-256 compatible avec la vérification du navigateur
function cta_audit_hash($prev, $n, $t, $u, $r, $m, $a, $d) { return hash('sha256', $prev . '|' . $n . '|' . $t . '|' . $u . '|' . $r . '|' . $m . '|' . $a . '|' . $d); }

function cta_ajouter_audit($db, $u, $r, $m, $a, $d, $ip) {
    return cta_tx($db, function () use ($db, $u, $r, $m, $a, $d, $ip) {
        $last = $db->query('SELECT n, h FROM cta_audit ORDER BY n DESC LIMIT 1')->fetch();
        $n = $last ? (int)$last['n'] + 1 : 1;
        $prev = $last ? $last['h'] : 'GENESIS';
        $t = gmdate('Y-m-d\TH:i:s') . '.' . sprintf('%03d', (int)(microtime(true) * 1000) % 1000) . 'Z';
        $m = mb_substr($m, 0, 60); $a = mb_substr($a, 0, 200); $d = mb_substr((string)$d, 0, 2000);
        $h = cta_audit_hash($prev, $n, $t, $u, $r, $m, $a, $d);
        $db->prepare('INSERT INTO cta_audit (n, t, u, r, m, a, d, ip, h) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$n, $t, $u, $r, $m, $a, $d, $ip, $h]);
        return $n;
    });
}

function cta_audit_liste($db) {
    $out = [];
    foreach ($db->query('SELECT n, t, u, r, m, a, d, h FROM cta_audit ORDER BY n')->fetchAll() as $e) { $e['n'] = (int)$e['n']; $out[] = $e; }
    return $out;
}

function cta_audit_version($db) { return (int)($db->query('SELECT MAX(n) AS n FROM cta_audit')->fetch()['n'] ?? 0); }

// Reprise de l'ancien journal (v13, stocké comme document) dans la table, en conservant le chaînage
function cta_reprise_audit($db) {
    if ((int)$db->query('SELECT COUNT(*) AS n FROM cta_audit')->fetch()['n'] > 0) return;
    $r = $db->query("SELECT valeur FROM cta_kv WHERE cle = 'cta.audit.v1'")->fetch();
    if (!$r) return;
    $L = json_decode($r['valeur'], true);
    if (!is_array($L) || !$L) return;
    $ins = $db->prepare('INSERT INTO cta_audit (n, t, u, r, m, a, d, ip, h) VALUES (?,?,?,?,?,?,?,?,?)');
    cta_tx($db, function () use ($L, $ins) {
        foreach ($L as $e) { if (!isset($e['n'], $e['t'], $e['h'])) continue; $ins->execute([(int)$e['n'], $e['t'], (string)($e['u'] ?? ''), (string)($e['r'] ?? ''), (string)($e['m'] ?? ''), (string)($e['a'] ?? ''), (string)($e['d'] ?? ''), null, $e['h']]); }
    });
    $db->exec("DELETE FROM cta_kv WHERE cle = 'cta.audit.v1'");
}

// ---------- compteur de reçus attribué par le serveur
function cta_suivant($db, $nom) {
    return cta_tx($db, function () use ($db, $nom) {
        $s = $db->prepare('SELECT valeur FROM cta_seq WHERE nom = ?'); $s->execute([$nom]);
        $x = $s->fetch();
        if (!$x) { // première utilisation : on repart du plus grand numéro déjà émis
            $v = 0;
            $r = $db->query("SELECT valeur FROM cta_kv WHERE cle = 'cta.caisse.v1'")->fetch();
            if ($r) {
                $d = json_decode($r['valeur'], true);
                if (is_array($d)) {
                    $v = (int)($d['rseq'] ?? 0);
                    foreach (['prest', 'ext', 'extEx', 'cert'] as $k) foreach (($d[$k] ?? []) as $e) { if (isset($e['recu']) && preg_match('/(\d+)$/', (string)$e['recu'], $mm)) $v = max($v, (int)$mm[1]); }
                }
            }
            $db->prepare('INSERT INTO cta_seq (nom, valeur) VALUES (?, ?)')->execute([$nom, $v + 1]);
            return $v + 1;
        }
        $v = (int)$x['valeur'] + 1;
        $db->prepare('UPDATE cta_seq SET valeur = ? WHERE nom = ?')->execute([$v, $nom]);
        return $v;
    });
}


// ---------- Laboratoire : le serveur ne lui montre et ne lui laisse modifier que les réactifs du document pharmacie
function cta_est_labo($user) { return $user && ($user['role'] ?? '') === 'Laboratoire'; }
function cta_labo_sous_doc($valeur) { // document pharmacie complet -> uniquement les réactifs (meds, lots, mouvements)
    $d = json_decode((string)$valeur, true);
    $s = (is_array($d) && isset($d['state']) && is_array($d['state'])) ? $d['state'] : [];
    $meds = []; $ids = [];
    foreach (($s['meds'] ?? []) as $m) { if (is_array($m) && ($m['cat'] ?? '') === 'reactif') { $meds[] = $m; $ids[$m['id'] ?? 0] = true; } }
    $lots = []; $lids = [];
    foreach (($s['lots'] ?? []) as $l) { if (is_array($l) && isset($ids[$l['med'] ?? 0])) { $lots[] = $l; $lids[$l['id'] ?? 0] = true; } }
    $mov = [];
    foreach (($s['mov'] ?? []) as $x) { if (is_array($x) && isset($ids[$x['med'] ?? 0])) $mov[] = $x; }
    return ['state' => ['meds' => $meds, 'lots' => $lots, 'mov' => $mov, 'pat' => [], 'mid' => (int)($s['mid'] ?? 0), 'lid' => (int)($s['lid'] ?? 0), 'pid' => (int)($s['pid'] ?? 0)]];
}
function cta_labo_json($valeur) { return json_encode(cta_labo_sous_doc($valeur), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
function cta_labo_version($valeur) { // version "virtuelle" : ne change que si les réactifs changent
    $j = cta_labo_sous_doc($valeur); $s = $j['state'];
    return (int)sprintf('%u', crc32(json_encode([$s['meds'], $s['lots'], $s['mov']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) % 2147483000 + 1;
}
function cta_labo_fusion($stocke, $recu) { // $stocke : document pharmacie complet ; $recu : ce qu'envoie le laboratoire. Retourne [json complet, réattribué?]
    $d = json_decode((string)$stocke, true);
    if (!is_array($d) || !isset($d['state']) || !is_array($d['state'])) $d = ['state' => ['meds' => [], 'pat' => [], 'lots' => [], 'mov' => [], 'mid' => 0, 'lid' => 0, 'pid' => 0]];
    $s = $d['state'];
    $in = json_decode((string)$recu, true); $r = (is_array($in) && isset($in['state']) && is_array($in['state'])) ? $in['state'] : [];
    $isR = function ($m) { return is_array($m) && ($m['cat'] ?? '') === 'reactif'; };
    $autresMeds = []; $idsAutresMeds = [];
    foreach (($s['meds'] ?? []) as $m) { if (!$isR($m)) { $autresMeds[] = $m; $idsAutresMeds[$m['id'] ?? 0] = true; } }
    $idsAutresLots = []; $autresLots = [];
    $idsReactifsStockes = [];
    foreach (($s['meds'] ?? []) as $m) { if ($isR($m)) $idsReactifsStockes[$m['id'] ?? 0] = true; }
    foreach (($s['lots'] ?? []) as $l) { if (!isset($idsReactifsStockes[$l['med'] ?? 0])) { $autresLots[] = $l; $idsAutresLots[$l['id'] ?? 0] = true; } }
    $autresMov = []; $idsAutresMov = [];
    foreach (($s['mov'] ?? []) as $x) { if (!isset($idsReactifsStockes[$x['med'] ?? 0])) { $autresMov[] = $x; $idsAutresMov[$x['id'] ?? 0] = true; } }
    $mid = (int)($s['mid'] ?? 0); $lid = (int)($s['lid'] ?? 0);
    $changed = false;
    // réactifs reçus : seulement cat = reactif ; tout le reste est ignoré
    $rm = []; $mapMed = [];
    foreach (($r['meds'] ?? []) as $m) {
        if (!$isR($m)) continue;
        $old = $m['id'] ?? 0;
        if (isset($idsAutresMeds[$old])) { $mid++; $m['id'] = $mid; $mapMed[$old] = $mid; $changed = true; }
        $rm[] = $m;
    }
    $okMeds = []; foreach ($rm as $m) $okMeds[$m['id']] = true;
    $rl = []; $mapLot = [];
    foreach (($r['lots'] ?? []) as $l) {
        if (!is_array($l)) continue;
        if (isset($mapMed[$l['med'] ?? 0])) $l['med'] = $mapMed[$l['med']];
        if (!isset($okMeds[$l['med'] ?? 0])) continue;
        $old = $l['id'] ?? 0;
        if (isset($idsAutresLots[$old])) { $lid++; $l['id'] = $lid; $mapLot[$old] = $lid; $changed = true; }
        $rl[] = $l;
    }
    $rx = [];
    foreach (($r['mov'] ?? []) as $x) {
        if (!is_array($x)) continue;
        if (isset($mapMed[$x['med'] ?? 0])) $x['med'] = $mapMed[$x['med']];
        if (!isset($okMeds[$x['med'] ?? 0])) continue;
        if (isset($mapLot[$x['lot'] ?? 0])) $x['lot'] = $mapLot[$x['lot']];
        if (isset($idsAutresMov[$x['id'] ?? 0])) { $x['id'] = max(array_merge([0], array_keys($idsAutresMov), array_map(function ($y) { return (int)($y['id'] ?? 0); }, $rx))) + 1; $changed = true; }
        $rx[] = $x;
    }
    $max = function ($tab) { $m = 0; foreach ($tab as $e) $m = max($m, (int)($e['id'] ?? 0)); return $m; };
    $s['meds'] = array_merge($autresMeds, $rm);
    $s['lots'] = array_merge($autresLots, $rl);
    $s['mov'] = array_merge($autresMov, $rx);
    $s['mid'] = max($mid, (int)($r['mid'] ?? 0), $max($s['meds']));
    $s['lid'] = max($lid, (int)($r['lid'] ?? 0), $max($s['lots']));
    $d['state'] = $s;
    return [json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $changed];
}

// ---------- documents accessibles à l'utilisateur, tels que le navigateur doit les recevoir
function cta_charger($db, $user) {
    $keys = [];
    foreach ($db->query('SELECT cle, valeur, version FROM cta_kv')->fetchAll() as $x) {
        if (!preg_match(CTA_CLES, $x['cle'])) continue;
        $doc = cta_cle_doc($x['cle']);
        if ($doc === 'audit' || !cta_peut($user, $doc)) continue;
        $v = $x['valeur'];
        if ($doc === 'shell') $v = cta_shell_sortie($db, $v);
        $ver = (int)$x['version'];
        if ($doc === 'pharma' && cta_est_labo($user)) { $ver = cta_labo_version($v); $v = cta_labo_json($v); }
        $keys[$x['cle']] = ['v' => $v, 'ver' => $ver];
    }
    if (cta_peut($user, 'shell') && !isset($keys['cta.shell.v1'])) $keys['cta.shell.v1'] = ['v' => cta_shell_sortie($db, '{}'), 'ver' => 0];
    if (cta_peut($user, 'audit')) $keys['cta.audit.v1'] = ['v' => json_encode(cta_audit_liste($db), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ver' => cta_audit_version($db)];
    return $keys;
}

function cta_initialiser($db) { // à appeler une fois après la connexion à la base
    cta_reprise_audit($db);
    cta_semer_utilisateurs($db);
}
