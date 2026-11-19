<?php
// Génère les deux modèles Word PAYSAGE de la pharmacie :
//   registre_pharma.docx  registre de suivi des patients sous ARV (12 mois)
//   stock_pharma.docx     état du stock d'un compartiment (courant, consommable, ARV, réactif)
// Les balises {{...}} sont remplies par cta/docx.php au moment de l'export.
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
    $marges = '<w:tblCellMar><w:top w:w="20" w:type="dxa"/><w:left w:w="40" w:type="dxa"/>'
        . '<w:bottom w:w="20" w:type="dxa"/><w:right w:w="40" w:type="dxa"/></w:tblCellMar>';
    $grid = '';
    foreach ($widths as $w) $grid .= '<w:gridCol w:w="' . $w . '"/>';
    return '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        . $bordures . $marges . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $rows . '</w:tbl>';
}
function entete_cta($titre, $sous = '') {
    return para('CENTRE DE TRAITEMENT AMBULATOIRE DE LAMBARÉNÉ (CTA)', ['align' => 'center', 'b' => true, 'sz' => 18, 'color' => '1F5C4A', 'after' => 0])
        . para($titre, ['align' => 'center', 'b' => true, 'sz' => 22, 'after' => 20])
        . ($sous !== '' ? para($sous, ['align' => 'center', 'sz' => 16, 'after' => 80]) : '');
}

