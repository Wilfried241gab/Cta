window.XLS_MOD='Shell';
/* Export Excel (.xlsx) sans bibliothèque externe : fonctionne hors ligne */
(function(){
if(window.XLS)return;
const enc=new TextEncoder();
const CRC=(()=>{const t=new Uint32Array(256);for(let n=0;n<256;n++){let c=n;for(let k=0;k<8;k++)c=c&1?0xEDB88320^(c>>>1):c>>>1;t[n]=c>>>0}return t})();
const crc32=b=>{let c=0xFFFFFFFF;for(let i=0;i<b.length;i++)c=CRC[(c^b[i])&255]^(c>>>8);return(c^0xFFFFFFFF)>>>0};
const esc=s=>String(s).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g,'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const colName=i=>{let s='';i++;while(i>0){const m=(i-1)%26;s=String.fromCharCode(65+m)+s;i=Math.floor((i-1)/26)}return s};
function zip(files){
  const parts=[],cen=[];let off=0;
  const u16=v=>[v&255,(v>>8)&255],u32=v=>[v&255,(v>>8)&255,(v>>16)&255,(v>>>24)&255];
  files.forEach(f=>{
    const name=enc.encode(f.name),data=enc.encode(f.data),crc=crc32(data);
    const lh=new Uint8Array([0x50,0x4b,3,4,20,0,0,8,0,0,0,0,0x21,0,...u32(crc),...u32(data.length),...u32(data.length),...u16(name.length),0,0]);
    parts.push(lh,name,data);
    cen.push(new Uint8Array([0x50,0x4b,1,2,20,0,20,0,0,8,0,0,0,0,0x21,0,...u32(crc),...u32(data.length),...u32(data.length),...u16(name.length),0,0,0,0,0,0,0,0,0,0,0,0,...u32(off)]),name);
    off+=lh.length+name.length+data.length;
  });
  const cl=cen.reduce((s,a)=>s+a.length,0);
  const end=new Uint8Array([0x50,0x4b,5,6,0,0,0,0,...u16(files.length),...u16(files.length),...u32(cl),...u32(off),0,0]);
  return new Blob([...parts,...cen,end],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
}
const safeSheet=(n,used)=>{let s=String(n||'Feuille').replace(/[\[\]:*?\/\\]/g,' ').replace(/\s+/g,' ').trim().slice(0,28)||'Feuille';let r=s,i=2;while(used.has(r.toLowerCase()))r=s.slice(0,26)+' '+i++;used.add(r.toLowerCase());return r};
function sheetXml(sh){
  const bold=new Set(sh.bold||[]);let maxc=0;
  const rows=sh.rows.map((row,ri)=>{
    maxc=Math.max(maxc,row.length);
    const cells=row.map((v,ci)=>{
      if(v===''||v==null)return'';
      const r=colName(ci)+(ri+1),st=bold.has(ri)?' s="1"':'';
      if(typeof v==='number'&&isFinite(v))return`<c r="${r}"${st}><v>${v}</v></c>`;
      return`<c r="${r}"${st} t="inlineStr"><is><t xml:space="preserve">${esc(v)}</t></is></c>`;
    }).join('');
    return`<row r="${ri+1}">${cells}</row>`;
  }).join('');
  const w=[];for(let c=0;c<maxc;c++){let m=8;sh.rows.forEach(r=>{const v=r[c];if(v!=null&&v!=='')m=Math.max(m,Math.min(60,String(v).length+2))});w.push(`<col min="${c+1}" max="${c+1}" width="${m}" customWidth="1"/>`)}
  return`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>${w.length?'<cols>'+w.join('')+'</cols>':''}<sheetData>${rows}</sheetData></worksheet>`;
}
function build(sheets){
  const used=new Set(),S=sheets.map(s=>({name:safeSheet(s.name,used),rows:s.rows&&s.rows.length?s.rows:[['(aucune donnée)']],bold:s.bold}));
  const files=[
   {name:'[Content_Types].xml',data:`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>${S.map((_,i)=>`<Override PartName="/xl/worksheets/sheet${i+1}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>`).join('')}</Types>`},
   {name:'_rels/.rels',data:'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'},
   {name:'xl/workbook.xml',data:`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>${S.map((s,i)=>`<sheet name="${esc(s.name)}" sheetId="${i+1}" r:id="rId${i+1}"/>`).join('')}</sheets></workbook>`},
   {name:'xl/_rels/workbook.xml.rels',data:`<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${S.map((_,i)=>`<Relationship Id="rId${i+1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet${i+1}.xml"/>`).join('')}<Relationship Id="rId${S.length+1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>`},
   {name:'xl/styles.xml',data:'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>'},
   ...S.map((s,i)=>({name:`xl/worksheets/sheet${i+1}.xml`,data:sheetXml(s)}))
  ];
  return zip(files);
}
const NUM=/^-?(0|[1-9]\d{0,2}([\s  ]\d{3})+|[1-9]\d{0,5}|0)(,\d+)?$/;
function cellVal(t){
  t=String(t).replace(/\s+/g,' ').trim();
  const m=t.replace(/\s?(F|FCFA)$/,'');
  if(m!==''&&NUM.test(m)){const n=Number(m.replace(/[\s  ]/g,'').replace(',','.'));if(isFinite(n))return n}
  return t;
}
function fromTable(tb){
  const rows=[],bold=[];
  tb.querySelectorAll('tr').forEach(tr=>{
    if(tr.closest('table')!==tb)return;
    const row=[];let isHead=false;
    tr.querySelectorAll(':scope>th,:scope>td').forEach(c=>{
      if(c.tagName==='TH')isHead=true;
      let v;const inp=c.querySelector('input,select,textarea');
      if(inp){v=inp.type==='checkbox'?(inp.checked?'oui':'non'):inp.tagName==='SELECT'?(inp.options[inp.selectedIndex]||{}).text||'':inp.value}
      else v=c.innerText!==undefined?c.innerText:c.textContent;
      const cs=Math.max(1,+c.getAttribute('colspan')||1);
      row.push(isHead?String(v).replace(/\s+/g,' ').trim():cellVal(v));
      for(let k=1;k<cs;k++)row.push('');
    });
    if(row.some(x=>x!==''))rows.push(row);if(isHead&&row.some(x=>x!==''))bold.push(rows.length-1);
  });
  return{rows,bold};
}
const slug=s=>String(s||'tableau').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^A-Za-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,50)||'tableau';
const stamp=()=>{const d=new Date(),p=n=>String(n).padStart(2,'0');return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())};
function audit(a,d){try{const P=window.parent&&window.parent.AUDIT?window.parent:window;P.AUDIT&&P.AUDIT(window.XLS_MOD||'Application',a,d)}catch(e){}}
function download(name,blob){const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(a.href),2000)}
function save(name,sheets){
  const f=name.replace(/\.xlsx$/i,'')+'.xlsx';
  download(f,build(sheets));audit('Export Excel',f);
  try{(window.toast||(window.parent&&window.parent.toast))&&(window.toast||window.parent.toast)('Fichier Excel téléchargé : '+f)}catch(e){}
  return f;
}
function titleOf(tb){
  const t=tb.getAttribute('data-title');if(t)return t;
  let el=tb.closest('.scroll')||tb;
  for(let hop=0;hop<6&&el;hop++){
    let p=el.previousElementSibling;
    while(p){
      if(/^H[1-4]$/.test(p.tagName)||p.classList.contains('blk-h')){return(p.querySelector('b')&&p.classList.contains('blk-h')?p.querySelector('b').textContent:p.textContent).trim().slice(0,60)}
      const h=p.querySelector&&p.querySelector('h2,h3');if(h)return h.textContent.trim().slice(0,60);
      p=p.previousElementSibling;
    }
    el=el.parentElement;
    if(el&&/^SECTION$|^BODY$/.test(el.tagName)){const h=el.querySelector('h2,h3,.bar b');return h?h.textContent.trim().slice(0,60):'Tableau'}
  }
  return'Tableau';
}
function enhance(){
  document.querySelectorAll('table').forEach(tb=>{
    if(tb.dataset.noxl||tb.closest('#recu')||!tb.querySelector('tr'))return;
    const w=tb.closest('.scroll')||tb;
    const prev=w.previousElementSibling;
    if(prev&&prev.classList&&prev.classList.contains('xlbar'))return;
    if(tb.closest('.xlbar'))return;
    const bar=document.createElement('div');bar.className='xlbar';bar.style.cssText='display:flex;justify-content:flex-end;margin:6px 0 2px';
    const b=document.createElement('button');b.type='button';b.textContent='Exporter ce tableau en Excel';b.title='Télécharger ce tableau au format Excel (.xlsx)';
    b.style.cssText='font:inherit;font-size:.8rem;padding:5px 10px;border:1px solid #9aa5a1;border-radius:8px;background:#fff;color:#14342b;cursor:pointer';
    b.onclick=e=>{e.preventDefault();e.stopPropagation();const t=titleOf(tb),d=fromTable(tb);save(`CTA_${slug(window.XLS_MOD||'app')}_${slug(t)}_${stamp()}`,[{name:t,rows:d.rows,bold:d.bold}])};
    bar.appendChild(b);w.parentNode.insertBefore(bar,w);
  });
}
let tmr;
function start(){
  const st=document.createElement('style');st.textContent='@media print{.xlbar{display:none!important}}';document.head.appendChild(st);
  enhance();
  new MutationObserver(()=>{clearTimeout(tmr);tmr=setTimeout(enhance,250)}).observe(document.body,{childList:true,subtree:true});
}
window.XLS={build,save,fromTable,enhance,cellVal};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
