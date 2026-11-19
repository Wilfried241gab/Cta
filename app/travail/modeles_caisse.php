<?php
// Génère les modèles Word PAYSAGE de la caisse (à remplir par cta/docx.php) :
//   registre_caisse_pvvih.docx  registre des prestations PVVIH
//   registre_caisse_ext.docx    registre de la consultation externe
//   registre_caisse_cert.docx   registre des certificats médicaux
//   journal_depenses.docx       journal des dépenses (avec total)
//   synthese_caisse.docx        synthèse mensuelle (12 mois + total, une page)
$dest = __DIR__ . '/../cta/modeles';
if (!is_dir($dest)) mkdir($dest, 0775, true);

function xe($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function run($t, $b = false, $sz = 16, $color = '') {
    $rpr = ($b ? '<w:b/>' : '') . ($color ? '<w:color w:val="' . $color . '"/>' : '')
        . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>';
    return '<w:r><w:rPr>' . $rpr . '</w:rPr><w:t xml:space="preserve">' . xe($t) . '</w:t></w:r>';
}
function para($t = '', $o = []) {
    $al = $o['align'] ?? ''; $b = $o['b'] ?? false; $sz = $o['sz'] ?? 16;
    $after = $o['after'] ?? 60; $before = $o['before'] ?? 0; $color = $o['color'] ?? '';
    $ppr = ($al ? '<w:jc w:val="' . $al . '"/>' : '')
        . '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="240" w:lineRule="auto"/>';
    return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . ($t === '' ? '' : run($t, $b, $sz, $color)) . '</w:p>';
}
function cell($t, $w, $o = []) {
    $al = $o['align'] ?? 'left'; $b = $o['b'] ?? false; $sz = $o['sz'] ?? 14;
    $fill = $o['fill'] ?? ''; $span = (int)($o['span'] ?? 1); $after = $o['after'] ?? 0;
    $tcpr = '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
        . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
        . ($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '')
        . '<w:vAlign w:val="center"/>';
    return '<w:tc><w:tcPr>' . $tcpr . '</w:tcPr>' . para($t, ['align' => $al, 'b' => $b, 'sz' => $sz, 'after' => $after]) . '</w:tc>';
}
function row($cells) { return '<w:tr>' . implode('', $cells) . '</w:tr>'; }
function tbl($rows, $widths) {
    $bordures = '<w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $cote) {
        $bordures .= '<w:' . $cote . ' w:val="single" w:sz="4" w:space="0" w:color="808080"/>';
    }
    $bordures .= '</w:tblBorders>';
    $marges = '<w:tblCellMar><w:top w:w="15" w:type="dxa"/><w:left w:w="35" w:type="dxa"/>'
        . '<w:bottom w:w="15" w:type="dxa"/><w:right w:w="35" w:type="dxa"/></w:tblCellMar>';
    $grid = '';
    foreach ($widths as $w) $grid .= '<w:gridCol w:w="' . $w . '"/>';
    return '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        . $bordures . $marges . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $rows . '</w:tbl>';
}
function entete_cta($titre, $sous = '') {
    return para('CENTRE DE TRAITEMENT AMBULATOIRE DE LAMBARÉNÉ (CTA)', ['align' => 'center', 'b' => true, 'sz' => 18, 'color' => '1F5C4A', 'after' => 0])
        . para($titre, ['align' => 'center', 'b' => true, 'sz' => 22, 'after' => 20])
        . ($sous !== '' ? para($sous, ['align' => 'center', 'sz' => 14, 'after' => 60]) : '');
}
function pied($mention) {
    return para('', ['after' => 40]) . para($mention, ['sz' => 12, 'after' => 40])
        . para('', ['after' => 100])
        . para('Le caissier : ______________________          Le médecin-chef : ______________________', ['sz' => 14]);
}

$modeles = [];

