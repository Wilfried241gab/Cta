<?php
// CTA Lambaréné — point d'entrée. Sans session : page de connexion. Avec session : l'application,
// livrée avec les seules données auxquelles le rôle de la personne donne droit.
require __DIR__ . '/common.php';
cta_entetes_securite();
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

function cta_page_erreur($titre, $msg) {
    http_response_code(503);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CTA Lambaréné</title><body style="font:16px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:#0b3d2b;color:#fff;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px;text-align:center"><div style="max-width:560px"><h1 style="margin:0 0 10px;font-size:1.5rem">' . htmlspecialchars($titre) . '</h1><p style="opacity:.92">' . htmlspecialchars($msg) . '</p><p style="opacity:.8;font-size:.92rem">Vérifiez que Apache et MySQL sont démarrés dans XAMPP et ouvrez <b>etat.php</b> pour le diagnostic.</p><button onclick="location.reload()" style="margin-top:12px;padding:12px 24px;border:0;border-radius:10px;font:700 1rem system-ui;background:#f5c542;color:#1b1b1b;cursor:pointer">Réessayer</button></div></body>';
    exit;
}

if (!cta_acces_autorise()) { http_response_code(403); echo 'Accès refusé pour cette adresse.'; exit; }
$c = cta_config();
if (!empty($c['forcer_https']) && !cta_en_https() && !cta_en_local()) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301); exit;
}

try { $db = cta_db(); $user = cta_utilisateur($db); }
catch (Throwable $e) { cta_log('index : ' . $e->getMessage()); cta_page_erreur('Serveur de données injoignable', 'La base de données ne répond pas. Le détail est dans logs/cta_erreurs.log.'); }

if ($user && !$user['must']) {
    $app = @file_get_contents(__DIR__ . '/app.html');
    if ($app === false) cta_page_erreur('Application introuvable', 'Le fichier app.html est absent du dossier de l’application.');
    try { $keys = cta_charger($db, $user); }
    catch (Throwable $e) { cta_log('index charger : ' . $e->getMessage()); cta_page_erreur('Lecture des données impossible', 'Le détail est dans logs/cta_erreurs.log.'); }
    $boot = ['user' => ['id' => $user['id'], 'nom' => $user['nom'], 'role' => $user['role']], 'keys' => $keys, 'droits' => array_map(fn($d) => 'cta.' . $d . '.v1', array_values(array_diff(CTA_DROITS[$user['role']] ?? [], ['audit']))), 'version' => CTA_VERSION, 'heure' => date('Y-m-d H:i:s')];
    $json = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo str_replace('<!--CTA_BOOT-->', '<script type="application/json" id="ctaBoot">' . $json . '</script>', $app);
    exit;
}

