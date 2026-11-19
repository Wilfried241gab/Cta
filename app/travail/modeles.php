<?php
// Génère les trois modèles Word du CNAMGS (facture, récapitulatif, état de la dette)
// en reprenant la présentation des feuilles fournies par le CTA.
$dest = __DIR__ . '/../cta/modeles';
if (!is_dir($dest)) mkdir($dest, 0775, true);

function xe($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function run($t, $b = false, $sz = 20, $color = '', $caps = false) {
    $rpr = ($b ? '<w:b/>' : '') . ($caps ? '<w:caps/>' : '')
        . ($color ? '<w:color w:val="' . $color . '"/>' : '')
        . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>';
    return '<w:r><w:rPr>' . $rpr . '</w:rPr><w:t xml:space="preserve">' . xe($t) . '</w:t></w:r>';
}
function para($t = '', $o = []) {
    $al = $o['align'] ?? ''; $b = $o['b'] ?? false; $sz = $o['sz'] ?? 20;
    $after = $o['after'] ?? 100; $before = $o['before'] ?? 0; $color = $o['color'] ?? '';
    $ppr = ($al ? '<w:jc w:val="' . $al . '"/>' : '')
        . '<w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="264" w:lineRule="auto"/>';
    return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . ($t === '' ? '' : run($t, $b, $sz, $color)) . '</w:p>';
}
function cell($t, $w, $o = []) {
    $al = $o['align'] ?? 'left'; $b = $o['b'] ?? false; $sz = $o['sz'] ?? 18;
    $fill = $o['fill'] ?? ''; $span = (int)($o['span'] ?? 1); $vm = $o['vmerge'] ?? '';
    $after = $o['after'] ?? 0;
    $tcpr = '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
        . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
        . ($vm === 'restart' ? '<w:vMerge w:val="restart"/>' : ($vm === 'cont' ? '<w:vMerge/>' : ''))
        . ($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '')
        . '<w:vAlign w:val="center"/>';
    $txt = $vm === 'cont' ? '' : $t;
    return '<w:tc><w:tcPr>' . $tcpr . '</w:tcPr>' . para($txt, ['align' => $al, 'b' => $b, 'sz' => $sz, 'after' => $after]) . '</w:tc>';
}
function cellp($paras, $w) {
    $tcpr = '<w:tcW w:w="' . $w . '" w:type="dxa"/><w:vAlign w:val="top"/>';
    $inner = '';
    foreach ($paras as $p) $inner .= para($p[0], $p[1] ?? []);
    return '<w:tc><w:tcPr>' . $tcpr . '</w:tcPr>' . $inner . '</w:tc>';
}
function row($cells) { return '<w:tr>' . implode('', $cells) . '</w:tr>'; }
// Image (logo République Gabonaise) centrée, relation rId9 vers word/media/logo.png
function logo($cx, $cy) {
    return '<w:p><w:pPr><w:jc w:val="center"/>'
        . '<w:spacing w:before="0" w:after="80" w:line="240" w:lineRule="auto"/></w:pPr>'
        . '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"'
        . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
        . '<wp:docPr id="1" name="Logo République Gabonaise"/>'
        . '<wp:cNvGraphicFramePr><a:graphicFrameLocks'
        . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
        . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="1" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip r:embed="rId9"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
}
function tbl($rows, $widths, $o = []) {
    $couleur = $o['bordure'] ?? '595959';
    $bordures = '<w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $cote) {
        $bordures .= '<w:' . $cote . ' w:val="single" w:sz="6" w:space="0" w:color="' . $couleur . '"/>';
    }
    $bordures .= '</w:tblBorders>';
    $marges = '<w:tblCellMar><w:top w:w="30" w:type="dxa"/><w:left w:w="60" w:type="dxa"/>'
        . '<w:bottom w:w="30" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar>';
    $grid = '';
    foreach ($widths as $w) $grid .= '<w:gridCol w:w="' . $w . '"/>';
    return '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        . $bordures . $marges . '</w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>' . $rows . '</w:tbl>';
}
function entete($bp = false) {
    return para('CENTRE DE TRAITEMENT AMBULATOIRE (CTA)', ['align' => 'center', 'b' => true, 'sz' => 22, 'color' => '1F5C4A', 'after' => 0])
        . para('LAMBARÉNÉ', ['align' => 'center', 'b' => true, 'sz' => 22, 'color' => '1F5C4A', 'after' => 20])
        . ($bp ? para('BP 344', ['align' => 'center', 'sz' => 18, 'after' => 0]) : '')
        . para('Tél. 011 58 22 65', ['align' => 'center', 'sz' => 18, 'after' => 0])
        . para('Lambaréné', ['align' => 'center', 'sz' => 18, 'after' => 120]);
}
// Tableau des fonds : ligne à cocher
function fonds() {
    $w = [3212, 3213, 3213];
    return para('Cocher le Fonds correspondant / SVP', ['align' => 'center', 'b' => true, 'sz' => 18, 'after' => 40])
        . tbl(
            row([cell('FONDS 1', $w[0], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE']), cell('FONDS 2', $w[1], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE']), cell('FONDS 3', $w[2], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE'])])
            . row([cell('Secteur privé', $w[0], ['align' => 'center']), cell('S. public', $w[1], ['align' => 'center']), cell('GEF', $w[2], ['align' => 'center'])])
            . row([cell('{{c_f1}}', $w[0], ['align' => 'center', 'b' => true, 'sz' => 22]), cell('{{c_f2}}', $w[1], ['align' => 'center', 'b' => true, 'sz' => 22]), cell('{{c_f3}}', $w[2], ['align' => 'center', 'b' => true, 'sz' => 22])]),
            $w
        );
}
function natures($libelleMedicament = 'Médicament') {
    $w = [2409, 2409, 2410, 2410];
    return para('NATURE DE LA PRESTATION (à cocher)', ['b' => true, 'sz' => 18, 'after' => 30])
        . tbl(
            row([cell('Consultations', $w[0], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE']), cell('Examens', $w[1], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE']), cell('Hospit.', $w[2], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE']), cell($libelleMedicament, $w[3], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE'])])
            . row([cell('{{c_consult}}', $w[0], ['align' => 'center', 'b' => true, 'sz' => 22]), cell('{{c_examens}}', $w[1], ['align' => 'center', 'b' => true, 'sz' => 22]), cell('{{c_hospit}}', $w[2], ['align' => 'center', 'b' => true, 'sz' => 22]), cell('{{c_medicament}}', $w[3], ['align' => 'center', 'b' => true, 'sz' => 22])]),
            $w
        );
}
function signatures() {
    $w = [4819, 4819];
    // Chaque nom reste dans sa colonne, sous son titre.
    return tbl(
        row([cell('Le Gestionnaire des données CNAMGS', $w[0], ['b' => true, 'align' => 'center']), cell('Le Responsable de la structure', $w[1], ['b' => true, 'align' => 'center'])])
        . row([cell('', $w[0], ['align' => 'center', 'after' => 900]), cell('', $w[1], ['align' => 'center', 'after' => 900])])
        . row([cell('Mme Chimène KOUMBA Epse ESSINGONE EMANE', $w[0], ['b' => true, 'align' => 'center', 'sz' => 18]), cell('Dr Grace Myriam LUDOVIC NGABA LIMINI', $w[1], ['b' => true, 'align' => 'center', 'sz' => 18])]),
        $w,
        ['bordure' => 'FFFFFF']
    );
}
function docx_ecrire($chemin, $corps, $image = null) {
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
        . $corps
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="850" w:right="1134" w:bottom="850" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>'
        . '<w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:after="100" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
        . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:qFormat/>'
        . '<w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/>'
        . '<w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
        . '</w:styles>';
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . ($image ? '<Default Extension="png" ContentType="image/png"/>' : '')
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
    $drels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . ($image ? '<Relationship Id="rId9" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>' : '')
        . '</Relationships>';
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>CTA Lambaréné — ' . xe(basename($chemin, '.docx')) . '</dc:title>'
        . '<dc:creator>CTA Lambaréné</dc:creator><cp:lastModifiedBy>CTA Lambaréné</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">2026-01-01T00:00:00Z</dcterms:created></cp:coreProperties>';
    $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
        . '<Application>CTA Lambaréné</Application></Properties>';
    @unlink($chemin);
    $zip = new ZipArchive();
    if ($zip->open($chemin, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip impossible : ' . $chemin);
    $zip->addFromString('[Content_Types].xml', $types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/_rels/document.xml.rels', $drels);
    $zip->addFromString('word/styles.xml', $styles);
    $zip->addFromString('docProps/core.xml', $core);
    $zip->addFromString('docProps/app.xml', $app);
    if ($image) $zip->addFile($image, 'word/media/logo.png');
    $zip->close();
    return $chemin;
}

// ---------- Feuille 1 : FACTURE CNAMGS
$wf = [700, 1400, 2638, 1300, 1200, 1200, 1200];
$corps = entete()
    . fonds()
    . para('', ['after' => 60])
    . para('N° facture : {{num}}', ['b' => true, 'sz' => 20, 'after' => 20])
    . para('CLIENT / CNAMGS', ['b' => true, 'sz' => 20, 'after' => 100])
    . natures()
    . para('', ['after' => 60])
    . tbl(
        row([
            cell('Nbres feuille', $wf[0], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('N° de feuilles', $wf[1], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Noms et prénoms', $wf[2], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('N° Assuré NAG', $wf[3], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Montant total', $wf[4], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Montant payé par le patient', $wf[5], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Remboursement CNAMGS', $wf[6], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
        ])
        . row([
            cell('{{#lignes}}{{n}}', $wf[0], ['align' => 'center']),
            cell('{{feuille}}', $wf[1], ['align' => 'center']),
            cell('{{nom}}', $wf[2]),
            cell('{{nag}}', $wf[3]),
            cell('{{total}}', $wf[4], ['align' => 'right']),
            cell('{{part}}', $wf[5], ['align' => 'right']),
            cell('{{remb}}{{/lignes}}', $wf[6], ['align' => 'right']),
        ])
        . row([
            cell('TOTAL', 700 + 1400 + 2638 + 1300, ['b' => true, 'span' => 4, 'fill' => 'EAF1EE']),
            cell('{{total}}', $wf[4], ['b' => true, 'align' => 'right', 'fill' => 'EAF1EE']),
            cell('{{part}}', $wf[5], ['b' => true, 'align' => 'right', 'fill' => 'EAF1EE']),
            cell('{{remb}}', $wf[6], ['b' => true, 'align' => 'right', 'fill' => 'EAF1EE']),
        ]),
        $wf
    )
    . para('Remboursement CNAMGS : {{remb}} F', ['align' => 'right', 'b' => true, 'sz' => 20, 'before' => 120])
    . para('Arrêté la présente facture à la somme de {{somme_lettres}} FCFA', ['b' => true, 'sz' => 20, 'before' => 120])
    . para('Fait à Lambaréné le {{date_lettres}}', ['b' => true, 'sz' => 20, 'after' => 240])
    . signatures();
docx_ecrire($dest . '/facture.docx', $corps);

// ---------- Feuille 2 : RÉCAPITULATIF CNAMGS
$wr = [1200, 2600, 2400, 3438];
$corps = entete(true)
    . fonds()
    . para('', ['after' => 60])
    . para('RÉCAPITULATIF DES FACTURES N° {{num}}', ['b' => true, 'sz' => 24, 'after' => 20])
    . para('CLIENT / CNAMGS', ['b' => true, 'sz' => 20, 'after' => 100])
    . natures('Médicaments')
    . para('', ['after' => 60])
    . tbl(
        row([
            cell('Nbres de feuilles', $wr[0], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Facture N°', $wr[1], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Date Émission', $wr[2], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('Remboursement CNAMGS', $wr[3], ['b' => true, 'align' => 'center', 'fill' => 'EAF1EE', 'sz' => 16]),
        ])
        . row([
            cell('{{#factures}}{{n}}', $wr[0], ['align' => 'center']),
            cell('{{num}}', $wr[1], ['align' => 'center']),
            cell('{{date}}', $wr[2], ['align' => 'center']),
            cell('{{remb}}{{/factures}}', $wr[3], ['align' => 'right']),
        ])
        . row([
            cell('TOTAL', $wr[0] + $wr[1] + $wr[2], ['b' => true, 'span' => 3, 'fill' => 'EAF1EE']),
            cell('{{total}}', $wr[3], ['b' => true, 'align' => 'right', 'fill' => 'EAF1EE']),
        ]),
        $wr
    )
    . para('Remboursement CNAMGS : {{total}} F', ['align' => 'right', 'b' => true, 'sz' => 20, 'before' => 120])
    . para('Arrêté la présente facture à la somme de {{somme_lettres}} FCFA', ['b' => true, 'sz' => 20, 'before' => 120])
    . para('Fait à Lambaréné le {{mois_annee_lettres}}', ['b' => true, 'sz' => 20, 'after' => 240])
    . signatures();
docx_ecrire($dest . '/recapitulatif.docx', $corps);

// ---------- Feuille 3 : LISTING DE LA PRODUCTION ET SOLDE DE LA DETTE
$wd = [1450, 1080, 1080, 1080, 1080, 1080, 1080, 854, 854];
// En-tête du modèle d'origine : logo de la République Gabonaise (drapeau + sceau) centré,
// puis colonne de gauche (secrétariat / direction / CTA) et colonne de droite (objet du listing).
$logo = __DIR__ . '/logo_gabon.png';
$corps = logo(720000, 485623)
    . tbl(
        row([
            cellp([
                ['SECRÉTARIAT GÉNÉRAL', ['b' => true, 'sz' => 18, 'after' => 0]],
                ['DIRECTION RÉGIONALE DE SANTÉ CENTRE', ['b' => true, 'sz' => 18, 'after' => 0]],
                ['CENTRE DE TRAITEMENT AMBULATOIRE', ['b' => true, 'sz' => 18, 'after' => 0]],
            ], 5300),
            cellp([
                ['LISTING DE LA PRODUCTION {{annee}} PAR FONDS ET', ['b' => true, 'sz' => 20, 'after' => 0]],
                ['LE SOLDE DE LA DETTE AU {{date_situation}}', ['b' => true, 'sz' => 20, 'after' => 0]],
            ], 5100),
        ]),
        [5300, 5100],
        ['bordure' => 'FFFFFF']
    )
    . para('N° {{numero}}/MS/SG/DRSC/CTAL', ['sz' => 18, 'after' => 160])
    . tbl(
        row([
            cell('DATES', $wd[0], ['b' => true, 'align' => 'center', 'vmerge' => 'restart', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('ETAT DE LA DETTE CNAMGS', 1080 * 6, ['b' => true, 'align' => 'center', 'span' => 6, 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('TOTAL DEPOSE', $wd[7], ['b' => true, 'align' => 'center', 'vmerge' => 'restart', 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('TOTAL PAYE', $wd[8], ['b' => true, 'align' => 'center', 'vmerge' => 'restart', 'fill' => 'EAF1EE', 'sz' => 16]),
        ])
        . row([
            cell('', $wd[0], ['vmerge' => 'cont']),
            cell('FONDS 1', 2160, ['b' => true, 'align' => 'center', 'span' => 2, 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('FONDS 2', 2160, ['b' => true, 'align' => 'center', 'span' => 2, 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('FONDS 3', 2160, ['b' => true, 'align' => 'center', 'span' => 2, 'fill' => 'EAF1EE', 'sz' => 16]),
            cell('', $wd[7], ['vmerge' => 'cont']),
            cell('', $wd[8], ['vmerge' => 'cont']),
        ])
        . row([
            cell('', $wd[0], ['vmerge' => 'cont']),
            cell('DEPOSE', $wd[1], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('PAYE', $wd[2], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('DEPOSE', $wd[3], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('PAYE', $wd[4], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('DEPOSE', $wd[5], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('PAYE', $wd[6], ['b' => true, 'align' => 'center', 'sz' => 16]),
            cell('', $wd[7], ['vmerge' => 'cont']),
            cell('', $wd[8], ['vmerge' => 'cont']),
        ])
        . row([
            cell('ANNÉE', $wd[0], ['b' => true]),
            cell('{{init1}}', $wd[1], ['align' => 'right']), cell('', $wd[2]),
            cell('{{init2}}', $wd[3], ['align' => 'right']), cell('', $wd[4]),
            cell('{{init3}}', $wd[5], ['align' => 'right']), cell('', $wd[6]),
            cell('{{init_t}}', $wd[7], ['align' => 'right']), cell('', $wd[8]),
        ])
        . row([
            cell('{{#mois}}{{mois}}', $wd[0]),
            cell('{{d1}}', $wd[1], ['align' => 'right']), cell('{{p1}}', $wd[2], ['align' => 'right']),
            cell('{{d2}}', $wd[3], ['align' => 'right']), cell('{{p2}}', $wd[4], ['align' => 'right']),
            cell('{{d3}}', $wd[5], ['align' => 'right']), cell('{{p3}}', $wd[6], ['align' => 'right']),
            cell('{{dt}}', $wd[7], ['align' => 'right']), cell('{{pt}}{{/mois}}', $wd[8], ['align' => 'right']),
        ])
        . row([
            cell('TOTAL 1', $wd[0], ['b' => true, 'fill' => 'EAF1EE']),
            cell('{{t1_f1}}', $wd[1], ['align' => 'right', 'b' => true]), cell('{{paye_f1}}', $wd[2], ['align' => 'right']),
            cell('{{t1_f2}}', $wd[3], ['align' => 'right', 'b' => true]), cell('{{paye_f2}}', $wd[4], ['align' => 'right']),
            cell('{{t1_f3}}', $wd[5], ['align' => 'right', 'b' => true]), cell('{{paye_f3}}', $wd[6], ['align' => 'right']),
            cell('{{total_depots}}', $wd[7], ['align' => 'right', 'b' => true]), cell('{{total_paiements}}', $wd[8], ['align' => 'right']),
        ])
        . row([
            cell('TOTAL 2', $wd[0], ['b' => true, 'fill' => 'EAF1EE']),
            cell('{{t2_f1}}', $wd[1], ['align' => 'right', 'b' => true]), cell('{{paye_f1}}', $wd[2], ['align' => 'right']),
            cell('{{t2_f2}}', $wd[3], ['align' => 'right', 'b' => true]), cell('{{paye_f2}}', $wd[4], ['align' => 'right']),
            cell('{{t2_f3}}', $wd[5], ['align' => 'right', 'b' => true]), cell('{{paye_f3}}', $wd[6], ['align' => 'right']),
            cell('{{total_t2}}', $wd[7], ['align' => 'right', 'b' => true]), cell('{{total_paiements}}', $wd[8], ['align' => 'right']),
        ])
        . row([
            cell('DETTE', $wd[0], ['b' => true, 'fill' => 'EAF1EE']),
            cell('{{dette_f1}}', 2160, ['span' => 2, 'align' => 'right', 'b' => true]),
            cell('{{dette_f2}}', 2160, ['span' => 2, 'align' => 'right', 'b' => true]),
            cell('{{dette_f3}}', 2160, ['span' => 2, 'align' => 'right', 'b' => true]),
            cell('{{dette_totale}}', 1708, ['span' => 2, 'align' => 'right', 'b' => true]),
        ])
        . row([
            cell('TOTAL GENERAL IMPAYÉ au {{date_situation}}', 7930, ['span' => 7, 'b' => true, 'fill' => 'EAF1EE']),
            cell('{{dette_totale}}', 1708, ['span' => 2, 'b' => true, 'align' => 'right', 'fill' => 'EAF1EE']),
        ]),
        $wd
    )
    . para('Fait à Lambaréné, le {{date_lettres}}', ['sz' => 20, 'before' => 240])
    . para('Le Médecin Chef', ['align' => 'right', 'b' => true, 'sz' => 20, 'before' => 400]);
docx_ecrire($dest . '/dette.docx', $corps, is_file($logo) ? $logo : null);

echo "Modèles écrits :\n";
foreach (['facture', 'recapitulatif', 'dette'] as $m) {
    $p = $dest . '/' . $m . '.docx';
    echo ' - ' . $p . ' (' . filesize($p) . " octets)\n";
}
