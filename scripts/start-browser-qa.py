"""Servidor temporal de navegador: solamente base QA explícita en 3315."""
from pathlib import Path
import os, json, re, subprocess, socket, time, base64, secrets
root = Path(__file__).resolve().parent.parent
name = os.environ.get('ERP_G3_G4_TEST_DATABASE', '')
if not re.fullmatch(r'erp_(qa|test)_[a-z0-9_]+', name):
    raise SystemExit('Falta base exclusiva de pruebas')
credentials = json.loads((root/'storage/app/private/qa-browser/credentials.json').read_text())
env = os.environ.copy()
try:
    with socket.create_connection(('127.0.0.1',8015),timeout=0.2):
        raise SystemExit('El puerto QA 8015 ya está ocupado; no se reemplaza el servidor existente.')
except OSError:
    pass
# Clave efímera sólo del proceso QA: no modifica .env ni requiere copiar secretos.
env['APP_KEY'] = env.get('APP_KEY') or 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode('ascii')
env.update(APP_ENV='testing', APP_DEBUG='false', APP_URL='http://127.0.0.1:8015', DB_CONNECTION='mysql', DB_HOST='127.0.0.1',
 DB_PORT='3315', DB_DATABASE=name, DB_USERNAME=env.get('ERP_TEST_DB_USERNAME','root'), DB_PASSWORD=env.get('ERP_TEST_DB_PASSWORD',''), DB_URL='',
 SESSION_DRIVER='file',SESSION_SECURE_COOKIE='false',CACHE_STORE='file',MAIL_MAILER='array', INTERNAL_API_TOKEN=credentials['token'], INTERNAL_API_USER_ID=str(credentials['service']))
for key in ['ERP_API_TRANSPORT','VENTAS_INTEGRACION','CATALOG_TRANSPORT','SALES_TRANSPORT','BILLING_TRANSPORT','BALANCE_TRANSPORT','G1_RENDICIONES_TRANSPORT','G2_API_TRANSPORT','CASH_TRANSPORT']:env[key]='internal'
php=env.get('ERP_PHP','php')
log=open(root/'storage/logs/browser-qa.log','ab')
kwargs={'creationflags':subprocess.CREATE_NO_WINDOW} if os.name=='nt' else {'start_new_session':True}
p=subprocess.Popen([php,'-S','127.0.0.1:8015',str(root/'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],cwd=root/'public',env=env,stdout=log,stderr=log,**kwargs)
(root/'storage/app/private/qa-browser/server.pid').write_text(str(p.pid))
print('Servidor QA 8015; PID',p.pid)

for attempt in range(50):
    try:
        with socket.create_connection(('127.0.0.1',8015),timeout=0.2): break
    except OSError: time.sleep(0.1)
else: raise SystemExit('El servidor QA no respondió')
