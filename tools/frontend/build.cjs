const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const target = path.join(root, 'public/assets/vendor');
const copy = (source, dest) => {
    const full = path.join(target, dest);
    fs.mkdirSync(path.dirname(full), {recursive:true});
    fs.copyFileSync(path.join(__dirname, 'node_modules', source), full);
};
copy('lucide/dist/umd/lucide.min.js', 'lucide.min.js');
copy('bootstrap/dist/js/bootstrap.bundle.min.js', 'bootstrap.bundle.min.js');
copy('chart.js/dist/chart.umd.js', 'chart.umd.js');
copy('@fortawesome/fontawesome-free/css/all.min.css', 'fontawesome/css/all.min.css');
for (const name of fs.readdirSync(path.join(__dirname, 'node_modules/@fortawesome/fontawesome-free/webfonts'))) {
    copy('@fortawesome/fontawesome-free/webfonts/' + name, 'fontawesome/webfonts/' + name);
}
for (const [pkg,file] of [['lucide','LICENSE'],['bootstrap','LICENSE'],['chart.js','LICENSE.md'],['tailwindcss','LICENSE'],['@fortawesome/fontawesome-free','LICENSE.txt']]) {
    copy(pkg+'/'+file, 'licenses/'+pkg.replace('@','').replaceAll('/','-')+'.txt');
}
const packages = require('./package.json').devDependencies;
const hashes = {};
for (const name of ['g3-utilities.css','lucide.min.js','bootstrap.bundle.min.js','chart.umd.js','fontawesome/css/all.min.css']) {
    hashes[name] = crypto.createHash('sha256').update(fs.readFileSync(path.join(target,name))).digest('hex');
}
fs.writeFileSync(path.join(target,'manifest.json'), JSON.stringify({packages,sha256:hashes}, null, 2)+'\n');
console.log('Assets locales construidos; versiones y SHA-256 en public/assets/vendor/manifest.json.');
