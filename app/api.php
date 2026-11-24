<?php
// CTA Lambaréné — interface serveur. Toute requête de données exige une session ouverte ;
// le serveur décide des droits (le navigateur n'est jamais cru sur parole).
error_reporting(0);
require __DIR__ . '/common.php';
cta_entetes_securite();

if (!cta_acces_autorise()) cta_json(['ok' => false, 'err' => 'Accès refusé pour cette adresse (reseaux_autorises).'], 403);
$a = $_GET['a'] ?? '';
if (($_SERVER['HTTP_X_CTA'] ?? '') !== '1' && $a !== 'export') cta_json(['ok' => false, 'err' => 'Requête non reconnue.'], 400);

function entree() { $in = json_decode(file_get_contents('php://input'), true); return is_array($in) ? $in : []; }
function exiger($user, $mustOk = false) {
    if (!$user) cta_json(['ok' => false, 'auth' => true, 'err' => 'Session expirée : reconnectez-vous.'], 401);
    if ($user['must'] && !$mustOk) cta_json(['ok' => false, 'auth' => true, 'must' => true, 'err' => 'Changement de mot de passe obligatoire.'], 403);
}

try {
    $db = cta_db();
    $mysql = cta_est_mysql($db);
    $now = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user = cta_utilisateur($db);

    if ($a === 'ping') {
        cta_json(['ok' => true, 'version' => CTA_VERSION, 'schema' => CTA_SCHEMA, 'heure' => $now]);
    }

    if ($a === 'session') {
        cta_json(['ok' => true, 'user' => $user ? ['id' => $user['id'], 'nom' => $user['nom'], 'role' => $user['role'], 'must' => (bool)$user['must']] : null]);
    }

    // ---------- connexion
    if ($a === 'login') {
        $in = entree();
        $id = (int)($in['id'] ?? 0); $pw = (string)($in['pw'] ?? '');
        $s = $db->prepare('SELECT * FROM cta_user WHERE id = ?'); $s->execute([$id]);
        $u = $s->fetch();
        if (!$u || !(int)$u['actif']) { usleep(400000); cta_json(['ok' => false, 'err' => 'Identifiant ou mot de passe incorrect.']); }
        if ((int)$u['lock_until'] > time()) {
            $m = (int)ceil(((int)$u['lock_until'] - time()) / 60);
            cta_json(['ok' => false, 'locked' => true, 'err' => "Compte verrouillé après trop d’échecs. Réessayez dans $m minute(s) ou demandez une réinitialisation au médecin-chef."]);
        }
        $ok = false;
        if ($u['hash']) $ok = password_verify($pw, $u['hash']);
        elseif ($u['legacy_salt'] && $u['legacy_hash']) $ok = hash_equals($u['legacy_hash'], cta_hash_legacy($u['legacy_salt'], $pw));
        if (!$ok) {
            $f = (int)$u['fails'] + 1; $lock = 0; $msg = "Mot de passe incorrect ($f/5).";
            cta_ajouter_audit($db, $u['nom'], $u['role'], 'Session', 'Connexion échouée', 'Tentative ' . $f, $ip);
            if ($f >= 5) { $lock = time() + 300; $f = 0; $msg = 'Trop d’échecs : compte verrouillé 5 minutes.'; cta_ajouter_audit($db, $u['nom'], $u['role'], 'Session', 'Compte verrouillé', '5 échecs : 5 minutes', $ip); }
            $db->prepare('UPDATE cta_user SET fails = ?, lock_until = ? WHERE id = ?')->execute([$f, $lock, $id]);
            usleep(400000);
            cta_json(['ok' => false, 'err' => $msg, 'locked' => $lock > 0]);
        }
        if (!$u['hash'] || password_needs_rehash($u['hash'], PASSWORD_DEFAULT)) {
            $db->prepare('UPDATE cta_user SET hash = ?, legacy_salt = NULL, legacy_hash = NULL WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        }
        $db->prepare('UPDATE cta_user SET fails = 0, lock_until = 0 WHERE id = ?')->execute([$id]);
        cta_session(); session_regenerate_id(true);
        $_SESSION['uid'] = $id; $_SESSION['dern'] = time();
        cta_ajouter_audit($db, $u['nom'], $u['role'], 'Session', 'Connexion', (int)$u['must'] ? 'Changement de mot de passe obligatoire' : $u['nom'], $ip);
        cta_json(['ok' => true, 'must' => (bool)(int)$u['must'], 'user' => ['id' => $id, 'nom' => $u['nom'], 'role' => $u['role']]]);
    }

    if ($a === 'logout') {
        if ($user) cta_ajouter_audit($db, $user['nom'], $user['role'], 'Session', 'Déconnexion', '', $ip);
        cta_session(); $_SESSION = []; session_destroy();
        cta_json(['ok' => true]);
    }

    exiger($user, in_array($a, ['chpw', 'session'], true));

    if ($a === 'chpw') {
        $in = entree(); $o = (string)($in['old'] ?? ''); $n = (string)($in['new'] ?? '');
        $s = $db->prepare('SELECT hash, legacy_salt, legacy_hash FROM cta_user WHERE id = ?'); $s->execute([$user['id']]); $u = $s->fetch();
        $ok = $u['hash'] ? password_verify($o, $u['hash']) : ($u['legacy_salt'] && hash_equals($u['legacy_hash'], cta_hash_legacy($u['legacy_salt'], $o)));
        if (!$ok) cta_json(['ok' => false, 'err' => 'Mot de passe actuel incorrect.']);
        if ($n === $o || $n === cta_mdp_initial()) cta_json(['ok' => false, 'err' => 'Choisissez un mot de passe différent de l’ancien et du mot de passe initial.']);
        if ($m = cta_regle_mdp($n)) cta_json(['ok' => false, 'err' => $m]);
        $db->prepare('UPDATE cta_user SET hash = ?, legacy_salt = NULL, legacy_hash = NULL, must = 0, fails = 0, lock_until = 0, maj = ? WHERE id = ?')->execute([password_hash($n, PASSWORD_DEFAULT), $now, $user['id']]);
        cta_ajouter_audit($db, $user['nom'], $user['role'], 'Session', 'Mot de passe changé', '', $ip);
        cta_json(['ok' => true]);
    }

    // ---------- gestion des comptes
    if ($a === 'user_add') {
        if (!cta_prive($user)) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        $in = entree(); $l = trim((string)($in['last'] ?? '')); $f = trim((string)($in['first'] ?? '')); $role = (string)($in['role'] ?? ''); $pw = (string)($in['pw'] ?? '');
        $nom = trim(preg_replace('/\s+/', ' ', $f . ' ' . $l));
        if ($l === '') cta_json(['ok' => false, 'err' => 'Le nom est obligatoire.']);
        if (!in_array($role, cta_roles_gerables($user), true)) cta_json(['ok' => false, 'err' => 'Ce poste ne peut pas être créé avec votre compte.'], 403);
        $d = $db->prepare('SELECT 1 FROM cta_user WHERE LOWER(nom) = LOWER(?)'); $d->execute([$nom]);
        if ($d->fetch()) cta_json(['ok' => false, 'err' => 'Un utilisateur porte déjà ce nom : ajoutez le prénom ou une précision.']);
        if ($m = cta_regle_mdp($pw)) cta_json(['ok' => false, 'err' => $m]);
        $id = cta_tx($db, function () use ($db, $nom, $role, $pw, $now) {
            $id = (int)$db->query('SELECT COALESCE(MAX(id), 0) + 1 AS n FROM cta_user')->fetch()['n'];
            $db->prepare('INSERT INTO cta_user (id, nom, role, actif, hash, must, fails, lock_until, maj) VALUES (?,?,?,1,?,1,0,0,?)')->execute([$id, $nom, $role, password_hash($pw, PASSWORD_DEFAULT), $now]);
            return $id;
        });
        cta_ajouter_audit($db, $user['nom'], $user['role'], 'Paramètres', 'Utilisateur créé', $role . ' : ' . $nom, $ip);
        cta_json(['ok' => true, 'user' => ['id' => $id, 'nom' => $nom, 'last' => $l, 'first' => $f, 'role' => $role, 'actif' => true, 'must' => true]]);
    }

    if ($a === 'user_reset') {
        $in = entree(); $id = (int)($in['id'] ?? 0); $pw = (string)($in['pw'] ?? '');
        $s = $db->prepare('SELECT id, nom, role FROM cta_user WHERE id = ?'); $s->execute([$id]); $c = $s->fetch();
        if (!$c || !cta_prive($user) || !cta_peut_reinit($user, $c)) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        if ($m = cta_regle_mdp($pw)) cta_json(['ok' => false, 'err' => $m]);
        $db->prepare('UPDATE cta_user SET hash = ?, legacy_salt = NULL, legacy_hash = NULL, must = 1, fails = 0, lock_until = 0, maj = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $now, $id]);
        cta_ajouter_audit($db, $user['nom'], $user['role'], 'Paramètres', 'Mot de passe réinitialisé', $c['nom'], $ip);
        cta_json(['ok' => true]);
    }

    // ---------- journal : le serveur inscrit l'acteur d'après la session
    if ($a === 'audit') {
        $in = entree(); $n = 0;
        foreach (array_slice((array)($in['items'] ?? []), 0, 100) as $e) {
            if (!is_array($e) || !isset($e['m'], $e['a'])) continue;
            cta_ajouter_audit($db, $user['nom'], $user['role'], (string)$e['m'], (string)$e['a'], (string)($e['d'] ?? ''), $ip); $n++;
        }
        cta_json(['ok' => true, 'n' => $n]);
    }

    // ---------- numéros de reçu
    if ($a === 'seq') {
        if (!cta_peut($user, 'caisse')) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        cta_json(['ok' => true, 'n' => cta_suivant($db, 'recu')]);
    }

    // ---------- documents
    if ($a === 'load') {
        cta_json(['ok' => true, 'keys' => cta_charger($db, $user), 'heure' => $now]);
    }

    if ($a === 'versions') {
        $v = [];
        foreach ($db->query('SELECT cle, version FROM cta_kv')->fetchAll() as $x) { if (cta_peut($user, cta_cle_doc($x['cle'])) && cta_cle_doc($x['cle']) !== 'audit') $v[$x['cle']] = (cta_est_labo($user) && cta_cle_doc($x['cle']) === 'pharma') ? cta_labo_version($db->query("SELECT valeur FROM cta_kv WHERE cle = '" . $x['cle'] . "'")->fetchColumn()) : (int)$x['version']; }
        if (cta_peut($user, 'audit')) $v['cta.audit.v1'] = cta_audit_version($db);
        cta_json(['ok' => true, 'versions' => $v]);
    }

    if ($a === 'get') {
        $k = $_GET['k'] ?? '';
        if (!preg_match(CTA_CLES, $k)) cta_json(['ok' => false, 'err' => 'Clé invalide.'], 400);
        $doc = cta_cle_doc($k);
        if (!cta_peut($user, $doc)) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        if ($doc === 'audit') cta_json(['ok' => true, 'v' => json_encode(cta_audit_liste($db), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'ver' => cta_audit_version($db)]);
        $s = $db->prepare('SELECT valeur, version FROM cta_kv WHERE cle = ?'); $s->execute([$k]);
        $x = $s->fetch();
        if (!$x) cta_json(['ok' => true, 'v' => null, 'ver' => 0]);
        if ($doc === 'pharma' && cta_est_labo($user)) cta_json(['ok' => true, 'v' => cta_labo_json($x['valeur']), 'ver' => cta_labo_version($x['valeur'])]);
        cta_json(['ok' => true, 'v' => $doc === 'shell' ? cta_shell_sortie($db, $x['valeur']) : $x['valeur'], 'ver' => (int)$x['version']]);
    }

    if ($a === 'save') {
        $in = entree();
        if (!isset($in['k'], $in['v'])) cta_json(['ok' => false, 'err' => 'Requête incomplète.'], 400);
        $k = (string)$in['k']; $v = (string)$in['v']; $base = (int)($in['base'] ?? 0);
        if (!preg_match(CTA_CLES, $k)) cta_json(['ok' => false, 'err' => 'Clé invalide.'], 400);
        $doc = cta_cle_doc($k);
        if ($doc === 'audit') cta_json(['ok' => true, 'ver' => cta_audit_version($db), 'ignore' => true]); // le journal n'est écrit que par le serveur
        if (!cta_peut($user, $doc)) { cta_ajouter_audit($db, $user['nom'], $user['role'], 'Sécurité', 'Écriture refusée', $k, $ip); cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403); }
        if (strlen($v) > 60 * 1024 * 1024) cta_json(['ok' => false, 'err' => 'Document trop volumineux.'], 413);
        json_decode($v);
        if (json_last_error() !== JSON_ERROR_NONE) cta_json(['ok' => false, 'err' => 'Contenu JSON invalide.'], 400);
        $poste = mb_substr($user['nom'], 0, 120);

        $res = cta_tx($db, function () use ($db, $k, $v, $base, $now, $poste, $doc, $user) {
            $s = $db->prepare('SELECT valeur, version FROM cta_kv WHERE cle = ?' . (cta_est_mysql($db) ? ' FOR UPDATE' : ''));
            $s->execute([$k]);
            $x = $s->fetch();
            $labo = ($doc === 'pharma' && cta_est_labo($user));
            if ($labo) { // le laboratoire compare la version des seuls réactifs
                $cur = $x ? cta_labo_version($x['valeur']) : 0;
                if ($x && $cur !== $base) return ['conflict' => true, 'ver' => $cur, 'v' => cta_labo_json($x['valeur'])];
                [$v, $remap] = cta_labo_fusion($x ? $x['valeur'] : '{}', $v);
            } else {
                if ($x && (int)$x['version'] !== $base) return ['conflict' => true, 'ver' => (int)$x['version'], 'v' => $doc === 'shell' ? cta_shell_sortie($db, $x['valeur']) : $x['valeur']];
            }
            if ($doc === 'shell') $v = cta_shell_entree($db, $user, $v);
            if ($x) {
                $h = $db->prepare('SELECT 1 FROM cta_histo WHERE cle = ? AND jour = ?');
                $h->execute([$k, date('Y-m-d')]);
                if (!$h->fetch()) {
                    $db->prepare('INSERT INTO cta_histo (cle, jour, valeur, version, maj) VALUES (?,?,?,?,?)')->execute([$k, date('Y-m-d'), $x['valeur'], (int)$x['version'], $now]);
                    $lim = date('Y-m-d', strtotime('-' . (int)(cta_config()['conservation_jours'] ?? 60) . ' days'));
                    $db->prepare('DELETE FROM cta_histo WHERE jour < ?')->execute([$lim]);
                }
                $ver = (int)$x['version'] + 1;
                $db->prepare('UPDATE cta_kv SET valeur = ?, version = ?, maj = ?, poste = ? WHERE cle = ?')->execute([$v, $ver, $now, $poste, $k]);
            } else {
                $ver = 1;
                $db->prepare('INSERT INTO cta_kv (cle, valeur, version, maj, poste) VALUES (?,?,?,?,?)')->execute([$k, $v, $ver, $now, $poste]);
            }
            if ($labo) return ['ok' => true, 'ver' => cta_labo_version($v), 'remap' => $remap, 'v' => $remap ? cta_labo_json($v) : null];
            return ['ok' => true, 'ver' => $ver];
        });
        if (!empty($res['conflict'])) cta_json(['ok' => false, 'conflict' => true, 'ver' => $res['ver'], 'v' => $res['v']]);
        cta_json($res);
    }

    if ($a === 'export') { // sauvegarde complète : réservée au médecin-chef et à l'administrateur
        if (!cta_prive($user)) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        cta_ajouter_audit($db, $user['nom'], $user['role'], 'Sauvegarde', 'Export complet téléchargé', '', $ip);
        $out = ['cta_export' => 1, 'date' => $now, 'schema' => CTA_SCHEMA, 'keys' => [], 'audit' => cta_audit_liste($db), 'users' => $db->query('SELECT id, nom, role, actif FROM cta_user')->fetchAll()];
        foreach ($db->query('SELECT cle, valeur FROM cta_kv')->fetchAll() as $x) $out['keys'][$x['cle']] = $x['cle'] === 'cta.shell.v1' ? cta_shell_sortie($db, $x['valeur']) : $x['valeur'];
        header('Content-Disposition: attachment; filename="CTA_export_' . date('Y-m-d_His') . '.json"');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ---------- export Word : le logiciel remplit le modèle .docx de cta/modeles/
    if ($a === 'docx') {
        $in = entree();
        $modele = (string)($in['modele'] ?? '');
        // facture / récapitulatif / dette : module CNAMGS-RH ; registre et stock : pharmacie
        $docPharma = in_array($modele, ['registre_pharma', 'stock_pharma', 'fiche_mensuelle', 'inventaire_pharma', 'commande_pharma'], true);
        $docCaisse = in_array($modele, ['registre_caisse_pvvih', 'registre_caisse_ext', 'registre_caisse_cert', 'journal_depenses', 'synthese_caisse'], true);
        // suivi PTME : module PTME (le médecin-chef et l'administrateur y ont aussi accès)
        $docPtme = in_array($modele, ['suivi_ptme'], true);
        if (!cta_peut($user, 'rh') && !($docPharma && cta_peut($user, 'pharma')) && !($docCaisse && cta_peut($user, 'caisse')) && !($docPtme && cta_peut($user, 'ptme'))) cta_json(['ok' => false, 'err' => 'Droits insuffisants.'], 403);
        $champs = [];
        foreach (array_slice((array)($in['champs'] ?? []), 0, 200, true) as $k => $v) {
            if (is_scalar($v)) $champs[(string)$k] = (string)$v;
        }
        $boucles = [];
        foreach (array_slice((array)($in['boucles'] ?? []), 0, 10, true) as $k => $lignes) {
            if (!is_array($lignes)) continue;
            $l = [];
            foreach (array_slice($lignes, 0, 500) as $ligne) {
                if (!is_array($ligne)) continue;
                $o = [];
                foreach ($ligne as $kk => $vv) { if (is_scalar($vv)) $o[(string)$kk] = (string)$vv; }
                $l[] = $o;
            }
            $boucles[(string)$k] = $l;
        }
        require_once __DIR__ . '/docx.php';
        $bin = docx_remplir($modele, $champs, $boucles);
        if ($bin === null) cta_json(['ok' => false, 'err' => 'Export Word impossible : ' . ($GLOBALS['docx_erreur'] ?? 'modèle ' . basename($modele) . '.docx absent ou illisible') . '.'], 404);
        $fichier = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($in['fichier'] ?? ('export-' . $modele)));
        $fichier = preg_replace('/\.docx$/i', '', $fichier) . '.docx';
        cta_ajouter_audit($db, $user['nom'], $user['role'], $docPtme ? 'PTME' : ($docPharma ? 'Pharmacie' : ($docCaisse ? 'Caisse' : 'CNAMGS')), 'Export Word', $modele . ' · ' . $fichier, $ip);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fichier . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: no-store');
        echo $bin;
        exit;
    }

    cta_json(['ok' => false, 'err' => 'Action inconnue.'], 400);
} catch (Throwable $e) {
    cta_log('api ' . $a . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    cta_json(['ok' => false, 'err' => 'Erreur du serveur de données. Le détail est dans logs/cta_erreurs.log (à communiquer à l’administrateur).'], 500);
}