// ---------- page de connexion
$opts = '';
foreach ($db->query("SELECT id, nom, role FROM cta_user WHERE actif = 1 ORDER BY CASE role WHEN 'Médecin-chef' THEN 0 WHEN 'Administrateur' THEN 1 ELSE 2 END, nom")->fetchAll() as $u) {
    $opts .= '<option value="' . (int)$u['id'] . '">' . htmlspecialchars($u['nom']) . ' (' . htmlspecialchars($u['role']) . ')</option>';
}
$forcer = $user && $user['must'] ? 1 : 0;
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connexion – CTA de Lambaréné</title>
<style>
:root{color-scheme:light dark;--bg:#f2f5f2;--surface:#fff;--ink:#1b2a24;--mute:#5b6d64;--line:#d3ddd7;--green:#1d6b4d;--green-ink:#fff;--blue:#2a5d8f;--red:#b3261e}
@media(prefers-color-scheme:dark){:root{--bg:#0f1a15;--surface:#17241e;--ink:#e6efe9;--mute:#9bb0a5;--line:#2b3c34;--green:#4cb98a;--green-ink:#08150f;--blue:#86b5e4;--red:#ff8f85}}
*{box-sizing:border-box}body{margin:0;font:16px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:linear-gradient(rgba(8,30,22,.18),rgba(8,30,22,.30)),url(assets/cta-facade.jpg) center 40%/cover no-repeat fixed,#0b3d2b;color:var(--ink);min-height:100vh}
@media(max-width:700px){body{background-image:linear-gradient(rgba(8,30,22,.18),rgba(8,30,22,.30)),url(assets/cta-facade-s.jpg)}}
.top{background:linear-gradient(180deg,rgba(8,45,32,.94),rgba(11,61,43,.80) 55%,rgba(29,107,77,.35));color:#fff;padding:30px 16px 70px;text-align:center;box-shadow:0 4px 18px rgba(0,0,0,.25)}
.top h1{margin:0;font-size:1.35rem;letter-spacing:.2px}.top p{margin:6px 0 0;opacity:.85;font-size:.92rem}
.card{margin-bottom:16px;background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:22px;max-width:400px;margin:-44px auto 16px;box-shadow:0 12px 34px rgba(0,0,0,.14)}
label{display:block;font-size:.85rem;color:var(--mute);margin:12px 0 4px}label:first-child{margin-top:0}
select,input{width:100%;font:inherit;padding:11px 12px;border:1px solid var(--line);border-radius:9px;background:var(--bg);color:var(--ink)}
button:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid var(--blue);outline-offset:2px}
.btn{width:100%;margin-top:14px;padding:13px;border:0;border-radius:10px;background:var(--green);color:var(--green-ink);font:inherit;font-weight:700;cursor:pointer}
.btn:disabled{opacity:.6;cursor:wait}.err{color:var(--red);font-size:.88rem;min-height:1.3em;margin-top:8px}
.note{max-width:400px;margin:0 auto 24px;padding:8px 14px;color:var(--ink);background:color-mix(in srgb,var(--surface) 88%,transparent);border-radius:10px;font-size:.8rem;text-align:center}
.pw{position:relative}.pw input{padding-right:46px}.eye{position:absolute;right:4px;top:50%;transform:translateY(-50%);border:0;background:none;color:var(--mute);padding:7px 9px;cursor:pointer;line-height:0;border-radius:6px}
</style></head><body>
<section id="login" role="main" aria-label="Connexion">
<div class="top"><h1>Centre de Traitement Ambulatoire de Lambaréné</h1><p id="lgMsg" role="status"><?= $forcer ? 'Premier accès ou mot de passe réinitialisé : choisissez votre mot de passe personnel.' : 'Connexion à l’application de gestion' ?></p></div>
<form id="lgForm" class="card" autocomplete="off">
 <div id="lgIn"<?= $forcer ? ' hidden' : '' ?>>
  <label for="lgUsers">Utilisateur</label><select id="lgUsers"><?= $opts ?></select>
  <label for="lgPw">Mot de passe</label><div class="pw"><input type="password" id="lgPw" autocomplete="current-password"></div>
  <div class="err" id="lgErr" role="alert"></div>
  <button class="btn" type="submit" id="lgGo">Se connecter</button>
 </div>
 <div id="lgCh"<?= $forcer ? '' : ' hidden' ?>>
  <label for="chOld">Mot de passe actuel</label><div class="pw"><input type="password" id="chOld" autocomplete="current-password"></div>
  <label for="chNew">Nouveau mot de passe (8 caractères, lettres et chiffres)</label><div class="pw"><input type="password" id="chNew" autocomplete="new-password"></div>
  <label for="chNew2">Confirmer</label><div class="pw"><input type="password" id="chNew2" autocomplete="new-password"></div>
  <div class="err" id="chErr" role="alert"></div>
  <button class="btn" type="button" id="chGo">Enregistrer et entrer</button>
 </div>
</form>
<p class="note">Les données de santé sont confidentielles. Chaque connexion et chaque action importante sont inscrites au journal d’audit.</p>
</section>
<script>
(function(){
const $=i=>document.getElementById(i),H={'X-CTA':'1','Content-Type':'text/plain'};
const EYE='<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
document.querySelectorAll('.pw').forEach(w=>{const i=w.querySelector('input'),b=document.createElement('button');b.type='button';b.className='eye';b.setAttribute('aria-label','Afficher le mot de passe');b.innerHTML=EYE;b.onclick=()=>{i.type=i.type==='password'?'text':'password';i.focus()};w.appendChild(b)});
async function post(a,body){const r=await fetch('api.php?a='+a,{method:'POST',headers:H,body:JSON.stringify(body)});return r.json()}
function showCh(){$('lgIn').hidden=true;$('lgCh').hidden=false;$('lgMsg').textContent='Premier accès ou mot de passe réinitialisé : choisissez votre mot de passe personnel.';$('chOld').focus()}
$('lgForm').onsubmit=async ev=>{ev.preventDefault();if($('lgIn').hidden)return;const b=$('lgGo');b.disabled=true;$('lgErr').textContent='';
 try{const r=await post('login',{id:+$('lgUsers').value,pw:$('lgPw').value});
  if(r.ok){if(r.must){$('chOld').value=$('lgPw').value;showCh()}else location.reload()}
  else{$('lgErr').textContent=r.err||'Connexion impossible.';$('lgPw').value=''}
 }catch(e){$('lgErr').textContent='Serveur injoignable. Vérifiez que Apache et MySQL sont démarrés.'}
 b.disabled=false};
$('chGo').onclick=async()=>{const n=$('chNew').value;if(n!==$('chNew2').value){$('chErr').textContent='Les deux saisies ne correspondent pas.';return}
 try{const r=await post('chpw',{old:$('chOld').value,new:n});if(r.ok)location.reload();else $('chErr').textContent=r.err||'Échec.'}catch(e){$('chErr').textContent='Serveur injoignable.'}};
setTimeout(()=>{(<?= $forcer ?>?$('chOld'):$('lgPw')).focus()},50);
})();
</script></body></html>
