const fs=require('fs');
const src=fs.readFileSync('cta/assets/app-7cdb3cb089.js','utf8');
const line=src.split('\n').find(l=>l.trim().startsWith('const M={'));
const obj=new Function('return '+line.replace(/^const M=/,'').replace(/;\s*$/,''))();
for(const [k,v] of Object.entries(obj)){ const b=Buffer.from(v,'base64'); fs.writeFileSync(`travail/${k}.html`, b); console.log(k, b.length); }
