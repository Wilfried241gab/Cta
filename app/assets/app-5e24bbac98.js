
(function(){
  var LOGO='<svg viewBox="0 0 48 48" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M24 7v34M7 24h34"/><circle cx="24" cy="24" r="17" stroke-width="2" opacity=".55"/></svg>';
  var IC={
    caisse:'<path d="M3 7h18v10H3z"/><circle cx="12" cy="12" r="2.6"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/>',
    rh:'<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.6 2.7-6 6-6s6 2.4 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.2c3 .1 5 2 5 5.3"/>',
    pharma:'<path d="M10.5 3.5l10 10a4.2 4.2 0 01-6 6l-10-10a4.2 4.2 0 016-6z"/><path d="M8.5 8.5l7 7"/>',
    ptme:'<path d="M12 20s-7-4.4-7-10a4 4 0 017-2.4A4 4 0 0119 10c0 5.6-7 10-7 10z"/>',
    base:'<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
    audit:'<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
    backup:'<path d="M7 18a4.5 4.5 0 01-.6-8.9A6 6 0 0118 10a4 4 0 01-.5 8z"/><path d="M12 11v6m-3-3l3 3 3-3"/>',
    help:'<path d="M4 5.5A2.5 2.5 0 016.5 3H20v16H6.5A2.5 2.5 0 004 21.5z"/><path d="M4 5.5v16M9 8h7"/>',
    params:'<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2.2"/><circle cx="8" cy="17" r="2.2"/>'
  };
  function svg(p,sz){return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'+p+'</svg>'}
  function ready(f){document.readyState==='loading'?document.addEventListener('DOMContentLoaded',f):f()}
  ready(function(){
    var home=document.getElementById('home');
    if(home&&!home.querySelector('.hero')){
      var demo=home.querySelector('.demo'),h1=home.querySelector('h1'),p=h1&&h1.nextElementSibling,who=home.querySelector('.who');
      if(demo&&h1&&p&&who){
        var hero=document.createElement('div');hero.className='hero';
        var logo=document.createElement('div');logo.className='logo';logo.innerHTML=LOGO;
        var t=document.createElement('div');t.className='hero-t';
        var hi=document.createElement('div');hi.className='hi';hi.id='heroHi';
        function hello(){var d=new Date(),h=d.getHours(),g=h<12?'Bonjour':h<18?'Bon après-midi':'Bonsoir';var nm=(document.getElementById('whoName')||{}).textContent||'';var ds=d.toLocaleDateString('fr-FR',{weekday:'long',day:'numeric',month:'long',year:'numeric'});hi.textContent=g+(nm?', '+nm:'')+' · '+ds}
        hello();setInterval(hello,30000);
        h1.removeAttribute('style');
        t.appendChild(demo);t.appendChild(hi);t.appendChild(h1);t.appendChild(p);
        hero.appendChild(logo);hero.appendChild(t);hero.appendChild(who);
        home.insertBefore(hero,home.firstChild);
        // avatar + ligne utilisateur
        var nameEl=who.querySelector('#whoName'),ru=document.createElement('div');ru.className='row-u';
        var av=document.createElement('div');av.className='avatar';av.id='whoAv';
        function ini(){var n=(nameEl&&nameEl.textContent||'?').trim().split(/\s+/);av.textContent=((n[0]||'?')[0]+(n[1]?n[1][0]:'')).toUpperCase()}
        ini();
        if(nameEl){new MutationObserver(function(){ini();hello()}).observe(nameEl,{childList:true,characterData:true,subtree:true})}
        var first=who.firstElementChild;ru.appendChild(av);
        while(who.firstChild&&who.firstChild.id!=='myAcc'){ru.appendChild(who.firstChild)}
        who.insertBefore(ru,who.firstChild);
        var btns=document.createElement('div');btns.className='row-u';
        ['myAcc','logout'].forEach(function(id){var b=document.getElementById(id);if(b)btns.appendChild(b)});
        who.appendChild(btns);
      }
      home.querySelectorAll('.mod').forEach(function(m){
        if(m.querySelector('.ico'))return;var k=m.dataset.m;var i=document.createElement('i');i.className='ico';i.innerHTML=svg(IC[k]||IC.help);m.insertBefore(i,m.firstChild);
      });
    }
    var lg=document.getElementById('login');
    if(lg&&!lg.querySelector('.brand')){
      var b=document.createElement('div');b.className='brand';
      var chk='<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.2 4.2L19 7"/></svg>';
      b.innerHTML='<div class="logo">'+LOGO+'</div><h2>Centre de Traitement Ambulatoire de Lambaréné</h2><p>Caisse, pharmacie, RH et CNAMGS, suivi PTME : une seule application, des données partagées et tracées.</p><ul><li><i>'+chk+'</i>Chaque action est inscrite au journal d’audit</li><li><i>'+chk+'</i>Un compte par poste, accès limité à votre mission</li><li><i>'+chk+'</i>Sauvegardes automatiques et points de restauration</li></ul>';
      lg.insertBefore(b,lg.firstChild);
    }
  });
})();
