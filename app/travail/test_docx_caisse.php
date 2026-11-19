<?php
// Vérifie les cinq modèles paysage de la caisse : registre PVVIH, registre externe,
// registre des certificats, journal des dépenses, synthèse mensuelle.
require __DIR__ . '/../cta/docx.php';

$cas = [];

$cas[] = ['registre_caisse_pvvih', ['du' => '01/11/2026', 'au' => '19/11/2026', 'nb' => '2', 'total' => '23 500 F',
    'edite_le' => '19/11/2026', 'edite_par' => 'Caissier'], ['lignes' => [
    ['n' => '1', 'date' => '19/11/2026', 'heure' => '09:12', 'dossier' => '0231', 'nom' => 'Nzamba Pierre',
        'age' => '41', 'sexe' => 'M', 'elements' => 'Consultation médicale, NFS', 'prestation' => '2 500 F',
        'encaisse' => '2 500 F', 'non_rec' => '0 F', 'recu' => 'R-0012', 'caissier' => 'Caissier'],
    ['n' => '2', 'date' => '19/11/2026', 'heure' => '10:05', 'dossier' => '0232', 'nom' => 'Moussavou Marie',
        'age' => '35', 'sexe' => 'F', 'elements' => 'Bilan pré-thérapeutique adulte', 'prestation' => '10 000 F',
        'encaisse' => '10 000 F', 'non_rec' => '0 F', 'recu' => 'R-0013', 'caissier' => 'Caissier'],
]]];

$cas[] = ['registre_caisse_ext', ['du' => '01/11/2026', 'au' => '19/11/2026', 'nb' => '1', 'total' => '5 500 F',
    'edite_le' => '19/11/2026', 'edite_par' => 'Caissier'], ['lignes' => [
    ['n' => '1', 'date' => '19/11/2026', 'heure' => '11:30', 'nom' => 'Ondo Jean', 'age' => '28', 'sexe' => 'M',
        'consultation' => 'Médecin généraliste', 'examens' => 'NFS, Groupe sanguin-Rhésus', 'total' => '5 500 F',
        'recu' => 'R-0014', 'caissier' => 'Caissier'],
]]];

$cas[] = ['registre_caisse_cert', ['du' => '01/11/2026', 'au' => '19/11/2026', 'nb' => '1', 'total' => '5 000 F',
    'edite_le' => '19/11/2026', 'edite_par' => 'Caissier'], ['lignes' => [
    ['n' => '1', 'date' => '19/11/2026', 'heure' => '12:00', 'nom' => 'Bikoro Paul', 'naiss' => '03/04/1990',
        'piece' => 'CNI-4451', 'obs' => 'Coups et blessures', 'montant' => '5 000 F', 'recu' => 'R-0015', 'caissier' => 'Caissier'],
]]];

$cas[] = ['journal_depenses', ['periode' => 'Toutes les dépenses enregistrées', 'nb' => '2', 'total' => '45 000 F',
    'edite_le' => '19/11/2026', 'edite_par' => 'Caissier'], ['lignes' => [
    ['n' => '1', 'date' => '18/11/2026', 'type' => 'Achat', 'desc' => 'Rames de papier', 'dest' => 'Fournisseur central',
        'montant' => '20 000 F', 'piece' => 'FAC-2026-118'],
    ['n' => '2', 'date' => '19/11/2026', 'type' => 'Transport', 'desc' => 'Course réactifs', 'dest' => 'Laboratoire',
        'montant' => '25 000 F', 'piece' => 'REC-078'],
]]];

$lignes = [];
$mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
foreach ($mois as $i => $m) {
    $lignes[] = ['mois' => $m, 'n' => (string)(10 + $i), 'e' => (string)($i % 3), 'xn' => (string)(4 + $i),
        'rc' => '125 000 F', 'me' => '9 000 F', 'xr' => '48 000 F', 'tric' => '173 000 F', 'so' => '31 000 F',
        'v' => '142 000 F', 'nr' => '4 500 F'];
}
$cas[] = ['synthese_caisse', ['annee' => '2026', 'edite_le' => '19/11/2026', 'heure' => '13:40', 'edite_par' => 'Caissier',
    't1' => '126', 't2' => '4', 't3' => '70', 't4' => '1 500 000 F', 't5' => '108 000 F', 't6' => '576 000 F',
    't7' => '2 076 000 F', 't8' => '372 000 F', 't9' => '1 704 000 F', 't10' => '54 000 F'], ['lignes' => $lignes]];

$echec = false;
foreach ($cas as [$nom, $champs, $boucles]) {
    $bin = docx_remplir($nom, $champs, $boucles);
    if ($bin === null) { echo "$nom : ÉCHEC (modèle absent)\n"; $echec = true; continue; }
    $tmp = tempnam(sys_get_temp_dir(), 'ctatest') . '.docx';
    file_put_contents($tmp, $bin);
    $zip = new ZipArchive();
    $ok = $zip->open($tmp) === true;
    $xml = $ok ? $zip->getFromName('word/document.xml') : '';
    if ($ok) $zip->close();
    @unlink($tmp);
    $reste = $xml !== '' ? preg_match_all('/\{\{[^}]*\}\}/', $xml, $m) : 0;
    $valide = $xml !== '' && @simplexml_load_string($xml) !== false;
    $paysage = strpos($xml, 'w:orient="landscape"') !== false;
    echo "$nom : " . strlen($bin) . " octets, XML " . ($valide ? 'valide' : 'INVALIDE')
        . ", balises restantes : " . ($reste ? implode(' ', $m[0]) : 'aucune')
        . ", paysage : " . ($paysage ? 'oui' : 'NON')
        . ", lignes de tableau : " . substr_count($xml, '<w:tr>') . "\n";
    if (!$valide || $reste || !$paysage) $echec = true;
}
echo $echec ? "ÉCHEC\n" : "OK\n";
exit($echec ? 1 : 0);
