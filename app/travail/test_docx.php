<?php
// Vérifie que les trois modèles se remplissent : aucune balise {{...}} ne doit rester et le XML doit être valide.
require __DIR__ . '/../cta/docx.php';

$base = [
    'num' => '0101', 'date' => '12/01/2026', 'fonds' => 'Fonds 1', 'fonds_libelle' => 'Fonds 1 · Secteur privé',
    'nature' => 'Consultations', 'recap' => 'R-01', 'statut' => 'Récapitulatif R-01',
    'total' => '44 500', 'part' => '4 450', 'remb' => '40 050', 'somme_lettres' => 'QUARANTE-QUATRE MILLE CINQ CENTS',
    'date_lettres' => '12 JANVIER 2026', 'mois_annee_lettres' => 'JANVIER 2026',
    'c_f1' => 'X', 'c_f2' => '', 'c_f3' => '', 'c_consult' => 'X', 'c_examens' => '', 'c_hospit' => '', 'c_medicament' => '',
    'edite_le' => '19/11/2026', 'edite_par' => 'Médecin-chef',
];
$lignes = array_map(fn($i) => [
    'n' => (string)$i, 'feuille' => '30709' . $i, 'nom' => 'Nzé Alphonse', 'nag' => '1000001',
    'total' => $i <= 4 ? '12 000' : '', 'part' => $i <= 4 ? '1 200' : '', 'remb' => $i <= 4 ? '10 800' : '',
], range(1, 10));
$factures = array_map(fn($i) => ['n' => (string)$i, 'num' => '01' . str_pad((string)$i, 2, '0', STR_PAD_LEFT),
    'date' => '12/01/2026', 'remb' => $i <= 4 ? '10 800' : ''], range(1, 10));

$dette = [
    'annee' => '2026', 'numero' => '', 'statut' => 'Année ouverte', 'mode' => 'Dépôts saisis à la main',
    'date_situation' => '19/11/2026', 'date_lettres' => '19 NOVEMBRE 2026',
    'init1' => '0', 'init2' => '0', 'init3' => '0', 'init_t' => '0',
    't1_f1' => '1', 't1_f2' => '2', 't1_f3' => '3', 't2_f1' => '4', 't2_f2' => '5', 't2_f3' => '6', 'total_t2' => '11',
    'total_depots' => '49 885 285', 'total_paiements' => '20 000 000', 'dette_totale' => '29 885 285',
    'dep_f1' => '1', 'dep_f2' => '2', 'dep_f3' => '3', 'paye_f1' => '4', 'paye_f2' => '5', 'paye_f3' => '6',
    'dette_f1' => '7', 'dette_f2' => '8', 'dette_f3' => '9', 'f1' => 'Fonds 1', 'f2' => 'Fonds 2', 'f3' => 'Fonds 3',
    'edite_le' => '19/11/2026', 'edite_par' => 'Médecin-chef',
];
$mois = array_map(fn($m) => ['mois' => $m, 'd1' => '1 500 000', 'p1' => '1 000 000', 'd2' => '900 000', 'p2' => '700 000',
    'd3' => '10 000 000', 'p3' => '3 000 000', 'dt' => '12 400 000', 'pt' => '4 700 000'],
    ['janv', 'fév', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'sept', 'octo', 'nov', 'dec']);

$essais = [
    'facture' => [$base, ['lignes' => $lignes]],
    'recapitulatif' => [$base + ['nb' => '4'], ['factures' => $factures]],
    'dette' => [$dette, ['mois' => $mois]],
];

$ok = true;
foreach ($essais as $modele => [$champs, $boucles]) {
    $bin = docx_remplir($modele, $champs, $boucles);
    if ($bin === null) { echo "ECHEC $modele : remplissage impossible\n"; $ok = false; continue; }
    $out = sys_get_temp_dir() . "/test-$modele.docx";
    file_put_contents($out, $bin);
    $zip = new ZipArchive();
    if ($zip->open($out) !== true) { echo "ECHEC $modele : archive illisible\n"; $ok = false; continue; }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    $restants = [];
    if (preg_match_all('/\{\{[^}]*\}\}/', strip_tags($xml), $m)) $restants = array_unique($m[0]);
    $dom = new DOMDocument();
    $valide = @$dom->loadXML($xml);
    printf("%s : %d octets, XML %s, balises restantes : %s\n", $modele, strlen($bin),
        $valide ? 'valide' : 'INVALIDE', $restants ? implode(' ', $restants) : 'aucune');
    if (!$valide || $restants) $ok = false;
}
echo $ok ? "OK\n" : "PROBLEMES\n";
exit($ok ? 0 : 1);
