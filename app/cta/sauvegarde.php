<?php
// CTA Lambaréné — sauvegarde chiffrée (AES-256-GCM). À lancer par sauvegarde.bat (tâche planifiée), jamais depuis le web.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/common.php';

const CTA_TABLES = ['cta_meta', 'cta_kv', 'cta_histo', 'cta_user', 'cta_audit', 'cta_seq'];

function cta_dossier_secret() { return getenv('CTA_DIR_SECRET') ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cta_secret'; }
function cta_dossier_sauv() { return getenv('CTA_DIR_SAUV') ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cta_sauvegarde'; }

function cta_cle_sauvegarde($creer) {
    $d = cta_dossier_secret(); $f = $d . DIRECTORY_SEPARATOR . 'sauvegarde.key';
    if (is_file($f)) return hex2bin(trim(file_get_contents($f)));
    if (!$creer) throw new RuntimeException('Clé de sauvegarde introuvable : ' . $f);
    if (!is_dir($d)) mkdir($d, 0700, true);
    $k = random_bytes(32); file_put_contents($f, bin2hex($k));
    fwrite(STDERR, "\n*** NOUVELLE CLÉ DE SAUVEGARDE créée : $f\n*** Faites-en une copie sur un support à part (clé USB, coffre) : sans elle, les sauvegardes sont illisibles.\n\n");
    return $k;
}

function cta_exporter_tout($db) {
    $o = ['cta_sauvegarde' => 1, 'date' => date('c'), 'version' => CTA_VERSION, 'tables' => []];
    foreach (CTA_TABLES as $t) $o['tables'][$t] = $db->query("SELECT * FROM $t")->fetchAll();
    return $o;
}

function cta_chiffrer($clair, $cle) {
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt(gzencode($clair, 6), 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) throw new RuntimeException('Chiffrement impossible (extension openssl absente ?)');
    return 'CTAENC1' . $iv . $tag . $ct;
}

function cta_dechiffrer($bin, $cle) {
    if (substr($bin, 0, 7) !== 'CTAENC1') throw new RuntimeException('Fichier de sauvegarde non reconnu.');
    $iv = substr($bin, 7, 12); $tag = substr($bin, 19, 16); $ct = substr($bin, 35);
    $z = openssl_decrypt($ct, 'aes-256-gcm', $cle, OPENSSL_RAW_DATA, $iv, $tag);
    if ($z === false) throw new RuntimeException('Clé incorrecte ou fichier altéré.');
    return gzdecode($z);
}

function cta_sauvegarder($etiquette = '') {
    $db = cta_db();
    $cle = cta_cle_sauvegarde(true);
    $dir = cta_dossier_sauv(); if (!is_dir($dir)) mkdir($dir, 0700, true);
    $nom = 'cta_' . date('Y-m-d_His') . $etiquette . '.enc';
    $f = $dir . DIRECTORY_SEPARATOR . $nom;
    file_put_contents($f, cta_chiffrer(json_encode(cta_exporter_tout($db), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), $cle));
    $jours = (int)(cta_config()['sauvegarde_jours'] ?? 60);
    foreach (glob($dir . DIRECTORY_SEPARATOR . 'cta_*.enc') as $x) { if (filemtime($x) < time() - $jours * 86400) @unlink($x); }
    $ext = cta_config()['copie_externe'] ?? '';
    $copie = '';
    if ($ext !== '') {
        if (is_dir($ext) && @copy($f, rtrim($ext, '\\/') . DIRECTORY_SEPARATOR . $nom)) $copie = ' + copie externe OK';
        else { $copie = ' ; ATTENTION copie externe impossible vers ' . $ext; cta_log('Sauvegarde : copie externe impossible vers ' . $ext); }
    }
    $msg = 'Sauvegarde ' . $nom . ' (' . round(filesize($f) / 1024) . ' Ko)' . $copie;
    cta_log($msg);
    return [$f, $msg, $ext !== '' && $copie !== '' && strpos($copie, 'ATTENTION') === false || $ext === ''];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { [$f, $msg, $ok] = cta_sauvegarder(); echo $msg . "\n"; exit($ok ? 0 : 2); }
    catch (Throwable $e) { cta_log('Sauvegarde ÉCHEC : ' . $e->getMessage()); fwrite(STDERR, 'ÉCHEC : ' . $e->getMessage() . "\n"); exit(1); }
}
