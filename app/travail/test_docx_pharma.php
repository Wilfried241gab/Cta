<?php
// Vérifie que les cinq modèles paysage de la pharmacie se remplissent :
// registre ARV, état du stock, fiche mensuelle, inventaire, commande.
// Aucune balise {{...}} ne doit rester, le XML doit être valide et la page en paysage.
require __DIR__ . '/../cta/docx.php';

$cas = [];

// 1. Registre ARV
$lignes = [];
for ($i = 1; $i <= 8; $i++) {
    $l = ['n' => (string)$i, 'dossier' => '0' . (230 + $i), 'protocole' => 'Dolutegravir + lamivudine + ténofovir',
        'contact' => '07 41 00 00 0' . $i, 'obs' => $i === 2 ? 'Transfert entrant' : '-', 'renouvellement' => '12/12/2026'];
    for ($m = 1; $m <= 12; $m++) $l['m' . $m] = $m <= 10 ? '✓ 0' . $i . '/0' . $m : 'couvert';
    $lignes[] = $l;
}
$cas[] = ['registre_pharma',
    ['annee' => '2026', 'edite_le' => '19/11/2026', 'edite_par' => 'Responsable pharmacie', 'nb_patients' => '8'],
    ['lignes' => $lignes]];

// 2. État du stock
$articles = [
    ['n' => '1', 'designation' => 'Paracétamol 500 mg', 'dosage' => '500 mg', 'forme' => 'Comprimé', 'stock' => '1 200', 'cmm' => '120', 'mois_stock' => '10,0', 'peremption' => '09/2027', 'statut' => 'OK'],
    ['n' => '2', 'designation' => 'Amoxicilline 500 mg', 'dosage' => '500 mg', 'forme' => 'Comprimé', 'stock' => '0', 'cmm' => '40', 'mois_stock' => '—', 'peremption' => '—', 'statut' => 'Rupture'],
    ['n' => '3', 'designation' => 'Métronidazole 500 mg', 'dosage' => '500 mg', 'forme' => 'Comprimé', 'stock' => '45', 'cmm' => '40', 'mois_stock' => '1,1', 'peremption' => '03/2027', 'statut' => 'Stock bas'],
];
$cas[] = ['stock_pharma',
    ['compartiment' => 'Médicaments courants', 'date' => '19/11/2026', 'edite_par' => 'Responsable pharmacie',
        'nb_articles' => '3', 'nb_ruptures' => '1', 'nb_bas' => '1'],
    ['lignes' => $articles]];

// 3. Fiche mensuelle
$fiche = [];
for ($i = 1; $i <= 3; $i++) {
    $fiche[] = ['n' => (string)$i, 'molecule' => 'Molécule ' . $i, 'dosage' => '500 mg', 'forme' => 'Comprimé',
        'debut' => '120', 'recu' => '300', 'utilise' => '250', 'perdu' => $i === 2 ? '5' : '', 'correction' => $i === 3 ? '+2' : '',
        'fin' => (string)(120 + 300 - 250), 'rupture' => $i === 2 ? 'le 17' : ''];
}
$cas[] = ['fiche_mensuelle',
    ['compartiment' => 'ARV', 'mois' => 'novembre 2026', 'edite_le' => '19/11/2026', 'edite_par' => 'Responsable pharmacie'],
    ['lignes' => $fiche]];

// 4. Inventaire
$inv = [];
for ($i = 1; $i <= 3; $i++) {
    $inv[] = ['n' => (string)$i, 'designation' => 'Article ' . $i, 'dosage' => '250 mg', 'forme' => 'Comprimé',
        'patients' => (string)(10 * $i), 'cmm' => '42', 'stock_debut' => '500', 'peremption' => '06/2027', 'sorties' => '120', 'reste' => '380'];
}
$cas[] = ['inventaire_pharma',
    ['compartiment' => 'Consommables', 'date_debut' => '21/08/2026', 'date_fin' => '19/11/2026',
        'edite_le' => '19/11/2026', 'edite_par' => 'Responsable pharmacie', 'nb_articles' => '3'],
    ['lignes' => $inv]];

// 5. Commande
$cmd = [];
for ($i = 1; $i <= 3; $i++) {
    $cmd[] = ['n' => (string)$i, 'medicament' => 'Produit ' . $i, 'stock' => '120', 'cmm' => '40',
        'perimes' => $i === 1 ? '6' : '—', 'perimant' => '20', 'besoin' => '120', 'a_commander' => (string)(20 * $i)];
}
$cas[] = ['commande_pharma',
    ['compartiment' => 'Réactifs', 'mois_vises' => '3', 'date' => '19/11/2026', 'date_limite' => '17/02/2027',
        'edite_le' => '19/11/2026', 'edite_par' => 'Responsable pharmacie', 'nb_lignes' => '3'],
    ['lignes' => $cmd]];

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