/* ---------- Registre PVVIH ---------- */
$w = [480, 950, 650, 950, 2400, 550, 550, 3200, 1100, 1050, 1150, 850, 1824];
$t = ['N°', 'Date', 'Heure', 'N° de dossier', 'Nom et prénom', 'Âge', 'Sexe', 'Éléments payés', 'Prestation du jour', 'Encaissé', 'Non recouvré', 'Reçu', 'Caissier'];
$h = row(array_map(fn($i, $x) => cell($x, $w[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($w), $t));
$k = ['n', 'date', 'heure', 'dossier', 'nom', 'age', 'sexe', 'elements', 'prestation', 'encaisse', 'non_rec', 'recu', 'caissier'];
$l = [];
foreach ($k as $i => $f) $l[] = cell(($i === 0 ? '{{#lignes}}' : '') . '{{' . $f . '}}' . ($i === count($k) - 1 ? '{{/lignes}}' : ''), $w[$i], ['align' => in_array($f, ['date', 'heure', 'age', 'sexe', 'recu'], true) ? 'center' : (in_array($f, ['prestation', 'encaisse', 'non_rec'], true) ? 'right' : 'left'), 'sz' => 12]);
$modeles['registre_caisse_pvvih'] = entete_cta('REGISTRE DE CAISSE — PVVIH', 'Du {{du}} au {{au}} · {{nb}} enregistrement(s) · {{total}} encaissé(s)')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 60])
    . tbl($h . row($l), $w)
    . pied('Montants en francs CFA. Les lignes annulées ne figurent pas dans ce registre.');

/* ---------- Registre consultation externe ---------- */
$w = [500, 1000, 700, 2800, 600, 600, 1800, 4200, 1300, 900, 1304];
$t = ['N°', 'Date', 'Heure', 'Patient', 'Âge', 'Sexe', 'Consultation', 'Examens payés', 'Total payé', 'Reçu', 'Caissier'];
$h = row(array_map(fn($i, $x) => cell($x, $w[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($w), $t));
$k = ['n', 'date', 'heure', 'nom', 'age', 'sexe', 'consultation', 'examens', 'total', 'recu', 'caissier'];
$l = [];
foreach ($k as $i => $f) $l[] = cell(($i === 0 ? '{{#lignes}}' : '') . '{{' . $f . '}}' . ($i === count($k) - 1 ? '{{/lignes}}' : ''), $w[$i], ['align' => in_array($f, ['total'], true) ? 'right' : (in_array($f, ['date', 'heure', 'age', 'sexe', 'recu'], true) ? 'center' : 'left'), 'sz' => 12]);
$modeles['registre_caisse_ext'] = entete_cta('REGISTRE DE CAISSE — CONSULTATION EXTERNE', 'Du {{du}} au {{au}} · {{nb}} patient(s) · {{total}} encaissé(s)')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 60])
    . tbl($h . row($l), $w)
    . pied('« Examens payés » reprend les reçus d’examens encaissés pour le patient. Montants en francs CFA.');

/* ---------- Registre certificats médicaux ---------- */
$w = [500, 1300, 700, 2800, 1300, 1300, 3600, 1100, 900, 2204];
$t = ['N°', 'Date de réception', 'Heure', 'Nom et prénom', 'Date de naissance', 'N° de pièce', 'Observation', 'Montant', 'Reçu', 'Caissier'];
$h = row(array_map(fn($i, $x) => cell($x, $w[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($w), $t));
$k = ['n', 'date', 'heure', 'nom', 'naiss', 'piece', 'obs', 'montant', 'recu', 'caissier'];
$l = [];
foreach ($k as $i => $f) $l[] = cell(($i === 0 ? '{{#lignes}}' : '') . '{{' . $f . '}}' . ($i === count($k) - 1 ? '{{/lignes}}' : ''), $w[$i], ['align' => $f === 'montant' ? 'right' : (in_array($f, ['date', 'heure', 'naiss', 'piece', 'recu'], true) ? 'center' : 'left'), 'sz' => 12]);
$modeles['registre_caisse_cert'] = entete_cta('REGISTRE DE CAISSE — CERTIFICATS MÉDICAUX', 'Du {{du}} au {{au}} · {{nb}} certificat(s) · {{total}} encaissé(s)')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 60])
    . tbl($h . row($l), $w)
    . pied('Montants en francs CFA.');