/* ---------- 1. Registre ARV (paysage) ---------- */
$wReg = [600, 1050, 2400, 690, 690, 690, 690, 690, 690, 690, 690, 690, 690, 690, 690, 1150, 1120, 1000];
$mois = ['janv', 'fév', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'sept', 'octo', 'nov', 'déc'];
$h1 = row([
    cell('Ordre', 600, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('N° de dossier', 1050, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Protocole ARV', 2400, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Mois de l’année {{annee}}', 8280, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'span' => 12]),
    cell('Numéro de contact', 1150, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Observation', 1120, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Prochain renouvellement', 1000, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
]);
$h2 = row(array_merge([cell('', 600, ['fill' => 'EAF1EE']), cell('', 1050, ['fill' => 'EAF1EE']), cell('', 2400, ['fill' => 'EAF1EE'])],
    array_map(fn($m) => cell($m, 690, ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']), $mois),
    [cell('', 1150, ['fill' => 'EAF1EE']), cell('', 1120, ['fill' => 'EAF1EE']), cell('', 1000, ['fill' => 'EAF1EE'])]));
$ligne = row(array_merge([
        cell('{{#lignes}}{{n}}', 600, ['align' => 'center']),
        cell('{{dossier}}', 1050, ['align' => 'center', 'b' => true]),
        cell('{{protocole}}', 2400),
    ], array_map(fn($i) => cell('{{m' . $i . '}}', 690, ['align' => 'center']), range(1, 12)),
    [
        cell('{{contact}}', 1150, ['align' => 'center']),
        cell('{{obs}}', 1120),
        cell('{{renouvellement}}{{/lignes}}', 1000, ['align' => 'center']),
    ]));
$corpsReg = entete_cta('REGISTRE DE SUIVI DES PATIENTS SOUS ARV', 'Année {{annee}}')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 80])
    . tbl($h1 . $h2 . $ligne, $wReg)
    . para('', ['after' => 40])
    . para('✓ = dispensation du mois (jour indiqué) · « couvert » = couverture par une dispensation précédente · quantités en boîtes.', ['sz' => 12, 'after' => 40])
    . para('Nombre de patients suivis : {{nb_patients}}', ['sz' => 14, 'b' => true]);

/* ---------- 2. État du stock (paysage) ---------- */
$wSto = [650, 3600, 2300, 2500, 1500, 1300, 1500, 1700, 1650];
$hSto = row([
    cell('N°', $wSto[0], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Désignation', $wSto[1], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Dosage', $wSto[2], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Forme', $wSto[3], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Stock (boîtes)', $wSto[4], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('CMM', $wSto[5], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Mois de stock', $wSto[6], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Péremption la plus proche', $wSto[7], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
    cell('Statut', $wSto[8], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE']),
]);
$ligneSto = row([
    cell('{{#lignes}}{{n}}', $wSto[0], ['align' => 'center']),
    cell('{{designation}}', $wSto[1]),
    cell('{{dosage}}', $wSto[2]),
    cell('{{forme}}', $wSto[3]),
    cell('{{stock}}', $wSto[4], ['align' => 'right', 'b' => true]),
    cell('{{cmm}}', $wSto[5], ['align' => 'right']),
    cell('{{mois_stock}}', $wSto[6], ['align' => 'right']),
    cell('{{peremption}}', $wSto[7], ['align' => 'center']),
    cell('{{statut}}{{/lignes}}', $wSto[8], ['align' => 'center']),
]);
$corpsSto = entete_cta('ÉTAT DU STOCK — {{compartiment}}', 'Situation au {{date}}')
    . para('Édité par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 80])
    . tbl($hSto . $ligneSto, $wSto)
    . para('', ['after' => 40])
    . para('{{nb_articles}} article(s) suivi(s) · {{nb_ruptures}} rupture(s) · {{nb_bas}} stock(s) bas · quantités en boîtes.', ['sz' => 14, 'after' => 40])
    . para('CMM = consommation moyenne mensuelle des trois derniers mois. Mois de stock = stock ÷ CMM.', ['sz' => 12])
    . para('', ['after' => 120])
    . para('Responsable pharmacie : ______________________          Médecin-chef : ______________________', ['sz' => 14]);

/* ---------- 3. Fiche mensuelle de stock (paysage) ---------- */
$wFic = [600, 3200, 1900, 1900, 1150, 1050, 1050, 950, 1150, 1150, 1604];
$tFic = ['N°', 'Molécule', 'Dosage', 'Forme', 'Stock début du mois', 'Quantité reçue', 'Quantité utilisée', 'Quantité perdue', 'Correction d’inventaire', 'Stock fin du mois', 'Jour de rupture'];
$hFic = row(array_map(fn($i, $t) => cell($t, $wFic[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($wFic), $tFic));
$lFic = row([
    cell('{{#lignes}}{{n}}', $wFic[0], ['align' => 'center', 'sz' => 12]),
    cell('{{molecule}}', $wFic[1]),
    cell('{{dosage}}', $wFic[2]),
    cell('{{forme}}', $wFic[3]),
    cell('{{debut}}', $wFic[4], ['align' => 'right']),
    cell('{{recu}}', $wFic[5], ['align' => 'right']),
    cell('{{utilise}}', $wFic[6], ['align' => 'right']),
    cell('{{perdu}}', $wFic[7], ['align' => 'right']),
    cell('{{correction}}', $wFic[8], ['align' => 'right']),
    cell('{{fin}}', $wFic[9], ['align' => 'right', 'b' => true]),
    cell('{{rupture}}{{/lignes}}', $wFic[10], ['align' => 'center']),
]);
$corpsFic = entete_cta('FICHE MENSUELLE DE STOCK — {{compartiment}}', '{{mois}}')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 80])
    . tbl($hFic . $lFic, $wFic)
    . para('', ['after' => 40])
    . para('Stock fin du mois = stock début + reçu − utilisé − perdu (± correction d’inventaire). Quantités en boîtes.', ['sz' => 12])
    . para('', ['after' => 120])
    . para('Responsable pharmacie : ______________________          Médecin-chef : ______________________', ['sz' => 14]);

/* ---------- 4. Inventaire de stock (paysage) ---------- */
$wInv = [500, 3000, 1900, 1900, 1200, 1100, 1400, 1400, 1500, 1804];
$tInv = ['N°', 'Désignation', 'Dosage', 'Forme', 'Patients sous la molécule', 'CMM', 'Stock au {{date_debut}}', 'Date de péremption', 'Sorties du {{date_debut}} au {{date_fin}}', 'Reste en stock à ce jour'];
$hInv = row(array_map(fn($i, $t) => cell($t, $wInv[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($wInv), $tInv));
$lInv = row([
    cell('{{#lignes}}{{n}}', $wInv[0], ['align' => 'center', 'sz' => 12]),
    cell('{{designation}}', $wInv[1]),
    cell('{{dosage}}', $wInv[2]),
    cell('{{forme}}', $wInv[3]),
    cell('{{patients}}', $wInv[4], ['align' => 'right']),
    cell('{{cmm}}', $wInv[5], ['align' => 'right']),
    cell('{{stock_debut}}', $wInv[6], ['align' => 'right']),
    cell('{{peremption}}', $wInv[7], ['align' => 'center']),
    cell('{{sorties}}', $wInv[8], ['align' => 'right']),
    cell('{{reste}}{{/lignes}}', $wInv[9], ['align' => 'right', 'b' => true]),
]);
$corpsInv = entete_cta('INVENTAIRE DE STOCK — {{compartiment}}', 'Du {{date_debut}} au {{date_fin}} · situation au {{date_fin}}')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 80])
    . tbl($hInv . $lInv, $wInv)
    . para('', ['after' => 40])
    . para('{{nb_articles}} article(s) inventorié(s) · quantités en boîtes · CMM = consommation moyenne mensuelle.', ['sz' => 12])
    . para('', ['after' => 120])
    . para('Responsable pharmacie : ______________________          Médecin-chef : ______________________', ['sz' => 14]);

/* ---------- 5. Commande proposée (paysage) ---------- */
$wCmd = [600, 4000, 1700, 1800, 2000, 2200, 1800, 1604];
$tCmd = ['N°', 'Médicament', 'Stock', 'Utilisés (CMM)', 'Périmés retirés (3 mois)', 'Périmant avant {{date_limite}}', 'Besoin (CMM × {{mois_vises}})', 'À commander'];
$hCmd = row(array_map(fn($i, $t) => cell($t, $wCmd[$i], ['align' => 'center', 'b' => true, 'fill' => 'EAF1EE', 'sz' => 12]), array_keys($wCmd), $tCmd));
$lCmd = row([
    cell('{{#lignes}}{{n}}', $wCmd[0], ['align' => 'center', 'sz' => 12]),
    cell('{{medicament}}', $wCmd[1]),
    cell('{{stock}}', $wCmd[2], ['align' => 'right']),
    cell('{{cmm}}', $wCmd[3], ['align' => 'right']),
    cell('{{perimes}}', $wCmd[4], ['align' => 'right']),
    cell('{{perimant}}', $wCmd[5], ['align' => 'right']),
    cell('{{besoin}}', $wCmd[6], ['align' => 'right']),
    cell('{{a_commander}}{{/lignes}}', $wCmd[7], ['align' => 'right', 'b' => true]),
]);
$corpsCmd = entete_cta('COMMANDE PROPOSÉE — {{compartiment}}', '{{mois_vises}} mois de stock visés · au {{date}}')
    . para('Édité le {{edite_le}} par {{edite_par}}', ['align' => 'center', 'sz' => 14, 'after' => 80])
    . tbl($hCmd . $lCmd, $wCmd)
    . para('', ['after' => 40])
    . para('À commander = CMM × mois visés − (stock − lots qui périmeront avant {{date_limite}}). {{nb_lignes}} ligne(s).', ['sz' => 12])
    . para('', ['after' => 120])
    . para('Responsable pharmacie : ______________________          Médecin-chef : ______________________', ['sz' => 14]);

/* ---------- écriture des .docx (A4 paysage) ---------- */
function docx_ecrire_paysage($chemin, $corps) {
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
        . $corps
        . '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
        . '<w:pgMar w:top="567" w:right="567" w:bottom="567" w:left="567" w:header="283" w:footer="283" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
        . '<w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="60" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
        . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:qFormat/></w:style>'
        . '</w:styles>';
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>';
    $drels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
    @unlink($chemin);
    $zip = new ZipArchive();
    if ($zip->open($chemin, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip impossible : ' . $chemin);
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/_rels/document.xml.rels', $drels);
    $zip->addFromString('word/styles.xml', $styles);
    $zip->close();
}

docx_ecrire_paysage($dest . '/registre_pharma.docx', $corpsReg);
docx_ecrire_paysage($dest . '/stock_pharma.docx', $corpsSto);
docx_ecrire_paysage($dest . '/fiche_mensuelle.docx', $corpsFic);
docx_ecrire_paysage($dest . '/inventaire_pharma.docx', $corpsInv);
docx_ecrire_paysage($dest . '/commande_pharma.docx', $corpsCmd);
foreach (['registre_pharma', 'stock_pharma', 'fiche_mensuelle', 'inventaire_pharma', 'commande_pharma'] as $m) {
    echo $m . '.docx : ' . filesize($dest . '/' . $m . '.docx') . " octets\n";
}
