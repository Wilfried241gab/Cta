<?php
// ============================================================
//  CTA Lambaréné — page de contrôle de version
//  Ouvre http://localhost/cta/verif.php dans le navigateur.
//  Cette page ne modifie rien : elle compare seulement les
//  fichiers installés avec la version attendue et signale
//  ceux qui sont restés à l'ancienne version.
// ============================================================

$attendus = [
    'app.html'                       => 29052,
    'assets/app-66a4949a40.js'       => 813976,
    'assets/app-607e8be36c.js'       => 9893,
    'assets/app-5e24bbac98.js'       => 4752,
    'assets/app-48034cc073.js'       => 10343,
    'assets/app-f1a374fa59.css'      => null,
    'api.php'                        => null,
    'common.php'                     => null,
    'index.php'                      => null,
];

// Repères attendus dans le bundle des modules (base64 -> texte).
$reperes = [
    'pharma' => ['resetDisp', 'openLotCtl', 'Contrôle par lot', 'openDispList', 'Stock insuffisant', 'MODAL_DOWN'],
    'caisse' => ['Caisse : <b>', 'laboPush'],
    'rh'     => ['gSuggestions', 'MODAL_DOWN'],
    'ptme'   => ['MODAL_DOWN'],
];

function vh($o) { return htmlspecialchars((string)$o, ENT_QUOTES, 'UTF-8'); }

$lignes = [];
$toutOk = true;
foreach ($attendus as $f => $taille) {
    $p = __DIR__ . '/' . $f;
    $existe = is_file($p);
    $t = $existe ? filesize($p) : null;
    $ok = $existe && ($taille === null || $t === $taille);
    if (!$ok) $toutOk = false;
    $lignes[] = [$f, $taille, $t, $existe ? substr(hash_file('sha256', $p), 0, 12) : '—', $ok];
}

// Lecture du bundle et contrôle des repères de chaque module.
$modules = [];
$bundle = __DIR__ . '/assets/app-66a4949a40.js';
if (is_file($bundle)) {
    $js = file_get_contents($bundle);
    $i = strpos($js, 'const M=');
    if ($i !== false) {
        $j = strpos($js, '{', $i);
        $niveau = 0; $fin = null;
        for ($k = $j, $n = strlen($js); $k < $n; $k++) {
            if ($js[$k] === '{') $niveau++;
            elseif ($js[$k] === '}') { $niveau--; if ($niveau === 0) { $fin = $k + 1; break; } }
        }
        if ($fin) {
            $json = substr($js, $j, $fin - $j);
            $map = json_decode($json, true);
            if (is_array($map)) {
                foreach ($reperes as $mod => $liste) {
                    if (!isset($map[$mod])) { $modules[$mod] = ['absent du bundle', false]; continue; }
                    $html = base64_decode($map[$mod]);
                    $manquants = array_values(array_filter($liste, fn($r) => strpos($html, $r) === false));
                    $modules[$mod] = [$manquants ? 'manque : ' . implode(', ', $manquants) : 'à jour', $manquants === []];
                    if ($manquants) $toutOk = false;
                }
            }
        }
    }
}

// Fichiers réellement présents dans le dossier des scripts.
$assets = is_dir(__DIR__ . '/assets') ? scandir(__DIR__ . '/assets') : [];
$assets = array_values(array_diff($assets, ['.', '..']));

// Version du code serveur, lue directement dans common.php (sans l'exécuter).
$versionCode = 'illisible';
if (is_file(__DIR__ . '/common.php') && preg_match("/const CTA_VERSION = '([^']+)'/", file_get_contents(__DIR__ . '/common.php'), $mv)) {
    $versionCode = $mv[1];
}