/* ---------- Journal des dépenses ---------- */
$w = [500, 1200, 2000, 4600, 2600, 1500, 3304];
$t = ['N°', 'Date', 'Type d’opération', 'Description', 'Destinataire', 'Montant', 'Pièce justificative'];
$h = row(array_map(fn($i, $x) => cell($x, $w[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 14]), array_keys($w), $t));
$k = ['n', 'date', 'type', 'desc', 'dest', 'montant', 'piece'];
$l = [];
foreach ($k as $i => $f) $l[] = cell(($i === 0 ? '{{#lignes}}' : '') . '{{' . $f . '}}' . ($i === count($k) - 1 ? '{{/lignes}}' : ''), $w[$i], ['align' => $f === 'montant' ? 'right' : ($f === 'date' ? 'center' : 'left'), 'sz' => 14]);
$ligneTot = row([
    cell('Totaux', 10900, ['span' => 5, 'align' => 'right', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 14]),
    cell('{{total}}', 1500, ['align' => 'right', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 14]),
    cell('{{nb}} dépense(s)', 3304, ['fill' => 'EAF1EE', 'sz' => 12]),
]);
$modeles['journal_depenses'] = entete_cta('JOURNAL DES DÉPENSES', '{{periode}} · {{nb}} dépense(s)')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 60])
    . tbl($h . row($l) . $ligneTot, $w)
    . pied('Montants en francs CFA. Chaque dépense est justifiée par une pièce (facture ou reçu).');

/* ---------- Synthèse mensuelle ---------- */
$w = array_merge([1400], array_fill(0, 9, 1430), [1434]);
$t = ['Mois', 'Patients PVVIH reçus', 'PVVIH exonérés', 'Patients consultation externe', 'Recettes PVVIH (payants)',
    'Recettes PVVIH exonérés (coût, hors total)', 'Recettes consultation externe', 'Total recettes',
    'Total sortie', 'Versement', 'Non recouvré (information)'];
$h = row(array_map(fn($i, $x) => cell($x, $w[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($w), $t));
$k = ['mois', 'n', 'e', 'xn', 'rc', 'me', 'xr', 'tric', 'so', 'v', 'nr'];
$l = [];
foreach ($k as $i => $f) $l[] = cell(($i === 0 ? '{{#lignes}}' : '') . '{{' . $f . '}}' . ($i === count($k) - 1 ? '{{/lignes}}' : ''), $w[$i], ['align' => $i === 0 ? 'left' : 'right', 'sz' => 12]);
$cells = [];
foreach (['{{t1}}', '{{t2}}', '{{t3}}', '{{t4}}', '{{t5}}', '{{t6}}', '{{t7}}', '{{t8}}', '{{t9}}', '{{t10}}'] as $i => $tag) {
    $cells[] = cell($tag, $w[$i + 1], ['align' => 'right', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]);
}
$ligneTot = row(array_merge([cell('TOTAL {{annee}}', 1400, ['b' => true, 'fill' => 'EAF1EE', 'sz' => 12])], $cells));
$modeles['synthese_caisse'] = entete_cta('SYNTHÈSE MENSUELLE DE LA CAISSE', 'Année {{annee}}')
    . para('Édité le {{edite_le}} à {{heure}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 50])
    . tbl($h . row($l) . $ligneTot, $w)
    . para('', ['after' => 30])
    . para('Les recettes ne comptent que l’argent réellement encaissé. Les exonérés (coût) et le non recouvré sont affichés à part, pour information : ils n’entrent ni dans les recettes ni dans le versement. Versement = total recettes − total sortie.', ['sz' => 12, 'after' => 80])
    . para('Le caissier : ______________________          Le médecin-chef : ______________________', ['sz' => 14]);

/* ---------- écriture des .docx (A4 paysage) ---------- */
function docx_ecrire_paysage($fichier, $corps) {
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $corps
        . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="567" w:right="567" w:bottom="567" w:left="567" w:header="284" w:footer="284" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    $tmp = tempnam(sys_get_temp_dir(), 'ctamod');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document);
    $zip->close();
    copy($tmp, $fichier);
    unlink($tmp);
}

foreach ($modeles as $nom => $corps) {
    docx_ecrire_paysage($dest . '/' . $nom . '.docx', $corps);
    echo $nom . '.docx : ' . filesize($dest . '/' . $nom . '.docx') . " octets\n";
}
