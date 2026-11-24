/* CTA — stockage serveur : remplace localStorage par la base MySQL (via api.php).
   Chargé en tout premier dans la page principale ; les modules reçoivent CTA_LS par window.parent. */
(function(){
  const KEYRE=/^cta\.(shell|caisse|pharma|rh|ptme|audit)\.v1$/;
  const real=window.localStorage,API='api.php',H={'X-CTA':'1'};
  const D={},V={},R={},pending=new Set(),retries={};
  let inflight=false,timer=null,DEAD=false,lastOk=0,lastErr='',hbFail=0;
  const unsent=new Set(),seq={};let OBK='cta.outbox.v1.x',obT=null;
  function obRead(){try{return JSON.parse(real.getItem(OBK)||'{}')||{}}catch(e){return {}}}
  function obNow(){clearTimeout(obT);const o={};try{unsent.forEach(k=>{o[k]={v:D[k],base:V[k]||0,t:Date.now()}});if(Object.keys(o).length)real.setItem(OBK,JSON.stringify(o));else real.removeItem(OBK)}catch(e){lastErr='Copie locale impossible (espace du navigateur insuffisant)'}}
  const obSave=()=>{clearTimeout(obT);obT=setTimeout(obNow,250)};
  function banner(){
    let b=document.getElementById('ctaNet');
    const down=hbFail>=2||/injoignable/i.test(lastErr),n=unsent.size;
    if(!down){if(b)b.hidden=true;document.body.style.paddingTop='';return}
    if(!b){b=document.createElement('div');b.id='ctaNet';b.setAttribute('role','alert');b.setAttribute('style','position:fixed;left:0;right:0;top:0;z-index:99998;background:#b42318;color:#fff;font:600 15px/1.4 system-ui,Segoe UI,Arial,sans-serif;padding:10px 16px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.3)');(document.body||document.documentElement).appendChild(b)}
    b.hidden=false;setTimeout(()=>{try{document.body.style.paddingTop=(b.offsetHeight+6)+'px'}catch(e){}},0);b.textContent='Serveur injoignable : ne fermez pas cette page. '+(n?'Vos saisies non envoyées sont conservées sur ce poste et partiront automatiquement dès le retour du serveur.':'Nouvelle tentative en cours…');
  }
  const hooks=window.CTA_HOOKS={merge:{},onRemote:null,onConflict:null,onState:null,onAuth:null};
  const who=()=>'';
  const state=()=>{try{banner()}catch(e){}try{hooks.onState&&hooks.onState({ok:!lastErr,last:lastOk,err:lastErr,pending:pending.size+(inflight?1:0)})}catch(e){}};
  const routed=k=>KEYRE.test(k);
  function dead(msg){
    DEAD=true;
    const d=document.createElement('div');
    d.setAttribute('style','position:fixed;inset:0;z-index:99999;background:#0b3d2b;color:#fff;display:flex;align-items:center;justify-content:center;font:16px/1.5 system-ui,Segoe UI,Arial,sans-serif;padding:24px;text-align:center');
    d.innerHTML='<div style="max-width:560px"><h1 style="margin:0 0 10px;font-size:1.5rem">Serveur de données injoignable</h1><p style="opacity:.9">'+String(msg).replace(/</g,'&lt;')+'</p><p style="opacity:.8;font-size:.92rem">Vérifiez que Apache et MySQL sont démarrés dans XAMPP, que la base <b>cta</b> a été importée (base_cta.sql) et la page <b>etat.php</b> de l’application.</p><button id="ctaRetry" style="margin-top:12px;padding:12px 24px;border:0;border-radius:10px;font:700 1rem inherit;background:#f5c542;color:#1b1b1b;cursor:pointer">Réessayer</button></div>';
    (document.body||document.documentElement).appendChild(d);
    d.querySelector('#ctaRetry').onclick=()=>location.reload();
  }
  // ---------- chargement initial : les données arrivent avec la page (déjà filtrées selon le rôle), sans requête bloquante
  try{
    const el=document.getElementById('ctaBoot');
    if(!el)throw new Error('Données de démarrage absentes : ouvrez l’application par son adresse habituelle (index.php).');
    const r=JSON.parse(el.textContent);
    window.CTA_USER=r.user;const ALLOW=new Set(r.droits||[]);window.CTA_ALLOW=ALLOW;
    Object.keys(r.keys).forEach(k=>{D[k]=r.keys[k].v;V[k]=r.keys[k].ver});
    OBK='cta.outbox.v1.'+((r.user&&(r.user.id||r.user.nom))||'x');
    // récupération des saisies qui n'avaient pas pu être envoyées (serveur injoignable, page fermée, redémarrage)
    const ob=obRead();let rec=0;
    Object.keys(ob).forEach(k=>{const e=ob[k];if(ALLOW.has(k)&&KEYRE.test(k)&&e&&typeof e.v==='string'&&e.v!==D[k]){D[k]=e.v;V[k]=e.base||V[k]||0;unsent.add(k);pending.add(k);seq[k]=1;rec++}});
    if(rec){obNow();window.CTA_RECOVERED=rec;setTimeout(schedule,2500)}else{try{real.removeItem(OBK)}catch(e){}}
    lastOk=Date.now();
  }catch(e){dead('Impossible de lire les données : '+(e&&e.message||e))}
  // ---------- écriture différée vers le serveur
  const sleep=ms=>new Promise(r=>setTimeout(r,ms));
  function schedule(){clearTimeout(timer);timer=setTimeout(flush,150);state()}
  async function flush(){
    if(inflight||DEAD)return;inflight=true;
    try{
      while(pending.size){
        const k=pending.values().next().value;pending.delete(k);
        let r;const sq=seq[k]||0;
        try{
          const res=await fetch(API+'?a=save',{method:'POST',headers:Object.assign({'Content-Type':'text/plain'},H),body:JSON.stringify({k,v:D[k],base:V[k]||0,poste:who()})});
          r=await res.json();
        }catch(e){pending.add(k);lastErr='Serveur injoignable : modifications en attente';state();await sleep(3000);continue}
        if(r.auth){pending.add(k);lastErr='Session expirée : reconnectez-vous pour enregistrer';try{hooks.onAuth&&hooks.onAuth()}catch(e){}state();await sleep(3000);continue}
        if(r.ok){V[k]=r.ver;retries[k]=0;lastErr='';hbFail=0;lastOk=Date.now();if((seq[k]||0)===sq){unsent.delete(k);obNow()}
          if(r.remap&&r.v!=null){R[k]={v:r.v,ver:r.ver};try{hooks.onRemote&&hooks.onRemote(k,adopt)}catch(e){}}}
        else if(r.conflict){
          retries[k]=(retries[k]||0)+1;
          const mg=hooks.merge[k];
          if(mg&&retries[k]<=3){try{D[k]=mg(D[k],r.v);V[k]=r.ver;pending.add(k);continue}catch(e){}}
          try{real.setItem('cta.outbox.lost.'+k+'.'+Date.now(),D[k])}catch(e){}
          unsent.delete(k);obNow();
          D[k]=r.v;V[k]=r.ver;delete R[k];retries[k]=0;
          try{hooks.onConflict&&hooks.onConflict(k)}catch(e){}
        }else{lastErr=r.err||'Erreur serveur';try{window.STORAGE_FAIL&&window.STORAGE_FAIL(new Error(lastErr))}catch(e){}}
        state();
      }
    }finally{inflight=false;state()}
  }
  // ---------- surveillance des modifications faites depuis d'autres postes
  async function poll(){
    if(DEAD||inflight||pending.size)return;
    try{
      const r=await (await fetch(API+'?a=versions&t='+Date.now(),{headers:H})).json();
      if(!r.ok){if(r.auth){lastErr='Session expirée : reconnectez-vous';try{hooks.onAuth&&hooks.onAuth()}catch(e){}state()}return}lastErr='';lastOk=Date.now();
      for(const k of Object.keys(r.versions)){
        if((V[k]||0)>=r.versions[k]||(R[k]&&R[k].ver>=r.versions[k]))continue;
        const g=await (await fetch(API+'?a=get&k='+encodeURIComponent(k)+'&t='+Date.now(),{headers:H})).json();
        if(!g.ok||g.v==null)continue;
        R[k]={v:g.v,ver:g.ver};
        try{hooks.onRemote&&hooks.onRemote(k,adopt)}catch(e){}
      }
      state();
    }catch(e){lastErr='Serveur injoignable';state()}
  }
  function adopt(k){if(R[k]){D[k]=R[k].v;V[k]=R[k].ver;delete R[k]}}
  setInterval(poll,7000);
  // test de santé : le serveur répond-il vraiment ? (Apache allumé ne suffit pas)
  async function hb(){try{const c=new AbortController(),t=setTimeout(()=>c.abort(),4000);const r=await fetch(API+'?a=ping&t='+Date.now(),{headers:H,signal:c.signal,cache:'no-store'});clearTimeout(t);if(!r.ok)throw new Error('http');const j=await r.json();if(!j||j.ok===false)throw new Error('ko');hbFail=0}catch(e){hbFail++}state()}
  setInterval(hb,10000);
  // fermeture / rechargement : on enregistre d'abord tout ce qui est en mémoire, puis on envoie (keepalive) ce qui tient dans une requête de fermeture
  function lastChance(){
    try{window.flushAll&&window.flushAll()}catch(e){}
    obNow();
    for(const k of Array.from(pending)){
      const body=JSON.stringify({k,v:D[k],base:V[k]||0,poste:who()});
      if(body.length<60000){try{fetch(API+'?a=save',{method:'POST',keepalive:true,headers:Object.assign({'Content-Type':'text/plain'},H),body});pending.delete(k)}catch(e){}}
    }
  }
  addEventListener('beforeunload',e=>{lastChance();if(pending.size||inflight){e.preventDefault();e.returnValue='';}});
  addEventListener('pagehide',lastChance);
  document.addEventListener('visibilitychange',()=>{if(document.hidden){try{window.flushAll&&window.flushAll()}catch(e){}schedule()}});
  // ---------- faux localStorage
  const own=()=>Object.keys(D).concat(Object.keys(real).filter(k=>!routed(k)));
  const api={
    getItem:k=>{k=String(k);return routed(k)?(Object.prototype.hasOwnProperty.call(D,k)?D[k]:null):real.getItem(k)},
    setItem:(k,v)=>{k=String(k);v=String(v);if(!routed(k))return real.setItem(k,v);if(DEAD)throw new Error('Serveur injoignable');if(D[k]===v)return;D[k]=v;if(k==='cta.audit.v1'||!(window.CTA_ALLOW&&window.CTA_ALLOW.has(k)))return;pending.add(k);seq[k]=(seq[k]||0)+1;unsent.add(k);obSave();schedule()},
    removeItem:k=>{k=String(k);if(!routed(k))return real.removeItem(k);delete D[k]},
    clear:()=>{Object.keys(D).forEach(k=>delete D[k])},
    key:i=>own()[i]===undefined?null:own()[i]
  };
  window.CTA_LS=new Proxy({},{
    get(t,p){if(p==='length')return own().length;if(typeof p==='string'&&p in api)return api[p];if(typeof p==='string')return api.getItem(p)===null?undefined:api.getItem(p)},
    set(t,p,v){api.setItem(p,v);return true},
    deleteProperty(t,p){api.removeItem(p);return true},
    has(t,p){return typeof p==='string'&&(p in api||own().includes(p))},
    ownKeys(){return own()},
    getOwnPropertyDescriptor(t,p){return typeof p==='string'&&own().includes(p)?{value:api.getItem(p),writable:true,enumerable:true,configurable:true}:undefined}
  });
  try{Object.defineProperty(window,'localStorage',{value:window.CTA_LS,configurable:true})}catch(e){}
  window.CTA_STORE={flush,pending:()=>pending.size,adopt,version:k=>V[k]||0,alive:()=>!DEAD};
})();
