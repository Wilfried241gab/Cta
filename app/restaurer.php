<?php
// CTA Lambaréné — restauration complète depuis une sauvegarde chiffrée.
//   php restaurer.php chemin\vers\cta_2026-11-11_150000.enc [--oui]
// Arrêtez les utilisateurs avant : TOUTES les données actuelles sont remplacées (une sauvegarde de sécurité est faite juste avant).
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/sauvegarde.php';
$f = $argv[1] ?? '';
if (!$f || !is_file($f)) { fwrite(STDERR, "Usage : php restaurer.php fichier.enc [--oui]\n"); exit(1); }
try {
    $o = json_decode(cta_dechiffrer(file_get_contents($f), cta_cle_sauvegarde(false)), true);
    if (!is_array($o) || empty($o['tables'])) throw new RuntimeException('Contenu de sauvegarde invalide.');
    echo 'Sauvegarde du ' . ($o['date'] ?? '?') . ' (' . ($o['version'] ?? '?') . ") : fichier valide.\n";
    if (!in_array('--oui', $argv, true)) {
        echo "Les données actuelles seront REMPLACÉES. Tapez OUI pour continuer : ";
        if (strtoupper(trim(fgets(STDIN))) !== 'OUI') { echo "Annulé.\n"; exit(0); }
    }
    [$sec] = cta_sauvegarder('_avant_restauration');
    echo "Copie de sécurité de l'état actuel : $sec\n";
    $db = cta_db();
    cta_tx($db, function () use ($db, $o) {
        foreach (array_reverse(CTA_TABLES) as $t) { if ($t !== 'cta_meta') $db->exec("DELETE FROM $t"); }
        foreach (CTA_TABLES as $t) {
            if ($t === 'cta_meta') continue;
            foreach ($o['tables'][$t] ?? [] as $row) {
                $cols = array_keys($row);
                $db->prepare("INSERT INTO $t (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
            }
        }
    });
    cta_log('Restauration depuis ' . basename($f));
    echo "Restauration terminée.\n";
} catch (Throwable $e) { cta_log('Restauration ÉCHEC : ' . $e->getMessage()); fwrite(STDERR, 'ÉCHEC : ' . $e->getMessage() . "\n"); exit(1); }
