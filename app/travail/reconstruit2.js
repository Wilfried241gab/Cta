const fs=require('fs'),crypto=require('crypto');
process.chdir(__dirname+'/..');
// srcPath est touijours relatif à cta/ et commence par assets/
const srcName=(fs.readFileSync('travail/nouveau.txt','utf8').trim().replace(/^assets\//,''))||'app-f75d507583.js';
const srcPath='assets/'+srcName;
const src=fs.readFileSync('cta/'+srcPath,'utf8');
const lines=src.split('\n');
const i=lines.findIndex(l=>l.trim().startsWith('const M={'));
if(i<0)throw new Error('carte des modules introuvable');
const M=new Function('return '+lines[i].replace(/^const M=/,'').replace(/;\s*$/,''))();
const avant={};for(const [k,v] of Object.entries(M))avant[k]=Buffer.from(v,'base64').length;
M.rh=fs.readFileSync('travail/rh.html').toString('base64');
lines[i]='const M='+JSON.stringify(M)+';';
const out=lines.join('\n');
const hash=crypto.createHash('sha256').update(out).digest('hex').slice(0,10);
const newName=`app-${hash}.js`, newPath='assets/'+newName;
let html=fs.readFileSync('cta/app.html','utf8');
const n=html.split(srcPath).length-1;
if(n===0){throw new Error('reference '+srcPath+' absente de app.html : rien n\'a ete modifie');}
fs.writeFileSync('cta/'+newPath,out);
html=html.split(srcPath).join(newPath);
fs.writeFileSync('cta/app.html',html);
if(newPath!==srcPath){fs.unlinkSync('cta/'+srcPath);console.log('ancien asset supprime',srcPath);}
console.log('nouveau fichier',newPath,'taille',out.length,'avant rh',avant.rh,'apres rh',Buffer.from(M.rh,'base64').length);
console.log('references mises a jour:',n);
fs.writeFileSync('travail/nouveau.txt',newName);