$cfg = @include __DIR__ . '/config.php';
$pilote = is_array($cfg) ? ($cfg['pilote'] ?? '?') : 'config.php introuvable';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>CTA — contrôle de version installée</title>
<style>
 body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:24px;color:#182230;background:#f7f8fa}
 h1{font-size:1.25rem;margin:0 0 4px}
 p.lede{margin:0 0 18px;color:#4a5568;font-size:.92rem}
 .bilan{padding:12px 16px;border-radius:8px;font-weight:600;margin-bottom:18px}
 .ok{background:#e6f5ec;color:#17603a;border:1px solid #b7e0c6}
 .ko{background:#fdecec;color:#8a1c1c;border:1px solid #f3c2c2}
 table{border-collapse:collapse;width:100%;background:#fff;font-size:.9rem;margin-bottom:18px}
 th,td{border:1px solid #dde2e8;padding:7px 9px;text-align:left;vertical-align:top}
 th{background:#eef1f5;font-weight:600}
 td.oui{color:#17603a;font-weight:600}
 td.non{color:#8a1c1c;font-weight:600}
 code{font-size:.88rem}
 .note{font-size:.85rem;color:#4a5568;background:#fff;border:1px solid #dde2e8;border-radius:8px;padding:12px 14px}
</style>
</head>
<body>
<h1>Contrôle de version — CTA Lambaréné</h1>
<p class="lede">Page de diagnostic : elle ne modifie rien. Dossier analysé : <code><?= vh(__DIR__) ?></code></p>

<div class="bilan <?= $toutOk ? 'ok' : 'ko' ?>">
 <?= $toutOk
   ? 'Version à jour : tous les fichiers et tous les modules correspondent à la version validée.'
   : 'Version incomplète : au moins un fichier est resté à l\'ancienne version (lignes « ANCIEN » ci-dessous).' ?>
</div>

<table>
 <tr><th>Fichier</th><th>Taille attendue</th><th>Taille trouvée</th><th>Empreinte</th><th>État</th></tr>
 <?php foreach ($lignes as [$f, $att, $trouve, $sha, $ok]): ?>
 <tr>
  <td><code><?= vh($f) ?></code></td>
  <td><?= $att === null ? '—' : number_format($att, 0, '', ' ') ?></td>
  <td><?= $trouve === null ? 'fichier absent' : number_format($trouve, 0, '', ' ') ?></td>
  <td><code><?= vh($sha) ?></code></td>
  <td class="<?= $ok ? 'oui' : 'non' ?>"><?= $ok ? 'OK' : ($trouve === null ? 'ABSENT' : 'ANCIEN') ?></td>
 </tr>
 <?php endforeach; ?>
</table>

<table>
 <tr><th>Module (dans le script du menu)</th><th>Contrôle</th><th>État</th></tr>
 <?php foreach ($modules as $mod => [$etat, $ok]): ?>
 <tr><td><?= vh($mod) ?></td><td><?= vh($etat) ?></td><td class="<?= $ok ? 'oui' : 'non' ?>"><?= $ok ? 'OK' : 'À REMPLACER' ?></td></tr>
 <?php endforeach; ?>
 <?php if (!$modules): ?><tr><td colspan="3">Script du menu illisible : le fichier assets/app-66a4949a40.js est absent ou incomplet.</td></tr><?php endif; ?>
</table>

<div class="note">
 <p><strong>Version du code installé :</strong> <code><?= vh($versionCode) ?></code> (attendue : <code>16-2026-11-24</code>)</p>
 <p><strong>Environnement :</strong> PHP <?= vh(PHP_VERSION) ?> · pilote de base déclaré : <code><?= vh($pilote) ?></code></p>
 <p><strong>Fichiers présents dans assets/ :</strong><br><code><?= vh(implode(' · ', $assets)) ?></code></p>
 <p><strong>Que faire si un fichier est « ANCIEN » ou « ABSENT » :</strong> recopiez le fichier correspondant depuis l'archive
 téléchargée vers <code><?= vh(__DIR__) ?></code>, puis rechargez l'application avec
 <code>Ctrl + Maj + R</code>. Si Windows refuse la copie, c'est que le fichier est utilisé : fermez le navigateur,
 puis copiez à nouveau.</p>
</div>
</body>
</html>
