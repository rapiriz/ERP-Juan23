"""Empaqueta fuentes locales revisadas; excluye entornos, dumps y runtime. No publica."""
from pathlib import Path
import argparse, hashlib, json, re, zipfile

root = Path(__file__).resolve().parent.parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output', required=True, help='ZIP nuevo fuera del proyecto')
args = parser.parse_args()
target = Path(args.output).resolve()
if target.is_relative_to(root) or target.suffix.lower() != '.zip' or target.exists():
    raise SystemExit('Elegir un ZIP nuevo fuera del proyecto; no se sobrescriben archivos.')
directories = {'app', 'bootstrap', 'config', 'database', 'deploy', 'docs', 'public', 'resources', 'routes', 'scripts', 'tests', 'tools', '.github'}
root_files = {'.env.example', '.editorconfig', '.gitignore', '.gitattributes', 'artisan', 'composer.json', 'composer.lock', 'phpunit.xml', 'README.md', 'erp-install.php', 'LICENSE'}
secrets = []
envfile = root/'.env'
if envfile.is_file():
    for line in envfile.read_text(encoding='utf-8-sig').splitlines():
        if '=' not in line or line.lstrip().startswith('#'): continue
        key,value = line.split('=',1);value=value.strip().strip('"\'')
        if re.search(r'(PASSWORD|SECRET|TOKEN|KEY)$',key) and len(value)>=16:
            secrets.append((key,value.encode('utf-8')))
files = []
blocked = {'.git', 'node_modules', '__pycache__', 'backups', 'respaldos', 'private'}
for path in sorted(root.rglob('*')):
    if not path.is_file() or path.is_symlink(): continue
    rel = path.relative_to(root); name=rel.as_posix(); parts=set(rel.parts)
    if not (rel.parts[0] in directories or name in root_files): continue
    if parts & blocked or name.startswith(('bootstrap/cache/', 'tests/Browser/results/', 'public/storage/')): continue
    if path.name.startswith('qa-browser-'): continue
    if (path.name.startswith('.env') and name!='.env.example') or path.suffix.lower() in {'.sql','.sqlite','.sqlite3','.db','.zip','.bak','.log','.pem','.key','.p12','.pfx','.pdf'}: continue
    content=path.read_bytes()
    for key,value in secrets:
        if value in content: raise SystemExit('Se encontró un valor secreto de '+key+' en '+name+'; no se creó el ZIP.')
    files.append((path,name,hashlib.sha256(content).hexdigest()))
assert any(name=='public/assets/vendor/g3-utilities.css' for _,name,_ in files), 'Faltan assets compilados.'
target.parent.mkdir(parents=True,exist_ok=True)
with zipfile.ZipFile(target,'x',zipfile.ZIP_DEFLATED) as archive:
    for path,name,digest in files: archive.write(path,'ERP-Juan23/'+name)
    archive.writestr('ERP-Juan23/MANIFEST.sha256','\n'.join(digest+'  '+name for _,name,digest in files)+'\n')
with zipfile.ZipFile(target) as archive:
    if archive.testzip() is not None: raise SystemExit('Archivo ZIP inválido.')
    for _,name,digest in files:
        if hashlib.sha256(archive.read('ERP-Juan23/'+name)).hexdigest()!=digest:
            raise SystemExit('La fuente cambió durante el empaquetado: '+name)
print(json.dumps({'zip':str(target),'files':len(files),'sha256':hashlib.sha256(target.read_bytes()).hexdigest(),
    'excludes':['entornos reales','dumps SQL/SQLite','runtime privado','Git','dependencias instaladas','respaldos'],
    'includes':['fuentes','migraciones PHP','composer.lock','assets compilados y licencias','documentación Markdown','ejemplo de configuración sin credenciales']},ensure_ascii=False))
