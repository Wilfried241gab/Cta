const fs=require('fs'),crypto=require('crypto');
process.chdir(__dirname+'/..');
// Réinjecte le module caisse (travail/caisse.html) dans le paquet JS servi par cta/app.html.
const srcName=(fs.readFileSync('travail/nouveau.txt','utf8').trim().replace(/^assets\//,''));
const srcPath='assets/'+srcName;
const src=fs.readFileSync('cta/'+srcPath,'utf8');
const lines=src.split('\n');
const i=lines.findIndex(l=>l.trim().startsWith('const M={'));
if(i<0)throw new Error('carte des modules introuvable');
const M=new Function('return '+lines[i].replace(/^const M=/,'').replace(/;\s*$/,''))();
if(!('caisse' in M))throw new Error('module caisse absent de la carte');
const avant=Buffer.from(M.caisse,'base64').length;
M.caisse=fs.readFileSync('travail/caisse.html').toString('base64');
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
console.log('nouveau fichier',newPath,'taille',out.length,'caisse avant',avant,'apres',Buffer.from(M.caisse,'base64').length);
console.log('references mises a jour:',n);
fs.writeFileSync('travail/nouveau.txt',newName);
