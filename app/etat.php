<?php
// CTA Lambaréné — page de contrôle de l'installation : http://localhost/cta/etat.php
require __DIR__ . '/common.php';
cta_entetes_securite();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$ok = []; $err = null; $rows = []; $histo = []; $pilote = '-'; $mv = '-';
if (!cta_acces_autorise()) { http_response_code(403); die('Accès refusé.'); }
$c = cta_config();
// Détails réservés : ordinateur du serveur lui-même, ou médecin-chef / administrateur connecté. Les autres ne voient que « en service ».
$detail = cta_en_local();
if (!$detail) { try { $u0 = cta_utilisateur(cta_db()); $detail = $u0 && cta_prive($u0); } catch (Throwable $e) {} }
if (!$detail) { echo '<!doctype html><meta charset="utf-8"><title>CTA</title><body style="font:16px system-ui;padding:30px">'; try { cta_db(); echo 'Service CTA : <b>en service</b>.'; } catch (Throwable $e) { echo 'Service CTA : <b>indisponible</b>.'; } echo '</body>'; exit; }
$ext = ['pdo' => extension_loaded('pdo'), 'pdo_mysql' => extension_loaded('pdo_mysql'), 'mbstring' => extension_loaded('mbstring'), 'json' => extension_loaded('json')];
try {
    $db = cta_db();
    $pilote = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $mv = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    $rows = $db->query('SELECT cle, version, maj, poste, LENGTH(valeur) AS octets FROM cta_kv ORDER BY cle')->fetchAll();
    $histo = $db->query('SELECT cle, COUNT(*) AS n, MIN(jour) AS du, MAX(jour) AS au FROM cta_histo GROUP BY cle ORDER BY cle')->fetchAll();
    $sv = $db->query("SELECT valeur FROM cta_meta WHERE cle = 'schema_version'")->fetch()['valeur'] ?? '?';
    $nu = (int)$db->query('SELECT COUNT(*) AS n FROM cta_user WHERE actif = 1')->fetch()['n'];
    $na = (int)$db->query('SELECT COUNT(*) AS n FROM cta_audit')->fetch()['n'];
    $init = (int)$db->query('SELECT COUNT(*) AS n FROM cta_user WHERE must = 1')->fetch()['n'];
} catch (Throwable $e) { $err = $e->getMessage(); cta_log('etat : ' . $e->getMessage()); }
$bk = glob((getenv('CTA_DIR_SAUV') ?: dirname(__DIR__, 2) . '/cta_sauvegarde') . '/*.enc') ?: [];
$dernier = $bk ? max(array_map('filemtime', $bk)) : 0;
$avert = [];
if (($c['utilisateur'] ?? '') === 'root') $avert[] = 'La base est utilisée avec le compte MySQL « root » : créez un utilisateur dédié (voir DURCISSEMENT.txt).';
if (($c['pilote'] ?? 'mysql') === 'mysql' && ($c['mot_de_passe'] ?? '') === '') $avert[] = 'Le compte MySQL n’a pas de mot de passe.';
if (!cta_en_https()) $avert[] = 'La connexion n’est pas chiffrée (HTTP). Activez HTTPS avant tout accès hors du réseau local.';
if (!$dernier) $avert[] = 'Aucune sauvegarde chiffrée trouvée dans le dossier cta_sauvegarde.'; elseif (time() - $dernier > 2 * 86400) $avert[] = 'La dernière sauvegarde chiffrée date de plus de 2 jours.';
if (($c['mot_de_passe_initial'] ?? '') === 'cta2026') $avert[] = 'Le mot de passe provisoire par défaut (cta2026) est connu : changez « mot_de_passe_initial » dans config.php.';
if (!empty($init ?? 0)) $avert[] = $init . ' compte(s) ont encore un mot de passe provisoire.';
$logOk = is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs');
if (!$logOk) $avert[] = 'Le dossier logs n’est pas inscriptible : les erreurs ne seront pas journalisées.';
$maxp = ini_get('max_allowed_packet');
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CTA — état de l'installation</title>
<style>
:root{--g:#0b5d3b;--g2:#1b8a5a;--bg:#f2f5f3;--ink:#10251a;--mut:#5b6b62;--line:#d9e2dc}
*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
header{background:linear-gradient(120deg,#073d27,var(--g2));color:#fff;padding:22px 28px}
header h1{margin:0;font-size:1.35rem}header p{margin:4px 0 0;opacity:.85}
main{max-width:980px;margin:22px auto;padding:0 18px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px 20px;margin-bottom:16px;box-shadow:0 4px 16px rgba(10,60,40,.06)}
h2{margin:0 0 10px;font-size:1.02rem;color:var(--g)}
.pill{display:inline-block;padding:3px 11px;border-radius:99px;font-weight:700;font-size:.82rem}
.ok{background:#dff5e8;color:#0b5d3b}.ko{background:#fde2e2;color:#9b1c1c}
table{border-collapse:collapse;width:100%}th,td{padding:8px 10px;border-bottom:1px solid var(--line);text-align:left;font-size:.9rem}th{background:#eef4f0}
.r{text-align:right}.mut{color:var(--mut)}a.btn{display:inline-block;margin-top:8px;background:var(--g);color:#fff;padding:9px 16px;border-radius:9px;text-decoration:none;font-weight:700}
</style></head><body>
<header><h1>CTA Lambaréné — état de l'installation</h1><p>Contrôle du serveur, de la base de données et des documents enregistrés</p></header>
<main>
<div class="card"><h2>Serveur</h2>
 <p>PHP <b><?= $h(PHP_VERSION) ?></b> · Application <b><?= $h(CTA_VERSION) ?></b> ·
 <?php foreach ($ext as $k => $v): ?><span class="pill <?= $v ? 'ok' : 'ko' ?>"><?= $h($k) ?> <?= $v ? '✓' : '✗' ?></span> <?php endforeach; ?></p>
 <p class="mut">Base visée : <b><?= $h($c['base']) ?></b> sur <?= $h($c['hote']) ?>:<?= $h($c['port']) ?> (utilisateur « <?= $h($c['utilisateur']) ?> »)</p>
</div>
<?php if ($avert): ?><div class="card"><h2>Points à corriger</h2><ul><?php foreach ($avert as $w): ?><li><?= $h($w) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card"><h2>Base de données</h2>
<?php if ($err): ?>
 <p><span class="pill ko">Connexion impossible</span></p><p class="mut"><?= $h($err) ?></p>
 <p>Vérifiez que MySQL est démarré dans XAMPP, que <b>base_cta.sql</b> a été importé dans phpMyAdmin, et les identifiants dans <b>config.php</b>.</p>
<?php else: ?>
 <p><span class="pill ok">Connexion réussie</span> · <?= $h(strtoupper($pilote)) ?> <?= $h($mv) ?> · schéma v<?= $h($sv ?? '?') ?> · <?= (int)$nu ?> comptes actifs · <?= (int)$na ?> lignes de journal</p>
 <?php if (!$rows): ?><p class="mut">Aucun document enregistré : ils se créeront lors des premières saisies.</p>
 <?php else: ?>
 <table><thead><tr><th>Document</th><th class="r">Version</th><th class="r">Taille</th><th>Dernière écriture</th><th>Par</th></tr></thead><tbody>
 <?php foreach ($rows as $x): ?><tr><td><?= $h($x['cle']) ?></td><td class="r"><?= (int)$x['version'] ?></td><td class="r"><?= number_format((int)$x['octets'] / 1024, 1, ',', ' ') ?> Ko</td><td><?= $h($x['maj']) ?></td><td><?= $h($x['poste']) ?></td></tr><?php endforeach; ?>
 </tbody></table><?php endif; ?>
<?php endif; ?>
</div>
<?php if ($histo): ?><div class="card"><h2>Historique journalier</h2>
 <table><thead><tr><th>Document</th><th class="r">Jours conservés</th><th>Du</th><th>Au</th></tr></thead><tbody>
 <?php foreach ($histo as $x): ?><tr><td><?= $h($x['cle']) ?></td><td class="r"><?= (int)$x['n'] ?></td><td><?= $h($x['du']) ?></td><td><?= $h($x['au']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<div class="card"><h2>Sauvegarde</h2><p>Dernière sauvegarde chiffrée : <b><?= $dernier ? $h(date('d/m/Y H:i', $dernier)) : 'aucune' ?></b> (<?= count($bk) ?> fichier(s)). Elle est faite par <b>sauvegarde.bat</b> (tâche planifiée) et chiffrée avec une clé conservée hors du site.</p>
 <?php if (isset($u0) && $u0 && $u0['role'] === 'Médecin-chef'): ?><a class="btn" href="api.php?a=export">Télécharger l'export complet (non chiffré)</a><?php endif; ?></div>
<p><a href="index.php">← Ouvrir l'application</a></p>
</main></body></html>
