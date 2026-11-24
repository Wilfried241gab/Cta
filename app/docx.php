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
        'journal_depenses', 'synthese_caisse', 'suivi_ptme'];
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
            // {{n}} numérote la ligne, sauf si l'élément fournit lui-même un « n » (ex. nombre de patients de la synthèse).
            foreach ($items as $i => $it) $out .= docx_remplacer($modele, array_merge(['n' => (string)($i + 1)], $it));
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
                foreach ($items as $k => $it) $out .= docx_remplacer($bloc, array_merge(['n' => (string)($k + 1)], $it));
                $from = $rows[0][$debut][1];
                $to = $rows[0][$i][1] + strlen($rows[0][$i][0]);
                $xml = substr($xml, 0, $from) . $out . substr($xml, $to);
                break;
            }
        }
    }
    return $xml;
}

// Lecture d'un .docx (archive ZIP) en PHP pur : ne dépend ni de l'extension « zip » ni du dossier temporaire.
// Rend [nom => contenu], ou null si l'archive est illisible.
function docx_zip_lire($chemin) {
    $d = @file_get_contents($chemin);
    if ($d === false || strlen($d) < 22) return null;
    $fin = strrpos($d, "PK\x05\x06");
    if ($fin === false) return null;
    $e = unpack('vdisque/vdebut/vn1/vn/Vtaille/Vpos', substr($d, $fin + 4, 18));
    $pos = $e['pos'];
    $out = [];
    for ($i = 0; $i < $e['n']; $i++) {
        if (substr($d, $pos, 4) !== "PK\x01\x02") return null;
        $h = unpack('vvm/vvn/vflags/vmeth/vheure/vdate/Vcrc/Vcsize/Vusize/vnl/vel/vcl/vdk/via/Vea/Vlpos', substr($d, $pos + 4, 42));
        $nom = substr($d, $pos + 46, $h['nl']);
        $pos += 46 + $h['nl'] + $h['el'] + $h['cl'];
        if (substr($nom, -1) === '/') continue;
        if (substr($d, $h['lpos'], 4) !== "PK\x03\x04") return null;
        $l = unpack('vnl/vel', substr($d, $h['lpos'] + 26, 4));
        $brut = substr($d, $h['lpos'] + 30 + $l['nl'] + $l['el'], $h['csize']);
        if ($h['meth'] === 0) $contenu = $brut;
        elseif ($h['meth'] === 8 && function_exists('gzinflate')) $contenu = @gzinflate($brut);
        else return null;
        if ($contenu === false) return null;
        $out[$nom] = $contenu;
    }
    return $out ?: null;
}

// Écriture d'un .docx en PHP pur (compressé si zlib est disponible, sinon stocké tel quel).
function docx_zip_ecrire(array $fichiers) {
    $corps = ''; $central = ''; $n = 0;
    foreach ($fichiers as $nom => $contenu) {
        $nom = (string)$nom; $contenu = (string)$contenu;
        $crc = crc32($contenu);
        $usize = strlen($contenu);
        if (function_exists('gzdeflate')) { $comp = gzdeflate($contenu, 6); $meth = 8; }
        else { $comp = $contenu; $meth = 0; }
        if ($comp === false) { $comp = $contenu; $meth = 0; }
        $csize = strlen($comp);
        $off = strlen($corps);
        $corps .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, $meth, 0, 0x21, $crc, $csize, $usize, strlen($nom), 0) . $nom . $comp;
        $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, $meth, 0, 0x21, $crc, $csize, $usize, strlen($nom), 0, 0, 0, 0, 0, $off) . $nom;
        $n++;
    }
    return $corps . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($central), strlen($corps), 0);
}

// Rend le document rempli (chaîne binaire), ou null en cas d'échec.
// La raison précise de l'échec est placée dans $GLOBALS['docx_erreur'].
function docx_remplir($modele, array $champs, array $boucles) {
    $GLOBALS['docx_erreur'] = '';
    $src = docx_modele($modele);
    if (!$src) { $GLOBALS['docx_erreur'] = 'fichier modeles/' . basename($modele) . '.docx introuvable ou vide (dossier cherché : ' . __DIR__ . DIRECTORY_SEPARATOR . 'modeles)'; return null; }
    $fichiers = docx_zip_lire($src);
    if (!$fichiers) { $GLOBALS['docx_erreur'] = 'le fichier ' . basename($src) . ' n\'est pas un document Word valide (rouvrez-le dans Word et enregistrez-le en .docx)'; return null; }
    foreach ($fichiers as $part => $xml) {
        if ($part === 'word/document.xml' || preg_match('#^word/(header|footer)\d*\.xml$#', $part)) {
            if ($xml === '') continue;
            $fichiers[$part] = docx_remplacer(docx_boucles($xml, $boucles), $champs);
        }
    }
    return docx_zip_ecrire($fichiers);
}
