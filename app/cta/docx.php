<?php
// CTA Lambaréné — remplissage des modèles Word (.docx) de la facture, du récapitulatif et de l'état de la dette.
// Un .docx est une archive ZIP : on remplace les balises dans le XML, puis on rend l'archive complète.
//   {{champ}}                          une valeur
//   {{#lignes}} ... {{/lignes}}        répète les lignes du tableau (balises dans la même ligne,
//                                      ou seules sur une ligne de début et une ligne de fin)
// Dans une boucle, chaque ligne reçoit ses propres champs et {{n}} (numéro de ligne, à partir de 1).

function docx_modele($nom) {
    $noms = ['facture', 'recapitulatif', 'dette', 'registre_pharma', 'stock_pharma',
        'fiche_mensuelle', 'inventaire_pharma', 'commande_pharma',
        'registre_caisse_pvvih', 'registre_caisse_ext', 'registre_caisse_cert',
        'journal_depenses', 'synthese_caisse'];
    if (!in_array($nom, $noms, true)) return null;
    $p = __DIR__ . '/modeles/' . $nom . '.docx';
    return (is_file($p) && filesize($p) > 0) ? $p : null;
}

function docx_txt($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Texte lisible d'un fragment XML : les morceaux de texte coupés par Word se recollent ici.
function docx_texte($xml) {
    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Remplace les {{...}} d'un fragment, y compris quand Word a coupé une balise en plusieurs runs.
function docx_remplacer($xml, array $valeurs) {
    if (strpos($xml, '{{') === false) return $xml;
    return preg_replace_callback('/<w:p\b[^>]*>.*?<\/w:p>/s', function ($m) use ($valeurs) {
        return docx_paragraphe($m[0], $valeurs);
    }, $xml);
}

function docx_paragraphe($p, array $valeurs) {
    if (strpos($p, '{{') === false) return $p;
    if (!preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $p, $mm, PREG_OFFSET_CAPTURE)) return $p;
    $runs = []; $concat = '';
    foreach ($mm[1] as $cap) {
        $txt = html_entity_decode($cap[0], ENT_QUOTES | ENT_XML1, 'UTF-8');
        $runs[] = ['off' => $cap[1], 'len' => strlen($cap[0]), 'deb' => strlen($concat), 'fin' => strlen($concat) + strlen($txt)];
        $concat .= $txt;
    }
    if (strpos($concat, '{{') === false) return $p;
    if (!preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $concat, $bm, PREG_OFFSET_CAPTURE)) return $p;
    $rempl = []; $coupe = [];
    foreach ($bm[0] as $i => $occ) {
        $cle = $bm[1][$i][0];
        if (!array_key_exists($cle, $valeurs)) continue; // champ inconnu : laissé visible pour être repéré
        $deb = $occ[1]; $fin = $deb + strlen($occ[0]);
        $rempl[$deb] = docx_txt($valeurs[$cle]);
        for ($k = $deb; $k < $fin; $k++) $coupe[$k] = true;
    }
    if (!$rempl) return $p;
    $out = ''; $pos = 0;
    foreach ($runs as $r) {
        $neuf = '';
        for ($k = $r['deb']; $k < $r['fin']; $k++) {
            if (isset($rempl[$k])) $neuf .= $rempl[$k];
            elseif (!isset($coupe[$k])) $neuf .= $concat[$k];
        }
        $out .= substr($p, $pos, $r['off'] - $pos) . $neuf;
        $pos = $r['off'] + $r['len'];
    }
    return $out . substr($p, $pos);
}

// Répète les lignes d'un tableau pour chaque élément des boucles fournies.
function docx_boucles($xml, array $boucles) {
    foreach ($boucles as $nom => $items) {
        $nom = (string)$nom;
        if ($nom === '' || !is_array($items)) continue;
        $items = array_values(array_filter($items, 'is_array'));
        // 1) boucle tenant dans une seule ligne de tableau
        $xml = preg_replace_callback('/<w:tr\b[^>]*>.*?<\/w:tr>/s', function ($m) use ($nom, $items) {
            $t = docx_texte($m[0]);
            if (strpos($t, '{{#' . $nom . '}}') === false || strpos($t, '{{/' . $nom . '}}') === false) return $m[0];
            $modele = docx_remplacer($m[0], ['#' . $nom => '', '/' . $nom => '']);
            $out = '';
            foreach ($items as $i => $it) $out .= docx_remplacer($modele, array_merge($it, ['n' => (string)($i + 1)]));
            return $out;
        }, $xml);
        // 2) boucle de plusieurs lignes : {{#nom}} seule dans une ligne, {{/nom}} seule dans une autre
        if (strpos(docx_texte($xml), '{{#' . $nom . '}}') !== false
            && preg_match_all('/<w:tr\b[^>]*>.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE)) {
            $debut = -1;
            foreach ($rows[0] as $i => $row) {
                $t = docx_texte($row[0]);
                if ($debut < 0) {
                    if (strpos($t, '{{#' . $nom . '}}') !== false) $debut = $i;
                    continue;
                }
                if (strpos($t, '{{/' . $nom . '}}') === false) continue;
                $bloc = '';
                for ($j = $debut + 1; $j < $i; $j++) $bloc .= $rows[0][$j][0];
                $out = '';
                foreach ($items as $k => $it) $out .= docx_remplacer($bloc, array_merge($it, ['n' => (string)($k + 1)]));
                $from = $rows[0][$debut][1];
                $to = $rows[0][$i][1] + strlen($rows[0][$i][0]);
                $xml = substr($xml, 0, $from) . $out . substr($xml, $to);
                break;
            }
        }
    }
    return $xml;
}

// Rend le document rempli (chaîne binaire), ou null si le modèle est absent ou illisible.
function docx_remplir($modele, array $champs, array $boucles) {
    $src = docx_modele($modele);
    if (!$src || !class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'ctadocx');
    if (!$tmp || !@copy($src, $tmp)) return null;
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) { @unlink($tmp); return null; }
    $parts = ['word/document.xml'];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = (string)$zip->getNameIndex($i);
        if (preg_match('#^word/(header|footer)\d*\.xml$#', $n)) $parts[] = $n; // en-têtes et pieds de page
    }
    foreach ($parts as $part) {
        $xml = $zip->getFromName($part);
        if ($xml === false || $xml === '') continue;
        $xml = docx_remplacer(docx_boucles($xml, $boucles), $champs);
        $zip->addFromString($part, $xml);
    }
    $zip->close();
    $bin = @file_get_contents($tmp);
    @unlink($tmp);
    return ($bin === false || $bin === '') ? null : $bin;
}
